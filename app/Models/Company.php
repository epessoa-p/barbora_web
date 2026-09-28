<?php

namespace App\Models;

use App\Observers\CompanyObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Empresa = tenant. Todo dato operativo le pertenece vía company_id.
 *
 * Modelo GLOBAL: nunca lleva BelongsToCompany — es la tabla de tenants,
 * no un dato de tenant. Ver ARQUITECTURA §2.
 */
#[ObservedBy(CompanyObserver::class)]
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'tax_id', 'tax_id_label', 'country', 'currency', 'timezone',
        'address', 'phone', 'email', 'logo', 'description', 'receipt_footer', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Usuarios que pertenecen a esta empresa
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
                    ->withPivot('role_id', 'active');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * URL pública del logo, o null si todavía no se subió.
     *
     * Con asset() y no con Storage::url(): éste arma la URL con APP_URL, y si no
     * coincide con el host real (otro puerto, o un móvil que entra por la IP de
     * la red) la imagen sale rota. asset() usa el host de la propia petición.
     */
    public function logoUrl(): ?string
    {
        return $this->logo ? asset('storage/'.$this->logo) : null;
    }

    /** Lo que va al pie del comprobante. */
    public function receiptFooter(): string
    {
        return trim((string) $this->receipt_footer) !== ''
            ? $this->receipt_footer
            : '¡Gracias por tu visita!';
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function personals(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    /**
     * Obtener el rol de un usuario dentro de esta empresa
     */
    public function getRoleForUser(User $user): ?Role
    {
        $pivot = $this->users()
                      ->where('user_id', $user->id)
                      ->first()?->pivot;

        return $pivot ? Role::find($pivot->role_id) : null;
    }

    /**
     * Obtener permisos efectivos (del rol + permisos adicionales)
     */
    public function getPermissionsForUser(User $user): array
    {
        if ($user->is_super_admin) {
            return Permission::all()->pluck('slug')->toArray();
        }

        $role = $this->getRoleForUser($user);
        $permissions = [];

        if ($role) {
            $permissions = $role->permissions()->pluck('slug')->toArray();
        }

        // Agregar permisos adicionales directos
        $directPermissions = DB::table('user_permission')
            ->where('user_id', $user->id)
            ->where('company_id', $this->id)
            ->join('permissions', 'user_permission.permission_id', '=', 'permissions.id')
            ->pluck('permissions.slug')
            ->toArray();

        return array_unique(array_merge($permissions, $directPermissions));
    }

    // ── Suscripción, plan y cupos (fachada sobre Subscription) ───────────────

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Módulos realmente disponibles para esta empresa.
     *
     * @return array<int, string>
     */
    public function effectiveFeatures(): array
    {
        return $this->subscription?->effectiveFeatures() ?? [];
    }

    public function planAllows(string $feature): bool
    {
        return in_array($feature, $this->effectiveFeatures(), true);
    }

    /**
     * Tope real de un recurso para ESTA empresa. NULL = ilimitado.
     *
     * @param  string  $key  'users' | 'branches' | 'products'
     */
    public function effectiveLimit(string $key): ?int
    {
        return $this->subscription?->effectiveLimitFor($key);
    }

    /**
     * Consumo actual de un recurso limitado.
     *
     * Usa forCompany() a propósito: con el CompanyScope activo, un
     * where('company_id', …) normal añadiría un segundo filtro por la empresa
     * activa y devolvería 0 al contar desde el panel del superadmin.
     */
    public function usageFor(string $key): int
    {
        return match ($key) {
            'branches' => Branch::forCompany($this->id)->count(),
            'products' => Product::forCompany($this->id)->count(),
            'users'    => $this->users()->wherePivot('active', true)->count(),
            default    => 0,
        };
    }

    public function withinLimit(string $key): bool
    {
        $limit = $this->effectiveLimit($key);

        if ($limit === null) {
            return true;
        }

        return $this->usageFor($key) < $limit;
    }

    public function subscriptionAllowsWrite(): bool
    {
        return $this->subscription?->allowsWrite() ?? false;
    }

    public function subscriptionAllowsRead(): bool
    {
        return $this->subscription?->allowsRead() ?? false;
    }
}
