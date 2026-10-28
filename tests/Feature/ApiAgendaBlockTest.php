<?php

namespace Tests\Feature;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bloqueos de agenda desde el móvil: mismas reglas que la web.
 */
class ApiAgendaBlockTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Personal $barber;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'clientes']]);

        $this->barber = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id,
            'full_name' => 'TANIA MOLINA',
            'active' => true,
            'bookable' => true,
        ]));

        $this->admin = $this->userInCompany($this->company, [
            'agenda_blocks.view', 'agenda_blocks.create', 'agenda_blocks.edit', 'agenda_blocks.delete',
        ], 'admin_bloqueos');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    protected function day(): Carbon
    {
        return Carbon::today()->addWeek();
    }

    protected function block(array $attributes = []): AgendaBlock
    {
        return $this->inCompany($this->company, fn () => AgendaBlock::create($attributes + [
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'title' => 'Vacaciones',
            'reason' => 'vacaciones',
            'starts_at' => $this->day()->startOfDay(),
            'ends_at' => $this->day()->addDays(2)->endOfDay(),
            'all_day' => true,
        ]));
    }

    public function test_lista_los_proximos_con_lo_que_necesita_el_formulario(): void
    {
        $this->block();
        $this->block([
            'title' => 'Viejo',
            'starts_at' => Carbon::today()->subMonth(),
            'ends_at' => Carbon::today()->subMonth()->endOfDay(),
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/agenda-blocks', $this->headers())->assertOk();

        $this->assertSame(['Vacaciones'], array_column($response->json('data'), 'title'));
        $this->assertSame('TANIA MOLINA', $response->json('data.0.scope'));
        $this->assertContains('feriado', array_column($response->json('reasons'), 'value'));
        $this->assertSame($this->barber->id, $response->json('staff.0.id'));

        $past = $this->getJson('/api/v1/agenda-blocks?scope=pasados', $this->headers())->assertOk();
        $this->assertSame(['Viejo'], array_column($past->json('data'), 'title'));
    }

    public function test_se_crea_un_feriado_de_todo_el_dia(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/agenda-blocks', [
            'title' => 'Año nuevo',
            'reason' => 'feriado',
            'personal_id' => null,
            'start_date' => $this->day()->toDateString(),
            'end_date' => $this->day()->toDateString(),
            'all_day' => true,
        ], $this->headers())->assertCreated();

        $this->assertSame('Toda la barbería', $response->json('data.scope'));
        $this->assertSame(0, $response->json('clashes'));
        $this->assertDatabaseHas('agenda_blocks', [
            'company_id' => $this->company->id,
            'title' => 'Año nuevo',
            'personal_id' => null,
            'starts_at' => $this->day()->startOfDay()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_con_horas_y_avisa_de_las_citas_que_pisa(): void
    {
        $this->inCompany($this->company, function () {
            $client = Client::factory()->create(['company_id' => $this->company->id]);

            Appointment::create([
                'company_id' => $this->company->id,
                'personal_id' => $this->barber->id,
                'client_id' => $client->id,
                'starts_at' => $this->day()->setTime(13, 30),
                'ends_at' => $this->day()->setTime(14, 0),
                'status' => 'reservada',
            ]);
        });

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/agenda-blocks', [
            'title' => 'Almuerzo',
            'reason' => 'descanso',
            'personal_id' => $this->barber->id,
            'start_date' => $this->day()->toDateString(),
            'end_date' => $this->day()->toDateString(),
            'all_day' => false,
            'start_time' => '13:00',
            'end_time' => '14:00',
        ], $this->headers())->assertCreated();

        $this->assertSame(1, $response->json('clashes'));
        $this->assertStringContainsString('ya hay 1 cita', $response->json('message'));
        $this->assertStringContainsString('T13:00:00', $response->json('data.starts_at'));
    }

    public function test_sin_horas_y_sin_todo_el_dia_no_se_crea(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/agenda-blocks', [
            'title' => 'Permiso',
            'reason' => 'permiso',
            'start_date' => $this->day()->toDateString(),
            'end_date' => $this->day()->toDateString(),
            'all_day' => false,
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors(['start_time', 'end_time']);
    }

    public function test_el_fin_no_puede_ser_anterior_al_inicio(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/agenda-blocks', [
            'title' => 'Vacaciones',
            'reason' => 'vacaciones',
            'start_date' => $this->day()->toDateString(),
            'end_date' => $this->day()->subDay()->toDateString(),
            'all_day' => true,
        ], $this->headers())->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
    }

    public function test_se_edita_y_se_elimina(): void
    {
        $block = $this->block();

        Sanctum::actingAs($this->admin);

        $this->putJson("/api/v1/agenda-blocks/{$block->id}", [
            'title' => 'Vacaciones largas',
            'reason' => 'vacaciones',
            'personal_id' => $this->barber->id,
            'start_date' => $this->day()->toDateString(),
            'end_date' => $this->day()->addDays(9)->toDateString(),
            'all_day' => true,
        ], $this->headers())->assertOk()->assertJsonPath('data.title', 'Vacaciones largas');

        $this->assertSame(
            $this->day()->addDays(9)->endOfDay()->format('Y-m-d H:i:s'),
            $block->fresh()->ends_at->format('Y-m-d H:i:s')
        );

        $this->deleteJson("/api/v1/agenda-blocks/{$block->id}", [], $this->headers())->assertOk();
        $this->assertSoftDeleted('agenda_blocks', ['id' => $block->id]);
    }

    public function test_sin_permiso_solo_se_ve(): void
    {
        $block = $this->block();
        $lector = $this->userInCompany($this->company, ['agenda_blocks.view'], 'lector_bloqueos');

        Sanctum::actingAs($lector);

        $this->getJson('/api/v1/agenda-blocks', $this->headers())->assertOk();
        $this->postJson('/api/v1/agenda-blocks', ['title' => 'x'], $this->headers())->assertForbidden();
        $this->deleteJson("/api/v1/agenda-blocks/{$block->id}", [], $this->headers())->assertForbidden();
    }

    public function test_no_se_toca_el_bloqueo_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $ajeno = $this->inCompany($otra, fn () => AgendaBlock::create([
            'company_id' => $otra->id,
            'title' => 'Feriado ajeno',
            'reason' => 'feriado',
            'starts_at' => $this->day()->startOfDay(),
            'ends_at' => $this->day()->endOfDay(),
            'all_day' => true,
        ]));

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/v1/agenda-blocks/{$ajeno->id}", [], $this->headers())->assertNotFound();
        $this->assertNotSoftDeleted('agenda_blocks', ['id' => $ajeno->id]);
    }
}
