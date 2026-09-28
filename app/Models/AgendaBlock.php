<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un periodo en el que no se puede reservar.
 *
 * Complementa a WorkSchedule, no lo sustituye: el horario dice cuándo se
 * trabaja cada semana, el bloqueo tacha fechas concretas por encima. Un barbero
 * con turno los martes pero de vacaciones del 1 al 15 no atiende esos martes.
 *
 * Con personal_id en NULL el bloqueo es de toda la barbería: un feriado.
 */
class AgendaBlock extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const REASONS = [
        'vacaciones' => 'Vacaciones',
        'feriado' => 'Feriado',
        'descanso' => 'Descanso',
        'permiso' => 'Permiso',
        'otro' => 'Otro',
    ];

    protected $fillable = [
        'company_id', 'personal_id', 'branch_id',
        'title', 'reason', 'starts_at', 'ends_at', 'all_day', 'notes', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'all_day' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /**
     * Bloqueos que pisan el intervalo dado y afectan a ese barbero.
     *
     * Dos intervalos se solapan si cada uno empieza antes de que acabe el otro.
     * El borde no cuenta: un bloqueo que acaba a las 14:00 no choca con una
     * cita que empieza a las 14:00.
     */
    public function scopeAffecting(
        Builder $query,
        ?int $personalId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $branchId = null,
    ): Builder {
        return $query
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            // El de la barbería entera (personal_id NULL) afecta a todos.
            ->where(fn (Builder $q) => $q->whereNull('personal_id')
                                         ->when($personalId, fn (Builder $w) => $w->orWhere('personal_id', $personalId)))
            ->where(fn (Builder $q) => $q->whereNull('branch_id')
                                         ->when($branchId, fn (Builder $w) => $w->orWhere('branch_id', $branchId)));
    }

    /** Bloqueos que tocan el rango, para pintarlos en el calendario. */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>=', now());
    }

    // ── Presentación ────────────────────────────────────────────────────────

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function reasonColor(): string
    {
        return match ($this->reason) {
            'vacaciones' => 'info',
            'feriado' => 'danger',
            'descanso' => 'secondary',
            'permiso' => 'warning',
            default => 'dark',
        };
    }

    /** ¿A quién afecta? */
    public function scopeLabel(): string
    {
        return $this->personal_id
            ? ($this->personal?->full_name ?? 'Barbero eliminado')
            : 'Toda la barbería';
    }

    public function isCompanyWide(): bool
    {
        return $this->personal_id === null;
    }

    public function rangeLabel(): string
    {
        if ($this->all_day) {
            return $this->starts_at->isSameDay($this->ends_at)
                ? $this->starts_at->translatedFormat('d/m/Y').' · todo el día'
                : $this->starts_at->translatedFormat('d/m/Y').' – '.$this->ends_at->translatedFormat('d/m/Y');
        }

        if ($this->starts_at->isSameDay($this->ends_at)) {
            return $this->starts_at->translatedFormat('d/m/Y').' '
                 . $this->starts_at->format('H:i').' – '.$this->ends_at->format('H:i');
        }

        return $this->starts_at->translatedFormat('d/m/Y H:i').' – '.$this->ends_at->translatedFormat('d/m/Y H:i');
    }

    public function coversWholeDay(CarbonInterface $day): bool
    {
        return $this->starts_at->lessThanOrEqualTo($day->copy()->startOfDay())
            && $this->ends_at->greaterThanOrEqualTo($day->copy()->endOfDay());
    }
}
