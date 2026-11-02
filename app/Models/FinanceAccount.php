<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una cuenta de la barbería: capital, caja chica, banco, billetera… Es el
 * dinero que NO está en el cajón de la caja registradora.
 *
 * El saldo (`balance`) es corriente: lo mueve App\Support\Treasury junto con
 * cada movimiento, en la misma transacción.
 */
class FinanceAccount extends Model
{
    use SoftDeletes, BelongsToCompany;

    public const TYPES = [
        'capital' => 'Capital',
        'caja_chica' => 'Caja chica',
        'banco' => 'Banco',
        'billetera' => 'Billetera digital',
        'otro' => 'Otra',
    ];

    protected $fillable = [
        'company_id', 'branch_id', 'name', 'type', 'bank_name', 'account_number',
        'opening_balance', 'balance', 'notes', 'active',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'balance' => 'decimal:2',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FinanceMovement::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function icon(): string
    {
        return match ($this->type) {
            'capital' => 'bi-safe',
            'caja_chica' => 'bi-wallet2',
            'banco' => 'bi-bank',
            'billetera' => 'bi-phone',
            default => 'bi-cash-coin',
        };
    }
}
