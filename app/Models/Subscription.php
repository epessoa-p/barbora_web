<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vincula UNA empresa a UN plan. Relación 1:1 con la empresa.
 *
 * Modelo GLOBAL: NO lleva BelongsToCompany. Si se filtrara por company_id,
 * la empresa se auto-filtraría antes de poder comprobar su propio acceso y el
 * superadmin no podría listarlas. Ver ARQUITECTURA §2.
 *
 * El estado efectivo se DERIVA de las fechas, no solo de la columna `status`:
 * una suscripción vencida corta el acceso sola. Ver ARQUITECTURA §7.
 */
class Subscription extends Model
{
    use HasFactory;

    public const STATUSES = [
        'trial'     => 'En prueba',
        'active'    => 'Activa',
        'past_due'  => 'Vencida',
        'suspended' => 'Suspendida',
        'cancelled' => 'Cancelada',
    ];

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'trial_ends_at', 'current_period_end',
        'grace_days', 'notes', 'created_by',
        'max_users_override', 'max_branches_override', 'max_products_override',
        'features_override',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_days' => 'integer',
        'max_users_override' => 'integer',
        'max_branches_override' => 'integer',
        'max_products_override' => 'integer',
        'features_override' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Overrides por empresa (ARQUITECTURA §8) ──────────────────────────────

    /**
     * Límite real de esta empresa: el override si existe, si no el del plan.
     * NULL = ilimitado.
     */
    public function effectiveLimitFor(string $key): ?int
    {
        $override = $this->{'max_'.$key.'_override'};

        if ($override !== null) {
            return (int) $override;
        }

        return $this->plan?->limitFor($key);
    }

    /**
     * Módulos reales de esta empresa: el override REEMPLAZA por completo a los
     * del plan si no es null.
     *
     * @return array<int, string>
     */
    public function effectiveFeatures(): array
    {
        return $this->features_override ?? ($this->plan?->features ?? []);
    }

    // ── Estado derivado de fechas (ARQUITECTURA §7.2) ────────────────────────

    public function onTrial(): bool
    {
        return $this->status === 'trial'
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function isCurrent(): bool
    {
        return $this->status === 'active'
            && $this->current_period_end !== null
            && $this->current_period_end->isFuture();
    }

    /** Cortada a mano por el operador. */
    public function isBlocked(): bool
    {
        return in_array($this->status, ['suspended', 'cancelled'], true);
    }

    public function graceEndsAt(): ?Carbon
    {
        $end = $this->status === 'trial' ? $this->trial_ends_at : $this->current_period_end;

        return $end?->copy()->addDays($this->grace_days ?? 0);
    }

    /** Vencida pero dentro del periodo de gracia: entra, pero solo lee. */
    public function inGrace(): bool
    {
        if ($this->isBlocked() || $this->onTrial() || $this->isCurrent()) {
            return false;
        }

        return $this->graceEndsAt()?->isFuture() ?? false;
    }

    /** Uso normal: leer y escribir. */
    public function allowsWrite(): bool
    {
        return ! $this->isBlocked() && ($this->onTrial() || $this->isCurrent());
    }

    /** Al menos entrar y consultar. */
    public function allowsRead(): bool
    {
        return $this->allowsWrite() || $this->inGrace();
    }

    /** Etiqueta corta para la interfaz. */
    public function statusLabel(): string
    {
        if ($this->isBlocked()) {
            return self::STATUSES[$this->status];
        }

        if ($this->onTrial()) {
            return 'En prueba';
        }

        if ($this->isCurrent()) {
            return 'Al día';
        }

        if ($this->inGrace()) {
            return 'Solo lectura';
        }

        return 'Vencida';
    }
}
