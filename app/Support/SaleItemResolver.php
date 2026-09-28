<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Validation\ValidationException;

/**
 * Convierte lo que manda el formulario —tipo, id y cantidad— en las líneas de
 * la venta, con su descripción y su precio.
 *
 * Vive aquí, y no en el controlador, porque cobran dos sitios: el POS de la web
 * y la app móvil. Si cada uno resolviera los precios por su cuenta, tarde o
 * temprano cobrarían distinto.
 *
 * La regla que sostiene esto: **el precio lo pone el catálogo, nunca el
 * cliente**. Lo que llegue en la petición se ignora; se lee de la base.
 */
class SaleItemResolver
{
    /**
     * @param  array<int, array{type: string, id: int, quantity: mixed, personal_id?: int|null}>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $rows, ?int $defaultPersonalId = null): array
    {
        $serviceIds = collect($rows)->where('type', 'servicio')->pluck('id');
        $productIds = collect($rows)->where('type', 'producto')->pluck('id');

        // El CompanyScope acota ambas consultas: un id de otra barbería
        // simplemente no aparece, y más abajo se rechaza la línea.
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return collect($rows)->map(function (array $row) use ($services, $products, $defaultPersonalId) {
            if ($row['type'] === 'servicio') {
                $service = $services->get($row['id']);

                if (! $service) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los servicios de la comanda ya no existe.',
                    ]);
                }

                return [
                    'type' => 'servicio',
                    'service_id' => $service->id,
                    'personal_id' => $row['personal_id'] ?? $defaultPersonalId,
                    'description' => $service->name,
                    'quantity' => (float) $row['quantity'],
                    'unit_price' => (float) $service->price,
                    // Un servicio no tiene precio de compra: no es cero, es
                    // que no aplica.
                    'unit_cost' => null,
                ];
            }

            $product = $products->get($row['id']);

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos de la comanda ya no existe.',
                ]);
            }

            return [
                'type' => 'producto',
                'product_id' => $product->id,
                'personal_id' => $row['personal_id'] ?? $defaultPersonalId,
                'description' => $product->name,
                'quantity' => (float) $row['quantity'],
                'unit_price' => (float) $product->sale_price,
                // Se congela el costo de HOY, igual que el precio: si mañana
                // sube el precio de compra, el margen de esta venta no cambia.
                'unit_cost' => (float) $product->cost_price,
            ];
        })->all();
    }
}
