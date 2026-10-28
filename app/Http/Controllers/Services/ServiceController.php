<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServiceForm;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('category')->orderBy('sort_order')->orderBy('name');

        if ($categoryId = $request->integer('category')) {
            $query->where('service_category_id', $categoryId);
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.$request->string('q').'%');
        }

        return view('services.index', [
            'services' => $query->paginate(20)->withQueryString(),
            'categories' => $this->categories(),
        ]);
    }

    public function create()
    {
        return view('services.create', [
            'service' => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));

        return redirect()->route('services.index')
            ->with('success', "Servicio «{$service->name}» creado exitosamente.");
    }

    public function edit(Service $service)
    {
        return view('services.edit', [
            'service' => $service,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return redirect()->route('services.index')
            ->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()->route('services.index')
            ->with('success', 'Servicio eliminado exitosamente.');
    }

    protected function categories()
    {
        return ServiceCategory::where('active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Las reglas son las mismas que en la API: ver ServiceForm. */
    protected function validated(Request $request, ?Service $service = null): array
    {
        return app(ServiceForm::class)->validate(
            $request,
            $service?->company_id ?? $this->targetCompanyId(),
            $service,
            activeIsCheckbox: true,
        );
    }
}
