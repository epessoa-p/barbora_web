<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\Personal;
use App\Models\WorkSchedule;
use App\Support\WorkScheduleForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El horario de una persona del personal, desde la app: ver, agregar franjas
 * (varios días de una vez) y quitarlas. Vale para cualquiera, atienda citas
 * o no. Mismas reglas que la web (App\Support\WorkScheduleForm).
 */
class ScheduleController extends ApiController
{
    public function index(Personal $personal): JsonResponse
    {
        return $this->payload($personal);
    }

    public function store(Request $request, Personal $personal, WorkScheduleForm $form): JsonResponse
    {
        $created = $form->store($request, $personal);

        return $this->payload($personal, $created === 1 ? 'Franja agregada.' : "Se agregaron {$created} franjas.", 201);
    }

    public function destroy(Personal $personal, WorkSchedule $schedule): JsonResponse
    {
        abort_unless($schedule->personal_id === $personal->id, 404);

        $schedule->delete();

        return $this->payload($personal, 'Franja quitada.');
    }

    protected function payload(Personal $personal, ?string $message = null, int $status = 200): JsonResponse
    {
        $schedules = $personal->schedules()->with('branch')->orderBy('weekday')->orderBy('start_time')->get();

        return $this->json(array_filter([
            'message' => $message,
            'personal' => ['id' => $personal->id, 'name' => $personal->full_name, 'bookable' => (bool) $personal->bookable],
            'weekdays' => collect(WorkSchedule::WEEKDAYS)->map(fn ($label, $n) => ['value' => $n, 'label' => $label])->values()->all(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->all(),
            'data' => $schedules->map(fn (WorkSchedule $s) => [
                'id' => $s->id,
                'weekday' => $s->weekday,
                'weekday_label' => $s->weekdayLabel(),
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time' => substr((string) $s->end_time, 0, 5),
                'branch_id' => $s->branch_id,
                'branch' => $s->branchLabel(),
            ])->all(),
        ], fn ($v) => $v !== null), $status);
    }
}
