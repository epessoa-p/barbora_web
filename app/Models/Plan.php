<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Producto comercial del operador: qué módulos incluye y qué límites impone.
 *
 * Modelo GLOBAL: pertenece al operador de la plataforma, no a un tenant.
 * Nunca lleva BelongsToCompany. Ver ARQUITECTURA §2 y §6.
 */
class Plan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Catálogo de módulos del sistema. Un plan incluye una lista de estos
     * slugs en su columna `features`.
     */
    public const MODULES = [
        'agenda'          => 'Agenda / Citas',
        'pos'             => 'Ventas / POS',
        'caja'            => 'Caja',
        'inventario'      => 'Inventario',
        'comisiones'      => 'Comisiones',
        'clientes'        => 'Clientes / Fidelización',
        'estadisticas'    => 'Estadísticas / Reportes',
        'reservas_online' => 'Reservas online',
    ];

    /**
     * Mapa permissions.module → feature de plan que lo habilita.
     *
     * Un módulo que NO figura aquí es ADMINISTRATIVO y está SIEMPRE disponible,
     * independientemente del plan contratado: companies, users, roles, cargos,
     * personal, branches, plans y subscriptions.
     *
     * Cuando llegue el dominio de barbería se añadirán aquí sus módulos, p. ej.
     *   'services' => 'agenda',  'appointments' => 'agenda',
     *   'sales'    => 'pos',     'products'     => 'inventario',
     *
     * El valor puede ser un array: el módulo se habilita si el plan incluye
     * ALGUNA de esas features (OR). Ver ARQUITECTURA §6.2.
     */
    public const PERMISSION_MODULE_FEATURES = [
        'appointments' => 'agenda',
        'agenda_blocks' => 'agenda',
        'services' => 'agenda',
        'service_categories' => 'agenda',
        'clients' => 'clientes',
        'warehouses' => 'inventario',
        'products' => 'inventario',
        'product_categories' => 'inventario',
        'stock' => 'inventario',
        'sales' => 'pos',
        'commissions' => 'comisiones',
        'commission_rules' => 'comisiones',
        'cajas' => 'caja',
        'cash' => 'caja',
        'reports' => 'estadisticas',
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_period', 'trial_days',
        'max_users', 'max_branches', 'max_products', 'features', 'active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_products' => 'integer',
        'features' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Features de plan que habilitan un módulo de permisos.
     * Devuelve [] si el módulo es administrativo (siempre disponible).
     *
     * @return array<int, string>
     */
    public static function featuresForPermissionModule(string $module): array
    {
        $feature = static::PERMISSION_MODULE_FEATURES[$module] ?? null;

        if ($feature === null) {
            return [];
        }

        return is_array($feature) ? $feature : [$feature];
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /**
     * Límite del plan para un recurso. NULL = ilimitado.
     *
     * @param  string  $key  'users' | 'branches' | 'products'
     */
    public function limitFor(string $key): ?int
    {
        $column = 'max_'.$key;

        return $this->{$column};
    }
}
