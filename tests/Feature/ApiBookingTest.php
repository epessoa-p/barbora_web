<?php

namespace Tests\Feature;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Reservar citas desde el móvil.
 *
 * La promesa que hay que cumplir: una hora que la API ofrece como libre es una
 * hora que la API acepta al reservar. Si las dos cosas se separan, el usuario
 * elige un hueco de la lista y le sale un error.
 */
class ApiBookingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Personal $barber;
    protected Service $corte;
    protected Client $client;
    protected User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'clientes']]);
        Branch::factory()->create(['company_id' => $this->company->id]);

        $this->inCompany($this->company, function () {
            $this->barber = Personal::create([
                'company_id' => $this->company->id,
                'full_name' => 'Tania Molina',
                'active' => true,
                'bookable' => true,
            ]);

            // Turno de 09:00 a 12:00 todos los días.
            foreach (range(1, 7) as $weekday) {
                WorkSchedule::create([
                    'company_id' => $this->company->id,
                    'personal_id' => $this->barber->id,
                    'weekday' => $weekday,
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'active' => true,
                ]);
            }

            $this->corte = Service::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Corte',
                'duration_minutes' => 60,
                'price' => 50,
            ]);

            $this->client = Client::factory()->create(['company_id' => $this->company->id]);
        });

        $this->recepcion = $this->userInCompany($this->company, [
            'appointments.view', 'appointments.create', 'appointments.edit',
            'clients.view', 'clients.create',
        ], 'recepcion_api');
    }

    protected function headers(): array
    {
        return ['X-Company-Id' => (string) $this->company->id];
    }

    protected function day(): Carbon
    {
        return Carbon::today()->addWeek();
    }

    /* ── Huecos ─────────────────────────────────────────────────────────── */

    public function test_los_huecos_respetan_el_turno_y_la_duracion(): void
    {
        Sanctum::actingAs($this->recepcion);

        $slots = $this->getJson('/api/v1/booking/slots?'.http_build_query([
            'personal_id' => $this->barber->id,
            'date' => $this->day()->toDateString(),
            'services' => [$this->corte->id],
        ]), $this->headers())->assertOk()->json('data');

        // Turno 09–12, servicio de 60 min, cada 15: el último inicio es 11:00.
        $this->assertSame('09:00', $slots[0]);
        $this->assertSame('11:00', end($slots));
        $this->assertNotContains('11:15', $slots, 'A las 11:15 el corte acabaría fuera del turno.');
    }

    public function test_un_hueco_ocupado_no_se_ofrece(): void
    {
        $this->inCompany($this->company, fn () => Appointment::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'starts_at' => $this->day()->setTime(10, 0),
            'ends_at' => $this->day()->setTime(11, 0),
            'status' => 'reservada',
        ]));

        Sanctum::actingAs($this->recepcion);

        $slots = $this->getJson('/api/v1/booking/slots?'.http_build_query([
            'personal_id' => $this->barber->id,
            'date' => $this->day()->toDateString(),
            'services' => [$this->corte->id],
        ]), $this->headers())->json('data');

        // De 09:15 a 10:45 un corte de 60 min pisaría la cita de las 10:00.
        $this->assertContains('09:00', $slots);
        $this->assertNotContains('09:15', $slots);
        $this->assertNotContains('10:30', $slots);
        $this->assertContains('11:00', $slots);
    }

    public function test_un_dia_bloqueado_no_tiene_huecos(): void
    {
        $this->inCompany($this->company, fn () => AgendaBlock::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'title' => 'Vacaciones',
            'reason' => 'vacaciones',
            'starts_at' => $this->day()->startOfDay(),
            'ends_at' => $this->day()->endOfDay(),
            'all_day' => true,
        ]));

        Sanctum::actingAs($this->recepcion);

        $this->getJson('/api/v1/booking/slots?'.http_build_query([
            'personal_id' => $this->barber->id,
            'date' => $this->day()->toDateString(),
            'services' => [$this->corte->id],
        ]), $this->headers())->assertOk()->assertJsonPath('data', []);
    }

    /** La promesa del test de clase: lo ofrecido se puede reservar. */
    public function test_cada_hueco_ofrecido_se_puede_reservar(): void
    {
        Sanctum::actingAs($this->recepcion);

        $slots = $this->getJson('/api/v1/booking/slots?'.http_build_query([
            'personal_id' => $this->barber->id,
            'date' => $this->day()->toDateString(),
            'services' => [$this->corte->id],
        ]), $this->headers())->json('data');

        $this->postJson('/api/v1/appointments', [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => $slots[0],
            'services' => [$this->corte->id],
        ], $this->headers())->assertCreated();
    }

    /* ── Reservar ───────────────────────────────────────────────────────── */

    public function test_se_reserva_una_cita(): void
    {
        Sanctum::actingAs($this->recepcion);

        $response = $this->postJson('/api/v1/appointments', [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '09:30',
            'services' => [$this->corte->id],
            'notes' => 'Prefiere máquina 2',
        ], $this->headers())->assertCreated();

        $this->assertSame('reservada', $response->json('data.status'));
        $this->assertStringContainsString('T09:30:00', $response->json('data.starts_at'));
        $this->assertStringContainsString('T10:30:00', $response->json('data.ends_at'));
        $this->assertSame(50.0, $response->json('data.total'));
    }

    public function test_no_se_reserva_encima_de_otra_cita(): void
    {
        Sanctum::actingAs($this->recepcion);

        $payload = [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '10:00',
            'services' => [$this->corte->id],
        ];

        $this->postJson('/api/v1/appointments', $payload, $this->headers())->assertCreated();
        $this->postJson('/api/v1/appointments', $payload, $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('time');
    }

    public function test_no_se_reserva_fuera_del_turno(): void
    {
        Sanctum::actingAs($this->recepcion);

        $this->postJson('/api/v1/appointments', [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '15:00',
            'services' => [$this->corte->id],
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('time');
    }

    public function test_sin_permiso_no_se_reserva(): void
    {
        $barbero = $this->userInCompany($this->company, ['appointments.view'], 'solo_mira_api');
        Sanctum::actingAs($barbero);

        $this->postJson('/api/v1/appointments', [], $this->headers())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_no_se_reserva_con_un_barbero_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan();
        $ajeno = $this->inCompany($otra, fn () => Personal::create([
            'company_id' => $otra->id, 'full_name' => 'Ajeno', 'active' => true, 'bookable' => true,
        ]));

        Sanctum::actingAs($this->recepcion);

        $this->postJson('/api/v1/appointments', [
            'personal_id' => $ajeno->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '09:00',
            'services' => [$this->corte->id],
        ], $this->headers())->assertStatus(422)->assertJsonValidationErrors('personal_id');
    }

    /* ── Reprogramar ────────────────────────────────────────────────────── */

    public function test_se_reprograma_una_cita_a_otra_hora(): void
    {
        $cita = $this->inCompany($this->company, function () {
            $a = Appointment::create([
                'company_id' => $this->company->id,
                'personal_id' => $this->barber->id,
                'client_id' => $this->client->id,
                'starts_at' => $this->day()->setTime(9, 0),
                'ends_at' => $this->day()->setTime(10, 0),
                'status' => 'reservada',
            ]);
            $a->services()->attach($this->corte->id, ['duration_minutes' => 60, 'price' => 50]);

            return $a;
        });

        Sanctum::actingAs($this->recepcion);

        $response = $this->putJson("/api/v1/appointments/{$cita->id}", [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '11:00',
            'services' => [$this->corte->id],
        ], $this->headers())->assertOk();

        $this->assertStringContainsString('T11:00:00', $response->json('data.starts_at'));
        $this->assertDatabaseHas('appointments', [
            'id' => $cita->id,
            'starts_at' => $this->day()->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_al_reprogramar_no_choca_consigo_misma(): void
    {
        $cita = $this->inCompany($this->company, function () {
            $a = Appointment::create([
                'company_id' => $this->company->id,
                'personal_id' => $this->barber->id,
                'client_id' => $this->client->id,
                'starts_at' => $this->day()->setTime(9, 0),
                'ends_at' => $this->day()->setTime(10, 0),
                'status' => 'reservada',
            ]);
            $a->services()->attach($this->corte->id, ['duration_minutes' => 60, 'price' => 50]);

            return $a;
        });

        Sanctum::actingAs($this->recepcion);

        // Reprogramar sin moverla: su propio hueco no debe contar como ocupado.
        $this->putJson("/api/v1/appointments/{$cita->id}", [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '09:00',
            'services' => [$this->corte->id],
        ], $this->headers())->assertOk();
    }

    public function test_una_cita_cerrada_no_se_reprograma(): void
    {
        $cita = $this->inCompany($this->company, fn () => Appointment::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'starts_at' => $this->day()->setTime(9, 0),
            'ends_at' => $this->day()->setTime(10, 0),
            'status' => 'atendida',
        ]));

        Sanctum::actingAs($this->recepcion);

        $this->putJson("/api/v1/appointments/{$cita->id}", [
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'date' => $this->day()->toDateString(),
            'time' => '11:00',
            'services' => [$this->corte->id],
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonPath('error', 'appointment_closed');
    }

    public function test_sin_permiso_de_edicion_no_se_reprograma(): void
    {
        $cita = $this->inCompany($this->company, fn () => Appointment::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'client_id' => $this->client->id,
            'starts_at' => $this->day()->setTime(9, 0),
            'ends_at' => $this->day()->setTime(10, 0),
            'status' => 'reservada',
        ]));

        // Recepción puede crear pero no editar: son permisos distintos.
        $soloCrea = $this->userInCompany($this->company, ['appointments.create'], 'solo_crea_api');
        Sanctum::actingAs($soloCrea);

        $this->putJson("/api/v1/appointments/{$cita->id}", [], $this->headers())
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden');
    }

    /* ── Apoyo ──────────────────────────────────────────────────────────── */

    public function test_la_lista_de_barberos_solo_trae_a_quien_atiende(): void
    {
        $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id, 'full_name' => 'Cajero Sin Agenda',
            'active' => true, 'bookable' => false,
        ]));

        Sanctum::actingAs($this->recepcion);

        $names = collect($this->getJson('/api/v1/staff', $this->headers())->assertOk()->json('data'))
            ->pluck('full_name');

        $this->assertTrue($names->contains('Tania Molina'));
        $this->assertFalse($names->contains('Cajero Sin Agenda'));
    }

    public function test_quien_reserva_ve_los_servicios_sin_permiso_de_catalogo(): void
    {
        Sanctum::actingAs($this->recepcion);

        $this->getJson('/api/v1/services', $this->headers())->assertForbidden();

        $this->getJson('/api/v1/booking/services', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Corte');
    }

    public function test_la_ficha_del_cliente_trae_su_resumen_y_ultimas_citas(): void
    {
        $this->inCompany($this->company, function () {
            $cita = Appointment::create([
                'company_id' => $this->company->id,
                'personal_id' => $this->barber->id,
                'client_id' => $this->client->id,
                'starts_at' => Carbon::today()->subWeek()->setTime(9, 0),
                'ends_at' => Carbon::today()->subWeek()->setTime(10, 0),
                'status' => 'atendida',
            ]);
            $cita->services()->attach($this->corte->id, ['duration_minutes' => 60, 'price' => 50]);

            Sale::create([
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'number' => 'V-0001',
                'status' => 'pagada',
                'subtotal' => 50, 'discount' => 0, 'tip' => 0, 'total' => 50,
                'sold_at' => now()->subDays(3),
            ]);
        });

        Sanctum::actingAs($this->recepcion);

        $response = $this->getJson("/api/v1/clients/{$this->client->id}", $this->headers())
            ->assertOk()
            ->assertJsonPath('data.id', $this->client->id)
            ->assertJsonPath('data.stats.visits', 1)
            ->assertJsonPath('data.stats.total_spent', 50.0);

        $this->assertCount(1, $response->json('data.recent_appointments'));
        $this->assertSame('atendida', $response->json('data.recent_appointments.0.status'));
    }

    public function test_no_se_ve_la_ficha_de_un_cliente_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['clientes']]);
        $ajeno = $this->inCompany($otra, fn () => Client::factory()->create(['company_id' => $otra->id]));

        Sanctum::actingAs($this->recepcion);

        // Con la cabecera de MI barbería, el binding no encuentra al cliente ajeno.
        $this->getJson("/api/v1/clients/{$ajeno->id}", $this->headers())->assertNotFound();
    }

    public function test_se_edita_un_servicio_desde_el_movil(): void
    {
        $editor = $this->userInCompany($this->company, ['services.view', 'services.edit'], 'editor_api');
        Sanctum::actingAs($editor);

        $this->putJson("/api/v1/services/{$this->corte->id}", [
            'name' => 'Corte premium',
            'duration_minutes' => 45,
            'price' => 80,
            'active' => 1,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.price', 80.0)
            ->assertJsonPath('data.name', 'Corte premium');

        $this->assertDatabaseHas('services', [
            'id' => $this->corte->id,
            'name' => 'Corte premium',
            'duration_minutes' => 45,
        ]);
    }

    public function test_sin_permiso_de_edicion_no_se_edita_un_servicio(): void
    {
        // Recepción no tiene services.edit.
        Sanctum::actingAs($this->recepcion);

        $this->putJson("/api/v1/services/{$this->corte->id}", [
            'name' => 'Hackeado', 'duration_minutes' => 30, 'price' => 1,
        ], $this->headers())->assertForbidden();
    }

    public function test_alta_rapida_de_cliente(): void
    {
        Sanctum::actingAs($this->recepcion);

        $this->postJson('/api/v1/clients', ['full_name' => 'Juan Nuevo', 'phone' => '70000000'], $this->headers())
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Juan Nuevo');

        $this->assertDatabaseHas('clients', [
            'company_id' => $this->company->id,
            'full_name' => 'Juan Nuevo',
        ]);
    }
}
