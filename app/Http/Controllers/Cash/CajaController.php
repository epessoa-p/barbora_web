<?php

namespace App\Http\Controllers\Cash;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Branch;
use App\Models\Personal;
use App\Support\CajaForm;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function index()
    {
        $cajas = Caja::with('branch')->latest()->get();

        return view('admin.cajas.index', compact('cajas'));
    }

    public function create()
    {
        return view('admin.cajas.create', [
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'personals' => Personal::with('branch')->where('active', true)->orderBy('full_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;

        $validated = $request->validate($this->rules($companyId));

        $validated['company_id'] = $companyId;
        $validated['active'] = $request->boolean('active', true);

        Caja::create($validated);

        return redirect()->route('cajas.index')->with('success', 'Caja creada exitosamente.');
    }

    public function edit(Caja $caja)
    {
        $this->authorizeCaja($caja);

        return view('admin.cajas.edit', [
            'caja' => $caja,
            'branches' => Branch::where('active', true)->orderBy('name')->get(),
            'personals' => Personal::with('branch')->where('active', true)->orderBy('full_name')->get(),
        ]);
    }

    public function update(Request $request, Caja $caja)
    {
        $this->authorizeCaja($caja);

        $validated = $request->validate($this->rules($caja->company_id));
        $validated['active'] = $request->boolean('active', true);

        $caja->update($validated);

        return redirect()->route('cajas.index')->with('success', 'Caja actualizada exitosamente.');
    }

    public function destroy(Caja $caja)
    {
        $this->authorizeCaja($caja);

        $caja->delete();

        return redirect()->route('cajas.index')->with('success', 'Caja eliminada exitosamente.');
    }

    /** Las reglas viven en CajaForm: la API del móvil usa las mismas. */
    protected function rules(?int $companyId): array
    {
        return CajaForm::rules($companyId);
    }

    protected function authorizeCaja(Caja $caja): void
    {
        $user = auth()->user();

        if (! $user->is_super_admin && $caja->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
