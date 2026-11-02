<?php

namespace App\Support;

use App\Models\FinanceAccount;
use App\Models\FinanceMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Único punto por el que se mueve el dinero de las cuentas (capital, caja
 * chica, banco…). Igual que StockManager con el stock: el saldo corriente
 * (`finance_accounts.balance`) y el historial (`finance_movements`) se
 * escriben en la misma transacción, con la cuenta bloqueada, así que no
 * pueden divergir.
 *
 * Nada se borra: anular marca el movimiento y escribe el contrario.
 */
class Treasury
{
    /**
     * Ingreso o egreso. `$attributes` admite origin, reference, occurred_on y
     * transfer_movement_id.
     */
    public function move(FinanceAccount $account, string $type, float $amount, string $concept, array $attributes = []): FinanceMovement
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El importe tiene que ser mayor que cero.']);
        }

        if (! $account->active) {
            throw ValidationException::withMessages([
                'finance_account_id' => "La cuenta «{$account->name}» está dada de baja.",
            ]);
        }

        return DB::transaction(function () use ($account, $type, $amount, $concept, $attributes) {
            $locked = FinanceAccount::withoutGlobalScopes()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $balance = round((float) $locked->balance + ($type === 'ingreso' ? $amount : -$amount), 2);
            $locked->update(['balance' => $balance]);
            $account->balance = $balance;

            return FinanceMovement::withoutGlobalScopes()->create([
                'company_id' => $locked->company_id,
                'finance_account_id' => $locked->id,
                'type' => $type,
                'origin' => $attributes['origin'] ?? 'manual',
                'concept' => $concept,
                'amount' => round($amount, 2),
                'balance_after' => $balance,
                'occurred_on' => $attributes['occurred_on'] ?? now()->toDateString(),
                'reference' => $attributes['reference'] ?? null,
                'transfer_movement_id' => $attributes['transfer_movement_id'] ?? null,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /** Pasa dinero de una cuenta a otra: dos movimientos enlazados. */
    public function transfer(FinanceAccount $from, FinanceAccount $to, float $amount, ?string $concept = null, CarbonInterface|string|null $on = null): array
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_account_id' => 'Elige una cuenta distinta a la de origen.']);
        }

        return DB::transaction(function () use ($from, $to, $amount, $concept, $on) {
            $attrs = ['origin' => 'transferencia', 'occurred_on' => $on ?? now()->toDateString()];

            $out = $this->move($from, 'egreso', $amount, $concept ?: "Transferencia a {$to->name}", $attrs);
            $in = $this->move($to, 'ingreso', $amount, $concept ?: "Transferencia desde {$from->name}",
                $attrs + ['transfer_movement_id' => $out->id]);
            $out->update(['transfer_movement_id' => $in->id]);

            return [$out, $in];
        });
    }

    /**
     * Anula un movimiento: lo marca y escribe el contrario. En una
     * transferencia se anulan las dos patas.
     */
    public function cancel(FinanceMovement $movement, ?string $reason = null): void
    {
        if ($movement->isCancelled()) {
            throw ValidationException::withMessages(['error' => 'Ese movimiento ya está anulado.']);
        }

        DB::transaction(function () use ($movement, $reason) {
            foreach (array_filter([$movement, $movement->transfer_movement_id ? FinanceMovement::withoutGlobalScopes()->find($movement->transfer_movement_id) : null]) as $leg) {
                if ($leg->isCancelled()) {
                    continue;
                }

                $account = FinanceAccount::withoutGlobalScopes()->withTrashed()->findOrFail($leg->finance_account_id);
                $locked = FinanceAccount::withoutGlobalScopes()->withTrashed()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                $balance = round((float) $locked->balance + ($leg->isIncome() ? -(float) $leg->amount : (float) $leg->amount), 2);
                $locked->update(['balance' => $balance]);

                FinanceMovement::withoutGlobalScopes()->create([
                    'company_id' => $leg->company_id,
                    'finance_account_id' => $leg->finance_account_id,
                    'type' => $leg->isIncome() ? 'egreso' : 'ingreso',
                    'origin' => 'anulacion',
                    'concept' => 'Anulación: '.$leg->concept.($reason ? " ({$reason})" : ''),
                    'amount' => $leg->amount,
                    'balance_after' => $balance,
                    'occurred_on' => now()->toDateString(),
                    'reference' => $leg->reference,
                    'created_by' => auth()->id(),
                ]);

                $leg->update(['cancelled_at' => now()]);
            }
        });
    }
}
