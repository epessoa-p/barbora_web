<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reglas de la ficha de un producto, compartidas por la web y la API.
 */
class ProductForm
{
    /**
     * @param  bool  $flagsAreCheckboxes  en la web, una casilla sin marcar no
     *                                    llega y vale «no»; en la API, si no
     *                                    llega, no se toca (y un alta usa los
     *                                    valores habituales).
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?int $companyId, ?Product $product = null, bool $flagsAreCheckboxes = false): array
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('products', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($product?->id),
            ],
            'sku' => [
                'nullable', 'string', 'max:50',
                Rule::unique('products', 'sku')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($product?->id),
            ],
            'product_category_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where('company_id', $companyId),
            ],
            'description' => 'nullable|string|max:255',
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'is_sellable' => 'sometimes|boolean',
            'track_stock' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ], [
            'name.required' => 'Escribe el nombre del producto.',
            'name.unique' => 'Ya existe un producto con ese nombre.',
            'sku.unique' => 'Ese código ya está en uso.',
        ]);

        $data['name'] = trim($data['name']);

        // Alta por la API sin estos campos: vendible, con control de stock y activo.
        $defaults = ['is_sellable' => true, 'track_stock' => true, 'active' => true];

        foreach (array_keys($defaults) as $flag) {
            if ($flagsAreCheckboxes || $request->has($flag)) {
                $data[$flag] = $request->boolean($flag);
            } elseif (! $product) {
                $data[$flag] = $defaults[$flag];
            }
        }

        return $data;
    }
}
