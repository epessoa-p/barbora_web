<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un turno de caja: se abre con un fondo, acumula movimientos y se cierra
 * contando el efectivo.
 */
class CashSession extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id', 'caja_id', 'status',
        'opened_at', 'opened_by', 'opening_amount', 'opening_notes',
        'closed_at', 'closed_by', 'closing_amount', 'expected_amount', 'difference', 'closing_notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('id');
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'abierta');
    }

    public function isOpen(): bool
    {
        return $this->status === 'abierta';
    }

    // ── Cálculos del turno ──────────────────────────────────────────────────

    /**
     * Totales del turno, separando el efectivo del resto.
     *
     * @return array{income: float, expense: float, cash_income: float, cash_expense: float, expected_cash: float, net: float}
     */
    public function totals(): array
    {
        $movements = $this->relationLoaded('movements') ? $this->movements : $this->movements()->get();

        $income = (float) $movements->where('type', 'ingreso')->sum('amount');
        $expense = (float) $movements->where('type', 'egreso')->sum('amount');

        // Solo lo que de verdad está en el cajón. La lista sale de los métodos
        // de pago de la empresa, no de un literal: así añadir «Tigo Money» no
        // lo mete por error en el arqueo, que es dinero que está en el banco.
        $cash = PaymentMethod::cashSlugs();

        $cashIncome = (float) $movements->where('type', 'ingreso')
            ->whereIn('payment_method', $cash)->sum('amount');

        $cashExpense = (float) $movements->where('type', 'egreso')
            ->whereIn('payment_method', $cash)->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'cash_income' => $cashIncome,
            'cash_expense' => $cashExpense,
            // Lo que debería haber en el cajón: fondo + efectivo que entró − el que salió.
            'expected_cash' => (float) $this->opening_amount + $cashIncome - $cashExpense,
            'net' => $income - $expense,
        ];
    }

    /** Desglose por método de pago, para el resumen del cierre. */
    public function byPaymentMethod(): array
    {
        $movements = $this->relationLoaded('movements') ? $this->movements : $this->movements()->get();

        $breakdown = [];

        // Se recorren los métodos configurados, más cualquier slug que aparezca
        // en los movimientos: un método dado de baja tiene que seguir saliendo
        // en el arqueo de los turnos donde se usó.
        $slugs = collect(array_keys(PaymentMethod::options()))
            ->merge($movements->pluck('payment_method'))
            ->filter()
            ->unique()
            ->values();

        foreach ($slugs as $method) {
            $rows = $movements->where('payment_method', $method);

            $breakdown[$method] = [
                'income' => (float) $rows->where('type', 'ingreso')->sum('amount'),
                'expense' => (float) $rows->where('type', 'egreso')->sum('amount'),
                'count' => $rows->count(),
            ];
        }

        return $breakdown;
    }

    /** Efectivo que debería haber ahora mismo en un turno abierto. */
    public function expectedCash(): float
    {
        return $this->isOpen() ? $this->totals()['expected_cash'] : (float) $this->expected_amount;
    }

    public function durationLabel(): string
    {
        $end = $this->closed_at ?? now();
        $minutes = (int) $this->opened_at->diffInMinutes($end);

        return $minutes < 60
            ? "{$minutes} min"
            : intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
    }

    /** Sobrante, faltante o cuadrada: es lo primero que se mira en un arqueo. */
    public function differenceLabel(): string
    {
        $difference = (float) $this->difference;

        return match (true) {
            abs($difference) < 0.01 => 'Cuadrada',
            $difference > 0 => 'Sobrante',
            default => 'Faltante',
        };
    }

    public function differenceColor(): string
    {
        $difference = (float) $this->difference;

        return match (true) {
            abs($difference) < 0.01 => 'success',
            $difference > 0 => 'info',
            default => 'danger',
        };
    }
}
