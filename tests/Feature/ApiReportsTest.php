<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los reportes desde el móvil: reusan el cálculo de la web y llegan en un
 * formato uniforme (resumen + tablas) que la app pinta con una sola pantalla.
 */
class ApiReportsTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $gerente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['estadisticas']]);
        $this->gerente = $this->userInCompany($this->company, ['reports.view'], 'gerente_api');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    public function test_el_reporte_de_ventas_llega_con_resumen_y_tablas(): void
    {
        Sanctum::actingAs($this->gerente);

        $this->getJson('/api/v1/reports/ventas?preset=mes', $this->headers())
            ->assertOk()
            ->assertJsonPath('key', 'ventas')
            ->assertJsonPath('title', 'Ventas')
            ->assertJsonPath('period.preset', 'mes')
            ->assertJsonStructure([
                'period' => ['label', 'from', 'to', 'presets'],
                'summary' => [['label', 'value', 'kind']],
                'charts' => [['type', 'title', 'labels', 'data']],
                'tables' => [['title', 'columns', 'rows']],
            ]);
    }

    public function test_todos_los_reportes_responden(): void
    {
        Sanctum::actingAs($this->gerente);

        foreach (['ventas', 'servicios', 'barberos', 'productos', 'clientes', 'ganancias'] as $type) {
            $this->getJson("/api/v1/reports/{$type}?preset=mes", $this->headers())
                ->assertOk()
                ->assertJsonPath('key', $type);
        }
    }

    public function test_el_reporte_de_productos_no_falla_con_stock_bajo(): void
    {
        // Reproduce el 500: con un producto bajo mínimo, la tabla «Stock bajo»
        // llega como arrays, no como modelos Stock.
        $this->inCompany($this->company, function () {
            $warehouse = Warehouse::create([
                'company_id' => $this->company->id,
                'name' => 'Almacén',
                'code' => 'ALM-1',
            ]);
            $product = Product::create([
                'company_id' => $this->company->id,
                'name' => 'Pomada Clasica',
                'unit' => 'unidad',
                'cost_price' => 10,
                'sale_price' => 20,
                'min_stock' => 5,
                'is_sellable' => true,
                'track_stock' => true,
                'active' => true,
            ]);
            Stock::create([
                'company_id' => $this->company->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => 2, // por debajo del mínimo
            ]);
        });

        Sanctum::actingAs($this->gerente);

        $this->getJson('/api/v1/reports/productos?preset=mes', $this->headers())
            ->assertOk()
            ->assertJsonPath('key', 'productos')
            ->assertSee('Pomada Clasica');
    }

    public function test_un_reporte_desconocido_da_404(): void
    {
        Sanctum::actingAs($this->gerente);

        $this->getJson('/api/v1/reports/inventado', $this->headers())->assertNotFound();
    }

    public function test_sin_el_modulo_estadisticas_no_hay_reportes(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($otra, ['reports.view'], 'sin_stats');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/ventas', ['X-Company-Id' => (string) $otra->id])
            ->assertStatus(403)
            ->assertJsonPath('error', 'plan_required');
    }

    public function test_sin_permiso_no_hay_reportes(): void
    {
        $user = $this->userInCompany($this->company, [], 'sin_permiso');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/ventas', $this->headers())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }
}
