<?php

namespace App\Http\Controllers\Appointments;

use App\Http\Controllers\Controller;
use App\Models\AgendaBlock;
use App\Models\Branch;
use App\Models\Personal;
use App\Support\AgendaBlockForm;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

    /** Las reglas son las mismas que en la API: ver AgendaBlockForm. */
    protected function validated(Request $request, ?AgendaBlock $block = null): array
    {
        return app(AgendaBlockForm::class)->validate($request, $block?->company_id ?? $this->targetCompanyId());
    }

    protected function successMessage(AgendaBlock $block, string $verb): string
    {
        return app(AgendaBlockForm::class)->message($block, $verb);
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
