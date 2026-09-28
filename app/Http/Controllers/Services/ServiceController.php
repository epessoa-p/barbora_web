<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    protected function validated(Request $request, ?Service $service = null): array
    {
        $companyId = $service?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('services', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($service?->id),
            ],
            // La categoría tiene que ser de la misma empresa.
            'service_category_id' => [
                'nullable',
                Rule::exists('service_categories', 'id')->where('company_id', $companyId),
            ],
            'description' => 'nullable|string|max:255',
            'duration_minutes' => 'required|integer|min:5|max:600',
            'price' => 'required|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ], [
            'name.unique' => 'Ya existe un servicio con ese nombre.',
            'duration_minutes.min' => 'La duración mínima es de 5 minutos.',
            'duration_minutes.max' => 'La duración máxima es de 10 horas.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
