<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Observers\BranchObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Sucursal: local físico de una empresa. Recurso limitado por plan. */
#[ObservedBy(BranchObserver::class)]
class Branch extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'phone',
        'email',
        'address',
        'manager_name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    /** Almacén espejo, creado y mantenido por esta sucursal (BranchObserver). */
    public function warehouse(): HasOne
    {
        return $this->hasOne(Warehouse::class);
    }

    /** Personal cuya sucursal base es esta. */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    /**
     * La sucursal por defecto de la empresa (la primera activa), para lo que
     * llega sin sucursal. Null solo si la empresa no tiene ninguna.
     */
    public static function defaultIdFor(int $companyId): ?int
    {
        return static::allCompanies()
            ->where('company_id', $companyId)
            ->orderByDesc('active')
            ->orderBy('id')
            ->value('id');
    }

    /**
     * La sucursal base del usuario (la de su ficha de personal) en esa
     * empresa. Null si no tiene ficha o no tiene sucursal.
     */
    public static function idForUser(?User $user, ?int $companyId): ?int
    {
        if (! $user || ! $companyId) {
            return null;
        }

        $id = Personal::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->value('branch_id');

        return $id ? (int) $id : null;
    }

    /**
     * Las sucursales en las que trabaja el usuario: las que ve en los
     * selectores del día a día (vender, agendar, gastar, abrir caja).
     *
     *   · Superadmin y quien administra sucursales (branches.edit): todas.
     *   · Con ficha de personal: su base y sus adicionales.
     *   · Sin ficha (o sin ninguna asignada): todas, como hasta ahora.
     *
     * Devuelve null cuando son todas (sin filtro).
     *
     * @return array<int, int>|null
     */
    public static function workableIdsFor(?User $user, ?int $companyId): ?array
    {
        if (! $user || ! $companyId || $user->is_super_admin) {
            return null;
        }

        $company = Company::find($companyId);

        if ($company && $user->hasPermissionInCompany('branches.edit', $company)) {
            return null;
        }

        $personal = Personal::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->first();

        $ids = $personal?->branchIds() ?? [];

        return $ids === [] ? null : $ids;
    }

    /**
     * Las sucursales activas que el usuario puede elegir (ver workableIdsFor).
     *
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    public static function workableFor(?User $user, ?int $companyId)
    {
        $ids = static::workableIdsFor($user, $companyId);

        return static::where('active', true)
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }

    /**
     * Acota una consulta (cajas, turnos, almacenes) a las sucursales del
     * usuario. Lo que no tiene sucursal (un almacén central) se deja.
     */
    public static function limitToWorkable($query, string $column, ?User $user, ?int $companyId)
    {
        $ids = static::workableIdsFor($user, $companyId);

        return $query->when($ids !== null, fn ($q) => $q->where(
            fn ($w) => $w->whereIn($column, $ids)->orWhereNull($column),
        ));
    }

    /**
     * La que se propone en los formularios: la del usuario y, si no tiene, la
     * por defecto de la empresa.
     */
    public static function preferredIdFor(?User $user, ?int $companyId): ?int
    {
        return static::idForUser($user, $companyId) ?? ($companyId ? static::defaultIdFor($companyId) : null);
    }

    /**
     * Siguiente código libre de la empresa: S01, S02…
     *
     * Con allCompanies() y borradas incluidas, igual que Warehouse::nextCodeFor:
     * desde el Modo Global no hay empresa activa y un código no se reutiliza.
     */
    public static function nextCodeFor(int $companyId): string
    {
        $used = static::allCompanies()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->pluck('code')
            ->filter()
            ->all();

        $sequence = count($used) + 1;

        do {
            $code = 'S'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
            $sequence++;
        } while (in_array($code, $used, true));

        return $code;
    }
}