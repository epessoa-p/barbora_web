<?php

namespace App\Support;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Personal;
use App\Models\Service;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reservar una cita: único sitio donde viven las reglas.
 *
 * Lo usan la agenda de la web y la API del móvil. Si cada uno validara por su
 * cuenta, tarde o temprano uno dejaría reservar encima de unas vacaciones y el
 * otro no.
 *
 * Las reglas, en el orden en que se comprueban:
 *   1. No se reserva en el pasado.
 *   2. La cita cabe entera en un turno del barbero ese día.
 *   3. No cae en un bloqueo (vacaciones, feriado, descanso).
 *   4. El barbero no tiene otra cita en ese hueco.
 */
class AppointmentBooker
{
    /** Cada cuántos minutos se ofrece una hora de inicio. */
    public const SLOT_STEP = 15;

    /**
     * Valida la petición y devuelve los datos listos para guardar.
     *
     * @return array{branch_id: ?int, personal_id: int, client_id: int, starts_at: Carbon, ends_at: Carbon, status: string, notes: ?string, services: array}
     */
    public function validate(Request $request, int $companyId, ?Appointment $appointment = null): array
    {
        $data = $request->validate([
            'personal_id' => ['required', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'services' => 'required|array|min:1',
            'services.*' => [Rule::exists('services', 'id')->where('company_id', $companyId)],
            'status' => ['nullable', Rule::in(array_keys(Appointment::STATUSES))],
            'notes' => 'nullable|string',
        ], [
            'services.required' => 'Elige al menos un servicio: su duración es la que reserva el hueco.',
            'client_id.required' => 'Elige el cliente de la cita.',
            'personal_id.required' => 'Elige el barbero que atenderá.',
        ]);

        $barber = Personal::with('schedules')->findOrFail($data['personal_id']);

        if (! $barber->bookable) {
            throw ValidationException::withMessages([
                'personal_id' => "{$barber->full_name} no atiende citas. Márcalo en su ficha si debería hacerlo.",
            ]);
        }

        // La duración y el precio se congelan aquí: si mañana cambia la tarifa,
        // esta cita conserva lo pactado.
        $services = Service::whereIn('id', $data['services'])->get();

        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time']);
        $end = $start->copy()->addMinutes((int) $services->sum('duration_minutes'));

        $this->assertNotInThePast($start, $appointment);
        $this->assertBarberWorks($barber, $start, $end, $data['branch_id'] ?? null);
        $this->assertNotBlocked($barber, $start, $end, $data['branch_id'] ?? null);
        $this->assertSlotIsFree($barber->id, $start, $end, $appointment?->id);

        return [
            'branch_id' => $data['branch_id'] ?? null,
            'personal_id' => $barber->id,
            'client_id' => (int) $data['client_id'],
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => $data['status'] ?? 'reservada',
            'notes' => $data['notes'] ?? null,
            'services' => $services->mapWithKeys(fn (Service $s) => [
                $s->id => ['duration_minutes' => $s->duration_minutes, 'price' => $s->price],
            ])->all(),
        ];
    }

    /** Guarda una cita ya validada. */
    public function create(array $data, ?int $createdBy): Appointment
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $appointment = Appointment::create([
                'branch_id' => $data['branch_id'],
                'personal_id' => $data['personal_id'],
                'client_id' => $data['client_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'created_by' => $createdBy,
            ]);

            $appointment->services()->attach($data['services']);

            return $appointment;
        });
    }

    public function update(Appointment $appointment, array $data): void
    {
        DB::transaction(function () use ($appointment, $data) {
            $appointment->update([
                'branch_id' => $data['branch_id'],
                'personal_id' => $data['personal_id'],
                'client_id' => $data['client_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'],
                'notes' => $data['notes'],
            ]);

            $appointment->services()->sync($data['services']);
        });
    }

    /**
     * Horas de inicio libres de un barbero para un servicio de X minutos.
     *
     * Aplica las mismas cuatro reglas que validate(), pero hacia delante: en
     * vez de decir «no» a una hora, ofrece solo las que dirían «sí». Así el
     * móvil enseña una lista de horas y no un reloj donde casi todo falla.
     *
     * Se resuelve en memoria con las citas y bloqueos del día: un par de
     * consultas, no una por hueco.
     *
     * @return Collection<int, string>  ['09:00', '09:15', …]
     */
    public function freeSlots(Personal $barber, CarbonInterface $day, int $minutes, ?int $branchId = null): Collection
    {
        $minutes = max($minutes, self::SLOT_STEP);
        $dayStart = Carbon::parse($day)->startOfDay();
        $dayEnd = $dayStart->copy()->endOfDay();

        $shifts = $barber->schedules
            ->where('active', true)
            ->where('weekday', $dayStart->dayOfWeekIso)
            ->filter(fn ($s) => $s->branch_id === null || $branchId === null || $s->branch_id === $branchId);

        if ($shifts->isEmpty()) {
            return collect();
        }

        $busy = Appointment::blocking()
            ->where('personal_id', $barber->id)
            ->where('starts_at', '<', $dayEnd)
            ->where('ends_at', '>', $dayStart)
            ->get(['starts_at', 'ends_at'])
            ->toBase()
            ->map(fn ($a) => [$a->starts_at, $a->ends_at]);

        $blocks = AgendaBlock::affecting($barber->id, $dayStart, $dayEnd, $branchId)
            ->get(['starts_at', 'ends_at'])
            ->toBase()
            ->map(fn ($b) => [$b->starts_at, $b->ends_at]);

        $taken = $busy->merge($blocks);
        $now = now();
        $slots = collect();

        foreach ($shifts->sortBy('start_time') as $shift) {
            $cursor = $dayStart->copy()->setTimeFromTimeString(substr((string) $shift->start_time, 0, 5));
            $shiftEnd = $dayStart->copy()->setTimeFromTimeString(substr((string) $shift->end_time, 0, 5));

            while ($cursor->copy()->addMinutes($minutes)->lessThanOrEqualTo($shiftEnd)) {
                $end = $cursor->copy()->addMinutes($minutes);

                $clashes = $taken->contains(
                    fn ($range) => $range[0]->lessThan($end) && $range[1]->greaterThan($cursor)
                );

                if (! $clashes && $cursor->greaterThan($now)) {
                    $slots->push($cursor->format('H:i'));
                }

                $cursor->addMinutes(self::SLOT_STEP);
            }
        }

        return $slots->unique()->sort()->values();
    }

    // ── Reglas ──────────────────────────────────────────────────────────────

    public function assertNotInThePast(Carbon $start, ?Appointment $appointment): void
    {
        // Al editar se permite tocar una cita ya empezada (corregir notas, por
        // ejemplo); lo que no se permite es reservar hacia atrás.
        if ($appointment === null && $start->isPast()) {
            throw ValidationException::withMessages([
                'time' => 'No se puede reservar en el pasado.',
            ]);
        }
    }

    public function assertBarberWorks(Personal $barber, Carbon $start, Carbon $end, ?int $branchId): void
    {
        if ($barber->worksDuring($start, $end, $branchId)) {
            return;
        }

        $day = $start->translatedFormat('l');
        $shifts = $barber->schedules
            ->where('active', true)
            ->where('weekday', $start->dayOfWeekIso)
            ->map(fn ($s) => $s->rangeLabel())
            ->implode(', ');

        throw ValidationException::withMessages([
            'time' => $shifts === ''
                ? "{$barber->full_name} no trabaja los {$day}."
                : "La cita no cabe en el turno de {$barber->full_name} del {$day} ({$shifts}).",
        ]);
    }

    /**
     * Un bloqueo manda sobre el horario: el barbero tiene turno los martes,
     * pero si está de vacaciones ese martes concreto, no se reserva.
     */
    public function assertNotBlocked(Personal $barber, Carbon $start, Carbon $end, ?int $branchId): void
    {
        $block = AgendaBlock::affecting($barber->id, $start, $end, $branchId)->first();

        if (! $block) {
            return;
        }

        $who = $block->isCompanyWide()
            ? 'La barbería está cerrada'
            : "{$barber->full_name} no está disponible";

        throw ValidationException::withMessages([
            'time' => "{$who}: {$block->title} ({$block->reasonLabel()}, {$block->rangeLabel()}).",
        ]);
    }

    public function assertSlotIsFree(int $personalId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $clash = Appointment::overlapping($personalId, $start, $end, $ignoreId)
            ->with('client')
            ->first();

        if (! $clash) {
            return;
        }

        throw ValidationException::withMessages([
            'time' => "Ese hueco ya está ocupado: {$clash->rangeLabel()} con "
                    . ($clash->client?->full_name ?? 'otro cliente').'.',
        ]);
    }
}
