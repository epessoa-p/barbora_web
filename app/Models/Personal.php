<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/** Empleado de una empresa (barbero, recepcionista, cajero…). */
class Personal extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    /** Eloquent pluralizaría a "personals"; la tabla es "personal". */
    protected $table = 'personal';

    protected $fillable = [
        'company_id',
        'cargo_id',
        'user_id',
        'full_name',
        'photo',
        'specialty',
        'agenda_color',
        'bookable',
        'id_number',
        'phone',
        'email',
        'address',
        'hire_date',
        'notes',
        'active',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'bookable' => 'boolean',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /** Quienes atienden clientes: los únicos que ocupan hueco en la agenda. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('bookable', true)->where('active', true);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * ¿Trabaja en ese intervalo? La cita tiene que caber entera dentro de una
     * de sus franjas; un servicio a caballo entre el turno y el cierre no vale.
     */
    public function worksDuring(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end, ?int $branchId = null): bool
    {
        return $this->schedules
            ->where('active', true)
            ->contains(fn (WorkSchedule $schedule) => $schedule->coversRange($start, $end, $branchId));
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::url($this->photo) : null;
    }

    /** Iniciales para el hueco cuando todavía no hay foto. */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    /** Minutos de trabajo a la semana, para dar contexto en los listados. */
    public function weeklyMinutes(): int
    {
        return $this->schedules->where('active', true)->sum(function (WorkSchedule $schedule) {
            $start = strtotime('1970-01-01 '.$schedule->start_time);
            $end = strtotime('1970-01-01 '.$schedule->end_time);

            return max(0, (int) (($end - $start) / 60));
        });
    }
}
