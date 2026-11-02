<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * El catálogo público: una página sin sesión con los servicios (y, si se
 * quiere, los productos) de la barbería, para compartir por enlace o QR.
 */
class CatalogPublicTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan();

        $this->inCompany($this->company, function () {
            $cortes = ServiceCategory::create(['company_id' => $this->company->id, 'name' => 'CORTES', 'color' => '#111111', 'active' => true]);
            Service::factory()->create(['company_id' => $this->company->id, 'service_category_id' => $cortes->id, 'name' => 'CORTE CLÁSICO', 'price' => 50, 'active' => true]);
            Service::factory()->create(['company_id' => $this->company->id, 'name' => 'SERVICIO RETIRADO', 'active' => false]);
            Product::create(['company_id' => $this->company->id, 'name' => 'CERA MATE', 'sale_price' => 45, 'is_sellable' => true, 'active' => true]);
        });
    }

    public function test_nace_apagado_y_el_enlace_no_muestra_nada(): void
    {
        $token = Catalog::token($this->company);

        $this->get(route('catalog.public', $token))->assertNotFound();
        $this->get(route('catalog.poster', $token))->assertNotFound();
    }

    public function test_publicado_lo_ve_cualquiera_sin_sesion(): void
    {
        $this->company->update(['catalog_enabled' => true]);
        $token = Catalog::token($this->company);

        $this->get(route('catalog.public', $token))
            ->assertOk()
            ->assertSee($this->company->name)
            ->assertSee('CORTE CLÁSICO')
            ->assertSee('CORTES')
            ->assertDontSee('SERVICIO RETIRADO')
            // Los productos, solo si la barbería lo elige.
            ->assertDontSee('CERA MATE')
            ->assertSee('Descargar PDF');

        $this->company->update(['catalog_show_products' => true]);

        $this->get(route('catalog.public', $token))->assertSee('CERA MATE');

        $this->get(route('catalog.poster', $token))
            ->assertOk()
            ->assertSee('qrcode.min.js', false)
            ->assertSee(route('catalog.public', $token));
    }

    public function test_regenerar_invalida_el_enlace_anterior(): void
    {
        $this->company->update(['catalog_enabled' => true]);
        $old = Catalog::token($this->company);

        $user = $this->userInCompany($this->company, ['settings.view', 'settings.edit']);
        $this->actingInCompany($user, $this->company)
            ->post(route('catalog-settings.regenerate'))
            ->assertRedirect(route('catalog-settings.edit'));

        $new = $this->company->refresh()->catalog_token;
        $this->assertNotSame($old, $new);
        $this->get(route('catalog.public', $old))->assertNotFound();
        $this->get(route('catalog.public', $new))->assertOk();
    }

    public function test_la_configuracion_web_publica_y_muestra_el_enlace(): void
    {
        $user = $this->userInCompany($this->company, ['settings.view', 'settings.edit']);

        $this->actingInCompany($user, $this->company)
            ->get(route('catalog-settings.edit'))
            ->assertOk()
            ->assertSee(Catalog::url($this->company->refresh()));

        $this->actingInCompany($user, $this->company)
            ->put(route('catalog-settings.update'), ['catalog_enabled' => 1, 'catalog_show_products' => 1])
            ->assertRedirect();

        $this->company->refresh();
        $this->assertTrue($this->company->catalog_enabled);
        $this->assertTrue($this->company->catalog_show_products);

        // Sin permiso de configuración, no se toca.
        $other = $this->userInCompany($this->company, ['appointments.view'], 'solo_mira');
        $this->actingInCompany($other, $this->company)->get(route('catalog-settings.edit'))->assertForbidden();
    }

    public function test_la_app_configura_el_catalogo_y_las_categorias(): void
    {
        $user = $this->userInCompany($this->company, [
            'settings.view', 'settings.edit',
            'service_categories.view', 'service_categories.create', 'service_categories.edit',
        ], 'admin_app');
        Sanctum::actingAs($user);
        $headers = ['X-Company-Id' => (string) $this->company->id];

        $this->putJson('/api/v1/admin/catalog', ['enabled' => true], $headers)
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.show_products', false)
            ->assertJsonPath('data.services_count', 1);

        $url = $this->getJson('/api/v1/admin/catalog', $headers)->json('data.url');
        $this->get($url)->assertOk();

        $old = $url;
        $new = $this->postJson('/api/v1/admin/catalog/regenerate', [], $headers)->assertOk()->json('data.url');
        $this->assertNotSame($old, $new);

        // Categorías: listar (con inactivas), crear y editar.
        $this->getJson('/api/v1/service-categories/manage', $headers)
            ->assertOk()
            ->assertJsonPath('data.0.name', 'CORTES')
            ->assertJsonPath('data.0.services_count', 1);

        $id = $this->postJson('/api/v1/service-categories', ['name' => 'BARBA', 'color' => '#AA3300'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.active', true)
            ->json('data.id');

        $this->postJson('/api/v1/service-categories', ['name' => 'BARBA', 'color' => '#AA3300'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->putJson("/api/v1/service-categories/{$id}", ['name' => 'BARBAS', 'color' => '#AA3300', 'active' => false], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'BARBAS')
            ->assertJsonPath('data.active', false);
    }
}
