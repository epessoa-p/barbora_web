<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * En qué se gasta: alquiler, luz, comida, pasajes… Las define cada barbería;
 * al entrar por primera vez a Gastos se le crean las habituales (DEFAULTS).
 *
 * `payee` dice a quién se le suele pagar, para ofrecer el selector que toca:
 * un proveedor, alguien del personal o nadie en concreto.
 */
class ExpenseCategory extends Model
{
    use SoftDeletes, BelongsToCompany;

    public const PAYEES = [
        'ninguno' => 'Nadie en concreto',
        'proveedor' => 'Un proveedor',
        'personal' => 'Alguien del personal',
    ];

    /** @var array<int, array{0: string, 1: string}> */
    public const DEFAULTS = [
        ['PAGO A PROVEEDORES', 'proveedor'],
        ['PAGO A PERSONAL', 'personal'],
        ['ALQUILER', 'ninguno', true],
        ['LUZ', 'ninguno', true],
        ['AGUA', 'ninguno', true],
        ['INTERNET Y TELÉFONO', 'ninguno', true],
        ['COMIDA', 'ninguno'],
        ['PASAJES Y TRANSPORTE', 'ninguno'],
        ['PEDIDOS Y ENVÍOS', 'ninguno'],
        ['LIMPIEZA Y MANTENIMIENTO', 'ninguno'],
        ['OTROS', 'ninguno'],
    ];

    protected $fillable = ['company_id', 'name', 'payee', 'recurring', 'active', 'sort_order'];

    protected $casts = [
        'active' => 'boolean',
        'recurring' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Los grupos de la app (pestañas de Gastos): a proveedores, a personal y
     * servicios básicos (lo mensual o anual: alquiler, luz, agua…).
     */
    public const GROUPS = ['proveedor', 'personal', 'basicos'];

    public function scopeInGroup(Builder $query, ?string $group): Builder
    {
        return match ($group) {
            'proveedor', 'personal' => $query->where('payee', $group),
            'basicos' => $query->where('recurring', true),
            default => $query,
        };
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** La categoría de las liquidaciones de comisiones. */
    public const COMMISSIONS = 'COMISIONES';

    /**
     * Una categoría que usa el propio sistema (las comisiones): se crea si no
     * existe y se reactiva si se había dado de baja.
     */
    public static function system(int $companyId, string $name, string $payee = 'ninguno'): self
    {
        $category = static::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)->where('name', $name)->first();

        if (! $category) {
            return static::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'name' => $name, 'payee' => $payee, 'active' => true,
                'sort_order' => (int) static::withoutGlobalScopes()->where('company_id', $companyId)->max('sort_order') + 1,
            ]);
        }

        if ($category->trashed()) {
            $category->restore();
        }

        if (! $category->active) {
            $category->update(['active' => true]);
        }

        return $category;
    }

    /** Crea las habituales si la barbería todavía no tiene ninguna. */
    public static function ensureDefaults(int $companyId): void
    {
        if (static::withoutGlobalScopes()->withTrashed()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $i => $default) {
            [$name, $payee] = $default;

            static::withoutGlobalScopes()->create([
                'company_id' => $companyId, 'name' => $name, 'payee' => $payee, 'recurring' => $default[2] ?? false,
                'active' => true, 'sort_order' => $i,
            ]);
        }
    }

    public function payeeLabel(): string
    {
        return self::PAYEES[$this->payee] ?? $this->payee;
    }
}
