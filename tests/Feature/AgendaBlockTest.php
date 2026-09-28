<?php

namespace Tests\Feature;

use App\Models\AgendaBlock;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bloqueos de agenda: vacaciones, feriados y descansos.
 *
 * La regla que sostiene todo el módulo: un bloqueo manda sobre el horario. El
 * barbero puede tener turno ese día de la semana, pero si está de vacaciones
 * esa fecha concreta, no se reserva.
 */
class AgendaBlockTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Personal $barber;
    protected Client $client;
    protected Service $service;
    protected User $recepcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->companyWithPlan(planAttributes: ['features' => ['agenda', 'reservas_online']]);
        Branch::factory()->create(['company_id' => $this->company->id]);

        $this->barber = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id,
            'full_name' => 'Tania Rojas',
            'active' => true,
            'bookable' => true,
        ]));

        // Turno amplio todos los días, para que lo único que falle sea el bloqueo.
        $this->inCompany($this->company, function () {
            foreach (range(1, 7) as $weekday) {
                WorkSchedule::create([
                    'company_id' => $this->company->id,
                    'personal_id' => $this->barber->id,
                    'weekday' => $weekday,
                    'start_time' => '08:00',
                    'end_time' => '20:00',
                    'active' => true,
                ]);
            }
        });

        $this->client = $this->inCompany($this->company, fn () => Client::factory()->create([
            'company_id' => $this->company->id,
            'phone' => '59171234567',
        ]));

        $this->service = $this->inCompany($this->company, fn () => Service::factory()->create([
            'company_id' => $this->company->id,
            'duration_minutes' => 60,
            'price' => 80,
        ]));

        $this->recepcion = $this->userInCompany($this->company, [
            'appointments.view', 'appointments.create', 'appointments.edit',
            'agenda_blocks.view', 'agenda_blocks.create', 'agenda_blocks.edit', 'agenda_blocks.delete',
        ], 'recepcion');
    }

    /* ---------------------------------------------------------------------
     | La regla central
     |--------------------------------------------------------------------- */

    public function test_no_se_reserva_sobre_las_vacaciones_de_un_barbero(): void
    {
        $day = $this->nextMonday();

        $this->blockFor($this->barber, $day, $day->copy()->addDays(6), 'Vacaciones de Tania', 'vacaciones');

        $this->book($day->copy()->setTime(10, 0))
            ->assertSessionHasErrors('time');

        $this->assertSame(0, $this->inCompany($this->company, fn () => Appointment::count()));
    }

    public function test_un_feriado_cierra_la_agenda_de_todos(): void
    {
        $day = $this->nextMonday();

        // Sin personal_id: afecta a toda la barbería.
        $this->blockFor(null, $day, $day, 'Día del trabajo', 'feriado');

        $this->book($day->copy()->setTime(10, 0))
            ->assertSessionHasErrors('time');
    }

    public function test_fuera_del_bloqueo_si_se_reserva(): void
    {
        $day = $this->nextMonday();

        $this->blockFor($this->barber, $day, $day, 'Vacaciones', 'vacaciones');

        // El día siguiente está libre.
        $this->book($day->copy()->addDay()->setTime(10, 0))
            ->assertRedirect();

        $this->assertSame(1, $this->inCompany($this->company, fn () => Appointment::count()));
    }

    public function test_un_descanso_parcial_solo_tapa_sus_horas(): void
    {
        $day = $this->nextMonday();

        $this->inCompany($this->company, fn () => AgendaBlock::create([
            'company_id' => $this->company->id,
            'personal_id' => $this->barber->id,
            'title' => 'Almuerzo',
            'reason' => 'descanso',
            'starts_at' => $day->copy()->setTime(13, 0),
            'ends_at' => $day->copy()->setTime(14, 0),
            'all_day' => false,
        ]));

        $this->book($day->copy()->setTime(13, 0))->assertSessionHasErrors('time');

        // A las 14:00 el descanso ya terminó: el borde no bloquea.
        $this->book($day->copy()->setTime(14, 0))->assertRedirect();
    }

    public function test_el_bloqueo_de_un_barbero_no_afecta_a_otro(): void
    {
        $day = $this->nextMonday();

        $otro = $this->inCompany($this->company, fn () => Personal::create([
            'company_id' => $this->company->id,
            'full_name' => 'Marco Lima',
            'active' => true,
            'bookable' => true,
        ]));

        $this->inCompany($this->company, fn () => WorkSchedule::create([
            'company_id' => $this->company->id,
            'personal_id' => $otro->id,
            'weekday' => $day->dayOfWeekIso,
            'start_time' => '08:00',
            'end_time' => '20:00',
            'active' => true,
        ]));

        $this->blockFor($this->barber, $day, $day, 'Vacaciones de Tania', 'vacaciones');

        $this->book($day->copy()->setTime(10, 0), $otro)->assertRedirect();
    }

    /* ---------------------------------------------------------------------
     | CRUD y aislamiento
     |--------------------------------------------------------------------- */

    public function test_se_crea_un_bloqueo_de_dia_completo(): void
    {
        $day = $this->nextMonday();

        $this->actingInCompany($this->recepcion, $this->company)
            ->post(route('agenda-blocks.store'), [
                'title' => 'Feriado',
                'reason' => 'feriado',
                'start_date' => $day->toDateString(),
                'end_date' => $day->toDateString(),
                'all_day' => 1,
            ])
            ->assertRedirect(route('agenda-blocks.index'));

        $block = $this->inCompany($this->company, fn () => AgendaBlock::firstOrFail());

        $this->assertTrue($block->all_day);
        $this->assertSame('00:00:00', $block->starts_at->format('H:i:s'));
        $this->assertSame('23:59:59', $block->ends_at->format('H:i:s'));
        $this->assertNull($block->personal_id, 'Sin barbero elegido, afecta a toda la barbería.');
    }

    public function test_el_fin_no_puede_ser_anterior_al_inicio(): void
    {
        $day = $this->nextMonday();

        $this->actingInCompany($this->recepcion, $this->company)
            ->post(route('agenda-blocks.store'), [
                'title' => 'Al revés',
                'reason' => 'otro',
                'start_date' => $day->copy()->addDays(3)->toDateString(),
                'end_date' => $day->toDateString(),
                'all_day' => 1,
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_avisa_de_las_citas_que_quedan_dentro_del_bloqueo(): void
    {
        $day = $this->nextMonday();

        $this->book($day->copy()->setTime(10, 0))->assertRedirect();

        $this->actingInCompany($this->recepcion, $this->company)
            ->post(route('agenda-blocks.store'), [
                'title' => 'Vacaciones',
                'reason' => 'vacaciones',
                'personal_id' => $this->barber->id,
                'start_date' => $day->toDateString(),
                'end_date' => $day->toDateString(),
                'all_day' => 1,
            ])
            ->assertSessionHas('success', fn (string $m) => str_contains($m, '1 cita reservada'));

        // La cita sigue viva: el sistema avisa, no cancela por su cuenta.
        $this->assertSame(1, $this->inCompany($this->company, fn () => Appointment::blocking()->count()));
    }

    public function test_no_se_ve_el_bloqueo_de_otra_barberia(): void
    {
        $otra = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);

        $ajeno = $this->inCompany($otra, fn () => AgendaBlock::create([
            'company_id' => $otra->id,
            'title' => 'Feriado ajeno',
            'reason' => 'feriado',
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDay()->endOfDay(),
            'all_day' => true,
        ]));

        $this->actingInCompany($this->recepcion, $this->company)
            ->get(route('agenda-blocks.index'))
            ->assertOk()
            ->assertDontSee('Feriado ajeno');

        $this->actingInCompany($this->recepcion, $this->company)
            ->get(route('agenda-blocks.edit', $ajeno))
            ->assertNotFound();
    }

    public function test_sin_permiso_no_se_crean_bloqueos(): void
    {
        $barbero = $this->userInCompany($this->company, ['appointments.view', 'agenda_blocks.view'], 'solo_mira');

        $this->actingInCompany($barbero, $this->company)
            ->get(route('agenda-blocks.create'))
            ->assertForbidden();
    }

    public function test_los_bloqueos_requieren_el_modulo_agenda(): void
    {
        $sinAgenda = $this->companyWithPlan(planAttributes: ['features' => ['pos']]);
        $user = $this->userInCompany($sinAgenda, ['agenda_blocks.view'], 'sin_agenda');

        $this->actingInCompany($user, $sinAgenda)
            ->get(route('agenda-blocks.index'))
            ->assertForbidden();
    }

    /* ---------------------------------------------------------------------
     | Vista de mes
     |--------------------------------------------------------------------- */

    public function test_la_vista_de_mes_responde_y_cuenta_las_citas(): void
    {
        $day = $this->nextMonday();
        $this->book($day->copy()->setTime(10, 0))->assertRedirect();

        $response = $this->actingInCompany($this->recepcion, $this->company)
            ->get(route('appointments.calendar', ['view' => 'month', 'day' => $day->toDateString()]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('totals')['appointments']);
        // Seis semanas de rejilla como mucho, y siempre filas completas de 7.
        $this->assertSame(7, $response->viewData('weeks')->first()->count());
    }

    public function test_la_rejilla_del_mes_empieza_en_lunes(): void
    {
        $response = $this->actingInCompany($this->recepcion, $this->company)
            ->get(route('appointments.calendar', ['view' => 'month', 'day' => '2026-09-15']))
            ->assertOk();

        $first = $response->viewData('weeks')->first()->first();

        $this->assertSame(1, $first['date']->dayOfWeekIso, 'La primera celda tiene que ser un lunes.');
        $this->assertSame('2026-08-31', $first['key']);
    }

    /* ---------------------------------------------------------------------
     | Recordatorios
     |--------------------------------------------------------------------- */

    public function test_la_cita_proxima_aparece_como_pendiente_de_avisar(): void
    {
        $tomorrow = Carbon::tomorrow()->setTime(10, 0);
        $this->bookAt($tomorrow)->assertRedirect();

        $response = $this->actingInCompany($this->recepcion, $this->company)
            ->get(route('reminders.index'))
            ->assertOk();

        $this->assertCount(1, $response->viewData('pending'));
        $this->assertCount(0, $response->viewData('done'));
    }

    public function test_marcar_el_recordatorio_deja_rastro(): void
    {
        $tomorrow = Carbon::tomorrow()->setTime(10, 0);
        $this->bookAt($tomorrow)->assertRedirect();

        $appointment = $this->inCompany($this->company, fn () => Appointment::firstOrFail());

        $this->actingInCompany($this->recepcion, $this->company)
            ->post(route('reminders.store', $appointment))
            ->assertRedirect();

        $appointment->refresh();

        $this->assertTrue($appointment->wasReminded());
        $this->assertSame($this->recepcion->id, $appointment->reminded_by);
    }

    public function test_el_recordatorio_se_puede_desmarcar(): void
    {
        $tomorrow = Carbon::tomorrow()->setTime(10, 0);
        $this->bookAt($tomorrow)->assertRedirect();

        $appointment = $this->inCompany($this->company, fn () => Appointment::firstOrFail());

        $this->actingInCompany($this->recepcion, $this->company)->post(route('reminders.store', $appointment));
        $this->actingInCompany($this->recepcion, $this->company)->delete(route('reminders.destroy', $appointment));

        $this->assertFalse($appointment->refresh()->wasReminded());
    }

    public function test_el_enlace_de_whatsapp_lleva_el_mensaje_escrito(): void
    {
        $tomorrow = Carbon::tomorrow()->setTime(10, 0);
        $this->bookAt($tomorrow)->assertRedirect();

        $appointment = $this->inCompany($this->company, fn () => Appointment::with(['client', 'personal', 'services'])->firstOrFail());
        $url = $appointment->whatsappUrl($this->company);

        $this->assertStringStartsWith('https://wa.me/59171234567?text=', $url);
        $this->assertStringContainsString(rawurlencode('Tania Rojas'), $url);
    }

    public function test_sin_telefono_no_hay_enlace_de_whatsapp(): void
    {
        $this->inCompany($this->company, fn () => $this->client->update(['phone' => null]));

        $tomorrow = Carbon::tomorrow()->setTime(10, 0);
        $this->bookAt($tomorrow)->assertRedirect();

        $appointment = $this->inCompany($this->company, fn () => Appointment::with('client')->firstOrFail());

        $this->assertNull($appointment->whatsappUrl($this->company));
    }

    public function test_los_recordatorios_requieren_reservas_online(): void
    {
        $sinReservas = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinReservas, ['appointments.view'], 'sin_reservas');

        $this->actingInCompany($user, $sinReservas)
            ->get(route('reminders.index'))
            ->assertForbidden();
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    /** Un lunes futuro: fecha estable, sin depender del día en que corra el test. */
    protected function nextMonday(): Carbon
    {
        return Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY);
    }

    protected function blockFor(?Personal $person, Carbon $from, Carbon $to, string $title, string $reason): AgendaBlock
    {
        return $this->inCompany($this->company, fn () => AgendaBlock::create([
            'company_id' => $this->company->id,
            'personal_id' => $person?->id,
            'title' => $title,
            'reason' => $reason,
            'starts_at' => $from->copy()->startOfDay(),
            'ends_at' => $to->copy()->endOfDay(),
            'all_day' => true,
        ]));
    }

    protected function book(Carbon $at, ?Personal $person = null)
    {
        return $this->actingInCompany($this->recepcion, $this->company)
            ->post(route('appointments.store'), [
                'personal_id' => ($person ?? $this->barber)->id,
                'client_id' => $this->client->id,
                'date' => $at->toDateString(),
                'time' => $at->format('H:i'),
                'services' => [$this->service->id],
            ]);
    }

    protected function bookAt(Carbon $at)
    {
        return $this->book($at);
    }
}
