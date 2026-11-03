<?php

namespace App\Models\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto opcional en la columna `photo` (servicios, productos). Se guarda en
 * el disco «public», en una carpeta por empresa: {PHOTO_FOLDER}/{company_id}/.
 *
 * Igual que el logo de la empresa: el archivo nuevo se guarda antes; si la
 * base falla, se borra; el anterior se borra solo después de guardar bien.
 */
trait HasPhoto
{
    /** Validación de la foto, la misma en la web y en la API. */
    public const PHOTO_RULES = [
        'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        'remove_photo' => ['nullable', 'boolean'],
    ];

    public const PHOTO_MESSAGES = [
        'photo.max' => 'La foto no puede pesar más de 2 MB.',
        'photo.mimes' => 'La foto tiene que ser JPG, PNG o WEBP.',
        'photo.image' => 'El archivo tiene que ser una imagen.',
    ];

    /**
     * URL absoluta de la foto (o null). Con el host de la petición, igual que
     * el logo: el móvil y el catálogo público la abren tal cual.
     */
    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    /** Sube (`photo`) o quita (`remove_photo`) la foto según la petición. */
    public function syncPhoto(Request $request): void
    {
        $previous = $this->photo;
        $photo = $request->boolean('remove_photo') ? null : $previous;
        $uploaded = null;

        if ($request->hasFile('photo')) {
            $uploaded = $request->file('photo')->store(static::PHOTO_FOLDER."/{$this->company_id}", 'public');
            $photo = $uploaded;
        }

        if ($photo === $previous) {
            return;
        }

        try {
            $this->update(['photo' => $photo]);
        } catch (\Throwable $e) {
            if ($uploaded) {
                Storage::disk('public')->delete($uploaded);
            }

            throw $e;
        }

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }
    }
}
