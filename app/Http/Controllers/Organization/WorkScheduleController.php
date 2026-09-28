<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Personal;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkScheduleController extends Controller
{
    /** Vista general: la semana de todo el personal que atiende clientes. */
    public function index()
    {
        return view('organization.schedules.index', [
            'staff' => Personal::bookable()
                ->with(['cargo', 'schedules.branch'])
                ->orderBy('full_name')
                ->get(),
            'weekdays' => WorkSchedule::WEEKDAYS,
        ]);
    }

    /** Horario de una persona concreta. */
    public function show(Personal $personal)
    {
        $this->authorizePersonal($personal);

        return view('organization.schedules.show', [
            'personal' => $personal->load('cargo'),
            'schedules' => $personal->schedules()->with('branch')
                ->orderBy('weekday')->orderBy('start_time')->get(),
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'weekdays' => WorkSchedule::WEEKDAYS,
        ]);
    }

    public function store(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $data = $this->validated($request, $personal);

        // Se puede aplicar la misma franja a varios días de una vez: es como se
        // define un horario real («de lunes a viernes, de 9 a 13»).
        $created = 0;

        foreach ($data['weekdays'] as $weekday) {
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

        return redirect()->route('schedules.show', $personal)
            ->with('success', $created === 1
                ? 'Franja añadida al horario.'
                : "Se añadieron {$created} franjas al horario.");
    }

    public function destroy(Personal $personal, WorkSchedule $schedule)
    {
        $this->authorizePersonal($personal);

        if ($schedule->personal_id !== $personal->id) {
            abort(404);
        }

        $schedule->delete();

        return redirect()->route('schedules.show', $personal)
            ->with('success', 'Franja eliminada del horario.');
    }

    protected function validated(Request $request, Personal $personal): array
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
     * Un barbero no puede estar en dos sitios a la vez: si la franja nueva pisa
     * una existente, se rechaza indicando cuál.
     */
    protected function assertNoOverlap(WorkSchedule $candidate, Personal $personal): void
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

    protected function authorizePersonal(Personal $personal): void
    {
        $user = auth()->user();

        if (! $user->is_super_admin && $personal->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
