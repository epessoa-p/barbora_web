<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de la ficha de un cliente, compartidas por la web y la API.
 */
class ClientForm
{
    /**
     * Valida la petición y devuelve los atributos listos para guardar.
     *
     * `active` difiere según quién llama: en la web es una casilla (sin marcar
     * = no llega = inactivo); en la API, si no llega, no se toca (y un alta
     * nace activa).
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?int $companyId, ?Client $client = null, bool $activeIsCheckbox = false): array
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            // El documento es opcional, pero si se registra no puede repetirse
            // dentro de la misma empresa.
            'document_number' => [
                'nullable', 'string', 'max:30',
                Rule::unique('clients', 'document_number')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($client?->id),
            ],
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'birth_date' => 'nullable|date|before:today',
            'preferences' => 'nullable|string|max:255',
            'allergies' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ], [
            'full_name.required' => 'Escribe el nombre del cliente.',
            'document_number.unique' => 'Ya hay un cliente registrado con ese documento.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);

        $data['full_name'] = trim($data['full_name']);

        if ($activeIsCheckbox) {
            $data['active'] = $request->boolean('active');
        } elseif ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        } elseif (! $client) {
            $data['active'] = true;
        }

        return $data;
    }
}
