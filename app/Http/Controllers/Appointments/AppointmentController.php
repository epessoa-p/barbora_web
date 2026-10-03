<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use App\Support\AppointmentBooker;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    /** Las reglas de reserva viven en AppointmentBooker: las comparte con la API. */
    public function __construct(protected AppointmentBooker $booker)
    {
    }

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
        $data = $this->booker->validate($request, $this->targetCompanyId());

        $appointment = $this->booker->create($data, auth()->id());

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

        $data = $this->booker->validate($request, $appointment->company_id, $appointment);

        $this->booker->update($appointment, $data);

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
            $this->booker->assertSlotIsFree(
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
