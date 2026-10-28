<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de un servicio del catálogo, compartidas por la web y la API.
 */
class ServiceForm
{
    /**
     * @param  bool  $activeIsCheckbox  en la web, una casilla sin marcar no llega
     *                                  y vale «inactivo»; en la API, si no llega,
     *                                  no se toca (y un alta nace activa).
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?int $companyId, ?Service $service = null, bool $activeIsCheckbox = false): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('services', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($service?->id),
            ],
            // La categoría tiene que ser de la misma empresa.
            'service_category_id' => [
                'nullable',
                Rule::exists('service_categories', 'id')->where('company_id', $companyId),
            ],
            'description' => 'nullable|string|max:255',
            'duration_minutes' => 'required|integer|min:5|max:600',
            'price' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ], [
            'name.required' => 'Escribe el nombre del servicio.',
            'name.unique' => 'Ya existe un servicio con ese nombre.',
            'duration_minutes.min' => 'La duración mínima es de 5 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 10 horas.',
        ]);

        $data['name'] = trim($data['name']);

        // El orden solo se fija si llega; un alta sin él va al principio (0).
        if (array_key_exists('sort_order', $data) || ! $service) {
            $data['sort_order'] = $data['sort_order'] ?? 0;
        }

        if ($activeIsCheckbox || $request->has('active')) {
            $data['active'] = $request->boolean('active');
        } elseif (! $service) {
            $data['active'] = true;
        }

        return $data;
    }
}
