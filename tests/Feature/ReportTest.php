<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ReportPeriod;
use App\Support\Reports\ClientsReport;
use App\Support\Reports\EarningsReport;
use App\Support\Reports\ProductsReport;
use App\Support\Reports\SalesReport;
use App\Support\Reports\ServicesReport;
use App\Support\Reports\StaffReport;
use App\Support\StockManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Reportes. Módulo de plan «estadisticas».
 *
 * Lo crítico aquí no es que las cifras se pinten, sino que no mezclen datos:
 * un reporte agrega muchas filas de golpe, así que un fallo del CompanyScope
 * aparecería como un número, sin error visible. Por eso el primer test es el
 * aislamiento entre barberías.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected const FEATURES = ['estadisticas', 'pos', 'caja', 'inventario', 'agenda', 'clientes', 'comisiones'];

    /* ---------------------------------------------------------------------
     | Aislamiento y accesos
     |--------------------------------------------------------------------- */

    public function test_un_reporte_no_suma_las_ventas_de_otra_barberia(): void
    {
        $mine = $this->company();
        $theirs = $this->company();

        $this->sell($mine, $this->service($mine, 100), 100);
        $this->sell($theirs, $this->service($theirs, 900), 900);

        $totals = $this->inCompany($mine, fn () => app(SalesReport::class)->totals($this->period()));

        $this->assertSame(1, $totals['sales']);
        $this->assertSame(100.0, $totals['total'], 'El reporte se llevó dinero de la otra barbería.');
    }

    public function test_sin_el_modulo_estadisticas_no_se_entra(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['pos']]);
        $user = $this->userInCompany($company, ['reports.view'], 'sin_modulo');

        $this->actingInCompany($user, $company)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_sin_permiso_no_se_ven_los_reportes(): void
    {
        $company = $this->company();
        $user = $this->userInCompany($company, ['sales.view'], 'sin_permiso');

        $this->actingInCompany($user, $company)
            ->get(route('reports.sales'))
            ->assertForbidden();
    }

    public function test_las_seis_pantallas_responden(): void
    {
        $company = $this->company();
        $this->sell($company, $this->service($company, 80), 80);
        $user = $this->reader($company);

        foreach (['index', 'sales', 'services', 'staff', 'products', 'clients', 'earnings'] as $route) {
            $this->actingInCompany($user, $company)
                ->get(route("reports.{$route}"))
                ->assertOk();
        }
    }

    /* ---------------------------------------------------------------------
     | Ventas
     |--------------------------------------------------------------------- */

    public function test_una_venta_fuera_del_periodo_no_cuenta(): void
    {
        $company = $this->company();
        $sale = $this->sell($company, $this->service($company, 100), 100);

        // La misma venta, movida al mes pasado.
        $this->inCompany($company, fn () => $sale->forceFill(['sold_at' => now()->subMonths(2)])->save());

        $totals = $this->inCompany($company, fn () => app(SalesReport::class)->totals($this->period()));

        $this->assertSame(0, $totals['sales']);
        $this->assertSame(0.0, $totals['total']);
    }

    public function test_una_venta_anulada_no_cuenta_como_ingreso(): void
    {
        $company = $this->company();
        $service = $this->service($company, 100);
        $this->sell($company, $service, 100);
        $sale = $this->sell($company, $service, 100);

        $user = $this->reader($company, ['sales.view', 'sales.delete']);
        $this->actingInCompany($user, $company)
            ->patch(route('sales.cancel', $sale), ['cancel_reason' => 'Prueba'])
            ->assertRedirect();

        $totals = $this->inCompany($company, fn () => app(SalesReport::class)->totals($this->period()));

        $this->assertSame(1, $totals['sales']);
        $this->assertSame(100.0, $totals['total']);
        $this->assertSame(1, $totals['cancelled']);
    }

    public function test_el_ticket_medio_no_incluye_la_propina(): void
    {
        $company = $this->company();
        $this->sell($company, $this->service($company, 100), 110, tip: 10);

        $totals = $this->inCompany($company, fn () => app(SalesReport::class)->totals($this->period()));

        $this->assertSame(110.0, $totals['total']);
        $this->assertSame(10.0, $totals['tip']);
        $this->assertSame(100.0, $totals['ticket'], 'La propina es del barbero, no parte del ticket.');
    }

    public function test_la_serie_diaria_rellena_los_dias_sin_ventas(): void
    {
        $company = $this->company();
        $this->sell($company, $this->service($company, 50), 50);

        $period = new ReportPeriod(
            now()->toImmutable()->subDays(3)->startOfDay(),
            now()->toImmutable()->endOfDay(),
        );

        $daily = $this->inCompany($company, fn () => app(SalesReport::class)->daily($period));

        $this->assertCount(4, $daily, 'Un gráfico con huecos miente sobre la forma de la curva.');
        $this->assertSame(0.0, $daily->first()['total']);
        $this->assertSame(50.0, $daily->last()['total']);
    }

    public function test_el_desglose_por_metodo_de_pago_cuadra(): void
    {
        $company = $this->company();
        $service = $this->service($company, 100);

        $this->sell($company, $service, 100, payments: [
            ['payment_method' => 'efectivo', 'amount' => 60],
            ['payment_method' => 'tarjeta', 'amount' => 40],
        ]);

        $rows = $this->inCompany($company, fn () => app(SalesReport::class)->byPaymentMethod($this->period()));

        $this->assertCount(2, $rows);
        $this->assertSame(100.0, (float) $rows->sum('total'));
    }

    /* ---------------------------------------------------------------------
     | Servicios, barberos, productos
     |--------------------------------------------------------------------- */

    public function test_los_servicios_se_ordenan_por_ingreso(): void
    {
        $company = $this->company();
        $barato = $this->service($company, 20);
        $caro = $this->service($company, 150);

        $this->sell($company, $barato, 20);
        $this->sell($company, $barato, 20);
        $this->sell($company, $caro, 150);

        $rows = $this->inCompany($company, fn () => app(ServicesReport::class)->ranking($this->period()));

        $this->assertSame($caro->name, $rows->first()['service']);
        $this->assertSame(150.0, $rows->first()['revenue']);
        $this->assertSame(2, $rows->last()['times']);
    }

    public function test_el_reporte_de_barberos_atribuye_ingreso_y_propina(): void
    {
        $company = $this->company();
        $person = $this->barber($company);
        $this->sell($company, $this->service($company, 200), 220, tip: 20, personal: $person);

        $rows = $this->inCompany($company, fn () => app(StaffReport::class)->ranking($this->period()));

        $this->assertCount(1, $rows);
        $this->assertSame($person->id, $rows->first()['personal']->id);
        $this->assertSame(200.0, $rows->first()['revenue']);
        $this->assertSame(20.0, $rows->first()['tips']);
    }

    /**
     * El corazón de este cambio: el margen de una venta ya cerrada no se mueve
     * cuando sube el precio de compra.
     *
     * Antes se calculaba con el costo ACTUAL del producto, así que subir el
     * precio de compra hacía bajar solas las ganancias del mes pasado. Si este
     * test vuelve a fallar, los números históricos han dejado de ser fiables.
     */
    public function test_subir_el_costo_no_cambia_el_margen_de_lo_ya_vendido(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        $antes = $this->inCompany($company, fn () => app(ProductsReport::class)->ranking($this->period()));

        $this->assertSame(40.0, $antes->first()['cost']);
        $this->assertSame(60.0, $antes->first()['margin']);

        // El proveedor sube el precio: de 40 a 70.
        $this->inCompany($company, fn () => $product->update(['cost_price' => 70]));

        $despues = $this->inCompany($company, fn () => app(ProductsReport::class)->ranking($this->period()));

        $this->assertSame(40.0, $despues->first()['cost'], 'El costo de esa venta era 40.');
        $this->assertSame(60.0, $despues->first()['margin'], 'El margen histórico no puede moverse.');
        $this->assertFalse($despues->first()['estimated']);
    }

    public function test_una_venta_antigua_sin_costo_se_estima_y_se_avisa(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        // Se simula una línea anterior a la migración: sin costo guardado.
        $this->inCompany($company, fn () => \App\Models\SaleItem::query()
            ->where('product_id', $product->id)
            ->update(['unit_cost' => null]));

        $data = $this->inCompany($company, fn () => app(ProductsReport::class)->for($this->period()));

        // Se cae al costo actual, pero queda marcado como estimación.
        $this->assertSame(40.0, $data['rows']->first()['cost']);
        $this->assertTrue($data['rows']->first()['estimated']);
        $this->assertTrue($data['estimated'], 'La vista tiene que poder avisarlo.');
    }

    public function test_sin_ventas_antiguas_el_reporte_no_avisa_de_nada(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        $data = $this->inCompany($company, fn () => app(ProductsReport::class)->for($this->period()));

        // El aviso desaparece solo cuando ya no hay nada que estimar.
        $this->assertFalse($data['estimated']);
    }

    public function test_un_servicio_no_arrastra_un_costo_inventado(): void
    {
        $company = $this->company();
        $service = $this->service($company, 80);

        $this->sell($company, $service, 80);

        $item = $this->inCompany($company, fn () => \App\Models\SaleItem::services()->firstOrFail());

        // Null, no cero: un servicio no tiene precio de compra.
        $this->assertNull($item->unit_cost);
        $this->assertFalse($item->hasFrozenCost());
    }

    public function test_las_ganancias_tambien_congelan_el_costo(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        $this->inCompany($company, fn () => $product->update(['cost_price' => 90]));

        $earnings = $this->inCompany($company, fn () => app(EarningsReport::class)->for($this->period()));

        $this->assertSame(40.0, $earnings['product_cost']);
        $this->assertSame(60.0, $earnings['gross']);
        $this->assertFalse($earnings['estimated']);
    }

    public function test_el_margen_de_producto_usa_el_costo(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        $rows = $this->inCompany($company, fn () => app(ProductsReport::class)->ranking($this->period()));

        $this->assertSame(100.0, $rows->first()['revenue']);
        $this->assertSame(40.0, $rows->first()['cost']);
        $this->assertSame(60.0, $rows->first()['margin']);
    }

    public function test_el_stock_bajo_avisa_del_producto_por_debajo_del_minimo(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 50, costPrice: 20, stock: 2, minStock: 5);

        $low = $this->inCompany($company, fn () => app(ProductsReport::class)->lowStock());

        $this->assertCount(1, $low);
        $this->assertSame($product->name, $low->first()['product']);
    }

    /* ---------------------------------------------------------------------
     | Clientes y ganancias
     |--------------------------------------------------------------------- */

    public function test_se_distingue_al_cliente_que_vuelve(): void
    {
        $company = $this->company();
        $service = $this->service($company, 50);
        $habitual = $this->client($company);
        $ocasional = $this->client($company);

        $this->sell($company, $service, 50, client: $habitual);
        $this->sell($company, $service, 50, client: $habitual);
        $this->sell($company, $service, 50, client: $ocasional);

        $data = $this->inCompany($company, fn () => app(ClientsReport::class)->for($this->period()));

        $this->assertSame(2, $data['summary']['active']);
        $this->assertSame(1, $data['summary']['returning'], 'Solo uno vino más de una vez.');
        $this->assertSame(100.0, $data['top']->first()['spent']);
    }

    public function test_las_ganancias_descuentan_costo_y_comisiones(): void
    {
        $company = $this->company();
        $product = $this->product($company, salePrice: 100, costPrice: 40);

        $this->sellProduct($company, $product, 100);

        $earnings = $this->inCompany($company, fn () => app(EarningsReport::class)->for($this->period()));

        $this->assertSame(100.0, $earnings['revenue']);
        $this->assertSame(40.0, $earnings['product_cost']);
        $this->assertSame(60.0, $earnings['gross']);
        // Sin reglas de comisión sembradas, el neto es el bruto.
        $this->assertSame(60.0, $earnings['net']);
    }

    public function test_la_propina_no_suma_al_resultado(): void
    {
        $company = $this->company();
        $this->sell($company, $this->service($company, 100), 130, tip: 30);

        $earnings = $this->inCompany($company, fn () => app(EarningsReport::class)->for($this->period()));

        $this->assertSame(100.0, $earnings['revenue'], 'La propina entra y sale: no es ingreso de la barbería.');
        $this->assertSame(30.0, $earnings['tips']);
    }

    /* ---------------------------------------------------------------------
     | Periodo
     |--------------------------------------------------------------------- */

    public function test_un_rango_al_reves_se_endereza(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'from' => '2026-09-30',
            'to' => '2026-09-01',
        ]));

        $this->assertSame('2026-09-01', $period->from->toDateString());
        $this->assertSame('2026-09-30', $period->to->toDateString());
    }

    public function test_una_fecha_invalida_cae_al_mes_en_curso(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['from' => 'no-es-fecha']));

        $this->assertSame(now()->startOfMonth()->toDateString(), $period->from->toDateString());
        $this->assertSame(now()->endOfMonth()->toDateString(), $period->to->toDateString());
    }

    public function test_el_periodo_anterior_tiene_el_mismo_tamano(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'from' => '2026-09-10',
            'to' => '2026-09-16',
        ]));

        $previous = $period->previous();

        $this->assertSame(7, $period->days());
        $this->assertSame(7, $previous->days());
        $this->assertSame('2026-09-09', $previous->to->toDateString());
    }

    /* ---------------------------------------------------------------------
     | Exportación
     |--------------------------------------------------------------------- */

    public function test_se_descarga_el_csv_con_bom(): void
    {
        $company = $this->company();
        $this->sell($company, $this->service($company, 70), 70);
        $user = $this->reader($company, ['reports.view', 'reports.export']);

        $response = $this->actingInCompany($user, $company)
            ->get(route('reports.export', ['report' => 'ventas']))
            ->assertOk();

        $content = $response->streamedContent();

        // Sin el BOM, Excel se come los acentos de las cabeceras.
        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $content);
        $this->assertStringContainsString('Fecha;Ventas;Total', $content);
        $this->assertStringContainsString('70,00', $content, 'Excel en español espera coma decimal.');
    }

    public function test_sin_permiso_de_exportar_no_se_descarga(): void
    {
        $company = $this->company();
        $user = $this->reader($company, ['reports.view']);

        $this->actingInCompany($user, $company)
            ->get(route('reports.export', ['report' => 'ventas']))
            ->assertForbidden();
    }

    public function test_un_reporte_inexistente_no_se_exporta(): void
    {
        $company = $this->company();
        $user = $this->reader($company, ['reports.view', 'reports.export']);

        $this->actingInCompany($user, $company)
            ->get('/reportes/inventado/exportar')
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    protected function company(): Company
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => self::FEATURES]);

        // La sucursal crea su almacén, que la venta de productos necesita.
        Branch::factory()->create(['company_id' => $company->id]);

        $caja = $this->inCompany($company, fn () => Caja::create([
            'company_id' => $company->id,
            'name' => 'Caja '.fake()->unique()->numerify('###'),
            'balance' => 0,
            'active' => true,
        ]));

        $this->inCompany($company, fn () => CashSession::create([
            'company_id' => $company->id,
            'caja_id' => $caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opening_amount' => 100,
        ]));

        return $company;
    }

    protected function period(): ReportPeriod
    {
        return ReportPeriod::fromRequest(Request::create('/', 'GET'));
    }

    protected function reader(Company $company, array $permissions = ['reports.view']): User
    {
        return $this->userInCompany($company, $permissions, 'lector_'.fake()->unique()->numerify('####'));
    }

    protected function service(Company $company, float $price): Service
    {
        return $this->inCompany($company, fn () => Service::factory()->create([
            'company_id' => $company->id,
            'price' => $price,
            'duration_minutes' => 30,
        ]));
    }

    protected function client(Company $company): Client
    {
        return $this->inCompany($company, fn () => Client::factory()->create(['company_id' => $company->id]));
    }

    protected function barber(Company $company): Personal
    {
        return $this->inCompany($company, fn () => Personal::create([
            'company_id' => $company->id,
            'full_name' => fake()->name(),
            'active' => true,
            'bookable' => true,
        ]));
    }

    protected function product(
        Company $company,
        float $salePrice,
        float $costPrice,
        float $stock = 10,
        float $minStock = 0,
    ): Product {
        return $this->inCompany($company, function () use ($company, $salePrice, $costPrice, $stock, $minStock) {
            $product = Product::create([
                'company_id' => $company->id,
                'name' => 'Producto '.fake()->unique()->numerify('####'),
                'unit' => 'unidad',
                'cost_price' => $costPrice,
                'sale_price' => $salePrice,
                'min_stock' => $minStock,
                'is_sellable' => true,
                'track_stock' => true,
                'active' => true,
            ]);

            $warehouse = Warehouse::allCompanies()->where('company_id', $company->id)->firstOrFail();
            app(StockManager::class)->receive($product, $warehouse, $stock);

            return $product;
        });
    }

    /** Cobra un servicio por la ruta real, para que se creen pagos y caja. */
    protected function sell(
        Company $company,
        Service $service,
        float $total,
        float $tip = 0,
        ?Client $client = null,
        ?Personal $personal = null,
        ?array $payments = null,
    ): Sale {
        $seller = $this->reader($company, ['sales.view', 'sales.create']);

        $payload = [
            'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
            'payments' => $payments ?? [['payment_method' => 'efectivo', 'amount' => $total]],
        ];

        if ($tip > 0) {
            $payload['tip'] = $tip;
        }

        if ($client) {
            $payload['client_id'] = $client->id;
        }

        if ($personal) {
            $payload['personal_id'] = $personal->id;
            $payload['items'][0]['personal_id'] = $personal->id;
        }

        $this->actingInCompany($seller, $company)
            ->post(route('sales.store'), $payload)
            ->assertRedirect();

        return $this->inCompany($company, fn () => Sale::latest('id')->firstOrFail());
    }

    protected function sellProduct(Company $company, Product $product, float $total): Sale
    {
        $seller = $this->reader($company, ['sales.view', 'sales.create']);

        $this->actingInCompany($seller, $company)
            ->post(route('sales.store'), [
                'items' => [['type' => 'producto', 'id' => $product->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'efectivo', 'amount' => $total]],
            ])
            ->assertRedirect();

        return $this->inCompany($company, fn () => Sale::latest('id')->firstOrFail());
    }
}
