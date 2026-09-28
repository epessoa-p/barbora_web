<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /** Citas del día: la pantalla de recepción. */
    public function index(Request $request)
    {
        $day = $this->parseDay($request->query('day'));

        $query = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->onDay($day)
            ->orderBy('starts_at');

        if ($personalId = $request->integer('personal')) {
            $query->where('personal_id', $personalId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $appointments = $query->get();

        return view('appointments.index', [
            'day' => $day,
            'appointments' => $appointments,
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'summary' => $this->summarize($appointments),
        ]);
    }

    public function create(Request $request)
    {
        return view('appointments.create', [
            'appointment' => null,
            'defaults' => [
                'starts_at' => $this->parseDay($request->query('day'))
                    ->setTimeFromTimeString($request->query('time', '09:00')),
                'personal_id' => $request->integer('personal') ?: null,
            ],
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $appointment = DB::transaction(function () use ($data) {
            $appointment = Appointment::create([
                'branch_id' => $data['branch_id'],
                'personal_id' => $data['personal_id'],
                'client_id' => $data['client_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'],
                'notes' => $data['notes'],
                'created_by' => auth()->id(),
            ]);

            $appointment->services()->attach($data['services']);

            return $appointment;
        });

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Cita reservada para el '
                . $appointment->starts_at->translatedFormat('l d \d\e F \a \l\a\s H:i') . '.');
    }

    public function show(Appointment $appointment)
    {
        $appointment->load(['client', 'personal', 'services', 'branch', 'creator']);

        return view('appointments.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        if (! $appointment->isEditable()) {
            return redirect()->route('appointments.show', $appointment)->withErrors([
                'error' => 'Una cita '.strtolower($appointment->statusLabel())
                         . ' ya no se puede reprogramar. Crea una nueva.',
            ]);
        }

        $appointment->load('services');

        return view('appointments.edit', ['appointment' => $appointment] + $this->formData());
    }

    public function update(Request $request, Appointment $appointment)
    {
        if (! $appointment->isEditable()) {
            return redirect()->route('appointments.show', $appointment)->withErrors([
                'error' => 'Una cita '.strtolower($appointment->statusLabel()).' ya no se puede reprogramar.',
            ]);
        }

        $data = $this->validated($request, $appointment);

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

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Cita actualizada exitosamente.');
    }

    /**
     * Cambiar el estado es la acción más frecuente del día a día, así que va
     * por su propia ruta: un botón, sin pasar por el formulario completo.
     */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Appointment::STATUSES))],
            'cancel_reason' => 'nullable|string|max:255',
        ]);

        // Al reactivar una cita liberada hay que comprobar que el hueco sigue
        // libre: puede haberse reservado otra cosa mientras tanto.
        if ($appointment->isReleased() && ! in_array($data['status'], Appointment::RELEASED_STATUSES, true)) {
            $this->assertSlotIsFree(
                $appointment->personal_id,
                $appointment->starts_at,
                $appointment->ends_at,
                $appointment->id,
            );
        }

        $appointment->update([
            'status' => $data['status'],
            'cancel_reason' => $data['status'] === 'cancelada' ? ($data['cancel_reason'] ?? null) : null,
        ]);

        return back()->with('success', 'La cita pasó a «'.$appointment->statusLabel().'».');
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return redirect()->route('appointments.index')
            ->with('success', 'Cita eliminada de la agenda.');
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────

    protected function formData(): array
    {
        return [
            'staff' => Personal::bookable()->with('schedules')->orderBy('full_name')->get(),
            'clients' => Client::where('active', true)->orderBy('full_name')->get(),
            'services' => Service::where('active', true)->with('category')->orderBy('name')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
        ];
    }

    protected function parseDay(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }

    /**
     * Valida la cita y calcula su fin a partir de los servicios elegidos.
     *
     * @return array{branch_id: ?int, personal_id: int, client_id: int, starts_at: Carbon, ends_at: Carbon, status: string, notes: ?string, services: array}
     */
    protected function validated(Request $request, ?Appointment $appointment = null): array
    {
        $companyId = $appointment?->company_id ?? $this->targetCompanyId();

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

    protected function assertNotInThePast(Carbon $start, ?Appointment $appointment): void
    {
        // Al editar se permite tocar una cita ya empezada (corregir notas, por
        // ejemplo); lo que no se permite es reservar hacia atrás.
        if ($appointment === null && $start->isPast()) {
            throw ValidationException::withMessages([
                'time' => 'No se puede reservar en el pasado.',
            ]);
        }
    }

    protected function assertBarberWorks(Personal $barber, Carbon $start, Carbon $end, ?int $branchId): void
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
    protected function assertNotBlocked(Personal $barber, Carbon $start, Carbon $end, ?int $branchId): void
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

    protected function assertSlotIsFree(int $personalId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
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

    /** @param  \Illuminate\Support\Collection<int, Appointment>  $appointments */
    protected function summarize($appointments): array
    {
        return [
            'total' => $appointments->count(),
            'pending' => $appointments->whereIn('status', ['reservada', 'confirmada'])->count(),
            'done' => $appointments->where('status', 'atendida')->count(),
            'minutes' => $appointments->reject->isReleased()->sum->durationMinutes(),
        ];
    }
}
