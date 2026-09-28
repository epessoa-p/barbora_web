<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Segunda capa: el módulo incluido en el plan.
 *
 * En esta fase ningún módulo real depende del plan (todos los permisos son
 * administrativos), así que el test registra su propia ruta. Sin esto el
 * middleware se pudriría sin que nadie lo notase hasta que llegue la agenda.
 */
class CheckPlanModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'plan:agenda'])
            ->get('/_test/agenda', fn () => response('agenda ok'));

        Route::middleware(['web', 'auth', 'plan:pos,caja'])
            ->get('/_test/cobro', fn () => response('cobro ok'));
    }

    public function test_con_el_modulo_en_el_plan_pasa(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'caja']]);
        $user = $this->userInCompany($company);

        $this->actingInCompany($user, $company)->get('/_test/agenda')->assertOk()->assertSee('agenda ok');
    }

    public function test_sin_el_modulo_no_pasa(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($company);

        $this->actingInCompany($user, $company)->get('/_test/agenda')->assertForbidden();
    }

    public function test_varios_modulos_se_evaluan_con_or(): void
    {
        // El plan solo trae 'caja', pero la ruta acepta 'pos' O 'caja'.
        $company = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($company);

        $this->actingInCompany($user, $company)->get('/_test/cobro')->assertOk();
    }

    public function test_el_override_de_features_reemplaza_al_plan(): void
    {
        $company = $this->companyWithPlan(
            planAttributes: ['features' => ['agenda', 'pos', 'caja']],
            subscriptionAttributes: ['features_override' => ['caja']],
        );
        $user = $this->userInCompany($company);

        $this->actingInCompany($user, $company)->get('/_test/agenda')->assertForbidden();
        $this->actingInCompany($user, $company)->get('/_test/cobro')->assertOk();
    }

    public function test_el_superadmin_salta_el_plan(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => []]);
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingInCompany($superAdmin, $company)->get('/_test/agenda')->assertOk();
    }

    public function test_cajas_requiere_el_modulo_caja_en_el_plan(): void
    {
        // Ruta real, no de prueba: con el permiso pero sin el módulo, no entra.
        $sinCaja = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinCaja, ['cajas.view'], 'con-cajas');

        $this->actingInCompany($user, $sinCaja)->get(route('cajas.index'))->assertForbidden();
    }

    public function test_cajas_pasa_cuando_el_plan_trae_el_modulo(): void
    {
        $conCaja = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($conCaja, ['cajas.view'], 'con-cajas');

        $this->actingInCompany($user, $conCaja)->get(route('cajas.index'))->assertOk();
    }

    public function test_el_permiso_no_basta_sin_el_modulo_y_viceversa(): void
    {
        // Con el módulo pero sin el permiso: tampoco entra. Son capas distintas.
        $company = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($company, ['branches.view'], 'sin-cajas');

        $this->actingInCompany($user, $company)->get(route('cajas.index'))->assertForbidden();
    }
}
