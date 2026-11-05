<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Personal;
use App\Models\WorkSchedule;
use App\Support\WorkScheduleForm;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    /**
     * Vista general: la semana de todo el personal activo. No solo quien
     * atiende citas: una recepcionista también tiene horario.
     */
    public function index()
    {
        return view('organization.schedules.index', [
            'staff' => Personal::where('active', true)
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

    public function store(Request $request, Personal $personal, WorkScheduleForm $form)
    {
        $this->authorizePersonal($personal);

        // Se puede aplicar la misma franja a varios días de una vez: es como se
        // define un horario real («de lunes a viernes, de 9 a 13»).
        $created = $form->store($request, $personal);

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

    protected function authorizePersonal(Personal $personal): void
    {
        $user = auth()->user();

        if (! $user->is_super_admin && $personal->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
