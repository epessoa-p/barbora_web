<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una franja de trabajo: «lunes de 09:00 a 13:00, en la sucursal Centro».
 */
class WorkSchedule extends Model
{
    use HasFactory, BelongsToCompany;

    /** ISO-8601: 1 = lunes … 7 = domingo. */
    public const WEEKDAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $fillable = [
        'company_id', 'personal_id', 'branch_id',
        'weekday', 'start_time', 'end_time', 'active',
    ];

    protected $casts = [
        'weekday' => 'integer',
        'active' => 'boolean',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '—';
    }

    /** «09:00 – 13:00», sin los segundos que guarda la base. */
    public function rangeLabel(): string
    {
        return $this->shortTime($this->start_time).' – '.$this->shortTime($this->end_time);
    }

    public function branchLabel(): string
    {
        return $this->branch?->name ?? 'Cualquier sucursal';
    }

    /**
     * ¿Esta franja cubre por completo el intervalo dado?
     *
     * Una cita tiene que caber entera dentro de un turno: no vale empezar a las
     * 12:30 un servicio de una hora si el turno acaba a las 13:00.
     */
    public function coversRange(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, ?int $branchId = null): bool
    {
        if ($this->weekday !== $start->dayOfWeekIso) {
            return false;
        }

        // Una franja sin sucursal vale para cualquiera.
        if ($this->branch_id !== null && $branchId !== null && $this->branch_id !== $branchId) {
            return false;
        }

        return $start->format('H:i') >= $this->shortTime($this->start_time)
            && $end->format('H:i') <= $this->shortTime($this->end_time);
    }

    /**
     * ¿Se pisa con otra franja? Dos turnos del mismo día solo conviven si no
     * se solapan, y solo importan si comparten sucursal (o alguna vale para
     * todas).
     */
    public function overlapsWith(WorkSchedule $other): bool
    {
        if ($this->weekday !== $other->weekday) {
            return false;
        }

        $sameBranch = $this->branch_id === $other->branch_id
            || $this->branch_id === null
            || $other->branch_id === null;

        if (! $sameBranch) {
            return false;
        }

        return $this->shortTime($this->start_time) < $this->shortTime($other->end_time)
            && $this->shortTime($other->start_time) < $this->shortTime($this->end_time);
    }

    protected function shortTime(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }
}
