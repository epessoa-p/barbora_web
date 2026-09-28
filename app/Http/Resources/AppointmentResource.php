<?php

namespace App\Http\Resources;

use App\Support\ShopTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una cita, tal como la ve la app del personal.
 *
 * Las fechas van en ISO 8601 con la zona horaria de la BARBERÍA (ver ShopTime):
 * el teléfono puede estar en otra zona que el servidor, y una cita a las 15:00
 * tiene que seguir siendo a las 15:00 de la barbería.
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $company = $request->attributes->get('tenant_company');

        return [
            'id' => $this->id,
            'starts_at' => ShopTime::iso($this->starts_at, $company),
            'ends_at' => ShopTime::iso($this->ends_at, $company),
            'duration_minutes' => $this->durationMinutes(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'is_editable' => $this->isEditable(),
            'notes' => $this->notes,

            'client' => $this->whenLoaded('client', fn () => $this->client ? [
                'id' => $this->client->id,
                'full_name' => $this->client->full_name,
                'phone' => $this->client->phone,
            ] : null),

            'personal' => $this->whenLoaded('personal', fn () => $this->personal ? [
                'id' => $this->personal->id,
                'full_name' => $this->personal->full_name,
            ] : null),

            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->name),

            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                // Precio y duración congelados el día de la reserva, no los
                // actuales del catálogo.
                'duration_minutes' => (int) $service->pivot->duration_minutes,
                'price' => (float) $service->pivot->price,
            ])->all()),

            'total' => $this->whenLoaded('services', fn () => $this->total()),
            'reminded_at' => ShopTime::iso($this->reminded_at, $company),
        ];
    }
}
