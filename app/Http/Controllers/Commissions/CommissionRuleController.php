<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\Personal;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionRuleController extends Controller
{
    public function index()
    {
        $rules = CommissionRule::with(['personal', 'service'])->get()
            ->sortBy(fn (CommissionRule $r) => [$r->specificity(), $r->targetLabel()])
            ->values();

        return view('commissions.rules.index', [
            'rules' => $rules,
            // Sin reglas no se devenga nada: conviene decirlo, no dejar que se
            // descubra a fin de mes.
            'hasRules' => $rules->where('active', true)->isNotEmpty(),
        ]);
    }

    public function create()
    {
        return view('commissions.rules.create', ['rule' => null] + $this->formData());
    }

    public function store(Request $request)
    {
        $rule = CommissionRule::create($this->validated($request));

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla de comisión creada exitosamente.');
    }

    public function edit(CommissionRule $commissionRule)
    {
        return view('commissions.rules.edit', ['rule' => $commissionRule] + $this->formData());
    }

    public function update(Request $request, CommissionRule $commissionRule)
    {
        $commissionRule->update($this->validated($request, $commissionRule));

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla actualizada exitosamente.');
    }

    public function destroy(CommissionRule $commissionRule)
    {
        // Las comisiones ya devengadas guardan su tipo y valor, así que borrar
        // la regla no altera lo ganado; solo deja de aplicarse en adelante.
        $commissionRule->delete();

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla eliminada. Las comisiones ya devengadas no cambian.');
    }

    protected function formData(): array
    {
        return [
            'staff' => Personal::bookable()->orderBy('full_name')->get(),
            'services' => Service::where('active', true)->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?CommissionRule $rule = null): array
    {
        $companyId = $rule?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'personal_id' => ['nullable', Rule::exists('personal', 'id')->where('company_id', $companyId)],
            'applies_to' => ['required', Rule::in(array_keys(CommissionRule::APPLIES_TO))],
            'service_id' => [
                'nullable',
                Rule::requiredIf(fn () => $request->input('applies_to') === 'servicio'),
                Rule::exists('services', 'id')->where('company_id', $companyId),
            ],
            'type' => ['required', Rule::in(array_keys(CommissionRule::TYPES))],
            'value' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'active' => 'sometimes|boolean',
        ], [
            'service_id.required' => 'Elige el servicio al que se aplica la regla.',
        ]);

        if ($data['type'] === 'porcentaje' && $data['value'] > 100) {
            return throw \Illuminate\Validation\ValidationException::withMessages([
                'value' => 'Un porcentaje de comisión no puede superar el 100 %.',
            ]);
        }

        // El servicio solo tiene sentido cuando la regla es de ese alcance.
        $data['service_id'] = $data['applies_to'] === 'servicio' ? $data['service_id'] : null;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
