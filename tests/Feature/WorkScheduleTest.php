<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\Personal;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Horarios de trabajo: la pieza que le falta a la agenda para saber quién
 * está disponible y cuándo.
 */
class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function barber(Company $company, array $attributes = []): Personal
    {
        $cargo = Cargo::create([
            'company_id' => $company->id,
            'name' => 'Barbero '.fake()->unique()->numerify('###'),
            'active' => true,
        ]);

        return Personal::create(array_merge([
            'company_id' => $company->id,
            'cargo_id' => $cargo->id,
            'full_name' => fake()->name(),
            'bookable' => true,
            'active' => true,
        ], $attributes));
    }

    public function test_se_anade_una_franja(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), [
                'weekdays' => [2],
                'start_time' => '09:00',
                'end_time' => '13:00',
            ])
            ->assertRedirect(route('schedules.show', $barber));

        $schedule = WorkSchedule::allCompanies()->firstOrFail();

        $this->assertSame(2, $schedule->weekday);
        $this->assertSame($company->id, $schedule->company_id);
        $this->assertSame('Martes', $schedule->weekdayLabel());
    }

    public function test_una_misma_franja_se_aplica_a_varios_dias(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), [
                'weekdays' => [1, 2, 3, 4, 5],
                'start_time' => '09:00',
                'end_time' => '13:00',
            ])
            ->assertRedirect(route('schedules.show', $barber));

        $this->assertSame(5, WorkSchedule::allCompanies()->count());
    }

    public function test_se_admite_un_turno_partido_el_mismo_dia(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        foreach ([['09:00', '13:00'], ['15:00', '20:00']] as [$start, $end]) {
            $this->actingInCompany($user, $company)
                ->post(route('schedules.store', $barber), [
                    'weekdays' => [2],
                    'start_time' => $start,
                    'end_time' => $end,
                ])
                ->assertRedirect();
        }

        $this->assertSame(2, WorkSchedule::allCompanies()->count());
    }

    public function test_no_se_admite_una_franja_que_se_solapa(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '09:00', 'end_time' => '13:00'])
            ->assertRedirect();

        // 12:00–16:00 pisa la franja anterior: un barbero no está en dos sitios.
        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '12:00', 'end_time' => '16:00'])
            ->assertSessionHasErrors('start_time');

        $this->assertSame(1, WorkSchedule::allCompanies()->count());
    }

    public function test_dos_sucursales_distintas_no_se_consideran_solape(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $a = Branch::factory()->create(['company_id' => $company->id]);
        $b = Branch::factory()->create(['company_id' => $company->id]);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '09:00', 'end_time' => '13:00', 'branch_id' => $a->id])
            ->assertRedirect();

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '09:00', 'end_time' => '13:00', 'branch_id' => $b->id])
            ->assertRedirect();

        $this->assertSame(2, WorkSchedule::allCompanies()->count());
    }

    public function test_la_salida_tiene_que_ser_posterior_a_la_entrada(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '18:00', 'end_time' => '09:00'])
            ->assertSessionHasErrors('end_time');
    }

    public function test_no_se_asigna_una_sucursal_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $barber = $this->barber($mia);
        $sucursalAjena = Branch::factory()->create(['company_id' => $ajena->id]);
        $user = $this->userInCompany($mia, ['schedules.view', 'schedules.edit']);

        $this->actingInCompany($user, $mia)
            ->post(route('schedules.store', $barber), [
                'weekdays' => [2], 'start_time' => '09:00', 'end_time' => '13:00',
                'branch_id' => $sucursalAjena->id,
            ])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_en_horarios_aparece_todo_el_personal_aunque_no_atienda_citas(): void
    {
        $company = $this->companyWithPlan();
        $this->barber($company, ['full_name' => 'Barbero Visible', 'bookable' => true]);
        $this->barber($company, ['full_name' => 'Recepcionista Ana', 'bookable' => false]);
        $this->barber($company, ['full_name' => 'Ex Empleado', 'active' => false]);

        $user = $this->userInCompany($company, ['schedules.view']);

        // La recepcionista también tiene horario; solo se marca que no atiende citas.
        $this->actingInCompany($user, $company)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertSee('Barbero Visible')
            ->assertSee('Recepcionista Ana')
            ->assertSee('No atiende citas')
            ->assertDontSee('Ex Empleado');
    }

    public function test_la_app_ve_agrega_y_quita_franjas_del_horario(): void
    {
        $company = $this->companyWithPlan();
        $ana = $this->barber($company, ['full_name' => 'Recepcionista Ana', 'bookable' => false]);
        $user = $this->userInCompany($company, ['schedules.view', 'schedules.edit']);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        $headers = ['X-Company-Id' => (string) $company->id];

        $this->getJson("/api/v1/personal/{$ana->id}/schedules", $headers)->assertOk()->assertJsonCount(0, 'data');

        // De lunes a viernes, de 9 a 13, de una vez.
        $this->postJson("/api/v1/personal/{$ana->id}/schedules", [
            'weekdays' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '13:00',
        ], $headers)->assertCreated()->assertJsonCount(5, 'data')->assertJsonPath('data.0.start_time', '09:00');

        // Pisar una franja se rechaza, y no se crea ninguna de las pedidas.
        $this->postJson("/api/v1/personal/{$ana->id}/schedules", [
            'weekdays' => [6, 1], 'start_time' => '12:00', 'end_time' => '15:00',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('start_time');
        $this->assertSame(5, $ana->schedules()->count());

        $first = $ana->schedules()->first();
        $this->deleteJson("/api/v1/personal/{$ana->id}/schedules/{$first->id}", [], $headers)
            ->assertOk()->assertJsonCount(4, 'data');
    }

    public function test_no_se_ve_el_horario_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $barberoAjeno = $this->barber($ajena, ['full_name' => 'Barbero Ajeno']);
        $user = $this->userInCompany($mia, ['schedules.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('schedules.show', $barberoAjeno))
            ->assertNotFound();
    }

    public function test_sin_permiso_de_edicion_no_se_toca_el_horario(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $user = $this->userInCompany($company, ['schedules.view']);   // solo consulta

        $this->actingInCompany($user, $company)->get(route('schedules.show', $barber))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('schedules.store', $barber), ['weekdays' => [2], 'start_time' => '09:00', 'end_time' => '13:00'])
            ->assertForbidden();
    }

    public function test_se_calculan_las_horas_de_la_semana(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);

        foreach ([1, 2] as $weekday) {
            WorkSchedule::create([
                'company_id' => $company->id,
                'personal_id' => $barber->id,
                'weekday' => $weekday,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'active' => true,
            ]);
        }

        $minutes = $this->inCompany($company, fn () => $barber->load('schedules')->weeklyMinutes());

        $this->assertSame(480, $minutes);
    }
}
