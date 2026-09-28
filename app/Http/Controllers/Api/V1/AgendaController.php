<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Company;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * La agenda desde el móvil.
 *
 * El CompanyScope ya acota todo a la barbería de la cabecera X-Company-Id, así
 * que aquí no se filtra por empresa a mano: hacerlo escondería un fallo del
 * scope en lugar de dejarlo salir.
 */
class AgendaController extends ApiController
{
    /** Citas de un día, opcionalmente de un solo barbero. */
    public function index(Request $request): JsonResponse
    {
        $day = $this->parseDay($request->query('day'));

        $appointments = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->onDay($day)
            ->when($request->integer('personal_id'), fn ($q, $id) => $q->where('personal_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('starts_at')
            ->get();

        return $this->json([
            'day' => $day->toDateString(),
            'data' => AppointmentResource::collection($appointments)->resolve(),
            'summary' => $this->summarize($appointments),
            'blocks' => $this->blocksFor($day, $request->attributes->get('tenant_company')),
        ]);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $appointment->load(['client', 'personal', 'services', 'branch']);

        return $this->json(['data' => (new AppointmentResource($appointment))->resolve()]);
    }

    /**
     * Cambiar el estado es lo que más hace un barbero desde el móvil: llega el
     * cliente y marca «atendida», o no llega y marca «no se presentó».
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Appointment::STATUSES))],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $appointment->isEditable() && $appointment->status !== $data['status']) {
            return $this->json([
                'error' => 'appointment_closed',
                'message' => 'Una cita '.strtolower($appointment->statusLabel()).' ya no se puede cambiar.',
            ], 422);
        }

        $appointment->update([
            'status' => $data['status'],
            'cancel_reason' => $data['status'] === 'cancelada' ? ($data['cancel_reason'] ?? null) : null,
        ]);

        $appointment->load(['client', 'personal', 'services', 'branch']);

        return $this->json([
            'message' => 'Cita marcada como '.strtolower($appointment->statusLabel()).'.',
            'data' => (new AppointmentResource($appointment))->resolve(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    /** @param  \Illuminate\Support\Collection<int, Appointment>  $appointments */
    protected function summarize($appointments): array
    {
        return [
            'total' => $appointments->count(),
            'pending' => $appointments->whereIn('status', ['reservada', 'confirmada'])->count(),
            'attended' => $appointments->where('status', 'atendida')->count(),
            'no_show' => $appointments->where('status', 'no_show')->count(),
            'minutes' => $appointments->reject->isReleased()->sum->durationMinutes(),
        ];
    }

    /** Vacaciones y feriados del día: la app tiene que poder avisar. */
    protected function blocksFor(Carbon $day, ?Company $company): array
    {
        return AgendaBlock::with('personal')
            ->between($day->copy()->startOfDay(), $day->copy()->endOfDay())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (AgendaBlock $block) => [
                'id' => $block->id,
                'title' => $block->title,
                'reason' => $block->reason,
                'reason_label' => $block->reasonLabel(),
                'personal_id' => $block->personal_id,
                'scope' => $block->scopeLabel(),
                'all_day' => (bool) $block->all_day,
                'starts_at' => ShopTime::iso($block->starts_at, $company),
                'ends_at' => ShopTime::iso($block->ends_at, $company),
            ])
            ->all();
    }

    protected function parseDay(?string $value): Carbon
    {
        try {
            return $value ? Carbon::parse($value)->startOfDay() : Carbon::today();
        } catch (\Throwable) {
            return Carbon::today();
        }
    }
}
