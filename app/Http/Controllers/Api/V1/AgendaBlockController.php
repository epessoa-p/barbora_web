<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AgendaBlock;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Personal;
use App\Support\AgendaBlockForm;
use App\Support\ShopTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bloqueos de agenda desde el móvil: vacaciones, feriados, descansos.
 *
 * Mismas reglas que la web (AgendaBlockForm). Como allí, crear un bloqueo no
 * cancela las citas que caen dentro: se avisa en `message` y `clashes`, y la
 * barbería decide qué hace con esos clientes.
 */
class AgendaBlockController extends ApiController
{
    public function __construct(protected AgendaBlockForm $form)
    {
    }

    /**
     * Próximos (por defecto) o pasados (`scope=pasados`), opcionalmente de un
     * barbero. Lleva también lo que necesita el formulario: motivos, barberos
     * y sucursales.
     */
    public function index(Request $request): JsonResponse
    {
        $past = $request->query('scope') === 'pasados';

        $blocks = AgendaBlock::with(['personal', 'branch'])
            ->when($past,
                fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'),
                fn ($q) => $q->upcoming()->orderBy('starts_at'))
            ->when($request->integer('personal_id'), fn ($q, $id) => $q->where('personal_id', $id))
            ->limit(100)
            ->get();

        $company = $this->company($request);

        return $this->json([
            'scope' => $past ? 'pasados' : 'proximos',
            'data' => $blocks->map(fn (AgendaBlock $b) => $this->payload($b, $company))->all(),
            'reasons' => collect(AgendaBlock::REASONS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()->all(),
            'staff' => Personal::bookable()->orderBy('full_name')->get()
                ->map(fn (Personal $p) => ['id' => $p->id, 'full_name' => $p->full_name])->all(),
            'branches' => Branch::orderBy('name')->get()
                ->map(fn (Branch $b) => ['id' => $b->id, 'name' => $b->name])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->company($request);
        $data = $this->form->validate($request, $company?->id);

        $block = AgendaBlock::create($data + ['created_by' => $request->user()->id]);

        return $this->respond($block, 'creado', $company, 201);
    }

    public function update(Request $request, AgendaBlock $agendaBlock): JsonResponse
    {
        $agendaBlock->update($this->form->validate($request, $agendaBlock->company_id));

        return $this->respond($agendaBlock->refresh(), 'actualizado', $this->company($request));
    }

    public function destroy(AgendaBlock $agendaBlock): JsonResponse
    {
        $agendaBlock->delete();

        return $this->json(['message' => 'Bloqueo eliminado. Ese periodo vuelve a estar disponible.']);
    }

    /* ---------------------------------------------------------------------
     | Internos
     |--------------------------------------------------------------------- */

    protected function respond(AgendaBlock $block, string $verb, ?Company $company, int $status = 200): JsonResponse
    {
        $block->load(['personal', 'branch']);

        return $this->json([
            'message' => $this->form->message($block, $verb),
            'clashes' => $this->form->clashes($block),
            'data' => $this->payload($block, $company),
        ], $status);
    }

    protected function payload(AgendaBlock $block, ?Company $company): array
    {
        return [
            'id' => $block->id,
            'title' => $block->title,
            'reason' => $block->reason,
            'reason_label' => $block->reasonLabel(),
            'personal_id' => $block->personal_id,
            'branch_id' => $block->branch_id,
            'branch' => $block->branch?->name,
            'scope' => $block->scopeLabel(),
            'all_day' => (bool) $block->all_day,
            'starts_at' => ShopTime::iso($block->starts_at, $company),
            'ends_at' => ShopTime::iso($block->ends_at, $company),
            'range_label' => $block->rangeLabel(),
            'notes' => $block->notes,
        ];
    }

    protected function company(Request $request): ?Company
    {
        return $request->attributes->get('tenant_company');
    }
}
