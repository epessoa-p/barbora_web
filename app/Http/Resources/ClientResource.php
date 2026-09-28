<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'document_number' => $this->document_number,
            'preferences' => $this->preferences,
            'allergies' => $this->allergies,
            'active' => (bool) $this->active,
        ];
    }
}
