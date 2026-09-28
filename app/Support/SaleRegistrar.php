<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra y anula ventas.
 *
 * Una venta no es solo una fila: descuenta stock, mete el ingreso en la caja
 * abierta y cierra la cita de la que nació. Si cualquiera de esos pasos falla,
 * no debe quedar media venta registrada — de ahí que todo viva en una única
 * transacción y en un único sitio.
 */
class SaleRegistrar
{
    protected int $settledCommissions = 0;

    public function __construct(
        protected StockManager $stock,
        protected CommissionCalculator $commissions,
    ) {
    }

    /**
     * @param  array  $data  cabecera de la venta
     * @param  array  $items  líneas ya validadas
     * @param  array  $payments  pagos ya validados
     */
    public function register(Company $company, array $data, array $items, array $payments): Sale
    {
        $subtotal = collect($items)->sum(fn (array $i) => $i['quantity'] * $i['unit_price']);
        $discount = (float) ($data['discount'] ?? 0);
        $tip = (float) ($data['tip'] ?? 0);
        $total = round($subtotal - $discount + $tip, 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages([
                'discount' => 'El descuento no puede superar el subtotal.',
            ]);
        }

        $paid = round(collect($payments)->sum('amount'), 2);

        if (abs($paid - $total) >= 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'Los pagos suman '.Money::format($paid, $company)
                            . ' y el total es '.Money::format($total, $company).'.',
            ]);
        }

        $session = $this->resolveCashSession($company, $data['branch_id'] ?? null);
        $warehouse = $this->resolveWarehouse($company, $items, $data['branch_id'] ?? null);

        return DB::transaction(function () use ($company, $data, $items, $payments, $subtotal, $discount, $tip, $total, $session, $warehouse) {
            $sale = Sale::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'personal_id' => $data['personal_id'] ?? null,
                'cash_session_id' => $session?->id,
                'number' => Sale::nextNumberFor($company->id),
                'status' => 'pagada',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tip' => $tip,
                'total' => $total,
                'sold_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                $sale->items()->create([
                    'company_id' => $company->id,
                    'type' => $item['type'],
                    'service_id' => $item['service_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'personal_id' => $item['personal_id'] ?? ($data['personal_id'] ?? null),
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    // El costo del día, congelado como el precio. Null en los
                    // servicios, que no tienen precio de compra.
                    'unit_cost' => $item['unit_cost'] ?? null,
                    'total' => round($item['quantity'] * $item['unit_price'], 2),
                ]);

                // Lo vendido sale del almacén. Los servicios no consumen stock.
                if ($item['type'] === 'producto' && $warehouse) {
                    $product = Product::withoutGlobalScopes()->findOrFail($item['product_id']);

                    if ($product->track_stock) {
                        $this->stock->issue($product, $warehouse, (float) $item['quantity'], [
                            'reason' => 'venta',
                            'reference' => $sale->number,
                        ]);
                    }
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'company_id' => $company->id,
                    'payment_method' => $payment['payment_method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);

                // Un movimiento por método: solo el efectivo entra en el arqueo.
                $session?->movements()->create([
                    'company_id' => $company->id,
                    'type' => 'ingreso',
                    'payment_method' => $payment['payment_method'],
                    'concept' => "Venta {$sale->number}",
                    'amount' => $payment['amount'],
                    'reference' => $sale->number,
                    'created_by' => auth()->id(),
                ]);
            }

            // La cita de la que nació la venta queda atendida: cobrar es la
            // prueba de que se atendió.
            if ($sale->appointment_id) {
                Appointment::withoutGlobalScopes()
                    ->whereKey($sale->appointment_id)
                    ->update(['status' => 'atendida']);
            }

            // Las comisiones se devengan aquí, con la regla vigente hoy.
            if ($company->planAllows('comisiones')) {
                $this->commissions->accrue($sale);
            }

            return $sale;
        });
    }

    /**
     * Anular revierte lo que la venta provocó: devuelve el stock y saca de caja
     * lo que entró. Nunca se borra, para que el hueco en la numeración no
     * obligue a explicar nada.
     */
    public function cancel(Sale $sale, ?string $reason = null): Sale
    {
        if ($sale->isCancelled()) {
            throw ValidationException::withMessages([
                'error' => 'Esta venta ya está anulada.',
            ]);
        }

        $sale->load(['items', 'payments', 'cashSession']);

        $warehouse = $this->warehouseFor($sale->company_id, $sale->branch_id);
        $session = $this->openSessionFor($sale->company_id, $sale->branch_id);

        return DB::transaction(function () use ($sale, $reason, $warehouse, $session) {
            foreach ($sale->items()->products()->get() as $item) {
                if (! $item->product_id || ! $warehouse) {
                    continue;
                }

                $product = Product::withoutGlobalScopes()->find($item->product_id);

                if ($product?->track_stock) {
                    $this->stock->receive($product, $warehouse, (float) $item->quantity, [
                        'reason' => 'devolucion',
                        'reference' => "Anulación {$sale->number}",
                    ]);
                }
            }

            // El egreso va al turno abierto ahora, no al de la venta: un turno
            // cerrado ya fue arqueado y no se puede tocar.
            foreach ($sale->payments as $payment) {
                $session?->movements()->create([
                    'company_id' => $sale->company_id,
                    'type' => 'egreso',
                    'payment_method' => $payment->payment_method,
                    'concept' => "Anulación venta {$sale->number}",
                    'amount' => $payment->amount,
                    'reference' => $sale->number,
                    'created_by' => auth()->id(),
                ]);
            }

            // Las comisiones pendientes se borran; las ya liquidadas no, porque
            // ese dinero se pagó y borrarlas descuadraría la liquidación.
            $this->settledCommissions = $this->commissions->reverse($sale);

            $sale->update([
                'status' => 'anulada',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
            ]);

            return $sale;
        });
    }

    /**
     * Comisiones ya liquidadas que sobrevivieron a la última anulación.
     * Si no es cero, hay que regularizarlas a mano con el barbero.
     */
    public function settledCommissionsLeftOver(): int
    {
        return $this->settledCommissions;
    }

    /**
     * El turno de caja donde entra el ingreso.
     *
     * Si la empresa tiene el módulo de caja, vender exige un turno abierto: es
     * la disciplina que hace que el arqueo signifique algo. Si no lo tiene, la
     * venta se registra igual y sin vínculo con caja.
     */
    protected function resolveCashSession(Company $company, ?int $branchId): ?CashSession
    {
        if (! $company->planAllows('caja')) {
            return null;
        }

        $session = $this->openSessionFor($company->id, $branchId);

        if (! $session) {
            throw ValidationException::withMessages([
                'error' => 'No hay ningún turno de caja abierto. Abre la caja antes de cobrar.',
            ]);
        }

        return $session;
    }

    protected function openSessionFor(int $companyId, ?int $branchId): ?CashSession
    {
        $open = CashSession::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', 'abierta')
            ->orderBy('opened_at');

        // Si la venta es de una sucursal, se prefiere la caja de esa sucursal;
        // si esa caja no tiene turno abierto, vale cualquiera de la empresa.
        // Sin whereHas: la relación aplicaría el scope de empresa y aquí se
        // consulta a propósito por fuera de él.
        if ($branchId) {
            $cajaIds = Caja::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->pluck('id');

            $ofBranch = $cajaIds->isNotEmpty()
                ? (clone $open)->whereIn('caja_id', $cajaIds)->first()
                : null;

            if ($ofBranch) {
                return $ofBranch;
            }
        }

        return $open->first();
    }

    /** El almacén del que sale la mercancía. */
    protected function resolveWarehouse(Company $company, array $items, ?int $branchId): ?Warehouse
    {
        $hasProducts = collect($items)->contains(fn (array $i) => $i['type'] === 'producto');

        if (! $hasProducts || ! $company->planAllows('inventario')) {
            return null;
        }

        $warehouse = $this->warehouseFor($company->id, $branchId);

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'error' => 'No hay ningún almacén donde descontar los productos. Crea uno primero.',
            ]);
        }

        return $warehouse;
    }

    protected function warehouseFor(int $companyId, ?int $branchId): ?Warehouse
    {
        $query = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->where('active', true);

        if ($branchId) {
            $ofBranch = (clone $query)->where('branch_id', $branchId)->first();

            if ($ofBranch) {
                return $ofBranch;
            }
        }

        return $query->orderBy('id')->first();
    }
}
