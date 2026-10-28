<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\ProductForm;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'stocks'])
            ->search($request->query('q'))
            ->orderBy('name');

        if ($categoryId = $request->integer('category')) {
            $query->where('product_category_id', $categoryId);
        }

        if ($request->query('filter') === 'low') {
            $query->tracked();
        }

        $products = $query->paginate(20)->withQueryString();

        // El aviso de stock bajo compara existencias con el umbral de cada
        // producto, así que se filtra después de cargar las relaciones.
        if ($request->query('filter') === 'low') {
            $products->setCollection($products->getCollection()->filter->isLowStock()->values());
        }

        return view('inventory.products.index', [
            'products' => $products,
            'categories' => $this->categories(),
            'limit' => $this->planLimitStatus($this->targetCompanyId(), 'products'),
        ]);
    }

    public function create()
    {
        return view('inventory.products.create', [
            'product' => null,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->targetCompanyId();

        if ($this->planLimitReached($companyId, 'products')) {
            return back()->withInput()->withErrors([
                'error' => 'Alcanzaste el límite de productos de tu plan. '
                         . 'Contacta a tu proveedor para ampliarlo.',
            ]);
        }

        $product = Product::create($this->validated($request, null, $companyId));

        return redirect()->route('products.show', $product)
            ->with('success', "Producto «{$product->name}» creado exitosamente.");
    }

    public function show(Product $product)
    {
        $product->load(['category', 'stocks.warehouse', 'movements.warehouse', 'movements.creator']);

        return view('inventory.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('inventory.products.edit', [
            'product' => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product, $product->company_id));

        return redirect()->route('products.show', $product)
            ->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(Product $product)
    {
        if ($product->totalStock() > 0) {
            return back()->withErrors([
                'error' => "«{$product->name}» todavía tiene existencias. "
                         . 'Da salida al stock antes de eliminarlo.',
            ]);
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', 'Producto eliminado exitosamente.');
    }

    protected function categories()
    {
        return ProductCategory::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Las reglas son las mismas que en la API: ver ProductForm. */
    protected function validated(Request $request, ?Product $product, ?int $companyId): array
    {
        return app(ProductForm::class)->validate($request, $companyId, $product, flagsAreCheckboxes: true);
    }
}
