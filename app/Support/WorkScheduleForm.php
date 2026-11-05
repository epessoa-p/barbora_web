<?php

namespace App\Support;

use App\Models\Personal;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * El horario de una persona: reglas de las franjas y el alta de varios días
 * de una vez («de lunes a viernes, de 9 a 13»). Lo usan la web y la API.
 *
 * Vale para cualquier persona del personal, atienda citas o no (una
 * recepcionista también tiene horario).
 */
class WorkScheduleForm
{
    public function validate(Request $request, Personal $personal): array
    {
        $data = $request->validate([
            'weekdays' => 'required|array|min:1',
            'weekdays.*' => ['integer', Rule::in(array_keys(WorkSchedule::WEEKDAYS))],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('company_id', $personal->company_id),
            ],
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ], [
            'weekdays.required' => 'Elige al menos un día.',
            'end_time.after' => 'La hora de salida tiene que ser posterior a la de entrada.',
        ]);

        $data['branch_id'] = $data['branch_id'] ?? null;

        return $data;
    }

    /**
     * Crea la franja en cada día pedido. Si alguna pisa una existente, no se
     * crea ninguna.
     *
     * @return int franjas creadas
     */
    public function store(Request $request, Personal $personal): int
    {
        $data = $this->validate($request, $personal);

        return DB::transaction(function () use ($data, $personal) {
            $created = 0;

            foreach (array_unique($data['weekdays']) as $weekday) {
                $schedule = new WorkSchedule([
                    'company_id' => $personal->company_id,
                    'personal_id' => $personal->id,
                    'branch_id' => $data['branch_id'],
                    'weekday' => $weekday,
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'active' => true,
                ]);

                $this->assertNoOverlap($schedule, $personal);

                $schedule->save();
                $created++;
            }

            return $created;
        });
    }

    /**
     * Una persona no puede estar en dos sitios a la vez: si la franja nueva
     * pisa una existente, se rechaza indicando cuál.
     */
    public function assertNoOverlap(WorkSchedule $candidate, Personal $personal): void
    {
        $existing = $personal->schedules()->where('weekday', $candidate->weekday)->get();

        foreach ($existing as $schedule) {
            if ($candidate->overlapsWith($schedule)) {
                throw ValidationException::withMessages([
                    'start_time' => "El {$candidate->weekdayLabel()} ya hay una franja de "
                                  . "{$schedule->rangeLabel()} ({$schedule->branchLabel()}) que se solapa con esta.",
                ]);
            }
        }
    }
}
