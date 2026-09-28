<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.plans.index', [
            'plans' => Plan::withCount('subscriptions')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.plans.create', ['plan' => null]);
    }

    public function store(Request $request)
    {
        $plan = Plan::create($this->validated($request));

        return redirect()->route('plans.index')
            ->with('success', "Plan Â«{$plan->name}Â» creado exitosamente.");
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('plans.index')
            ->with('success', "Plan Â«{$plan->name}Â» actualizado exitosamente.");
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors([
                'error' => 'No puedes eliminar un plan que tiene empresas suscritas. '
                         . 'DesactÃ­valo para que deje de ofrecerse.',
            ]);
        }

        $plan->delete();

        return redirect()->route('plans.index')->with('success', 'Plan eliminado exitosamente.');
    }

    protected function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'billing_period' => ['required', Rule::in(['monthly', 'yearly'])],
            'trial_days' => 'required|integer|min:0|max:365',
            // VacÃ­o = ilimitado.
            'max_users' => 'nullable|integer|min:0',
            'max_branches' => 'nullable|integer|min:0',
            'max_products' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.*' => [Rule::in(array_keys(Plan::MODULES))],
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $data['features'] = array_values($data['features'] ?? []);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
