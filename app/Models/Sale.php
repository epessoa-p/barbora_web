<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasBranchNumber;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory, BelongsToCompany, HasBranchNumber;

    public const STATUSES = [
        'pagada' => 'Pagada',
        'anulada' => 'Anulada',
    ];

    /** Correlativo de sucursal: V-000001… (HasBranchNumber). */
    public const NUMBER_TYPE = 'V';

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'appointment_id', 'personal_id',
        'cash_session_id', 'number', 'status',
        'subtotal', 'discount', 'tip', 'total',
        'sold_at', 'notes', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'created_by',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tip' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /** Ventas que cuentan para la facturación: las anuladas no. */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'pagada');
    }

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('sold_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    // ── Estado ──────────────────────────────────────────────────────────────

    public function isCancelled(): bool
    {
        return $this->status === 'anulada';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return $this->isCancelled() ? 'danger' : 'success';
    }

    // ── Presentación ────────────────────────────────────────────────────────

    public function paymentsLabel(): string
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return $payments->map(fn (SalePayment $p) => $p->methodLabel())->unique()->implode(' + ')
            ?: 'Sin pagos';
    }

    /** ¿Cuánto de esta venta entró al cajón? Es lo que afecta al arqueo. */
    public function cashAmount(): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        // Los métodos que cuentan como efectivo los define la empresa, no un
        // literal: lo cobrado por tarjeta o billetera está en el banco.
        return (float) $payments
            ->whereIn('payment_method', PaymentMethod::cashSlugs())
            ->sum('amount');
    }

    /**
     * Sin sucursal indicada, la de su turno de caja, su cita o su barbero; si
     * no, la primera de la empresa. SaleRegistrar ya la suele traer resuelta.
     */
    protected function resolveNumberBranch(): void
    {
        $this->branch_id ??= CashSession::withoutGlobalScopes()->whereKey($this->cash_session_id)->value('branch_id')
            ?? Appointment::withoutGlobalScopes()->whereKey($this->appointment_id)->value('branch_id')
            ?? Personal::withoutGlobalScopes()->whereKey($this->personal_id)->value('branch_id')
            ?? Branch::defaultIdFor((int) $this->company_id);
    }
}
