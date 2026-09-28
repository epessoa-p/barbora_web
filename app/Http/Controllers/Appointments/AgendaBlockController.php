<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bloqueos de agenda: vacaciones, feriados, descansos.
 *
 * Al crearlos se avisa de las citas que caen dentro, pero no se cancelan solas:
 * decidir qué hacer con un cliente ya citado es cosa de la barbería, no del
 * sistema. Lo que sí se impide es reservar nuevas citas encima.
 */
class AgendaBlockController extends Controller
{
    public function index(Request $request)
    {
        $query = AgendaBlock::with(['personal', 'branch'])->orderBy('starts_at');

        if ($request->query('scope') === 'pasados') {
            $query->where('ends_at', '<', now())->reorder('starts_at', 'desc');
        } else {
            $query->upcoming();
        }

        if ($personalId = $request->integer('personal')) {
            $query->where('personal_id', $personalId);
        }

        return view('appointments.blocks.index', [
            'blocks' => $query->paginate(20)->withQueryString(),
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'scope' => $request->query('scope') === 'pasados' ? 'pasados' : 'proximos',
        ]);
    }

    public function create(Request $request)
    {
        return view('appointments.blocks.create', $this->formData() + [
            'block' => null,
            'defaults' => ['starts_at' => $this->parseDay($request->query('day'))],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $block = AgendaBlock::create($data + ['created_by' => auth()->id()]);

        return redirect()->route('agenda-blocks.index')
            ->with('success', $this->successMessage($block, 'creado'));
    }

    public function edit(AgendaBlock $agendaBlock)
    {
        return view('appointments.blocks.edit', $this->formData() + ['block' => $agendaBlock]);
    }

    public function update(Request $request, AgendaBlock $agendaBlock)
    {
        $agendaBlock->update($this->validated($request, $agendaBlock));

        return redirect()->route('agenda-blocks.index')
            ->with('success', $this->successMessage($agendaBlock->refresh(), 'actualizado'));
    }

    public function destroy(AgendaBlock $agendaBlock)
    {
        $agendaBlock->delete();

        return redirect()->route('agenda-blocks.index')
            ->with('success', 'Bloqueo eliminado. Ese periodo vuelve a estar disponible.');
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    protected function formData(): array
    {
        return [
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?AgendaBlock $block = null): array
    {
        $companyId = $block?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'reason' => ['required', Rule::in(array_keys(AgendaBlock::REASONS))],
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'all_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'required_if:all_day,0', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:all_day,0', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'title.required' => 'Ponle un nombre al bloqueo, para reconocerlo en el calendario.',
            'start_time.required_if' => 'Indica la hora de inicio, o marca «todo el día».',
            'end_time.required_if' => 'Indica la hora de fin, o marca «todo el día».',
        ]);

        $allDay = $request->boolean('all_day');

        $start = $allDay
            ? Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay()
            : Carbon::createFromFormat('Y-m-d H:i', $data['start_date'].' '.$data['start_time']);

        $end = $allDay
            ? Carbon::createFromFormat('Y-m-d', $data['end_date'])->endOfDay()
            : Carbon::createFromFormat('Y-m-d H:i', $data['end_date'].' '.$data['end_time']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'El fin del bloqueo tiene que ser posterior a su inicio.',
            ]);
        }

        return [
            'title' => trim($data['title']),
            'reason' => $data['reason'],
            'personal_id' => $data['personal_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'all_day' => $allDay,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * Avisa si el bloqueo pisa citas ya reservadas. No las toca: cancelar o
     * reprogramar a un cliente es una decisión de la barbería.
     */
    protected function successMessage(AgendaBlock $block, string $verb): string
    {
        $clashes = Appointment::blocking()
            ->where('starts_at', '<', $block->ends_at)
            ->where('ends_at', '>', $block->starts_at)
            ->when($block->personal_id, fn ($q) => $q->where('personal_id', $block->personal_id))
            ->when($block->branch_id, fn ($q) => $q->where('branch_id', $block->branch_id))
            ->count();

        $base = "Bloqueo {$verb}.";

        if ($clashes === 0) {
            return $base;
        }

        return $base.' Ojo: '.($clashes === 1
            ? 'ya hay 1 cita reservada dentro de ese periodo.'
            : "ya hay {$clashes} citas reservadas dentro de ese periodo.")
            .' No se han cancelado; revísalas y avisa a los clientes.';
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
