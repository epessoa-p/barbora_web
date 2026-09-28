<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\CommissionEntry;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\WorkSchedule;
use App\Support\Money;
use App\Support\ReportPeriod;
use App\Support\ShopTime;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Los datos del propio usuario: su horario, su agenda y sus comisiones.
 *
 * Estas rutas NO llevan check-permission a propósito: un barbero no necesita
 * permiso sobre la agenda ajena para ver la suya. A cambio, la responsabilidad
 * de acotar cae entera en este controlador, y por eso TODO parte de
 * personal() — la ficha del usuario autenticado en la empresa activa.
 *
 * Si algún método dejara de filtrar por ese personal_id, un barbero vería las
 * comisiones de sus compañeros. Está cubierto en ApiTest.
 */
class MyController extends ApiController
{
    public function schedule(Request $request): JsonResponse
    {
        $personal = $this->personal($request);

        if (! $personal) {
            return $this->noProfile();
        }

        $schedules = WorkSchedule::with('branch')
            ->where('personal_id', $personal->id)
            ->where('active', true)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        return $this->json([
            'data' => $schedules->map(fn (WorkSchedule $s) => [
                'id' => $s->id,
                'weekday' => $s->weekday,
                'weekday_label' => $s->weekdayLabel(),
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
                'branch' => $s->branch?->name,
            ])->all(),
        ]);
    }

    /** Mi día de trabajo: solo mis citas. */
    public function agenda(Request $request): JsonResponse
    {
        $personal = $this->personal($request);

        if (! $personal) {
            return $this->noProfile();
        }

        $day = $this->parseDay($request->query('day'));

        $appointments = Appointment::with(['client', 'services', 'branch'])
            ->where('personal_id', $personal->id)
            ->onDay($day)
            ->orderBy('starts_at')
            ->get();

        return $this->json([
            'day' => $day->toDateString(),
            'personal_id' => $personal->id,
            'data' => AppointmentResource::collection($appointments)->resolve(),
            'summary' => [
                'total' => $appointments->count(),
                'pending' => $appointments->whereIn('status', ['reservada', 'confirmada'])->count(),
                'attended' => $appointments->where('status', 'atendida')->count(),
                'minutes' => $appointments->reject->isReleased()->sum->durationMinutes(),
            ],
        ]);
    }

    /** Lo que llevo ganado en el periodo, y cuánto está sin liquidar. */
    public function commissions(Request $request): JsonResponse
    {
        $personal = $this->personal($request);

        if (! $personal) {
            return $this->noProfile();
        }

        $period = ReportPeriod::fromRequest($request);
        $company = $request->attributes->get('tenant_company');

        $entries = CommissionEntry::with('item')
            ->where('personal_id', $personal->id)
            ->whereBetween('earned_at', $period->range())
            ->orderByDesc('earned_at')
            ->get();

        $pending = $entries->whereNull('commission_settlement_id');

        $tips = (float) Sale::paid()
            ->where('personal_id', $personal->id)
            ->whereBetween('sold_at', $period->range())
            ->sum('tip');

        return $this->json([
            'period' => [
                'from' => $period->from->toDateString(),
                'to' => $period->to->toDateString(),
                'label' => $period->label(),
            ],
            'totals' => [
                'earned' => (float) $entries->sum('amount'),
                'pending' => (float) $pending->sum('amount'),
                'settled' => (float) $entries->whereNotNull('commission_settlement_id')->sum('amount'),
                'tips' => $tips,
                'currency' => $company?->currency,
                'currency_symbol' => Money::symbol($company),
            ],
            'data' => $entries->map(fn (CommissionEntry $entry) => [
                'id' => $entry->id,
                'earned_at' => ShopTime::iso($entry->earned_at, $company),
                'description' => $entry->item?->description,
                'base_amount' => (float) $entry->base_amount,
                'rate_label' => $entry->rateLabel(),
                'amount' => (float) $entry->amount,
                'settled' => $entry->isSettled(),
            ])->all(),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    /**
     * La ficha de personal del usuario autenticado DENTRO de la empresa activa.
     * El CompanyScope hace el trabajo: si el usuario tiene ficha en otra
     * barbería, aquí no aparece.
     */
    protected function personal(Request $request): ?Personal
    {
        return Personal::where('user_id', $request->user()->id)->first();
    }

    protected function noProfile(): JsonResponse
    {
        return $this->json([
            'error' => 'no_personal_profile',
            'message' => 'Tu usuario no tiene ficha de personal en esta barbería.',
        ], 404);
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
