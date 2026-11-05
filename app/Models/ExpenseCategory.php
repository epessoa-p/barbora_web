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

    /** Cada cuánto se paga un servicio básico. */
    public const FREQUENCIES = [
        'mensual' => 'Mensual',
        'anual' => 'Anual',
    ];

    /**
     * El tipo que se elige al crear o editar: decide `payee` y `recurring`.
     * Un servicio básico no tiene destinatario: se paga la factura.
     */
    public const TYPES = [
        'general' => 'Gasto general',
        'basicos' => 'Servicio básico',
        'proveedor' => 'Pago a proveedor',
        'personal' => 'Pago a personal',
    ];

    protected $fillable = ['company_id', 'name', 'payee', 'recurring', 'frequency', 'active', 'sort_order'];

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
     * Los grupos (pestañas de Gastos), sin solaparse: generales, a
     * proveedores, a personal y servicios básicos (lo mensual o anual:
     * alquiler, luz, agua, hosting…). Servicio básico manda sobre el resto.
     */
    public const GROUPS = ['general', 'proveedor', 'personal', 'basicos'];

    /** Sin grupo (o uno desconocido), no filtra. */
    public function scopeInGroup(Builder $query, ?string $group): Builder
    {
        return match ($group) {
            'basicos' => $query->where('recurring', true),
            'proveedor', 'personal' => $query->where('recurring', false)->where('payee', $group),
            'general' => $query->where('recurring', false)->where('payee', 'ninguno'),
            default => $query,
        };
    }

    /** El grupo de esta categoría, con la misma regla que scopeInGroup. */
    public function group(): string
    {
        return $this->recurring ? 'basicos' : (in_array($this->payee, ['proveedor', 'personal'], true) ? $this->payee : 'general');
    }

    /** El tipo elegido → payee, recurring (y frequency si es servicio básico). */
    public static function attributesForType(string $type, ?string $frequency = null): array
    {
        return match ($type) {
            'basicos' => ['payee' => 'ninguno', 'recurring' => true, 'frequency' => array_key_exists((string) $frequency, self::FREQUENCIES) ? $frequency : 'mensual'],
            'proveedor', 'personal' => ['payee' => $type, 'recurring' => false],
            default => ['payee' => 'ninguno', 'recurring' => false],
        };
    }

    /**
     * Reglas del alta y la edición, las mismas en la web y en la API.
     *
     * Se elige el `type` (general, servicio básico, a proveedor, a personal)
     * y, si es servicio básico, la `frequency`. Los formularios viejos que
     * mandan `payee` y la casilla `recurring` siguen funcionando.
     */
    public static function validateForm(\Illuminate\Http\Request $request, ?int $companyId, ?self $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('expense_categories', 'name')->where('company_id', $companyId)->ignore($category?->id)],
            'type' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(self::TYPES))],
            'frequency' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(self::FREQUENCIES))],
            'payee' => [\Illuminate\Validation\Rule::requiredIf(! $request->filled('type')), 'nullable', \Illuminate\Validation\Rule::in(array_keys(self::PAYEES))],
            'recurring' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Ponle un nombre a la categoría.',
            'name.unique' => 'Ya hay una categoría con ese nombre.',
            'payee.required' => 'Elige el tipo de gasto.',
        ]);

        $attributes = ['name' => trim($data['name'])];

        if ($request->filled('type')) {
            return $attributes + self::attributesForType($data['type'], $data['frequency'] ?? null);
        }

        // Formulario viejo: payee + casilla de servicio básico.
        $attributes['payee'] = $data['payee'];
        if ($request->has('recurring') || ! $category) {
            $attributes['recurring'] = $request->boolean('recurring');
        }
        if (! empty($data['frequency'])) {
            $attributes['frequency'] = $data['frequency'];
        }

        return $attributes;
    }

    public function isAnnual(): bool
    {
        return $this->recurring && $this->frequency === 'anual';
    }

    /**
     * La categoría con la que se registra un pago a proveedor o a personal
     * sin tener que elegirla: la primera activa del grupo, o la habitual
     * («PAGO A PROVEEDORES» / «PAGO A PERSONAL») si no hay ninguna.
     */
    public static function defaultFor(int $companyId, string $group): self
    {
        $name = $group === 'personal' ? 'PAGO A PERSONAL' : 'PAGO A PROVEEDORES';
        $inGroup = static::withoutGlobalScopes()->where('company_id', $companyId)->active()->inGroup($group);

        // La habitual primero; si la renombraron, la primera del grupo que no
        // sea la de comisiones (esa la usa el sistema).
        return (clone $inGroup)->where('name', $name)->first()
            ?? (clone $inGroup)->where('name', '!=', self::COMMISSIONS)->ordered()->first()
            ?? static::system($companyId, $name, $group);
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
