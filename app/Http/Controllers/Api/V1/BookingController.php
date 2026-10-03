<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ClientResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use App\Support\AppointmentBooker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Reservar citas desde el móvil.
 *
 * Las reglas (turno del barbero, bloqueos, solapes, no reservar en el pasado)
 * son las de la web: viven en AppointmentBooker y aquí solo se exponen.
 */
class BookingController extends ApiController
{
    public function __construct(protected AppointmentBooker $booker)
    {
    }

    /** Barberos que atienden citas: los que se pueden elegir al reservar. */
    public function staff(): JsonResponse
    {
        $staff = Personal::bookable()->with('cargo')->orderBy('full_name')->get();

        return $this->json([
            'data' => $staff->map(fn (Personal $p) => [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'cargo' => $p->cargo?->name,
                'photo_url' => $p->photoUrl(),
                'agenda_color' => $p->agenda_color,
            ])->all(),
        ]);
    }

    /**
     * Horas libres de un barbero un día, para los servicios elegidos.
     *
     * La duración sale de la suma de los servicios, igual que al reservar: una
     * hora que aparece aquí es una hora que el POST va a aceptar.
     */
    public function slots(Request $request): JsonResponse
    {
        $companyId = $request->attributes->get('tenant_company')->id;

        $data = $request->validate([
            'personal_id' => ['required', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date_format:Y-m-d'],
            'services' => ['required', 'array', 'min:1'],
            'services.*' => [Rule::exists('services', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
        ], [
            'services.required' => 'Elige al menos un servicio: su duración decide qué horas caben.',
        ]);

        $barber = Personal::with('schedules')->findOrFail($data['personal_id']);
        $minutes = (int) Service::whereIn('id', $data['services'])->sum('duration_minutes');

        $slots = $this->booker->freeSlots(
            $barber,
            Carbon::createFromFormat('Y-m-d', $data['date']),
            $minutes,
            $data['branch_id'] ?? null,
        );

        return $this->json([
            'date' => $data['date'],
            'personal_id' => $barber->id,
            'duration_minutes' => $minutes,
            'data' => $slots->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->attributes->get('tenant_company');

        $data = $this->booker->validate($request, $company->id);

        $appointment = $this->booker->create($data, $request->user()->id);
        $appointment->load(['client', 'personal', 'services', 'branch']);

        return $this->json([
            'message' => 'Cita reservada para las '.$appointment->starts_at->format('H:i').'.',
            'data' => (new AppointmentResource($appointment))->resolve(),
        ], 201);
    }

    /**
     * Reprogramar: mover la cita de día, hora, barbero o servicios.
     *
     * Son las mismas reglas que al crear, pero sin dejar que una cita cerrada
     * (atendida, cancelada, no_show) se toque: eso ya es historia, y moverla
     * descuadraría comisiones y arqueo. Para esas se crea una nueva.
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        if (! $appointment->isEditable()) {
            return $this->json([
                'error' => 'appointment_closed',
                'message' => 'Una cita '.strtolower($appointment->statusLabel())
                           . ' ya no se puede reprogramar. Crea una nueva.',
            ], 422);
        }

        $data = $this->booker->validate($request, $appointment->company_id, $appointment);

        $this->booker->update($appointment, $data);
        $appointment->load(['client', 'personal', 'services', 'branch']);

        return $this->json([
            'message' => 'Cita reprogramada para las '.$appointment->starts_at->format('H:i').'.',
            'data' => (new AppointmentResource($appointment))->resolve(),
        ]);
    }

    /**
     * Alta rápida de cliente: nombre y teléfono, lo justo para reservar al que
     * llama por teléfono o entra por la puerta. La ficha completa se rellena
     * luego desde el panel.
     */
    public function storeClient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [
            'full_name.required' => 'Escribe el nombre del cliente.',
        ]);

        $client = Client::create([
            'company_id' => $request->attributes->get('tenant_company')->id,
            'full_name' => trim($data['full_name']),
            'phone' => $data['phone'] ?? null,
            'active' => true,
        ]);

        return $this->json([
            'message' => 'Cliente registrado.',
            'data' => (new ClientResource($client))->resolve(),
        ], 201);
    }
}
