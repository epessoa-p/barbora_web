<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un pago de una compra: al contado o, después, de la deuda. */
class PurchasePayment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'purchase_id', 'amount', 'paid_on', 'source', 'finance_account_id',
        'finance_movement_id', 'cash_session_id', 'cash_movement_id', 'reference',
        'created_by', 'cancelled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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

    /** «Caja · T-000012» o el nombre de la cuenta. */
    public function sourceLabel(): string
    {
        return $this->source === 'cash'
            ? 'Caja'.($this->cashSession?->number ? ' · '.$this->cashSession->number : '')
            : ($this->account?->name ?? 'Cuenta');
    }
}
