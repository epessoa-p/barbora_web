<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Turnos de caja y arqueo.
 *
 * La regla que gobierna todo el módulo: solo el efectivo está en el cajón, así
 * que solo él entra en el arqueo.
 */
class CashTest extends TestCase
{
    use RefreshDatabase;

    protected function caja(Company $company, array $attributes = []): Caja
    {
        return Caja::create(array_merge([
            'company_id' => $company->id,
            'name' => 'Caja '.fake()->unique()->numerify('###'),
            'balance' => 0,
            'active' => true,
        ], $attributes));
    }

    protected function openSession(Company $company, Caja $caja, float $opening = 100): CashSession
    {
        return CashSession::create([
            'company_id' => $company->id,
            'caja_id' => $caja->id,
            'status' => 'abierta',
            'opened_at' => now(),
            'opening_amount' => $opening,
        ]);
    }

    protected function move(CashSession $session, string $type, string $method, float $amount): CashMovement
    {
        return CashMovement::create([
            'company_id' => $session->company_id,
            'cash_session_id' => $session->id,
            'type' => $type,
            'payment_method' => $method,
            'concept' => 'Prueba',
            'amount' => $amount,
        ]);
    }

    // ── Abrir ───────────────────────────────────────────────────────────────

    public function test_se_abre_un_turno(): void
    {
        $company = $this->companyWithPlan();
        $caja = $this->caja($company);
        $user = $this->userInCompany($company, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $company)
            ->post(route('cash.open'), ['caja_id' => $caja->id, 'opening_amount' => 250])
            ->assertRedirect();

        $session = CashSession::allCompanies()->firstOrFail();

        $this->assertSame('abierta', $session->status);
        $this->assertSame('250.00', $session->opening_amount);
        $this->assertSame($user->id, $session->opened_by);
    }

    public function test_una_caja_no_abre_dos_turnos_a_la_vez(): void
    {
        $company = $this->companyWithPlan();
        $caja = $this->caja($company);
        $this->openSession($company, $caja);
        $user = $this->userInCompany($company, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $company)
            ->post(route('cash.open'), ['caja_id' => $caja->id, 'opening_amount' => 100])
            ->assertSessionHasErrors('caja_id');

        $this->assertSame(1, CashSession::allCompanies()->count());
    }

    public function test_dos_cajas_distintas_si_pueden_estar_abiertas(): void
    {
        $company = $this->companyWithPlan();
        $a = $this->caja($company);
        $b = $this->caja($company);
        $this->openSession($company, $a);
        $user = $this->userInCompany($company, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $company)
            ->post(route('cash.open'), ['caja_id' => $b->id, 'opening_amount' => 100])
            ->assertRedirect();

        $this->assertSame(2, CashSession::allCompanies()->count());
    }

    // ── Movimientos ─────────────────────────────────────────────────────────

    public function test_se_registra_un_ingreso(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company));
        $user = $this->userInCompany($company, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $company)
            ->post(route('cash.movements.store', $session), [
                'type' => 'ingreso',
                'payment_method' => 'efectivo',
                'concept' => 'Cobro corte',
                'amount' => 50,
            ])
            ->assertRedirect();

        $movement = CashMovement::allCompanies()->firstOrFail();

        $this->assertSame('ingreso', $movement->type);
        $this->assertSame(50.0, $movement->signedAmount());
    }

    public function test_un_egreso_resta(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company));

        $movement = $this->move($session, 'egreso', 'efectivo', 30);

        $this->assertSame(-30.0, $movement->signedAmount());
    }

    public function test_no_se_registran_movimientos_en_un_turno_cerrado(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company));
        $session->update(['status' => 'cerrada', 'closed_at' => now()]);
        $user = $this->userInCompany($company, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $company)
            ->post(route('cash.movements.store', $session), [
                'type' => 'ingreso', 'payment_method' => 'efectivo',
                'concept' => 'Tardío', 'amount' => 10,
            ])
            ->assertSessionHasErrors('error');

        $this->assertSame(0, CashMovement::allCompanies()->count());
    }

    public function test_anular_un_movimiento_lo_borra_en_blando(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company));
        $movement = $this->move($session, 'ingreso', 'efectivo', 50);
        $user = $this->userInCompany($company, ['cash.view', 'cash.delete']);

        $this->actingInCompany($user, $company)
            ->delete(route('cash.movements.destroy', $movement))
            ->assertRedirect();

        // Deja rastro: el turno tiene que poder explicarse después.
        $this->assertSame(0, CashMovement::allCompanies()->count());
        $this->assertSame(1, CashMovement::allCompanies()->withTrashed()->count());
    }

    // ── Arqueo ──────────────────────────────────────────────────────────────

    public function test_solo_el_efectivo_entra_en_el_arqueo(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company), 200);

        $this->move($session, 'ingreso', 'efectivo', 50);
        $this->move($session, 'ingreso', 'efectivo', 80);
        $this->move($session, 'ingreso', 'tarjeta', 120);       // no está en el cajón
        $this->move($session, 'ingreso', 'qr', 45);             // tampoco
        $this->move($session, 'egreso', 'efectivo', 60);

        $totals = $this->inCompany($company, fn () => $session->load('movements')->totals());

        $this->assertSame(295.0, $totals['income']);            // toda la recaudación
        $this->assertSame(130.0, $totals['cash_income']);       // solo el efectivo
        $this->assertSame(270.0, $totals['expected_cash']);     // 200 + 130 − 60
        $this->assertSame(235.0, $totals['net']);               // 295 − 60
    }

    public function test_cerrar_guarda_el_arqueo_y_la_diferencia(): void
    {
        $company = $this->companyWithPlan();
        $caja = $this->caja($company);
        $session = $this->openSession($company, $caja, 200);
        $this->move($session, 'ingreso', 'efectivo', 100);
        $user = $this->userInCompany($company, ['cash.view', 'cash.edit']);

        // Esperado 300, se cuentan 295: faltan 5.
        $this->actingInCompany($user, $company)
            ->put(route('cash.sessions.close', $session), ['closing_amount' => 295])
            ->assertRedirect(route('cash.sessions.show', $session));

        $session->refresh();

        $this->assertSame('cerrada', $session->status);
        $this->assertSame('300.00', $session->expected_amount);
        $this->assertSame('295.00', $session->closing_amount);
        $this->assertSame('-5.00', $session->difference);
        $this->assertSame('Faltante', $session->differenceLabel());
        $this->assertSame($user->id, $session->closed_by);
    }

    public function test_un_arqueo_exacto_queda_cuadrado(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company), 200);
        $this->move($session, 'ingreso', 'efectivo', 100);
        $user = $this->userInCompany($company, ['cash.view', 'cash.edit']);

        $this->actingInCompany($user, $company)
            ->put(route('cash.sessions.close', $session), ['closing_amount' => 300])
            ->assertRedirect();

        $this->assertSame('Cuadrada', $session->refresh()->differenceLabel());
    }

    public function test_al_cerrar_el_saldo_de_la_caja_es_lo_contado(): void
    {
        $company = $this->companyWithPlan();
        $caja = $this->caja($company, ['balance' => 0]);
        $session = $this->openSession($company, $caja, 200);
        $this->move($session, 'ingreso', 'efectivo', 100);
        $user = $this->userInCompany($company, ['cash.view', 'cash.edit']);

        $this->actingInCompany($user, $company)
            ->put(route('cash.sessions.close', $session), ['closing_amount' => 295])
            ->assertRedirect();

        // El saldo refleja el dinero real, no el teórico.
        $this->assertSame('295.00', $caja->refresh()->balance);
    }

    public function test_un_turno_cerrado_no_se_cierra_dos_veces(): void
    {
        $company = $this->companyWithPlan();
        $session = $this->openSession($company, $this->caja($company), 200);
        $user = $this->userInCompany($company, ['cash.view', 'cash.edit']);

        $this->actingInCompany($user, $company)
            ->put(route('cash.sessions.close', $session), ['closing_amount' => 200])
            ->assertRedirect();

        $this->actingInCompany($user, $company)
            ->put(route('cash.sessions.close', $session), ['closing_amount' => 999])
            ->assertSessionHasErrors('error');

        $this->assertSame('200.00', $session->refresh()->closing_amount);
    }

    // ── Aislamiento y permisos ──────────────────────────────────────────────

    public function test_los_turnos_se_aislan_por_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();

        $this->openSession($mia, $this->caja($mia, ['name' => 'Caja Propia']));
        $this->openSession($ajena, $this->caja($ajena, ['name' => 'Caja Ajena']));

        $user = $this->userInCompany($mia, ['cash.view']);

        $this->actingInCompany($user, $mia)
            ->get(route('cash.sessions.index'))
            ->assertOk()
            ->assertSee('Caja Propia')
            ->assertDontSee('Caja Ajena');
    }

    public function test_la_caja_requiere_el_modulo_caja(): void
    {
        $sinCaja = $this->companyWithPlan(planAttributes: ['features' => ['agenda']]);
        $user = $this->userInCompany($sinCaja, ['cash.view'], 'con-caja');

        $this->actingInCompany($user, $sinCaja)->get(route('cash.current'))->assertForbidden();
    }

    public function test_sin_permiso_no_se_abre_turno(): void
    {
        $company = $this->companyWithPlan();
        $this->caja($company);
        $user = $this->userInCompany($company, ['cash.view']);   // solo consulta

        $this->actingInCompany($user, $company)->get(route('cash.current'))->assertOk();

        $this->actingInCompany($user, $company)
            ->post(route('cash.open'), ['caja_id' => 1, 'opening_amount' => 100])
            ->assertForbidden();
    }

    public function test_no_se_abre_turno_en_una_caja_de_otra_empresa(): void
    {
        $mia = $this->companyWithPlan();
        $ajena = $this->companyWithPlan();
        $cajaAjena = $this->caja($ajena);
        $user = $this->userInCompany($mia, ['cash.view', 'cash.create']);

        $this->actingInCompany($user, $mia)
            ->post(route('cash.open'), ['caja_id' => $cajaAjena->id, 'opening_amount' => 100])
            ->assertSessionHasErrors('caja_id');
    }
}
