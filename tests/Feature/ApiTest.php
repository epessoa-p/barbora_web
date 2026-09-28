<?php

namespace Tests\Feature;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API de la app del personal.
 *
 * El riesgo propio de la API es que la empresa activa ya no viene de la sesión
 * (que controla el servidor) sino de la cabecera X-Company-Id, que manda el
 * cliente. Si no se comprobara, cualquiera con un token válido leería los datos
 * de cualquier barbería cambiando un número. Ése es el primer test.
 */
class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected const FEATURES = ['agenda', 'clientes', 'comisiones', 'pos', 'caja', 'inventario'];

    protected Company $mia;
    protected Company $ajena;
    protected User $barbero;
    protected Personal $personal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mia = $this->shop('Barbería Mía');
        $this->ajena = $this->shop('Barbería Ajena');

        $this->barbero = $this->userInCompany($this->mia, [
            'appointments.view', 'appointments.edit', 'services.view', 'clients.view',
        ], 'barbero_api');

        $this->personal = $this->staffFor($this->mia, $this->barbero, 'Tania Rojas');
    }

    /* ---------------------------------------------------------------------
     | X-Company-Id: la cabecera no se cree sin comprobar
     |--------------------------------------------------------------------- */

    public function test_no_se_entra_a_una_empresa_ajena_por_la_cabecera(): void
    {
        Sanctum::actingAs($this->barbero);

        $this->getJson('/api/v1/agenda', ['X-Company-Id' => $this->ajena->id])
            ->assertForbidden()
            ->assertJsonPath('error', 'company_forbidden');
    }

    public function test_una_cabecera_inventada_tampoco_entra(): void
    {
        Sanctum::actingAs($this->barbero);

        $this->getJson('/api/v1/agenda', ['X-Company-Id' => '99999'])
            ->assertForbidden()
            ->assertJsonPath('error', 'company_forbidden');
    }

    public function test_la_agenda_solo_trae_citas_de_la_empresa_de_la_cabecera(): void
    {
        $day = $this->nextMonday();

        $this->appointmentIn($this->mia, $this->personal, $day);

        // Una cita en la otra barbería, el mismo día.
        $otroPersonal = $this->staffFor($this->ajena, User::factory()->create(), 'Ajeno');
        $this->appointmentIn($this->ajena, $otroPersonal, $day);

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson(
            '/api/v1/agenda?day='.$day->toDateString(),
            ['X-Company-Id' => $this->mia->id],
        )->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Tania Rojas', $response->json('data.0.personal.full_name'));
    }

    public function test_sin_cabecera_y_con_varias_empresas_se_pide_elegir(): void
    {
        // El mismo usuario, dado de alta también en la otra barbería.
        $this->inCompany($this->ajena, fn () => $this->barbero->companies()->attach($this->ajena->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        Sanctum::actingAs($this->barbero);

        $this->getJson('/api/v1/agenda')
            ->assertStatus(409)
            ->assertJsonPath('error', 'company_required');
    }

    public function test_sin_cabecera_y_con_una_sola_empresa_se_asume_esa(): void
    {
        Sanctum::actingAs($this->barbero);

        $this->getJson('/api/v1/agenda')->assertOk();
    }

    public function test_el_binding_de_una_cita_ajena_devuelve_404(): void
    {
        $otroPersonal = $this->staffFor($this->ajena, User::factory()->create(), 'Ajeno');
        $ajena = $this->appointmentIn($this->ajena, $otroPersonal, $this->nextMonday());

        Sanctum::actingAs($this->barbero);

        $this->getJson("/api/v1/appointments/{$ajena->id}", ['X-Company-Id' => $this->mia->id])
            ->assertNotFound();
    }

    /* ---------------------------------------------------------------------
     | Autenticación
     |--------------------------------------------------------------------- */

    public function test_se_inicia_sesion_y_se_recibe_un_token(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Secreto@1234',
            'device_name' => 'iPhone de prueba',
        ])->assertOk();

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame($this->mia->id, $response->json('default_company_id'));
        $this->assertCount(1, $response->json('companies'));
    }

    public function test_una_contrasena_incorrecta_no_entra(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'otra-cosa',
            'device_name' => 'iPhone',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_un_usuario_sin_barberia_no_entra(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Secreto@1234',
            'device_name' => 'iPhone',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_un_usuario_desactivado_no_entra(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234', 'active' => false]);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'Secreto@1234',
            'device_name' => 'iPhone',
        ])->assertStatus(422);
    }

    public function test_sin_token_no_se_entra_a_nada(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/agenda')->assertUnauthorized();
    }

    public function test_volver_a_entrar_desde_el_mismo_dispositivo_revoca_el_token_anterior(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $credentials = ['email' => $user->email, 'password' => 'Secreto@1234', 'device_name' => 'iPhone'];

        $this->postJson('/api/v1/login', $credentials)->assertOk();
        $this->postJson('/api/v1/login', $credentials)->assertOk();

        $this->assertSame(1, $user->tokens()->count(), 'Un dispositivo, un token.');
    }

    public function test_al_cerrar_sesion_el_token_deja_de_valer(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email, 'password' => 'Secreto@1234', 'device_name' => 'iPhone',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // Dentro de un mismo test la aplicación se reutiliza y el guard deja el
        // usuario cacheado, así que sin esto la segunda petición pasaría aunque
        // el token ya no exista. En producción no hace falta: cada petición es
        // un proceso nuevo.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')->assertUnauthorized();
    }

    /* ---------------------------------------------------------------------
     | GET /me
     |--------------------------------------------------------------------- */

    /**
     * Con un token Bearer de verdad, no con Sanctum::actingAs().
     *
     * La diferencia importa: actingAs() cambia el guard por defecto, así que
     * auth()->user() funciona en los middleware de grupo. Con un token real no,
     * porque SetTenant corre antes que auth:sanctum y el guard sigue siendo
     * «web». Sin esta prueba, la API respondía 200 con la empresa en null y
     * cero permisos, y todo lo demás pasaba igual.
     */
    public function test_con_un_token_real_la_empresa_se_resuelve(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email, 'password' => 'Secreto@1234', 'device_name' => 'Pixel',
        ])->json('token');

        $this->app['auth']->forgetGuards();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Company-Id' => (string) $this->mia->id,
        ])->getJson('/api/v1/me')->assertOk();

        $this->assertSame($this->mia->id, $response->json('company.id'));
        $this->assertNotEmpty($response->json('permissions'));
        $this->assertNotEmpty($response->json('plan.modules'));
    }

    public function test_con_un_token_real_no_se_entra_a_una_empresa_ajena(): void
    {
        $user = User::factory()->create(['password' => 'Secreto@1234']);
        $this->inCompany($this->mia, fn () => $user->companies()->attach($this->mia->id, [
            'role_id' => $this->barbero->companies()->first()->pivot->role_id,
            'active' => true,
        ]));

        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email, 'password' => 'Secreto@1234', 'device_name' => 'Pixel',
        ])->json('token');

        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'X-Company-Id' => (string) $this->ajena->id,
        ])->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error', 'company_forbidden');
    }

    public function test_me_describe_la_empresa_activa(): void
    {
        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/me', ['X-Company-Id' => $this->mia->id])->assertOk();

        $this->assertSame($this->mia->id, $response->json('company.id'));
        $this->assertSame('Tania Rojas', $response->json('personal.full_name'));
        $this->assertContains('appointments.view', $response->json('permissions'));
        $this->assertContains('agenda', $response->json('plan.modules'));
        $this->assertSame('active', $response->json('subscription.status'));
    }

    public function test_me_no_filtra_permisos_de_otra_empresa(): void
    {
        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/me', ['X-Company-Id' => $this->mia->id])->assertOk();

        $this->assertNotContains('sales.create', $response->json('permissions'));
    }

    /* ---------------------------------------------------------------------
     | Las cuatro capas, en JSON
     |--------------------------------------------------------------------- */

    public function test_un_modulo_fuera_del_plan_responde_json_no_html(): void
    {
        $sinAgenda = $this->shop('Sin agenda', ['pos']);
        $user = $this->userInCompany($sinAgenda, ['appointments.view'], 'sin_agenda_api');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/agenda', ['X-Company-Id' => $sinAgenda->id])
            ->assertForbidden()
            ->assertJsonPath('error', 'plan_required')
            ->assertJsonPath('required_modules.0', 'agenda');
    }

    public function test_sin_permiso_responde_json_no_html(): void
    {
        $user = $this->userInCompany($this->mia, ['appointments.view'], 'sin_clientes_api');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/clients', ['X-Company-Id' => $this->mia->id])
            ->assertForbidden()
            ->assertJsonPath('error', 'forbidden')
            ->assertJsonPath('required_permissions.0', 'clients.view');
    }

    public function test_una_suscripcion_vencida_responde_402(): void
    {
        $vencida = $this->shop('Vencida', self::FEATURES, [
            'status' => 'active',
            'current_period_end' => now()->subMonth(),
            'grace_days' => 0,
        ]);

        $user = $this->userInCompany($vencida, ['appointments.view'], 'vencida_api');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/agenda', ['X-Company-Id' => $vencida->id])
            ->assertStatus(402)
            ->assertJsonPath('error', 'subscription_blocked');
    }

    public function test_me_sigue_respondiendo_con_la_suscripcion_vencida(): void
    {
        $vencida = $this->shop('Vencida', self::FEATURES, [
            'status' => 'active',
            'current_period_end' => now()->subMonth(),
            'grace_days' => 0,
        ]);

        $user = $this->userInCompany($vencida, ['appointments.view'], 'vencida_me');

        Sanctum::actingAs($user);

        // Si /me también se bloqueara, la app no podría ni explicar el motivo.
        $this->getJson('/api/v1/me', ['X-Company-Id' => $vencida->id])->assertOk();
    }

    /* ---------------------------------------------------------------------
     | Agenda
     |--------------------------------------------------------------------- */

    public function test_se_marca_una_cita_como_atendida(): void
    {
        $appointment = $this->appointmentIn($this->mia, $this->personal, $this->nextMonday());

        Sanctum::actingAs($this->barbero);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'atendida'],
            ['X-Company-Id' => $this->mia->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'atendida');

        $this->assertSame('atendida', $appointment->refresh()->status);
    }

    public function test_una_cita_ya_atendida_no_se_reabre(): void
    {
        $appointment = $this->appointmentIn($this->mia, $this->personal, $this->nextMonday(), 'atendida');

        Sanctum::actingAs($this->barbero);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'reservada'],
            ['X-Company-Id' => $this->mia->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'appointment_closed');
    }

    public function test_un_estado_inventado_se_rechaza(): void
    {
        $appointment = $this->appointmentIn($this->mia, $this->personal, $this->nextMonday());

        Sanctum::actingAs($this->barbero);

        $this->patchJson("/api/v1/appointments/{$appointment->id}/status", ['status' => 'inventado'],
            ['X-Company-Id' => $this->mia->id])
            ->assertStatus(422)->assertJsonValidationErrors('status');
    }

    /**
     * Una cita guardada a las 10:00 tiene que llegar al móvil como las 10:00
     * de la barbería, no como las 10:00 UTC.
     *
     * El sistema guarda «hora de pared» del local; si se serializara tal cual,
     * Carbon le colgaría el offset de config('app.timezone') —hoy UTC— y un
     * teléfono en Bolivia pintaría la cita cuatro horas antes.
     */
    public function test_las_horas_llegan_en_la_zona_de_la_barberia(): void
    {
        $day = $this->nextMonday();
        $this->appointmentIn($this->mia, $this->personal, $day, 'reservada', 10);

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/agenda?day='.$day->toDateString(),
            ['X-Company-Id' => $this->mia->id])->assertOk();

        $startsAt = $response->json('data.0.starts_at');

        $this->assertStringContainsString('T10:00:00', $startsAt, 'La hora no debe desplazarse.');
        $this->assertStringContainsString('-04:00', $startsAt, 'Debe llevar el offset de La Paz.');
    }

    /* ---------------------------------------------------------------------
     | Lo mío: sin check-permission, el filtro lo pone el controlador
     |--------------------------------------------------------------------- */

    public function test_mi_agenda_solo_trae_mis_citas(): void
    {
        $day = $this->nextMonday();

        $companero = $this->staffFor($this->mia, User::factory()->create(), 'Marco Lima');

        $this->appointmentIn($this->mia, $this->personal, $day);
        $this->appointmentIn($this->mia, $companero, $day, 'reservada', 12);

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/my/agenda?day='.$day->toDateString(),
            ['X-Company-Id' => $this->mia->id])->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->personal->id, $response->json('personal_id'));
    }

    public function test_mis_comisiones_no_incluyen_las_de_un_companero(): void
    {
        $companero = $this->staffFor($this->mia, User::factory()->create(), 'Marco Lima');

        $this->commissionFor($this->mia, $this->personal, 30);
        $this->commissionFor($this->mia, $companero, 500);

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/my/commissions', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        // 30.0 y no 30: la API serializa los importes siempre como decimal
        // (ver ApiController), para que el cliente Dart pueda leerlos como
        // double sin reventar cuando la cifra es redonda.
        $this->assertSame(30.0, $response->json('totals.earned'));
        $this->assertCount(1, $response->json('data'));
    }

    public function test_mi_horario_es_solo_el_mio(): void
    {
        $companero = $this->staffFor($this->mia, User::factory()->create(), 'Marco Lima');

        $this->inCompany($this->mia, function () use ($companero) {
            WorkSchedule::create([
                'company_id' => $this->mia->id, 'personal_id' => $this->personal->id,
                'weekday' => 1, 'start_time' => '09:00', 'end_time' => '13:00', 'active' => true,
            ]);
            WorkSchedule::create([
                'company_id' => $this->mia->id, 'personal_id' => $companero->id,
                'weekday' => 2, 'start_time' => '14:00', 'end_time' => '18:00', 'active' => true,
            ]);
        });

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/my/schedule', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('data.0.weekday'));
    }

    public function test_un_usuario_sin_ficha_de_personal_recibe_404_explicado(): void
    {
        $admin = $this->userInCompany($this->mia, ['appointments.view'], 'admin_sin_ficha');

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/my/agenda', ['X-Company-Id' => $this->mia->id])
            ->assertNotFound()
            ->assertJsonPath('error', 'no_personal_profile');
    }

    /* ---------------------------------------------------------------------
     | Catálogo
     |--------------------------------------------------------------------- */

    public function test_los_servicios_son_los_de_mi_barberia(): void
    {
        $this->inCompany($this->mia, fn () => Service::factory()->create([
            'company_id' => $this->mia->id, 'name' => 'Corte Mío', 'active' => true,
        ]));
        $this->inCompany($this->ajena, fn () => Service::factory()->create([
            'company_id' => $this->ajena->id, 'name' => 'Corte Ajeno', 'active' => true,
        ]));

        Sanctum::actingAs($this->barbero);

        $response = $this->getJson('/api/v1/services', ['X-Company-Id' => $this->mia->id])->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Corte Mío'));
        $this->assertFalse($names->contains('Corte Ajeno'));
    }

    public function test_los_productos_traen_su_stock(): void
    {
        $lector = $this->userInCompany($this->mia, ['products.view'], 'lector_productos');

        $product = $this->inCompany($this->mia, function () {
            $product = \App\Models\Product::create([
                'company_id' => $this->mia->id,
                'name' => 'Cera moldeadora',
                'unit' => 'unidad',
                'cost_price' => 20,
                'sale_price' => 45,
                'min_stock' => 5,
                'is_sellable' => true,
                'track_stock' => true,
                'active' => true,
            ]);

            $warehouse = \App\Models\Warehouse::allCompanies()
                ->where('company_id', $this->mia->id)->firstOrFail();

            app(\App\Support\StockManager::class)->receive($product, $warehouse, 3);

            return $product;
        });

        Sanctum::actingAs($lector);

        $response = $this->getJson('/api/v1/products', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $product->id);

        $this->assertSame('Cera moldeadora', $row['name']);
        $this->assertSame(45.0, $row['price']);
        $this->assertSame(3.0, $row['stock']);
        // Quedan 3 y el mínimo es 5: la app tiene que poder avisar.
        $this->assertTrue($row['low_stock']);
    }

    public function test_un_producto_sin_control_de_stock_no_miente_con_un_cero(): void
    {
        $lector = $this->userInCompany($this->mia, ['products.view'], 'lector_productos');

        $this->inCompany($this->mia, fn () => \App\Models\Product::create([
            'company_id' => $this->mia->id,
            'name' => 'Servicio de barbería a domicilio',
            'unit' => 'unidad',
            'cost_price' => 0,
            'sale_price' => 100,
            'min_stock' => 0,
            'is_sellable' => true,
            'track_stock' => false,
            'active' => true,
        ]));

        Sanctum::actingAs($lector);

        $response = $this->getJson('/api/v1/products', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('tracks_stock', false);

        // Null, no cero: «no se controla» no es lo mismo que «se agotó».
        $this->assertNull($row['stock']);
        $this->assertFalse($row['low_stock']);
    }

    public function test_los_productos_de_otra_barberia_no_aparecen(): void
    {
        $lector = $this->userInCompany($this->mia, ['products.view'], 'lector_productos');

        $this->inCompany($this->ajena, fn () => \App\Models\Product::create([
            'company_id' => $this->ajena->id,
            'name' => 'Producto Ajeno',
            'unit' => 'unidad',
            'cost_price' => 1,
            'sale_price' => 2,
            'min_stock' => 0,
            'is_sellable' => true,
            'track_stock' => false,
            'active' => true,
        ]));

        Sanctum::actingAs($lector);

        $response = $this->getJson('/api/v1/products', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        $names = collect($response->json('data'))->pluck('name');

        $this->assertFalse($names->contains('Producto Ajeno'));
    }

    public function test_los_clientes_se_buscan_y_se_paginan(): void
    {
        $lector = $this->userInCompany($this->mia, ['clients.view'], 'lector_clientes');

        $this->inCompany($this->mia, function () {
            Client::factory()->create(['company_id' => $this->mia->id, 'full_name' => 'Juan Pérez']);
            Client::factory()->create(['company_id' => $this->mia->id, 'full_name' => 'Ana Gómez']);
        });

        Sanctum::actingAs($lector);

        $response = $this->getJson('/api/v1/clients?q=Juan', ['X-Company-Id' => $this->mia->id])
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Juan Pérez', $response->json('data.0.full_name'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    /* ---------------------------------------------------------------------
     | Utilidades
     |--------------------------------------------------------------------- */

    protected function shop(string $name, array $features = self::FEATURES, array $subscription = []): Company
    {
        $company = $this->companyWithPlan(
            ['name' => $name],
            ['features' => $features],
            $subscription,
        );

        Branch::factory()->create(['company_id' => $company->id]);

        return $company;
    }

    protected function staffFor(Company $company, User $user, string $name): Personal
    {
        return $this->inCompany($company, fn () => Personal::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'full_name' => $name,
            'active' => true,
            'bookable' => true,
        ]));
    }

    protected function appointmentIn(
        Company $company,
        Personal $personal,
        Carbon $day,
        string $status = 'reservada',
        int $hour = 10,
    ): Appointment {
        return $this->inCompany($company, function () use ($company, $personal, $day, $status, $hour) {
            $client = Client::factory()->create(['company_id' => $company->id]);

            return Appointment::create([
                'company_id' => $company->id,
                'personal_id' => $personal->id,
                'client_id' => $client->id,
                'starts_at' => $day->copy()->setTime($hour, 0),
                'ends_at' => $day->copy()->setTime($hour + 1, 0),
                'status' => $status,
            ]);
        });
    }

    protected function commissionFor(Company $company, Personal $personal, float $amount): void
    {
        $this->inCompany($company, function () use ($company, $personal, $amount) {
            $client = Client::factory()->create(['company_id' => $company->id]);
            $service = Service::factory()->create(['company_id' => $company->id, 'price' => $amount * 10]);

            $sale = \App\Models\Sale::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'personal_id' => $personal->id,
                'number' => 'V-'.fake()->unique()->numerify('######'),
                'status' => 'pagada',
                'subtotal' => $amount * 10,
                'total' => $amount * 10,
                'sold_at' => now(),
            ]);

            $item = \App\Models\SaleItem::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'type' => 'servicio',
                'service_id' => $service->id,
                'personal_id' => $personal->id,
                'description' => $service->name,
                'quantity' => 1,
                'unit_price' => $amount * 10,
                'total' => $amount * 10,
            ]);

            \App\Models\CommissionEntry::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'sale_item_id' => $item->id,
                'personal_id' => $personal->id,
                'base_amount' => $amount * 10,
                'rate_type' => 'porcentaje',
                'rate_value' => 10,
                'amount' => $amount,
                'earned_at' => now(),
            ]);
        });
    }

    protected function nextMonday(): Carbon
    {
        return Carbon::today()->addWeek()->startOfWeek(Carbon::MONDAY);
    }
}
