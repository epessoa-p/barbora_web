<?php

namespace App\Support;

use App\Models\CommissionEntry;
use App\Models\CommissionRule;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;

/**
 * Calcula y devenga comisiones.
 *
 * Se ejecuta al cobrar, no al consultar: la comisión se congela con la regla
 * que estaba vigente ese día. Cambiar un porcentaje mañana no debe reescribir
 * lo que un barbero ya se ganó.
 */
class CommissionCalculator
{
    /**
     * Genera las comisiones de una venta recién cobrada.
     *
     * @return int  cuántas líneas devengaron comisión
     */
    public function accrue(Sale $sale): int
    {
        $sale->loadMissing('items');

        $rules = $this->rulesFor($sale->company_id);
        $created = 0;

        foreach ($sale->items as $item) {
            // Sin barbero asignado no hay a quién comisionar.
            if (! $item->personal_id) {
                continue;
            }

            $rule = $this->resolve($rules, $item);

            if (! $rule) {
                continue;
            }

            $base = (float) $item->total;
            $amount = $rule->commissionFor($base);

            if ($amount <= 0) {
                continue;
            }

            CommissionEntry::withoutGlobalScopes()->create([
                'company_id' => $sale->company_id,
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
                'personal_id' => $item->personal_id,
                'commission_rule_id' => $rule->id,
                'base_amount' => $base,
                'rate_type' => $rule->type,
                'rate_value' => $rule->value,
                'amount' => $amount,
                'earned_at' => $sale->sold_at,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Revierte las comisiones de una venta anulada.
     *
     * Las ya liquidadas no se tocan: ese dinero se pagó y borrarlo dejaría la
     * liquidación sin cuadrar. Se devuelve cuántas quedaron en pie para poder
     * avisar de que hay que regularizarlas a mano.
     */
    public function reverse(Sale $sale): int
    {
        $entries = CommissionEntry::withoutGlobalScopes()->where('sale_id', $sale->id)->get();

        $settled = $entries->filter->isSettled();

        CommissionEntry::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->whereNull('commission_settlement_id')
            ->delete();

        return $settled->count();
    }

    /**
     * Reglas activas de la empresa, ordenadas de más específica a menos: la
     * primera que encaje es la que manda.
     *
     * @return Collection<int, CommissionRule>
     */
    protected function rulesFor(int $companyId): Collection
    {
        return CommissionRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->get()
            ->sortBy(fn (CommissionRule $rule) => $rule->specificity())
            ->values();
    }

    /** @param  Collection<int, CommissionRule>  $rules */
    protected function resolve(Collection $rules, SaleItem $item): ?CommissionRule
    {
        return $rules->first(fn (CommissionRule $rule) => $this->matches($rule, $item));
    }

    protected function matches(CommissionRule $rule, SaleItem $item): bool
    {
        // Una regla con barbero solo vale para ese barbero.
        if ($rule->personal_id && $rule->personal_id !== $item->personal_id) {
            return false;
        }

        return match ($rule->applies_to) {
            'servicio' => $item->type === 'servicio' && $rule->service_id === $item->service_id,
            'servicios' => $item->type === 'servicio',
            'productos' => $item->type === 'producto',
            default => false,
        };
    }
}
