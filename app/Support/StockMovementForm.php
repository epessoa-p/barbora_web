<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registrar una entrada, salida o ajuste a mano, desde la web o la API.
 *
 * Valida y delega en StockManager, que es el único que mueve el stock.
 */
class StockMovementForm
{
    public function __construct(protected StockManager $stock)
    {
    }

    public function register(Request $request, ?int $companyId): StockMovement
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(StockMovement::TYPES))],
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => 'required|numeric|min:0',
            'reason' => ['nullable', Rule::in(array_keys(StockMovement::REASONS))],
            'unit_cost' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'quantity.required' => 'Indica la cantidad.',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $quantity = (float) $data['quantity'];

        $attributes = [
            'reason' => $data['reason'] ?? null,
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return match ($data['type']) {
            'entrada' => $this->stock->receive($product, $warehouse, $quantity, $attributes),
            'salida' => $this->stock->issue($product, $warehouse, $quantity, $attributes),
            'ajuste' => $this->stock->adjustTo($product, $warehouse, $quantity, $attributes),
        };
    }

    /** «Entrada registrada: «Cera» queda en 13 unidades en «Central».» */
    public function message(StockMovement $movement): string
    {
        // «Entrada registrada» pero «Ajuste registrado»: la concordancia se
        // resuelve aquí en vez de encajar el género a la fuerza.
        $verb = $movement->type === 'ajuste' ? 'registrado' : 'registrada';
        $product = $movement->product;

        return "{$movement->typeLabel()} {$verb}: «{$product->name}» queda en "
            .$product->formatQuantity((float) $movement->quantity_after)
            ." en «{$movement->warehouse->name}».";
    }
}
