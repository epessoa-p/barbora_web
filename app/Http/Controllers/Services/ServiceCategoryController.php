<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        return view('services.categories.index', [
            'categories' => ServiceCategory::withCount('services')
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('services.categories.create', ['category' => null]);
    }

    public function store(Request $request)
    {
        $category = ServiceCategory::create($this->validated($request));

        return redirect()->route('service-categories.index')
            ->with('success', "Categoría «{$category->name}» creada exitosamente.");
    }

    public function edit(ServiceCategory $serviceCategory)
    {
        return view('services.categories.edit', ['category' => $serviceCategory]);
    }

    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $serviceCategory->update($this->validated($request, $serviceCategory));

        return redirect()->route('service-categories.index')
            ->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(ServiceCategory $serviceCategory)
    {
        // Los servicios quedarían sin categoría (nullOnDelete): mejor avisar
        // que dejar el catálogo descolocado sin querer.
        if ($serviceCategory->services()->exists()) {
            return back()->withErrors([
                'error' => "La categoría «{$serviceCategory->name}» tiene servicios asignados. "
                         . 'Muévelos a otra categoría antes de eliminarla.',
            ]);
        }

        $serviceCategory->delete();

        return redirect()->route('service-categories.index')
            ->with('success', 'Categoría eliminada exitosamente.');
    }

    protected function validated(Request $request, ?ServiceCategory $category = null): array
    {
        $companyId = $category?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('service_categories', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($category?->id),
            ],
            'description' => 'nullable|string|max:255',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ], [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
            'color.regex' => 'El color debe ser un valor hexadecimal, por ejemplo #1f2937.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
