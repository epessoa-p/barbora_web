<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Inventario: productos, existencias y movimientos.
 *
 * La regla que gobierna el módulo: todo el stock se mueve por StockManager,
 * que escribe saldo e historial en la misma transacción. Los movimientos son
 * inmutables; un error se corrige con un ajuste.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function warehouse(Company $company): Warehouse
    {
        // Crear una sucursal levanta su almacén espejo (BranchObserver).
        $branch = Branch::factory()->create(['company_id' => $company->id]);

        return Warehouse::allCompanies()->where('branch_id', $branch->id)->firstOrFail();
    }

    protected function product(Company $company, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Producto '.fake()->unique()->numerify('###'),
            'unit' => 'unidad',
            'cost_price' => 20,
            'sale_price' => 40,
            'min_stock' => 5,
            'is_sellable' => true,
            'track_stock' => true,
            'active' => true,
        ], $attributes));
    }

    protected function stock(): StockManager
    {
        return app(StockManager::class);
    }

    // ── Productos ───────────────────────────────────────────────────────────

    public function test_se_crea_un_producto(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['products.view', 'products.create']);

        $this->actingInCompany($user, $company)
            ->post(route('products.store'), [
                'name' => 'Cera mate', 'unit' => 'unidad',
                'cost_price' => 28, 'sale_price' => 55, 'min_stock' => 5,
                'is_sellable' => 1, 'track_stock' => 1, 'active' => 1,
            ])
            ->assertRedirect();

        $product = Product::allCompanies()->firstOrFail();

        $this->assertSame('Cera mate', $product->name);
        $this->assertSame($company->id, $product->company_id);
        $this->assertSame(49.1, $product->marginPercent());
    }

    public function test_el_limite_de_productos_del_plan_se_aplica(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['max_products' => 1]);
        $this->product($company);
        $user = $this->userInCompany($company, ['products.view', 'products.create']);

        $this->actingInCompany($user, $company)
            ->post(route('products.store'), [
                'name' => 'Segundo', 'unit' => 'unidad',
                'cost_price' => 10, 'sale_price' => 20, 'min_stock' => 0,
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(1, Product::allCompanies()->count());
    }

    public function test_el_override_amplia_el_limite_de_productos(): void
    {
        $company = $this->companyWithPlan(
            planAttributes: ['max_products' => 1],
            subscriptionAttributes: ['max_products_override' => 5],
        );
        $this->product($company);
        $user = $this->userInCompany($company, ['products.view', 'products.create']);

        $this->actingInCompany($user, $company)
            ->post(route('products.store'), [
                'name' => 'Segundo', 'unit' => 'unidad',
                'cost_price' => 10, 'sale_price' => 20, 'min_stock' => 0,
            ])
            ->assertRedirect();

        $this->assertSame(2, Product::allCompanies()->count());
    }

    public function test_no_se_borra_un_producto_con_existencias(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);
        $this->stock()->receive($product, $warehouse, 10);

        $user = $this->userInCompany($company, ['products.view', 'products.delete']);

        $this->actingInCompany($user, $company)
            ->delete(route('products.destroy', $product))
            ->assertSessionHasErrors('error');

        $this->assertNull($product->refresh()->deleted_at);
    }

    // ── Movimientos ─────────────────────────────────────────────────────────

    public function test_una_entrada_suma_al_almacen(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);

        $movement = $this->stock()->receive($product, $warehouse, 24, ['reason' => 'compra']);

        $this->assertSame('entrada', $movement->type);
        $this->assertSame(24.0, (float) $movement->quantity);
        $this->assertSame(24.0, (float) $movement->quantity_after);
        $this->assertSame(24.0, $this->inCompany($company, fn () => $product->load('stocks')->totalStock()));
    }

    public function test_una_salida_resta(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);

        $this->stock()->receive($product, $warehouse, 24);
        $movement = $this->stock()->issue($product, $warehouse, 4, ['reason' => 'consumo']);

        $this->assertSame(-4.0, (float) $movement->quantity);
        $this->assertSame(20.0, (float) $movement->quantity_after);
    }

    public function test_el_stock_nunca_queda_en_negativo(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company, ['name' => 'Cera mate']);
        $warehouse = $this->warehouse($company);
        $this->stock()->receive($product, $warehouse, 3);

        $this->expectException(ValidationException::class);

        $this->stock()->issue($product, $warehouse, 10);
    }

    public function test_el_saldo_y_el_historial_no_divergen(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);

        $this->stock()->receive($product, $warehouse, 20);
        $this->stock()->issue($product, $warehouse, 5);
        $this->stock()->receive($product, $warehouse, 10);

        $saldo = (float) Stock::allCompanies()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->value('quantity');

        $historial = (float) StockMovement::allCompanies()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->sum('quantity');

        $this->assertSame(25.0, $saldo);
        $this->assertSame($saldo, $historial);
    }

    public function test_el_ajuste_registra_la_diferencia_no_la_cantidad(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);
        $this->stock()->receive($product, $warehouse, 20);

        // Recuento físico: hay 17, así que faltan 3.
        $movement = $this->stock()->adjustTo($product, $warehouse, 17);

        $this->assertSame('ajuste', $movement->type);
        $this->assertSame(-3.0, (float) $movement->quantity);
        $this->assertSame(17.0, (float) $movement->quantity_after);
        $this->assertSame('inventario', $movement->reason);
    }

    public function test_un_ajuste_sin_diferencia_se_rechaza(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);
        $this->stock()->receive($product, $warehouse, 20);

        $this->expectException(ValidationException::class);

        $this->stock()->adjustTo($product, $warehouse, 20);
    }

    public function test_no_se_mueve_stock_de_un_producto_sin_control(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company, ['track_stock' => false]);
        $warehouse = $this->warehouse($company);

        $this->expectException(ValidationException::class);

        $this->stock()->receive($product, $warehouse, 10);
    }

    public function test_no_se_mezclan_almacenes_de_empresas_distintas(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $product = $this->product($mia);
        $warehouseAjeno = $this->warehouse($ajena);

        $this->expectException(ValidationException::class);

        $this->stock()->receive($product, $warehouseAjeno, 10);
    }

    public function test_el_stock_es_por_almacen(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $a = $this->warehouse($company);
        $b = $this->warehouse($company);

        $this->stock()->receive($product, $a, 10);
        $this->stock()->receive($product, $b, 4);

        $this->inCompany($company, function () use ($product, $a, $b) {
            $product->load('stocks');

            $this->assertSame(10.0, $product->stockIn($a));
            $this->assertSame(4.0, $product->stockIn($b));
            $this->assertSame(14.0, $product->totalStock());
        });
    }

    // ── Aviso de stock bajo ─────────────────────────────────────────────────

    public function test_avisa_cuando_el_stock_baja_del_minimo(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company, ['min_stock' => 5]);
        $warehouse = $this->warehouse($company);

        $this->stock()->receive($product, $warehouse, 10);
        $this->assertFalse($this->inCompany($company, fn () => $product->load('stocks')->isLowStock()));

        $this->stock()->issue($product, $warehouse, 6);
        $this->assertTrue($this->inCompany($company, fn () => $product->load('stocks')->isLowStock()));
    }

    public function test_el_filtro_de_stock_bajo_solo_muestra_esos(): void
    {
        $company = $this->companyWithPlan();
        $warehouse = $this->warehouse($company);

        $bajo = $this->product($company, ['name' => 'Producto Bajo', 'min_stock' => 10]);
        $ok = $this->product($company, ['name' => 'Producto Sobrado', 'min_stock' => 2]);

        $this->stock()->receive($bajo, $warehouse, 3);
        $this->stock()->receive($ok, $warehouse, 50);

        $user = $this->userInCompany($company, ['stock.view']);

        $this->actingInCompany($user, $company)
            ->get(route('stock.index', ['filter' => 'low']))
            ->assertOk()
            ->assertSee('Producto Bajo')
            ->assertDontSee('Producto Sobrado');
    }

    // ── Categorías, aislamiento y permisos ──────────────────────────────────

    public function test_no_se_borra_una_categoria_con_productos(): void
    {
        $company = $this->companyWithPlan();
        $category = ProductCategory::create(['company_id' => $company->id, 'name' => 'Ceras', 'active' => true]);
        $this->product($company, ['product_category_id' => $category->id]);

        $user = $this->userInCompany($company, ['product_categories.view', 'product_categories.delete']);

        $this->actingInCompany($user, $company)
            ->delete(route('product-categories.destroy', $category))
            ->assertSessionHasErrors('error');
    }

    public function test_el_inventario_se_aisla_por_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        $this->product($mia, ['name' => 'Producto Propio']);
        $this->product($ajena, ['name' => 'Producto Ajeno']);

        $user = $this->userInCompany($mia, ['products.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Producto Propio')
            ->assertDontSee('Producto Ajeno');
    }

    public function test_el_inventario_requiere_el_modulo_inventario(): void
    {
        $sinInventario = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinInventario, ['products.view', 'stock.view'], 'con-inventario');

        $this->actingInCompany($user, $sinInventario)->get(route('products.index'))->assertForbidden();
        $this->actingInCompany($user, $sinInventario)->get(route('stock.index'))->assertForbidden();
    }

    public function test_sin_permiso_no_se_mueve_stock(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['stock.view']);   // solo consulta

        $this->actingInCompany($user, $company)->get(route('stock.index'))->assertOk();
        $this->actingInCompany($user, $company)->get(route('stock.create'))->assertForbidden();
    }

    public function test_se_registra_un_movimiento_desde_la_web(): void
    {
        $company = $this->companyWithPlan();
        $product = $this->product($company);
        $warehouse = $this->warehouse($company);
        $user = $this->userInCompany($company, ['stock.view', 'stock.create']);

        $this->actingInCompany($user, $company)
            ->post(route('stock.store'), [
                'type' => 'entrada',
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 12,
                'reason' => 'compra',
            ])
            ->assertRedirect();

        $movement = StockMovement::allCompanies()->firstOrFail();

        $this->assertSame(12.0, (float) $movement->quantity);
        $this->assertSame($user->id, $movement->created_by);
    }
}
