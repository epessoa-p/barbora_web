<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Compras a proveedores: registrar (lo comprado entra al almacén), pagar (al
 * contado, en parte o después) y anular (sale del almacén y vuelve el dinero).
 */
class PurchaseRegistrar
{
    public function __construct(
        protected StockManager $stock,
        protected PaymentSource $payments,
    ) {
    }

    public function register(Request $request, Company $company): Purchase
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $company->id)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $company->id)],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'purchased_on' => ['required', 'date', 'before_or_equal:today'],
            'due_on' => ['nullable', 'date', 'after_or_equal:purchased_on'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $company->id)],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            // Lo que se paga ahora: todo (contado), una parte o nada (crédito).
            'pay_amount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'warehouse_id.required' => 'Elige a qué almacén entra la mercadería.',
            'items.required' => 'Añade al menos un producto.',
            'items.min' => 'Añade al menos un producto.',
            'purchased_on.before_or_equal' => 'Una compra no puede tener fecha futura.',
        ]);

        $subtotal = round(collect($data['items'])->sum(fn ($i) => (float) $i['quantity'] * (float) $i['unit_cost']), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);
        $total = round($subtotal - $discount, 2);
        $payNow = round((float) ($data['pay_amount'] ?? 0), 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'El descuento no puede ser mayor que el subtotal.']);
        }

        if ($payNow > $total + 0.004) {
            throw ValidationException::withMessages(['pay_amount' => 'No se puede pagar más que el total de la compra.']);
        }

        // El origen del dinero solo se pide si se paga algo ahora.
        $source = $payNow > 0 ? $this->payments->validate($request, $company) : null;
        $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($data['warehouse_id']);

        return DB::transaction(function () use ($data, $company, $warehouse, $subtotal, $discount, $total, $payNow, $source, $request) {
            $purchase = Purchase::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'warehouse_id' => $warehouse->id,
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchased_on' => $data['purchased_on'],
                'due_on' => $data['due_on'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => 0,
                'status' => 'pendiente',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($data['items'] as $line) {
                $product = Product::withoutGlobalScopes()->findOrFail($line['product_id']);
                $quantity = (float) $line['quantity'];
                $unitCost = round((float) $line['unit_cost'], 2);

                // Solo mueve stock lo que lleva control de existencias.
                $movement = $product->track_stock
                    ? $this->stock->receive($product, $warehouse, $quantity, [
                        'reason' => 'compra',
                        'unit_cost' => $unitCost,
                        'reference' => $purchase->number,
                        'created_by' => $request->user()->id,
                    ])
                    : null;

                $purchase->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total' => round($quantity * $unitCost, 2),
                    'stock_movement_id' => $movement?->id,
                ]);

                // El costo del producto pasa a ser el de la última compra.
                $product->update(['cost_price' => $unitCost]);
            }

            if ($payNow > 0) {
                $this->addPayment($purchase, $source, $payNow, $data['purchased_on'], null, $request->user()->id);
            }

            return $purchase->refresh();
        });
    }

    /** Pagar (parte de) la deuda de una compra. */
    public function pay(Request $request, Purchase $purchase, Company $company): PurchasePayment
    {
        if ($purchase->isCancelled()) {
            throw ValidationException::withMessages(['error' => 'La compra está anulada.']);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.min' => 'El importe tiene que ser mayor que cero.',
        ]);

        if ((float) $data['amount'] > $purchase->balance() + 0.004) {
            throw ValidationException::withMessages([
                'amount' => 'Se deben '.Money::format($purchase->balance(), $company).': no se puede pagar más.',
            ]);
        }

        $source = $this->payments->validate($request, $company);

        return DB::transaction(fn () => $this->addPayment(
            $purchase, $source, round((float) $data['amount'], 2), $data['paid_on'], $data['reference'] ?? null, $request->user()->id,
        ));
    }

    /**
     * Anular: lo comprado sale del almacén y los pagos vuelven a su cuenta o a
     * la caja. Si ya se vendió o consumió parte, no hay stock que sacar y no se
     * puede anular (habría que ajustar el inventario a mano).
     */
    public function cancel(Purchase $purchase, ?string $reason, int $userId): void
    {
        if ($purchase->isCancelled()) {
            throw ValidationException::withMessages(['error' => 'Esta compra ya está anulada.']);
        }

        DB::transaction(function () use ($purchase, $reason, $userId) {
            $warehouse = Warehouse::withoutGlobalScopes()->findOrFail($purchase->warehouse_id);

            foreach ($purchase->items as $item) {
                if (! $item->stock_movement_id) {
                    continue;
                }

                $this->stock->issue(Product::withoutGlobalScopes()->findOrFail($item->product_id), $warehouse, (float) $item->quantity, [
                    'reason' => 'otro',
                    'reference' => "Anulación {$purchase->number}",
                    'created_by' => $userId,
                ]);
            }

            foreach ($purchase->payments()->whereNull('cancelled_at')->get() as $payment) {
                $this->payments->refund($payment, "Anulación compra {$purchase->number}");
                $payment->update(['cancelled_at' => now()]);
            }

            $purchase->update([
                'status' => 'anulada',
                'paid' => 0,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
            ]);
        });
    }

    protected function addPayment(Purchase $purchase, array $source, float $amount, string $on, ?string $reference, int $userId): PurchasePayment
    {
        $supplier = $purchase->supplier?->name;

        $payment = $purchase->payments()->create([
            'company_id' => $purchase->company_id,
            'amount' => $amount,
            'paid_on' => $on,
            'source' => $source[0],
            'reference' => $reference,
            'created_by' => $userId,
        ]);

        $payment->update($this->payments->charge(
            $source,
            $amount,
            "Compra {$purchase->number}".($supplier ? " · {$supplier}" : ''),
            'compra',
            $reference ?? $purchase->invoice_number,
            $on,
        ));

        $purchase->paid = round((float) $purchase->paid + $amount, 2);
        $purchase->refreshStatus();
        $purchase->save();

        return $payment;
    }
}
