<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory, BelongsToCompany;

    public const APPLIES_TO = [
        'servicios' => 'Todos los servicios',
        'productos' => 'Todos los productos',
        'servicio' => 'Un servicio concreto',
    ];

    public const TYPES = [
        'porcentaje' => 'Porcentaje',
        'monto_fijo' => 'Monto fijo',
    ];

    protected $fillable = [
        'company_id', 'personal_id', 'service_id',
        'applies_to', 'type', 'value', 'active', 'notes',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Cuanto más específica, antes gana. 1 es la más específica.
     * Ver la explicación del orden en la migración de commission_rules.
     */
    public function specificity(): int
    {
        return match (true) {
            $this->personal_id && $this->applies_to === 'servicio' => 1,
            (bool) $this->personal_id => 2,
            $this->applies_to === 'servicio' => 3,
            default => 4,
        };
    }

    public function targetLabel(): string
    {
        return $this->personal?->full_name ?? 'Todo el equipo';
    }

    public function scopeLabel(): string
    {
        return $this->applies_to === 'servicio'
            ? ($this->service?->name ?? 'Servicio eliminado')
            : self::APPLIES_TO[$this->applies_to];
    }

    /**
     * Solo el porcentaje se puede rotular aquí: un monto fijo necesita la
     * moneda de la empresa, y eso lo resuelve la vista con Money::format().
     */
    public function isPercentage(): bool
    {
        return $this->type === 'porcentaje';
    }

    public function percentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').' %';
    }

    /** Aplica la regla sobre el importe de una línea. */
    public function commissionFor(float $baseAmount): float
    {
        return $this->type === 'porcentaje'
            ? round($baseAmount * ((float) $this->value / 100), 2)
            : round((float) $this->value, 2);
    }
}
