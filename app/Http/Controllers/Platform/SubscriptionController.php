<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * SuscripciÃ³n de una empresa: plan, estado, fechas y overrides.
 * Ver ARQUITECTURA Â§7 y Â§8.
 */
class SubscriptionController extends Controller
{
    public function edit(Company $company)
    {
        $subscription = $company->subscription;

        return view('admin.subscriptions.edit', [
            'company' => $company,
            'subscription' => $subscription,
            'plans' => Plan::orderBy('sort_order')->orderBy('name')->get(),
            // Uso actual frente al tope efectivo, para que el operador vea el
            // efecto real de cada override antes de guardarlo.
            'usage' => [
                'users' => [
                    'usage' => $company->usageFor('users'),
                    'limit' => $company->effectiveLimit('users'),
                ],
                'branches' => [
                    'usage' => $company->usageFor('branches'),
                    'limit' => $company->effectiveLimit('branches'),
                ],
            ],
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => ['required', Rule::in(array_keys(Subscription::STATUSES))],
            'trial_ends_at' => 'nullable|date',
            'current_period_end' => 'nullable|date',
            'grace_days' => 'required|integer|min:0|max:90',
            'notes' => 'nullable|string',
            // VacÃ­o = heredar el lÃ­mite del plan.
            'max_users_override' => 'nullable|integer|min:0',
            'max_branches_override' => 'nullable|integer|min:0',
            'max_products_override' => 'nullable|integer|min:0',
            'features_override' => 'nullable|array',
            'features_override.*' => [Rule::in(array_keys(Plan::MODULES))],
        ]);

        // Sin marcar "personalizar mÃ³dulos", el override queda en NULL y la
        // empresa hereda los del plan. Un array vacÃ­o significarÃ­a "ninguno".
        $data['features_override'] = $request->boolean('override_features')
            ? array_values($request->input('features_override', []))
            : null;

        $subscription = $company->subscription;

        if ($subscription) {
            $subscription->update($data);
        } else {
            $company->subscription()->create($data + ['created_by' => auth()->id()]);
        }

        return redirect()->route('companies.show', $company)
            ->with('success', 'SuscripciÃ³n actualizada exitosamente.');
    }
}
