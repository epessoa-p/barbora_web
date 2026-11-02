<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Recordatorios de cita desde el móvil. Lo mismo que la pantalla web
 * (Appointments\ReminderController): no envía nada solo; da el enlace de
 * WhatsApp con el mensaje escrito y apunta quién avisó, para no avisar dos
 * veces.
 */
class ReminderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $days = max(0, min(14, (int) $request->query('days', 1)));
        $company = $request->attributes->get('tenant_company');

        $appointments = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->whereBetween('starts_at', [Carbon::today()->startOfDay(), Carbon::today()->addDays($days)->endOfDay()])
            ->whereIn('status', ['reservada', 'confirmada'])
            ->orderBy('starts_at')
            ->get();

        return $this->json([
            'days' => $days,
            'data' => $appointments->map(fn (Appointment $a) => $this->payload($a, $company))->all(),
        ]);
    }

    /** Marca la cita como avisada. */
    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->isReleased()) {
            throw ValidationException::withMessages(['error' => 'Esa cita ya no está activa.']);
        }

        $appointment->update(['reminded_at' => now(), 'reminded_by' => $request->user()->id]);

        return $this->json([
            'message' => 'Recordatorio marcado.',
            'data' => $this->payload($appointment->load(['client', 'personal', 'services', 'branch']),
                $request->attributes->get('tenant_company')),
        ]);
    }

    /** Deshace la marca, por si se apuntó por error. */
    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $appointment->update(['reminded_at' => null, 'reminded_by' => null]);

        return $this->json([
            'message' => 'Recordatorio desmarcado.',
            'data' => $this->payload($appointment->load(['client', 'personal', 'services', 'branch']),
                $request->attributes->get('tenant_company')),
        ]);
    }

    protected function payload(Appointment $appointment, $company): array
    {
        return [
            ...(new AppointmentResource($appointment))->resolve(),
            'whatsapp_url' => $appointment->whatsappUrl($company),
            'reminder_message' => $appointment->reminderMessage($company),
        ];
    }
}
