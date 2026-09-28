<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Cargo;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Service;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La agenda. Reservar exige tres cosas a la vez: que el barbero atienda citas,
 * que esté en su turno y que el hueco esté libre.
 */
class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    /** Martes de la semana que viene: siempre futuro y día laborable sembrado. */
    protected function nextTuesday(string $time = '10:00'): Carbon
    {
        return Carbon::today()->next(Carbon::TUESDAY)->setTimeFromTimeString($time);
    }

    protected function barber(Company $company, bool $bookable = true): Personal
    {
        $cargo = Cargo::create([
            'company_id' => $company->id,
            'name' => 'Barbero '.fake()->unique()->numerify('###'),
            'active' => true,
        ]);

        $barber = Personal::create([
            'company_id' => $company->id,
            'cargo_id' => $cargo->id,
            'full_name' => fake()->name(),
            'bookable' => $bookable,
            'active' => true,
        ]);

        // Martes de 09:00 a 13:00.
        WorkSchedule::create([
            'company_id' => $company->id,
            'personal_id' => $barber->id,
            'weekday' => 2,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'active' => true,
        ]);

        return $barber->load('schedules');
    }

    protected function service(Company $company, int $minutes = 30, float $price = 50): Service
    {
        return Service::factory()->create([
            'company_id' => $company->id,
            'duration_minutes' => $minutes,
            'price' => $price,
        ]);
    }

    protected function payload(Personal $barber, Client $client, array $services, string $time = '10:00', array $extra = []): array
    {
        return array_merge([
            'personal_id' => $barber->id,
            'client_id' => $client->id,
            'date' => $this->nextTuesday()->toDateString(),
            'time' => $time,
            'services' => collect($services)->pluck('id')->all(),
        ], $extra);
    }

    // ── Reservar ────────────────────────────────────────────────────────────

    public function test_se_reserva_una_cita(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 45, 80);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service]))
            ->assertRedirect();

        $appointment = Appointment::allCompanies()->with('services')->firstOrFail();

        $this->assertSame('reservada', $appointment->status);
        $this->assertSame(45, $appointment->durationMinutes());
        $this->assertSame('10:45', $appointment->ends_at->format('H:i'));
        $this->assertSame(80.0, $appointment->total());
    }

    public function test_la_duracion_es_la_suma_de_los_servicios(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $corte = $this->service($company, 30, 50);
        $barba = $this->service($company, 20, 35);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$corte, $barba]))
            ->assertRedirect();

        $appointment = Appointment::allCompanies()->with('services')->firstOrFail();

        $this->assertSame(50, $appointment->durationMinutes());
        $this->assertSame(85.0, $appointment->total());
    }

    public function test_el_precio_queda_congelado_al_reservar(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30, 50);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service]))
            ->assertRedirect();

        $service->update(['price' => 90]);

        $appointment = Appointment::allCompanies()->with('services')->firstOrFail();

        $this->assertSame(50.0, $appointment->total());
    }

    public function test_hace_falta_al_menos_un_servicio(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, []))
            ->assertSessionHasErrors('services');
    }

    // ── Reglas de la agenda ─────────────────────────────────────────────────

    public function test_no_se_reserva_fuera_del_turno(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);          // martes 09:00–13:00
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '16:00'))
            ->assertSessionHasErrors('time');

        $this->assertSame(0, Appointment::allCompanies()->count());
    }

    public function test_no_se_reserva_si_la_cita_se_sale_del_turno(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $largo = $this->service($company, 90);      // 12:30 + 90 min = 14:00 > 13:00
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$largo], '12:30'))
            ->assertSessionHasErrors('time');
    }

    public function test_no_se_reserva_un_dia_que_no_trabaja(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        // Miércoles: no tiene turno.
        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:00', [
                'date' => $this->nextTuesday()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('time');
    }

    public function test_no_se_doblan_dos_citas_del_mismo_barbero(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 60);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:00'))
            ->assertRedirect();

        // 10:30 pisa la anterior, que va de 10:00 a 11:00.
        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:30'))
            ->assertSessionHasErrors('time');

        $this->assertSame(1, Appointment::allCompanies()->count());
    }

    public function test_dos_citas_seguidas_no_se_consideran_solape(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 60);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        // 10:00–11:00 y 11:00–12:00: el borde no cuenta como choque.
        foreach (['10:00', '11:00'] as $time) {
            $this->actingInCompany($user, $company)
                ->post(route('appointments.store'), $this->payload($barber, $client, [$service], $time))
                ->assertRedirect();
        }

        $this->assertSame(2, Appointment::allCompanies()->count());
    }

    public function test_una_cita_cancelada_libera_el_hueco(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 60);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create', 'appointments.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:00'))
            ->assertRedirect();

        $first = Appointment::allCompanies()->firstOrFail();

        $this->actingInCompany($user, $company)
            ->patch(route('appointments.status', $first), ['status' => 'cancelada'])
            ->assertRedirect();

        // El mismo hueco vuelve a estar disponible.
        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:00'))
            ->assertRedirect();

        $this->assertSame(2, Appointment::allCompanies()->count());
    }

    public function test_no_se_reserva_con_quien_no_atiende_citas(): void
    {
        $company = $this->companyWithPlan();
        $noBookable = $this->barber($company, bookable: false);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($noBookable, $client, [$service]))
            ->assertSessionHasErrors('personal_id');
    }

    public function test_no_se_reserva_en_el_pasado(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service], '10:00', [
                'date' => Carbon::today()->previous(Carbon::TUESDAY)->toDateString(),
            ]))
            ->assertSessionHasErrors('time');
    }

    public function test_no_se_usa_un_cliente_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $barber = $this->barber($mia);
        $clienteAjeno = Client::factory()->create(['company_id' => $ajena->id]);
        $service = $this->service($mia, 30);
        $user = $this->userInCompany($mia, ['appointments.view', 'appointments.create']);

        $this->actingInCompany($user, $mia)
            ->post(route('appointments.store'), $this->payload($barber, $clienteAjeno, [$service]))
            ->assertSessionHasErrors('client_id');
    }

    // ── Estados y visibilidad ───────────────────────────────────────────────

    public function test_se_cambia_el_estado(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create', 'appointments.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service]))
            ->assertRedirect();

        $appointment = Appointment::allCompanies()->firstOrFail();

        $this->actingInCompany($user, $company)
            ->patch(route('appointments.status', $appointment), ['status' => 'confirmada'])
            ->assertRedirect();

        $this->assertSame('confirmada', $appointment->refresh()->status);
    }

    public function test_una_cita_atendida_ya_no_se_reprograma(): void
    {
        $company = $this->companyWithPlan();
        $barber = $this->barber($company);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $service = $this->service($company, 30);
        $user = $this->userInCompany($company, ['appointments.view', 'appointments.create', 'appointments.edit']);

        $this->actingInCompany($user, $company)
            ->post(route('appointments.store'), $this->payload($barber, $client, [$service]))
            ->assertRedirect();

        $appointment = Appointment::allCompanies()->firstOrFail();
        $appointment->update(['status' => 'atendida']);

        $this->actingInCompany($user, $company)
            ->get(route('appointments.edit', $appointment))
            ->assertRedirect(route('appointments.show', $appointment));
    }

    public function test_las_citas_se_aislan_por_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        $barberoAjeno = $this->barber($ajena);
        $clienteAjeno = Client::factory()->create(['company_id' => $ajena->id, 'full_name' => 'Cliente Ajeno']);

        Appointment::create([
            'company_id' => $ajena->id,
            'personal_id' => $barberoAjeno->id,
            'client_id' => $clienteAjeno->id,
            'starts_at' => $this->nextTuesday(),
            'ends_at' => $this->nextTuesday('10:30'),
            'status' => 'reservada',
        ]);

        $user = $this->userInCompany($mia, ['appointments.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('appointments.index', ['day' => $this->nextTuesday()->toDateString()]))
            ->assertOk()
            ->assertDontSee('Cliente Ajeno');
    }

    public function test_la_agenda_requiere_el_modulo_agenda(): void
    {
        $sinAgenda = $this->companyWithPlan(planAttributes: ['features' => ['caja']]);
        $user = $this->userInCompany($sinAgenda, ['appointments.view'], 'con-citas');

        $this->actingInCompany($user, $sinAgenda)->get(route('appointments.index'))->assertForbidden();
        $this->actingInCompany($user, $sinAgenda)->get(route('appointments.calendar'))->assertForbidden();
    }

    public function test_el_calendario_carga_en_dia_y_en_semana(): void
    {
        $company = $this->companyWithPlan();
        $this->barber($company);
        $user = $this->userInCompany($company, ['appointments.view']);

        $this->actingInCompany($user, $company)->get(route('appointments.calendar'))->assertOk();
        $this->actingInCompany($user, $company)
            ->get(route('appointments.calendar', ['view' => 'week']))->assertOk();
    }

    public function test_sin_permiso_de_creacion_no_se_reserva(): void
    {
        $company = $this->companyWithPlan();
        $user = $this->userInCompany($company, ['appointments.view']);

        $this->actingInCompany($user, $company)->get(route('appointments.create'))->assertForbidden();
    }
}
