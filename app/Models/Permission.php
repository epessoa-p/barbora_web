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

    /** Permisos que una empresa puede conceder a sus propios roles. */
    public function scopeGrantableByCompany(Builder $query): Builder
    {
        return $query->whereNotIn('module', self::PLATFORM_MODULES);
    }
}
