<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index()
    {
        // El CompanyScope ya filtra por la empresa activa. En Modo Global el
        // superadmin las ve todas, y puede acotar con ?company_id=.
        $query = Branch::with('company')->latest();

        if (auth()->user()->is_super_admin && request('company_id')) {
            $query->forCompany((int) request('company_id'));
        }

        return view('admin.branches.index', [
            'branches' => $query->paginate(15),
            'limit' => $this->planLimitStatus($this->targetCompanyId(), 'branches'),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return view('admin.branches.create', [
            'companies' => $user->is_super_admin ? Company::orderBy('name')->get() : collect([$user->getCurrentCompany()])->filter(),
        ]);
    }

    public function store()
    {
        try {
            $user = auth()->user();
            $companyId = $this->targetCompanyId();

            if ($this->planLimitReached($companyId, 'branches')) {
                return back()->withInput()->withErrors([
                    'error' => 'Alcanzaste el lÃ­mite de sucursales de tu plan. '
                             . 'Contacta a tu proveedor para ampliarlo.',
                ]);
            }

            $validated = request()->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => 'required|string|max:255',
                'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')],
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',
                'active' => 'sometimes|boolean',
            ]);

            if ($user->is_super_admin && empty($companyId)) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            Branch::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'manager_name' => $validated['manager_name'] ?? null,
                'active' => request()->boolean('active', true),
            ]);

            return redirect()->route('branches.index')->with('success', 'Sucursal creada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al crear sucursal', ['message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear la sucursal.']);
        }
    }

    public function show(Branch $branch)
    {
        $this->authorizeBranch($branch);
        $branch->load('company', 'cajas');
        return view('admin.branches.show', compact('branch'));
    }

    public function edit(Branch $branch)
    {
        $this->authorizeBranch($branch);
        $user = auth()->user();

        return view('admin.branches.edit', [
            'branch' => $branch,
            'companies' => $user->is_super_admin ? Company::orderBy('name')->get() : collect([$user->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(Branch $branch)
    {
        $this->authorizeBranch($branch);

        try {
            $user = auth()->user();
            $companyId = $user->is_super_admin ? request('company_id', $branch->company_id) : $branch->company_id;

            $validated = request()->validate([
                'company_id' => ['nullable', 'exists:companies,id'],
                'name' => 'required|string|max:255',
                'code' => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($branch->id)],
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:255',
                'manager_name' => 'nullable|string|max:255',
                'active' => 'sometimes|boolean',
            ]);

            if ($user->is_super_admin && empty($companyId)) {
                return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
            }

            $branch->update([
                ...$validated,
                'company_id' => $companyId,
                'active' => request()->boolean('active', false),
            ]);

            return redirect()->route('branches.index')->with('success', 'Sucursal actualizada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar sucursal', ['branch_id' => $branch->id, 'message' => $exception->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la sucursal.']);
        }
    }

    public function destroy(Branch $branch)
    {
        $this->authorizeBranch($branch);

        try {
            $branch->delete();
            return redirect()->route('branches.index')->with('success', 'Sucursal eliminada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar sucursal', ['branch_id' => $branch->id, 'message' => $exception->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible eliminar la sucursal.']);
        }
    }

    protected function authorizeBranch(Branch $branch): void
    {
        if (!auth()->user()->is_super_admin && $branch->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}