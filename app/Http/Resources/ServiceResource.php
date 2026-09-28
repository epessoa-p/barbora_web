<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => (int) $this->duration_minutes,
            'price' => (float) $this->price,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'active' => (bool) $this->active,
        ];
    }
}
