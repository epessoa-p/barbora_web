<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\HandlesCompanyLogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Support\CompanySetup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    use HandlesCompanyLogo;

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

        // «Dejar lista para usar»: sucursal, cargos, responsable con su
        // usuario, caja y horario, en el mismo alta. Ver App\Support\CompanySetup.
        $setup = $request->boolean('setup') ? app(CompanySetup::class) : null;
        $setupData = $setup ? $request->validate($setup->rules(), $setup->messages()) : [];
        $credentials = null;

        $company = DB::transaction(function () use ($request, $planId, $setup, $setupData, &$credentials) {
            // El archivo no se mass-assignea: el logo se guarda aparte, ya con id.
            $company = Company::create(Arr::except($request->validated(), ['logo', 'remove_logo']));
            $this->syncCompanyLogo($request, $company);

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

            if ($setup) {
                $credentials = $setup->run($company, $setupData, $request);
            }

            return $company;
        });

        // Las credenciales se enseñan una sola vez, en la ficha de la empresa
        // (flash: al recargar ya no están; la contraseña no se guarda en claro).
        return redirect()->route('companies.show', $company)
            ->with('success', $credentials
                ? 'Empresa creada y lista para usar.'
                : 'Empresa creada exitosamente')
            ->with('credentials', $credentials);
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
        $company->update(Arr::except($request->validated(), ['logo', 'remove_logo']));
        $this->syncCompanyLogo($request, $company);

        return redirect()->route('companies.show', $company)->with('success', 'Empresa actualizada exitosamente');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada exitosamente');
    }
}
