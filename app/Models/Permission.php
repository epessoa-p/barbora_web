<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Módulos que pertenecen al OPERADOR del SaaS, no a las barberías.
     * Una empresa nunca puede concederlos desde sus propios roles: si pudiera,
     * un administrador se daría de alta empresas, planes o roles del sistema.
     * El superadmin sí los reparte (los siembra RolePermissionSeeder).
     */
    public const PLATFORM_MODULES = ['companies', 'plans', 'subscriptions', 'roles'];

    protected $fillable = ['name', 'slug', 'module', 'description'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function isPlatform(): bool
    {
        return in_array($this->module, self::PLATFORM_MODULES, true);
    }

    /**
     * Módulos que en un cargo solo ve y concede el superadmin. Las empresas
     * gestionan a su gente con Personal y Cargos, no con Usuarios y Roles.
     */
    public const SUPER_ADMIN_MODULES = ['users', 'roles'];

    /** Permisos que una empresa puede conceder a sus propios roles. */
    public function scopeGrantableByCompany(Builder $query): Builder
    {
        return $query->whereNotIn('module', array_values(array_unique([
            ...self::PLATFORM_MODULES,
            ...self::SUPER_ADMIN_MODULES,
        ])));
    }

    /**
     * Permisos que este usuario puede conceder desde un cargo: el superadmin
     * suma Usuarios y Roles; empresas, planes y suscripciones nunca.
     */
    public function scopeGrantableBy(Builder $query, ?User $user): Builder
    {
        if (! $user?->is_super_admin) {
            return $query->grantableByCompany();
        }

        return $query->whereNotIn('module', array_values(array_diff(
            self::PLATFORM_MODULES,
            self::SUPER_ADMIN_MODULES,
        )));
    }
}
