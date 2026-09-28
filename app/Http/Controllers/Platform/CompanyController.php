<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with('subscription.plan')->paginate(15);

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.create', [
            'plans' => Plan::where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $planId = $request->validate([
            'plan_id' => 'nullable|exists:plans,id',
        ])['plan_id'] ?? null;

        $company = DB::transaction(function () use ($request, $planId) {
            $company = Company::create($request->validated());

            // Una empresa sin suscripciÃ³n queda bloqueada al entrar, asÃ­ que
            // se le asigna su periodo de prueba en el mismo acto de alta.
            if ($planId && $plan = Plan::find($planId)) {
                $company->subscription()->create([
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays($plan->trial_days ?: 14),
                    'grace_days' => 3,
                    'created_by' => auth()->id(),
                ]);
            }

            return $company;
        });

        return redirect()->route('companies.show', $company)
            ->with('success', 'Empresa creada exitosamente');
    }

    public function show(Company $company)
    {
        $users = $company->users()->paginate(10);
        $company->load('subscription.plan');

        return view('admin.companies.show', compact('company', 'users'));
    }

    public function edit(Company $company)
    {
        return view('admin.companies.edit', compact('company'));
    }

    public function update(StoreCompanyRequest $request, Company $company)
    {
        $company->update($request->validated());

        return redirect()->route('companies.show', $company)->with('success', 'Empresa actualizada exitosamente');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada exitosamente');
    }
}
