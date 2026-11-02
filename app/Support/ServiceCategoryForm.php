<?php

namespace App\Support;

use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de una categoría de servicios, compartidas por la web y la API.
 */
class ServiceCategoryForm
{
    /**
     * `active`: en la web es una casilla (sin marcar = no llega = inactiva); en
     * la API, si no llega, no se toca (y un alta nace activa).
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?int $companyId, ?ServiceCategory $category = null, bool $activeIsCheckbox = false): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('service_categories', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($category?->id),
            ],
            'description' => 'nullable|string|max:255',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ], [
            'name.required' => 'Ponle un nombre a la categoría.',
            'name.unique' => 'Ya existe una categoría con ese nombre.',
            'color.regex' => 'El color debe ser un valor hexadecimal, por ejemplo #1f2937.',
        ]);

        $data['name'] = trim($data['name']);
        $data['sort_order'] = $data['sort_order'] ?? ($category?->sort_order ?? 0);

        if ($activeIsCheckbox || $request->has('active')) {
            $data['active'] = $request->boolean('active');
        } elseif (! $category) {
            $data['active'] = true;
        }

        return $data;
    }
}
