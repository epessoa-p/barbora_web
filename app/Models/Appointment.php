<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    public const STATUSES = [
        'reservada' => 'Reservada',
        'confirmada' => 'Confirmada',
        'atendida' => 'Atendida',
        'no_show' => 'No se presentó',
        'cancelada' => 'Cancelada',
    ];

    /** Estados que ya no ocupan hueco: el barbero vuelve a estar libre. */
    public const RELEASED_STATUSES = ['cancelada', 'no_show'];

    protected $fillable = [
        'company_id', 'branch_id', 'personal_id', 'client_id',
        'starts_at', 'ends_at', 'status', 'notes', 'cancel_reason', 'created_by',
        'reminded_at', 'reminded_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reminded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ── Relaciones ──────────────────────────────────────────────────────────

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withPivot('duration_minutes', 'price')
            ->withTimestamps();
    }

    // ── Consultas ───────────────────────────────────────────────────────────

    /** Citas que siguen ocupando hueco en la agenda. */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::RELEASED_STATUSES);
    }

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('starts_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->whereBetween('starts_at', [$from, $to]);
    }

    /**
     * Citas del mismo barbero que pisan el intervalo dado.
     *
     * Dos intervalos se solapan si cada uno empieza antes de que acabe el otro.
     * El borde no cuenta: una cita que acaba a las 10:00 no choca con otra que
     * empieza a las 10:00.
     */
    public function scopeOverlapping(
        Builder $query,
        int $personalId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreId = null,
    ): Builder {
        return $query->blocking()
            ->where('personal_id', $personalId)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId));
    }

    // ── Estado ──────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'reservada' => 'secondary',
            'confirmada' => 'primary',
            'atendida' => 'success',
            'no_show' => 'warning',
            'cancelada' => 'danger',
            default => 'secondary',
        };
    }

    public function isReleased(): bool
    {
        return in_array($this->status, self::RELEASED_STATUSES, true);
    }

    /** Una cita cerrada ya no se reprograma: se crea otra. */
    public function isEditable(): bool
    {
        return ! in_array($this->status, ['atendida', 'cancelada'], true);
    }

    // ── Presentación ────────────────────────────────────────────────────────

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function rangeLabel(): string
    {
        return $this->starts_at->format('H:i').' – '.$this->ends_at->format('H:i');
    }

    public function total(): float
    {
        return (float) $this->services->sum(fn (Service $service) => $service->pivot->price);
    }

    public function servicesLabel(): string
    {
        return $this->services->pluck('name')->implode(' + ') ?: 'Sin servicios';
    }

    // ── Recordatorio ────────────────────────────────────────────────────────

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reminded_by');
    }

    public function wasReminded(): bool
    {
        return $this->reminded_at !== null;
    }

    /**
     * Mensaje que se le manda al cliente. Se arma aquí, y no en la vista, para
     * que el día que exista envío automático diga exactamente lo mismo.
     */
    public function reminderMessage(?Company $company = null): string
    {
        $name = $this->client?->full_name ?? '';
        $greeting = $name !== '' ? "Hola {$name}, " : 'Hola, ';
        $shop = $company?->name ? " en {$company->name}" : '';

        return $greeting.'te recordamos tu cita'.$shop.' el '
            .$this->starts_at->translatedFormat('l d \d\e F').' a las '
            .$this->starts_at->format('H:i')
            .' ('.$this->servicesLabel().')'
            .($this->personal?->full_name ? ' con '.$this->personal->full_name : '')
            .'. ¡Te esperamos!';
    }

    /** Enlace de WhatsApp con el mensaje ya escrito, si hay teléfono. */
    public function whatsappUrl(?Company $company = null): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $this->client?->phone);

        if ($phone === '' || $phone === null) {
            return null;
        }

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($this->reminderMessage($company));
    }
}
