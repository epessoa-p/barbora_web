<?php

namespace Tests\Feature;

use App\Support\UserManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** El manual: mismo contenido en la web y la app, con lo que cada usuario puede usar. */
class UserManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_sistema_lee_sus_pasos_y_solo_lo_que_puede_usar(): void
    {
        $company = $this->companyWithPlan(planAttributes: ['features' => ['caja', 'pos']]);
        $user = $this->userInCompany($company, ['cash.view', 'sales.create']);

        $this->actingInCompany($user, $company)->get(route('manual'))
            ->assertOk()
            ->assertSee('Manual de usuario')
            ->assertSee('Cobrar (ventas)')
            ->assertSee('Caja › Turnos')
            // Sin el módulo ni el permiso, esa parte no aparece.
            ->assertDontSee('Comisiones y liquidaciones');

        Sanctum::actingAs($user);
        $sections = collect($this->getJson('/api/v1/manual', ['X-Company-Id' => (string) $company->id])
            ->assertOk()->json('data'))->keyBy('key');

        $this->assertTrue($sections->has('caja'));
        $this->assertFalse($sections->has('gastos'));
        $this->assertStringContainsString('Turnos anteriores', json_encode($sections['caja'], JSON_UNESCAPED_UNICODE));
    }

    public function test_ningun_paso_queda_vacio_en_ningun_sistema(): void
    {
        $company = $this->companyWithPlan();
        $super = \App\Models\User::factory()->create(['is_super_admin' => true]);

        foreach (['web', 'app'] as $surface) {
            foreach (UserManual::for($surface, $super, $company) as $section) {
                $this->assertNotEmpty($section['where'], "{$surface}: {$section['key']} sin «dónde».");

                foreach ($section['topics'] as $topic) {
                    $this->assertNotEmpty($topic['steps'], "{$surface}: {$topic['title']} sin pasos.");
                }
            }
        }
    }
}
