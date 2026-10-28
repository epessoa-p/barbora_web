<?php

namespace App\Support;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de un bloqueo de agenda, compartidas por la web y la API.
 *
 * Las fechas llegan como «hora de pared» de la barbería (día + hora por
 * separado), igual que se guardan: ver ShopTime.
 */
class AgendaBlockForm
{
    /**
     * Valida la petición y devuelve los atributos listos para guardar.
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request, ?int $companyId): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'reason' => ['required', Rule::in(array_keys(AgendaBlock::REASONS))],
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'all_day' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'required_if:all_day,0,false', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:all_day,0,false', 'date_format:H:i'],
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

    /** Citas vivas que caen dentro del bloqueo. */
    public function clashes(AgendaBlock $block): int
    {
        return Appointment::blocking()
            ->where('starts_at', '<', $block->ends_at)
            ->where('ends_at', '>', $block->starts_at)
            ->when($block->personal_id, fn ($q) => $q->where('personal_id', $block->personal_id))
            ->when($block->branch_id, fn ($q) => $q->where('branch_id', $block->branch_id))
            ->count();
    }

    /**
     * Avisa si el bloqueo pisa citas ya reservadas. No las toca: cancelar o
     * reprogramar a un cliente es una decisión de la barbería.
     */
    public function message(AgendaBlock $block, string $verb): string
    {
        $clashes = $this->clashes($block);
        $base = "Bloqueo {$verb}.";

        if ($clashes === 0) {
            return $base;
        }

        return $base.' Ojo: '.($clashes === 1
            ? 'ya hay 1 cita reservada dentro de ese periodo.'
            : "ya hay {$clashes} citas reservadas dentro de ese periodo.")
            .' No se han cancelado; revísalas y avisa a los clientes.';
    }
}
