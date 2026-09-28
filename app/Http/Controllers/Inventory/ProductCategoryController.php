<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return view('inventory.categories.index', [
            'categories' => ProductCategory::withCount('products')
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('inventory.categories.create', ['category' => null]);
    }

    public function store(Request $request)
    {
        $category = ProductCategory::create($this->validated($request));

        return redirect()->route('product-categories.index')
            ->with('success', "Categoría «{$category->name}» creada exitosamente.");
    }

    public function edit(ProductCategory $productCategory)
    {
        return view('inventory.categories.edit', ['category' => $productCategory]);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $productCategory->update($this->validated($request, $productCategory));

        return redirect()->route('product-categories.index')
            ->with('success', 'Categoría actualizada exitosamente.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        if ($productCategory->products()->exists()) {
            return back()->withErrors([
                'error' => "La categoría «{$productCategory->name}» tiene productos asignados. "
                         . 'Muévelos a otra antes de eliminarla.',
            ]);
        }

        $productCategory->delete();

        return redirect()->route('product-categories.index')
            ->with('success', 'Categoría eliminada exitosamente.');
    }

    protected function validated(Request $request, ?ProductCategory $category = null): array
    {
        $companyId = $category?->company_id ?? $this->targetCompanyId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($category?->id),
            ],
            'description' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'active' => 'sometimes|boolean',
        ], [
            'name.unique' => 'Ya existe una categoría con ese nombre.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
