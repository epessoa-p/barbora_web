<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Modelo GLOBAL: un usuario puede pertenecer a varias empresas, con un rol
 * distinto en cada una. Nunca lleva BelongsToCompany. Ver ARQUITECTURA §2.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'phone',
        'is_super_admin',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Empresas a las que pertenece este usuario
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
                    ->withPivot('role_id', 'active');
    }

    /**
     * Roles que tiene el usuario en sus empresas
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'company_user', 'user_id', 'role_id')
                    ->distinct();
    }

    /**
     * Permisos asignados directamente al usuario en una empresa
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permission', 'user_id', 'permission_id')
                    ->withPivot('company_id');
    }

    public function personal(): HasOne
    {
        return $this->hasOne(Personal::class);
    }

    /**
     * Verificar si el usuario tiene un rol específico en una empresa
     */
    public function hasRoleInCompany(string $roleSlug, ?Company $company): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if (!$company) {
            return false;
        }

        $role = $company->users()
            ->where('user_id', $this->id)
            ->first()?->pivot->role_id;

        if (!$role) {
            return false;
        }

        return Role::find($role)->slug === $roleSlug;
    }

    /**
     * Verificar si el usuario tiene un permiso en una empresa
     */
    public function hasPermissionInCompany(string $permissionSlug, ?Company $company): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if (!$company) {
            return false;
        }

        $permissions = $company->getPermissionsForUser($this);
        return in_array($permissionSlug, $permissions);
    }

    /**
     * Obtener todas las empresas activas del usuario
     */
    public function activeCompanies()
    {
        return $this->companies()
                    ->where('company_user.active', true)
                    ->where('companies.active', true);
    }

    /**
     * Obtener empresa actual de la sesión o la primera que pertenezca
     */
    public function getCurrentCompany(): ?Company
    {
        if (auth()->check() && session()->has('current_company_id')) {
            return Company::find(session('current_company_id'));
        }

        return $this->activeCompanies()->first();
    }
}

