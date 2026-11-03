<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServicePhoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * La galería de fotos de un servicio: los cortes de los cuadros de la pared,
 * cada uno con su comentario («1», «Corte XXTT»…).
 *
 * Único punto por el que se tocan las fotos, en la web y en la API. La primera
 * (por `sort_order`) es la portada y se copia a `services.photo`, que es lo
 * que siguen leyendo la lista de servicios, el PDF y las apps viejas.
 *
 * Igual que HasPhoto: el archivo se guarda antes que la fila (si la base
 * falla, se borra) y el de una foto quitada se borra después.
 */
class ServiceGallery
{
    /** Tope de fotos por servicio. */
    public const MAX = 30;

    public const FILE_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];

    /** Lo que manda el formulario web (y la API al subir varias). */
    public const RULES = [
        'photos' => ['nullable', 'array', 'max:'.self::MAX],
        'photos.*' => self::FILE_RULES,
        'new_captions' => ['nullable', 'array'],
        'new_captions.*' => ['nullable', 'string', 'max:120'],
        'captions' => ['nullable', 'array'],
        'captions.*' => ['nullable', 'string', 'max:120'],
        'order' => ['nullable', 'array'],
        'order.*' => ['integer'],
        'remove_photos' => ['nullable', 'array'],
        'remove_photos.*' => ['integer'],
    ];

    public const MESSAGES = [
        'photos.max' => 'Un servicio puede tener hasta '.self::MAX.' fotos.',
        'photos.*.max' => 'Cada foto puede pesar hasta 2 MB.',
        'photos.*.mimes' => 'Las fotos tienen que ser JPG, PNG o WEBP.',
        'photos.*.image' => 'Cada archivo tiene que ser una imagen.',
        'captions.*.max' => 'El comentario de una foto puede tener hasta 120 caracteres.',
        'new_captions.*.max' => 'El comentario de una foto puede tener hasta 120 caracteres.',
    ];

    /**
     * Aplica de una vez lo que llega del formulario: quitar, comentarios,
     * orden y fotos nuevas (al final, cada una con su comentario).
     */
    public function apply(Service $service, Request $request): void
    {
        $photos = $service->photos()->get()->keyBy('id');

        $remove = collect($request->input('remove_photos', []))->map(fn ($id) => (int) $id);
        foreach ($remove as $id) {
            if ($photo = $photos->pull($id)) {
                $this->remove($photo, sync: false);
            }
        }

        foreach ((array) $request->input('captions', []) as $id => $caption) {
            if ($photo = $photos->get((int) $id)) {
                $this->caption($photo, $caption);
            }
        }

        if ($request->filled('order')) {
            $this->reorder($service, (array) $request->input('order'), sync: false);
        }

        $files = array_values(array_filter((array) $request->file('photos', [])));
        if ($request->hasFile('photo')) {
            $files[] = $request->file('photo');
        }

        if ($files) {
            $this->add($service, $files, array_values((array) $request->input('new_captions', [])), sync: false);
        }

        $this->syncCover($service);
    }

    /**
     * Suma fotos al final de la galería.
     *
     * @param  UploadedFile[]  $files
     * @param  array<int, ?string>  $captions  alineados con $files
     * @return ServicePhoto[]
     */
    public function add(Service $service, array $files, array $captions = [], bool $sync = true): array
    {
        $count = $service->photos()->count();

        if ($count + count($files) > self::MAX) {
            throw ValidationException::withMessages([
                'photos' => 'Un servicio puede tener hasta '.self::MAX.' fotos'.($count ? " (ya tiene {$count})" : '').'.',
            ]);
        }

        $next = (int) $service->photos()->max('sort_order') + ($count ? 1 : 0);
        $created = [];

        foreach (array_values($files) as $i => $file) {
            $path = $file->store(Service::PHOTO_FOLDER."/{$service->company_id}", 'public');

            try {
                $created[] = ServicePhoto::withoutGlobalScopes()->create([
                    'company_id' => $service->company_id,
                    'service_id' => $service->id,
                    'path' => $path,
                    'caption' => $this->clean($captions[$i] ?? null),
                    'sort_order' => $next++,
                ]);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);

                throw $e;
            }
        }

        if ($sync) {
            $this->syncCover($service);
        }

        return $created;
    }

    public function caption(ServicePhoto $photo, ?string $caption): void
    {
        $photo->update(['caption' => $this->clean($caption)]);
    }

    /**
     * Nuevo orden: los ids en el orden pedido. Los que no vengan quedan
     * detrás, en el orden que tenían. El primero es la portada.
     */
    public function reorder(Service $service, array $ids, bool $sync = true): void
    {
        $photos = $service->photos()->get();
        $ids = array_map('intval', $ids);

        $ordered = $photos->sortBy(fn (ServicePhoto $p) => [
            ($i = array_search($p->id, $ids, true)) === false ? PHP_INT_MAX : $i,
            $p->sort_order,
            $p->id,
        ])->values();

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $i => $photo) {
                if ($photo->sort_order !== $i) {
                    $photo->update(['sort_order' => $i]);
                }
            }
        });

        if ($sync) {
            $this->syncCover($service);
        }
    }

    public function remove(ServicePhoto $photo, bool $sync = true): void
    {
        $service = $photo->service()->withTrashed()->first();
        $path = $photo->path;

        $photo->delete();

        if ($sync && $service) {
            $this->syncCover($service);
        }

        // Otra fila con el mismo archivo (raro, pero posible) lo sigue usando.
        if (! ServicePhoto::withoutGlobalScopes()->where('path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Lo que hacía la foto única (apps viejas): cambiar la portada reemplaza
     * la primera foto; quitarla, la quita.
     */
    public function replaceCover(Service $service, ?UploadedFile $file): void
    {
        $first = $service->photos()->first();

        if ($first) {
            $this->remove($first, sync: false);
        }

        if ($file) {
            [$photo] = $this->add($service, [$file], [$first?->caption], sync: false);
            $this->reorder($service, [$photo->id], sync: false);
        }

        $this->syncCover($service);
    }

    /** La portada (`services.photo`) es la primera de la galería. */
    public function syncCover(Service $service): void
    {
        $cover = $service->photos()->value('path');

        if ($service->photo !== $cover) {
            $service->update(['photo' => $cover]);
        }

        $service->unsetRelation('photos');
    }

    protected function clean(?string $caption): ?string
    {
        $caption = trim((string) $caption);

        return $caption === '' ? null : mb_substr($caption, 0, 120);
    }
}
