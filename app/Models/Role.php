<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Un rol tiene dueño (ver ARQUITECTURA §5.1):
 *
 *   company_id = NULL  -> rol del SISTEMA. Catálogo del operador del SaaS,
 *                         visible desde cualquier empresa, editable SÓLO por
 *                         el superadmin.
 *   company_id = <id>  -> rol PROPIO de esa barbería. Sólo ella lo ve y lo edita.
 *
 * Modelo GLOBAL a propósito: NO lleva CompanyScope, porque el scope escondería
 * los roles del sistema (company_id NULL) a todas las empresas. El filtrado se
 * hace explícito con scopeAvailableFor()/scopeAssignableBy().
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    /** Rol del operador del SaaS: ninguna empresa puede asignarlo ni tocarlo. */
    public const SUPER_ADMIN = 'super_admin';

    protected $fillable = ['company_id', 'name', 'slug', 'description'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Usuarios que tienen este rol en alguna empresa.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'role_id', 'user_id')
                    ->withPivot('company_id', 'active');
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    /* ---------------------------------------------------------------------
     | Propiedad
     |--------------------------------------------------------------------- */

    public function isSystem(): bool
    {
        return $this->company_id === null;
    }

    public function isOwnedBy(?int $companyId): bool
    {
        return $companyId !== null && $this->company_id === $companyId;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSystem() && $this->slug === self::SUPER_ADMIN;
    }

    /** ¿Puede esta empresa reescribir los permisos del rol? Sólo si es suyo. */
    public function permissionsEditableBy(?int $companyId): bool
    {
        return $this->isOwnedBy($companyId);
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |--------------------------------------------------------------------- */

    /** Sólo el catálogo del operador. */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('company_id');
    }

    /** Sólo los roles propios de una empresa. */
    public function scopeOwnedBy(Builder $query, ?int $companyId): Builder
    {
        return $companyId === null
            ? $query->whereRaw('1 = 0')
            : $query->where('company_id', $companyId);
    }

    /**
     * Lo que una empresa puede VER: el catálogo del sistema más lo suyo.
     * Sin empresa activa, sólo el catálogo del sistema.
     */
    public function scopeAvailableFor(Builder $query, ?int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId) {
            $q->whereNull('company_id');

            if ($companyId !== null) {
                $q->orWhere('company_id', $companyId);
            }
        });
    }

    /**
     * Lo que una empresa puede ASIGNAR a un cargo o a un usuario: lo visible
     * menos el rol del operador. Sin este filtro, un administrador de barbería
     * podría darse a sí mismo todos los permisos del SaaS.
     */
    public function scopeAssignableBy(Builder $query, ?int $companyId): Builder
    {
        return $query->availableFor($companyId)
                     ->where(fn (Builder $q) => $q->whereNotNull('company_id')
                                                  ->orWhere('slug', '!=', self::SUPER_ADMIN));
    }

    /* ---------------------------------------------------------------------
     | Creación
     |--------------------------------------------------------------------- */

    /**
     * Genera un slug libre dentro del dueño indicado. Los slugs sólo compiten
     * con los de su propia empresa, así que dos barberías pueden llamar
     * "Supervisor" a su rol sin que la segunda acabe con "supervisor_1".
     */
    public static function generateSlug(string $name, ?int $companyId): string
    {
        $base = Str::of($name)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
        $base = $base !== '' ? $base : 'rol';

        $slug = $base;
        $counter = 1;

        while (static::withTrashed()
                ->where('company_id', $companyId)
                ->where('slug', $slug)
                ->exists()) {
            $slug = $base . '_' . $counter;
            $counter++;
        }

        return $slug;
    }
}
