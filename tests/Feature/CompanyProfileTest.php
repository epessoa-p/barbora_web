<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Datos de la barbería en el comprobante: logo, contacto y pie.
 */
class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->company = $this->companyWithPlan(
            ['name' => 'Barbería Demo', 'tax_id' => '1023456789'],
            ['features' => ['pos', 'caja', 'agenda']],
        );

        $this->admin = $this->userInCompany($this->company, ['settings.view', 'settings.edit'], 'admin_perfil');
    }

    public function test_se_actualizan_los_datos_del_comprobante(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'address' => 'Av. Arce 1234',
                'phone' => '71234567',
                'email' => 'hola@demo.test',
                'receipt_footer' => 'Síguenos en @barberiademo',
            ])
            ->assertRedirect(route('company-profile.edit'));

        $this->company->refresh();

        $this->assertSame('Av. Arce 1234', $this->company->address);
        $this->assertSame('Síguenos en @barberiademo', $this->company->receiptFooter());
    }

    public function test_se_sube_el_logo(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
            ])
            ->assertRedirect();

        $this->company->refresh();

        $this->assertNotNull($this->company->logo);
        $this->assertStringStartsWith("companies/{$this->company->id}/", $this->company->logo);
        Storage::disk('public')->assertExists($this->company->logo);
    }

    public function test_cambiar_el_logo_borra_el_anterior(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['logo' => UploadedFile::fake()->image('a.png')]);

        $old = $this->company->fresh()->logo;

        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['logo' => UploadedFile::fake()->image('b.png')]);

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($this->company->fresh()->logo);
    }

    public function test_se_puede_quitar_el_logo(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['logo' => UploadedFile::fake()->image('a.png')]);

        $old = $this->company->fresh()->logo;

        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['remove_logo' => 1]);

        $this->assertNull($this->company->fresh()->logo);
        Storage::disk('public')->assertMissing($old);
    }

    /**
     * Si falla el guardado, ni queda un archivo huérfano del logo nuevo ni se
     * pierde el anterior.
     */
    public function test_si_falla_el_guardado_no_quedan_archivos_huerfanos(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['logo' => UploadedFile::fake()->image('a.png')]);

        $old = $this->company->fresh()->logo;

        // La base rechaza cualquier actualización de la empresa.
        Company::updating(fn () => throw new \RuntimeException('fallo de base de datos'));

        $this->withoutExceptionHandling();

        try {
            $this->actingInCompany($this->admin, $this->company)
                ->put(route('company-profile.update'), ['logo' => UploadedFile::fake()->image('b.png')]);

            $this->fail('Tenía que fallar el guardado.');
        } catch (\RuntimeException) {
            // esperado
        }

        // Solo queda el logo anterior: el nuevo se borró y el viejo no se tocó.
        $this->assertSame([$old], Storage::disk('public')->allFiles("companies/{$this->company->id}"));
        $this->assertSame($old, $this->company->fresh()->logo);
    }

    public function test_un_logo_demasiado_grande_se_rechaza(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'logo' => UploadedFile::fake()->image('enorme.png')->size(3000),
            ])
            ->assertSessionHasErrors('logo');
    }

    public function test_un_archivo_que_no_es_imagen_se_rechaza(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'logo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasErrors('logo');
    }

    /**
     * El nombre y el NIT son los datos con los que el operador verifica la
     * identidad en soporte: el cliente no puede cambiarlos.
     */
    public function test_el_nombre_y_el_nit_no_se_cambian_desde_aqui(): void
    {
        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'name' => 'Otro nombre',
                'tax_id' => '9999999',
                'address' => 'Calle 1',
            ])
            ->assertRedirect();

        $this->company->refresh();

        $this->assertSame('Barbería Demo', $this->company->name);
        $this->assertSame('1023456789', $this->company->tax_id);
        $this->assertSame('Calle 1', $this->company->address);
    }

    public function test_sin_permiso_de_edicion_no_se_guarda(): void
    {
        $lector = $this->userInCompany($this->company, ['settings.view'], 'lector_perfil');

        $this->actingInCompany($lector, $this->company)
            ->get(route('company-profile.edit'))->assertOk();

        $this->actingInCompany($lector, $this->company)
            ->put(route('company-profile.update'), ['address' => 'X'])
            ->assertForbidden();
    }

    public function test_solo_se_toca_la_empresa_activa(): void
    {
        $otra = $this->companyWithPlan(['name' => 'Otra', 'address' => 'Original']);

        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), ['address' => 'Cambiada']);

        $this->assertSame('Original', $otra->fresh()->address);
    }

    public function test_sin_pie_se_usa_el_agradecimiento(): void
    {
        $this->assertSame('¡Gracias por tu visita!', $this->company->receiptFooter());
    }

    public function test_el_comprobante_lleva_logo_y_pie(): void
    {
        $this->company->update(['receipt_footer' => 'Síguenos en @barberiademo']);

        $this->actingInCompany($this->admin, $this->company)
            ->put(route('company-profile.update'), [
                'receipt_footer' => 'Síguenos en @barberiademo',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]);

        $sale = $this->sale();

        $seller = $this->userInCompany($this->company, ['sales.view'], 'mira_ventas');

        $this->actingInCompany($seller, $this->company)
            ->get(route('sales.receipt', $sale))
            ->assertOk()
            ->assertSee('Síguenos en @barberiademo')
            ->assertSee('/storage/companies/'.$this->company->id, false)
            ->assertSee('NIT: 1023456789');
    }

    public function test_la_api_manda_los_datos_del_comprobante(): void
    {
        $this->company->update(['address' => 'Av. Arce 1234', 'receipt_footer' => 'Vuelve pronto']);

        $user = $this->userInCompany($this->company, [], 'app_user');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me', ['X-Company-Id' => (string) $this->company->id])
            ->assertOk()
            ->assertJsonPath('company.tax_id', '1023456789')
            ->assertJsonPath('company.tax_id_label', 'NIT')
            ->assertJsonPath('company.address', 'Av. Arce 1234')
            ->assertJsonPath('company.receipt_footer', 'Vuelve pronto')
            ->assertJsonPath('company.logo_url', null);
    }

    /**
     * La URL del logo tiene que salir con el host por el que se llamó, no con
     * APP_URL: un móvil que entra por la IP de la red no llega a «localhost».
     */
    public function test_la_url_del_logo_usa_el_host_de_la_peticion(): void
    {
        $this->company->update(['logo' => "companies/{$this->company->id}/logo.png"]);

        $user = $this->userInCompany($this->company, [], 'app_user2');
        Sanctum::actingAs($user);

        $url = $this->getJson('http://192.168.1.50:8070/api/v1/me', [
            'X-Company-Id' => (string) $this->company->id,
        ])->assertOk()->json('company.logo_url');

        $this->assertStringStartsWith('http://192.168.1.50:8070/storage/', $url);
    }

    protected function sale(): Sale
    {
        Branch::factory()->create(['company_id' => $this->company->id]);

        $this->inCompany($this->company, function () {
            $caja = Caja::create(['company_id' => $this->company->id, 'name' => 'Caja', 'balance' => 0, 'active' => true]);
            CashSession::create([
                'company_id' => $this->company->id, 'caja_id' => $caja->id,
                'status' => 'abierta', 'opened_at' => now(), 'opening_amount' => 0,
            ]);
        });

        $service = $this->inCompany($this->company, fn () => Service::factory()->create([
            'company_id' => $this->company->id, 'price' => 50,
        ]));

        $seller = $this->userInCompany($this->company, ['sales.view', 'sales.create'], 'vendedor_perfil');

        $this->actingInCompany($seller, $this->company)->post(route('sales.store'), [
            'items' => [['type' => 'servicio', 'id' => $service->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'efectivo', 'amount' => 50]],
        ])->assertRedirect();

        return $this->inCompany($this->company, fn () => Sale::latest('id')->firstOrFail());
    }
}
