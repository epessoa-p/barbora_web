<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de servicios y fichas de cliente: los dos cimientos sobre los que
 * se apoyarán la agenda, el cobro y la fidelización.
 */
class CatalogTest extends TestCase
{
    use RefreshDatabase;

    // ── Servicios ───────────────────────────────────────────────────────────

    public function test_se_crea_un_servicio(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['services.view', 'services.create']);

        $this->actingInCompany($user, $company)
            ->post(route('services.store'), [
                'name' => 'Corte clásico',
                'duration_minutes' => 30,
                'price' => 50,
                'active' => 1,
            ])
            ->assertRedirect(route('services.index'));

        $service = Service::allCompanies()->firstOrFail();

        $this->assertSame('Corte clásico', $service->name);
        $this->assertSame($company->id, $service->company_id);
        $this->assertSame(30, $service->duration_minutes);
    }

    public function test_el_nombre_del_servicio_es_unico_dentro_de_la_empresa(): void
    {
        $company = $this->companyWithPlan();
        Service::factory()->create(['company_id' => $company->id, 'name' => 'Corte clásico']);
        $user = $this->userInCompany($company, ['services.view', 'services.create']);

        $this->actingInCompany($user, $company)
            ->post(route('services.store'), ['name' => 'Corte clásico', 'duration_minutes' => 30, 'price' => 50])
            ->assertSessionHasErrors('name');
    }

    public function test_dos_empresas_pueden_tener_el_mismo_servicio(): void
    {
        $a = $this->companyWithPlan();
        $b = $this->companyWithPlan();

        Service::factory()->create(['company_id' => $a->id, 'name' => 'Corte clásico']);
        $user = $this->userInCompany($b, ['services.view', 'services.create']);

        $this->actingInCompany($user, $b)
            ->post(route('services.store'), ['name' => 'Corte clásico', 'duration_minutes' => 30, 'price' => 50])
            ->assertRedirect(route('services.index'));

        $this->assertSame(2, Service::allCompanies()->where('name', 'Corte clásico')->count());
    }

    public function test_no_se_puede_asignar_una_categoria_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $categoriaAjena = ServiceCategory::factory()->create(['company_id' => $ajena->id]);

        $user = $this->userInCompany($mia, ['services.view', 'services.create']);

        $this->actingInCompany($user, $mia)
            ->post(route('services.store'), [
                'name' => 'Corte', 'duration_minutes' => 30, 'price' => 50,
                'service_category_id' => $categoriaAjena->id,
            ])
            ->assertSessionHasErrors('service_category_id');
    }

    public function test_los_servicios_se_aislan_por_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        Service::factory()->create(['company_id' => $mia->id, 'name' => 'Mi Servicio']);
        Service::factory()->create(['company_id' => $ajena->id, 'name' => 'Servicio Ajeno']);

        $user = $this->userInCompany($mia, ['services.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('services.index'))
            ->assertOk()
            ->assertSee('Mi Servicio')
            ->assertDontSee('Servicio Ajeno');
    }

    public function test_los_servicios_requieren_el_modulo_agenda(): void
    {
        $sinAgenda = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($sinAgenda, ['services.view'], 'con-servicios');

        $this->actingInCompany($user, $sinAgenda)->get(route('services.index'))->assertForbidden();
    }

    public function test_la_duracion_legible_se_formatea(): void
    {
        $company = $this->companyWithPlan();

        $corto = Service::factory()->create(['company_id' => $company->id, 'duration_minutes' => 45]);
        $exacto = Service::factory()->create(['company_id' => $company->id, 'duration_minutes' => 120]);
        $mixto = Service::factory()->create(['company_id' => $company->id, 'duration_minutes' => 75]);

        $this->assertSame('45 min', $corto->durationLabel());
        $this->assertSame('2 h', $exacto->durationLabel());
        $this->assertSame('1 h 15 min', $mixto->durationLabel());
    }

    // ── Categorías ──────────────────────────────────────────────────────────

    public function test_no_se_borra_una_categoria_con_servicios(): void
    {
        $company = $this->companyWithPlan();
        $category = ServiceCategory::factory()->create(['company_id' => $company->id]);
        Service::factory()->create(['company_id' => $company->id, 'service_category_id' => $category->id]);

        $user = $this->userInCompany($company, ['service_categories.view', 'service_categories.delete']);

        $this->actingInCompany($user, $company)
            ->delete(route('service-categories.destroy', $category))
            ->assertSessionHasErrors('error');

        $this->assertNull($category->refresh()->deleted_at);
    }

    public function test_una_categoria_vacia_si_se_borra(): void
    {
        $company = $this->companyWithPlan();
        $category = ServiceCategory::factory()->create(['company_id' => $company->id]);
        $user = $this->userInCompany($company, ['service_categories.view', 'service_categories.delete']);

        $this->actingInCompany($user, $company)
            ->delete(route('service-categories.destroy', $category))
            ->assertRedirect(route('service-categories.index'));

        $this->assertNotNull($category->refresh()->deleted_at);
    }

    // ── Clientes ────────────────────────────────────────────────────────────

    public function test_se_registra_un_cliente(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['clients.view', 'clients.create']);

        $this->actingInCompany($user, $company)
            ->post(route('clients.store'), [
                'full_name' => 'Carlos Mendoza',
                'phone' => '+591 7 111-2233',
                'allergies' => 'Tintes con amoníaco',
                'active' => 1,
            ])
            ->assertRedirect();

        $client = Client::allCompanies()->firstOrFail();

        $this->assertSame('Carlos Mendoza', $client->full_name);
        $this->assertSame($company->id, $client->company_id);
    }

    public function test_el_documento_no_se_repite_dentro_de_la_empresa(): void
    {
        $company = $this->companyWithPlan();
        Client::factory()->create(['company_id' => $company->id, 'document_number' => '1234567']);
        $user = $this->userInCompany($company, ['clients.view', 'clients.create']);

        $this->actingInCompany($user, $company)
            ->post(route('clients.store'), ['full_name' => 'Otro', 'document_number' => '1234567'])
            ->assertSessionHasErrors('document_number');
    }

    public function test_la_busqueda_encuentra_por_nombre_y_por_telefono(): void
    {
        $company = $this->companyWithPlan();
        Client::factory()->create(['company_id' => $company->id, 'full_name' => 'Carlos Mendoza', 'phone' => '+591 7 111-2233']);
        Client::factory()->create(['company_id' => $company->id, 'full_name' => 'Diego Rojas', 'phone' => '+591 7 999-8877']);

        $user = $this->userInCompany($company, ['clients.view']);

        $this->actingInCompany($user, $company)
            ->get(route('clients.index', ['q' => 'Mendoza']))
            ->assertOk()->assertSee('Carlos Mendoza')->assertDontSee('Diego Rojas');

        $this->actingInCompany($user, $company)
            ->get(route('clients.index', ['q' => '999-8877']))
            ->assertOk()->assertSee('Diego Rojas')->assertDontSee('Carlos Mendoza');
    }

    public function test_los_clientes_se_aislan_por_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        Client::factory()->create(['company_id' => $mia->id, 'full_name' => 'Cliente Propio']);
        Client::factory()->create(['company_id' => $ajena->id, 'full_name' => 'Cliente Ajeno']);

        $user = $this->userInCompany($mia, ['clients.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Cliente Propio')
            ->assertDontSee('Cliente Ajeno');
    }

    public function test_los_clientes_requieren_el_modulo_clientes(): void
    {
        $sinClientes = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinClientes, ['clients.view'], 'con-clientes');

        $this->actingInCompany($user, $sinClientes)->get(route('clients.index'))->assertForbidden();
    }

    public function test_sin_permiso_no_se_crea_un_cliente(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['clients.view']);   // ve, no crea

        $this->actingInCompany($user, $company)->get(route('clients.create'))->assertForbidden();
    }
}
