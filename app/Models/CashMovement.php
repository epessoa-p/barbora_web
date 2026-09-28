<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const TYPES = [
        'ingreso' => 'Ingreso',
        'egreso' => 'Gasto',
    ];

    /**
     * Los métodos que ofrece la empresa, como [slug => nombre].
     *
     * Antes era una constante con cuatro valores fijos, y eso dejaba fuera
     * Tigo Money, el QR de un banco concreto o cualquier billetera nueva.
     * Ahora cada barbería define los suyos (ver PaymentMethod).
     */
    public static function paymentMethods(): array
    {
        return PaymentMethod::options();
    }

    protected $fillable = [
        'company_id', 'cash_session_id', 'type', 'payment_method',
        'concept', 'amount', 'reference', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Solo el efectivo está en el cajón: es lo único que se arquea. */
    public function scopeCash(Builder $query): Builder
    {
        return $query->where('payment_method', 'efectivo');
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('type', 'ingreso');
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('type', 'egreso');
    }

    public function isIncome(): bool
    {
        return $this->type === 'ingreso';
    }

    /** Con signo: suma si entra, resta si sale. */
    public function signedAmount(): float
    {
        return $this->isIncome() ? (float) $this->amount : -(float) $this->amount;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function paymentMethodLabel(): string
    {
        return PaymentMethod::labelFor($this->payment_method);
    }

    /** ¿Este movimiento está en el cajón, o en el banco? */
    public function countsAsCash(): bool
    {
        return in_array($this->payment_method, PaymentMethod::cashSlugs(), true);
    }
}
