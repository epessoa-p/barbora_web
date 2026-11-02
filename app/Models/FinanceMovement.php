<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un ingreso o egreso de una cuenta. Nunca se borra: anular deja la marca y
 * escribe el movimiento contrario (origin «anulacion»), para que el saldo y el
 * historial cuadren siempre.
 */
class FinanceMovement extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'ingreso' => 'Ingreso',
        'egreso' => 'Egreso',
    ];

    public const ORIGINS = [
        'manual' => 'Manual',
        'apertura' => 'Saldo inicial',
        'transferencia' => 'Transferencia',
        'gasto' => 'Gasto',
        'compra' => 'Compra',
        'anulacion' => 'Anulación',
    ];

    protected $fillable = [
        'company_id', 'finance_account_id', 'type', 'origin', 'concept', 'amount',
        'balance_after', 'occurred_on', 'reference', 'transfer_movement_id',
        'created_by', 'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'occurred_on' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isIncome(): bool
    {
        return $this->type === 'ingreso';
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Solo lo manual se anula desde la cuenta; un gasto o una compra, desde su ficha. */
    public function isManual(): bool
    {
        return in_array($this->origin, ['manual', 'transferencia'], true);
    }

    public function originLabel(): string
    {
        return self::ORIGINS[$this->origin] ?? $this->origin;
    }
}
