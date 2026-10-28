<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Inventario desde el móvil: mismas reglas que la web, y el stock solo se
 * mueve por StockManager.
 */
class ApiInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Warehouse $warehouse;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['inventario', 'pos']]);

        $this->warehouse = $this->inCompany($this->company, fn () => Warehouse::firstOrCreate(
            ['company_id' => $this->company->id, 'code' => 'CEN'],
            ['name' => 'Central', 'active' => true],
        ));

        $this->admin = $this->userInCompany($this->company, [
            'products.view', 'products.create', 'products.edit', 'stock.view', 'stock.create',
        ], 'admin_inventario');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    protected function product(array $attributes = [], float $stock = 0): Product
    {
        return $this->inCompany($this->company, function () use ($attributes, $stock) {
            $product = Product::create($attributes + [
                'company_id' => $this->company->id,
                'name' => 'CERA MATE',
                'unit' => 'unidad',
                'cost_price' => 20,
                'sale_price' => 45,
                'min_stock' => 3,
                'is_sellable' => true,
                'track_stock' => true,
                'active' => true,
            ]);

            if ($stock > 0) {
                app(StockManager::class)->receive($product, $this->warehouse, $stock);
            }

            return $product;
        });
    }

    public function test_lista_con_existencias_y_filtra_el_stock_bajo(): void
    {
        $this->product(['name' => 'CERA MATE'], stock: 2);
        $this->product(['name' => 'SHAMPOO'], stock: 10);
        // Un insumo que no se vende también es inventario.
        $this->product(['name' => 'NAVAJAS', 'is_sellable' => false], stock: 50);

        Sanctum::actingAs($this->admin);

        $all = $this->getJson('/api/v1/inventory/products', $this->headers())->assertOk();

        $this->assertSame(['CERA MATE', 'NAVAJAS', 'SHAMPOO'], array_column($all->json('data'), 'name'));
        $this->assertSame(1, $all->json('low_count'));
        $this->assertSame('2 unidades', $all->json('data.0.stock_label'));
        $this->assertTrue($all->json('data.0.low_stock'));

        $low = $this->getJson('/api/v1/inventory/products?low=1', $this->headers())->assertOk();
        $this->assertSame(['CERA MATE'], array_column($low->json('data'), 'name'));
    }

    public function test_la_ficha_trae_existencias_por_almacen_y_movimientos(): void
    {
        $product = $this->product(stock: 12);

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/inventory/products/{$product->id}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.stocks.0.warehouse', 'Central')
            ->assertJsonPath('data.stocks.0.quantity', 12.0)
            ->assertJsonPath('data.movements.0.type', 'entrada')
            ->assertJsonPath('data.margin_percent', 55.6);
    }

    public function test_las_opciones_traen_lo_que_piden_los_formularios(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/inventory/options', $this->headers())->assertOk();

        $this->assertContains('Central', array_column($response->json('warehouses'), 'name'));
        $this->assertContains('ml', array_column($response->json('units'), 'value'));
        $this->assertContains('ajuste', array_column($response->json('types'), 'value'));
        $this->assertContains('compra', array_column($response->json('reasons'), 'value'));
    }

    public function test_se_crea_y_se_edita_un_producto(): void
    {
        Sanctum::actingAs($this->admin);

        $id = $this->postJson('/api/v1/inventory/products', [
            'name' => 'ACEITE PARA BARBA',
            'sku' => 'AB-01',
            'unit' => 'ml',
            'cost_price' => 30,
            'sale_price' => 60,
            'min_stock' => 2,
        ], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.track_stock', true)
            ->assertJsonPath('data.is_sellable', true)
            ->json('data.id');

        $this->putJson("/api/v1/inventory/products/{$id}", [
            'name' => 'ACEITE PARA BARBA',
            'sku' => 'AB-01',
            'unit' => 'ml',
            'cost_price' => 30,
            'sale_price' => 65,
            'min_stock' => 2,
            'is_sellable' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.sale_price', 65.0)
            ->assertJsonPath('data.is_sellable', false)
            ->assertJsonPath('data.track_stock', true, 'Lo que no se manda no se toca.');
    }

    public function test_el_nombre_no_se_repite(): void
    {
        $this->product(['name' => 'CERA MATE']);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/inventory/products', [
            'name' => 'CERA MATE', 'unit' => 'unidad', 'cost_price' => 1, 'sale_price' => 2, 'min_stock' => 0,
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_entrada_salida_y_ajuste(): void
    {
        $product = $this->product(stock: 5);

        Sanctum::actingAs($this->admin);

        $move = fn (string $type, float $quantity) => $this->postJson('/api/v1/inventory/movements', [
            'type' => $type,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => $quantity,
            'reason' => $type === 'entrada' ? 'compra' : null,
        ], $this->headers());

        $move('entrada', 10)->assertCreated()
            ->assertJsonPath('data.stock', 15.0)
            ->assertJsonPath('message', 'Entrada registrada: «CERA MATE» queda en 15 unidades en «Central».');

        $move('salida', 4)->assertCreated()->assertJsonPath('data.stock', 11.0);

        // El ajuste dice cuánto HAY, no cuánto entra.
        $move('ajuste', 9)->assertCreated()->assertJsonPath('data.stock', 9.0);
    }

    public function test_no_se_saca_mas_de_lo_que_hay(): void
    {
        $product = $this->product(stock: 2);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/inventory/movements', [
            'type' => 'salida',
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_sin_permiso_de_stock_no_se_mueve(): void
    {
        $product = $this->product(stock: 2);
        $lector = $this->userInCompany($this->company, ['products.view'], 'lector_inventario');

        Sanctum::actingAs($lector);

        $this->getJson('/api/v1/inventory/products', $this->headers())->assertOk();
        $this->postJson('/api/v1/inventory/movements', [
            'type' => 'entrada', 'product_id' => $product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 1,
        ], $this->headers())->assertForbidden();
        $this->postJson('/api/v1/inventory/products', ['name' => 'X'], $this->headers())->assertForbidden();
    }

    public function test_sin_el_modulo_de_inventario_no_se_entra(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($otra, ['products.view'], 'sin_inventario');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/inventory/products', ['X-Company-Id' => (string) $otra->id])
            ->assertForbidden();
    }

    public function test_no_se_ve_un_producto_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['inventario']]);
        $ajeno = $this->inCompany($otra, fn () => Product::create([
            'company_id' => $otra->id, 'name' => 'AJENO', 'unit' => 'unidad',
            'cost_price' => 1, 'sale_price' => 2, 'min_stock' => 0,
            'is_sellable' => true, 'track_stock' => true, 'active' => true,
        ]));

        Sanctum::actingAs($this->admin);

        $this->getJson("/api/v1/inventory/products/{$ajeno->id}", $this->headers())->assertNotFound();
    }
}
