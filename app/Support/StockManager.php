<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto por el que se mueve el stock.
 *
 * Centralizarlo aquí y no en los controladores es lo que garantiza que el saldo
 * corriente (`stocks`) y el historial (`stock_movements`) no puedan divergir:
 * ambos se escriben en la misma transacción, siempre. Cuando llegue Ventas, el
 * descuento al cobrar entrará por esta misma puerta.
 */
class StockManager
{
    /** Entrada de mercancía: compra, devolución, traslado recibido. */
    public function receive(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        array $attributes = [],
    ): StockMovement {
        $this->assertPositive($quantity);

        return $this->apply($product, $warehouse, $quantity, 'entrada', $attributes);
    }

    /** Salida: consumo en servicio, venta, merma. */
    public function issue(
        Product $product,
        Warehouse $warehouse,
        float $quantity,
        array $attributes = [],
    ): StockMovement {
        $this->assertPositive($quantity);

        return $this->apply($product, $warehouse, -$quantity, 'salida', $attributes);
    }

    /**
     * Ajuste tras un recuento físico: se indica cuánto HAY, no cuánto entra o
     * sale. El movimiento guarda la diferencia, que es lo que hay que explicar.
     */
    public function adjustTo(
        Product $product,
        Warehouse $warehouse,
        float $countedQuantity,
        array $attributes = [],
    ): StockMovement {
        if ($countedQuantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad contada no puede ser negativa.',
            ]);
        }

        $current = $this->currentQuantity($product, $warehouse);
        $delta = $countedQuantity - $current;

        if (abs($delta) < 0.001) {
            throw ValidationException::withMessages([
                'quantity' => 'El recuento coincide con el stock registrado: no hay nada que ajustar.',
            ]);
        }

        return $this->apply($product, $warehouse, $delta, 'ajuste', $attributes + [
            'reason' => 'inventario',
        ]);
    }

    /**
     * Escribe el movimiento y actualiza el saldo, atómicamente.
     *
     * @param  float  $delta  con signo: positivo entra, negativo sale
     */
    protected function apply(
        Product $product,
        Warehouse $warehouse,
        float $delta,
        string $type,
        array $attributes = [],
    ): StockMovement {
        if ($product->company_id !== $warehouse->company_id) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El almacén no pertenece a la misma empresa que el producto.',
            ]);
        }

        if (! $product->track_stock) {
            throw ValidationException::withMessages([
                'product_id' => "«{$product->name}» no lleva control de existencias. "
                              . 'Actívalo en su ficha si quieres registrar movimientos.',
            ]);
        }

        return DB::transaction(function () use ($product, $warehouse, $delta, $type, $attributes) {
            // lockForUpdate evita que dos salidas simultáneas lean el mismo
            // saldo y dejen el stock en negativo.
            $stock = Stock::withoutGlobalScopes()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = Stock::withoutGlobalScopes()->create([
                    'company_id' => $product->company_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                ]);
            }

            $after = (float) $stock->quantity + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "No hay suficiente stock de «{$product->name}» en «{$warehouse->name}»: "
                                . 'quedan '.$product->formatQuantity((float) $stock->quantity).'.',
                ]);
            }

            $stock->update(['quantity' => $after]);

            return StockMovement::withoutGlobalScopes()->create([
                'company_id' => $product->company_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $delta,
                'quantity_after' => $after,
                'reason' => $attributes['reason'] ?? null,
                'unit_cost' => $attributes['unit_cost'] ?? null,
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $attributes['created_by'] ?? auth()->id(),
            ]);
        });
    }

    protected function currentQuantity(Product $product, Warehouse $warehouse): float
    {
        return (float) (Stock::withoutGlobalScopes()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('quantity') ?? 0);
    }

    protected function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'La cantidad tiene que ser mayor que cero.',
            ]);
        }
    }
}
