<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto de la galería de un servicio (un corte de los cuadros de la pared),
 * con su comentario. La primera por `sort_order` es la portada. Se gestiona
 * siempre a través de App\Support\ServiceGallery.
 */
class ServicePhoto extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'service_id', 'path', 'caption', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** URL absoluta, con el host de la petición (igual que HasPhoto::photoUrl). */
    public function url(): string
    {
        return asset('storage/'.$this->path);
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'caption' => $this->caption,
            'sort_order' => $this->sort_order,
        ];
    }
}
