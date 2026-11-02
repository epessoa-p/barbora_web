<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasBranchNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un gasto: alquiler, luz, un pago a un proveedor o a alguien del personal,
 * comida, pasajes… Sale de una cuenta o de la caja abierta (`source`), y
 * guarda el movimiento que lo descontó para poder revertirlo al anular.
 */
class Expense extends Model
{
    use BelongsToCompany, HasBranchNumber;

    /** Correlativo de sucursal: G-000001… */
    public const NUMBER_TYPE = 'G';

    public const SOURCES = [
        'account' => 'Cuenta',
        'cash' => 'Caja',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'number', 'expense_category_id', 'supplier_id', 'personal_id', 'commission_settlement_id',
        'description', 'amount', 'spent_on', 'source', 'finance_account_id', 'finance_movement_id',
        'cash_session_id', 'cash_movement_id', 'reference', 'notes', 'status',
        'cancelled_at', 'cancelled_by', 'cancel_reason', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'anulado');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id')->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id')->withTrashed();
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** La liquidación de comisiones que lo originó, si es el pago de una. */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'commission_settlement_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'anulado';
    }

    /** A quién se le pagó, si a alguien. */
    public function payeeName(): ?string
    {
        return $this->supplier?->name ?? $this->personal?->full_name;
    }

    /** «Caja · T-000012» o el nombre de la cuenta. */
    public function sourceLabel(): string
    {
        return $this->source === 'cash'
            ? 'Caja'.($this->cashSession?->number ? ' · '.$this->cashSession->number : '')
            : ($this->account?->name ?? 'Cuenta');
    }
}
