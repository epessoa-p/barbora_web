<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Support\ServiceCategoryForm;
use Illuminate\Http\Request;

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

    /** Las reglas viven en ServiceCategoryForm: la API del móvil usa las mismas. */
    protected function validated(Request $request, ?ServiceCategory $category = null): array
    {
        return app(ServiceCategoryForm::class)->validate(
            $request,
            $category?->company_id ?? $this->targetCompanyId(),
            $category,
            activeIsCheckbox: true,
        );
    }
}
