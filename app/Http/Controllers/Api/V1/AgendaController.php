<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Personal;
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
    /**
     * Citas de un día (o de un rango, con `to`), opcionalmente de un barbero.
     *
     * La vista de semana del móvil manda `day` y `to`; sin `to` es un solo día.
     */
    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $appointments = Appointment::with(['client', 'personal', 'services', 'branch'])
            ->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($request->integer('personal_id'), fn ($q, $id) => $q->where('personal_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('starts_at')
            ->get();

        return $this->json([
            'day' => $from->toDateString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'data' => AppointmentResource::collection($appointments)->resolve(),
            'summary' => $this->summarize($appointments),
            'blocks' => $this->blocksFor($from, $to, $request->attributes->get('tenant_company')),
            // Para el filtro por barbero del móvil: quien puede ver la agenda
            // puede ver quiénes atienden, sin pedir otro permiso.
            'staff' => Personal::bookable()->orderBy('full_name')->get()
                ->map(fn (Personal $p) => [
                    'id' => $p->id,
                    'full_name' => $p->full_name,
                    'agenda_color' => $p->agenda_color,
                ])->all(),
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
    /**
     * El rango pedido: `day` (inicio) y `to` (fin, opcional). Sin `to`, o con
     * un `to` anterior a `day`, es un solo día. Se acota a 31 días para que
     * nadie pida un año entero desde el móvil.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(Request $request): array
    {
        $from = $this->parseDay($request->query('day'));
        $to = $request->filled('to') ? $this->parseDay($request->query('to')) : $from->copy();

        if ($to->lessThan($from)) {
            $to = $from->copy();
        }

        if ($from->diffInDays($to) > 31) {
            $to = $from->copy()->addDays(31);
        }

        return [$from, $to];
    }

    protected function blocksFor(Carbon $from, Carbon $to, ?Company $company): array
    {
        return AgendaBlock::with('personal')
            ->between($from->copy()->startOfDay(), $to->copy()->endOfDay())
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
