<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Una caja con un turno cerrado de ayer y otro abierto hoy, para que el módulo
 * se pueda mirar sin tener que montar todo a mano.
 */
class CashSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            $caja = Caja::firstOrCreate(
                ['name' => 'Caja principal'],
                ['code' => 'CAJA-01', 'description' => 'Mostrador de recepción', 'balance' => 0, 'active' => true]
            );

            CashMovement::query()->forceDelete();
            CashSession::query()->delete();

            // Turno de ayer, ya arqueado y cerrado con un pequeño faltante:
            // así se ve cómo se lee una diferencia real.
            $yesterday = CashSession::create([
                'company_id' => $demo->id,
                'caja_id' => $caja->id,
                'status' => 'abierta',
                'opened_at' => now()->subDay()->setTime(9, 0),
                'opening_amount' => 200,
            ]);

            $this->addMovements($demo->id, $yesterday, [
                ['ingreso', 'efectivo', 'Cobro corte clásico', 50],
                ['ingreso', 'efectivo', 'Cobro corte + barba', 80],
                ['ingreso', 'tarjeta', 'Cobro tinte', 120],
                ['ingreso', 'qr', 'Cobro afeitado', 45],
                ['egreso', 'efectivo', 'Compra de toallas', 60],
            ]);

            $yesterday->load('movements');
            $expected = $yesterday->totals()['expected_cash'];

            $yesterday->update([
                'status' => 'cerrada',
                'closed_at' => now()->subDay()->setTime(20, 30),
                'closing_amount' => $expected - 5,
                'expected_amount' => $expected,
                'difference' => -5,
                'closing_notes' => 'Faltan Bs 5, posible vuelto mal dado.',
            ]);

            $caja->update(['balance' => $expected - 5]);

            // Turno de hoy, todavía abierto.
            $today = CashSession::create([
                'company_id' => $demo->id,
                'caja_id' => $caja->id,
                'status' => 'abierta',
                'opened_at' => now()->setTime(9, 0),
                'opening_amount' => $caja->balance,
            ]);

            $this->addMovements($demo->id, $today, [
                ['ingreso', 'efectivo', 'Cobro perfilado de barba', 35],
                ['ingreso', 'efectivo', 'Cobro corte infantil', 40],
                ['ingreso', 'transferencia', 'Cobro decoloración', 180],
                ['egreso', 'efectivo', 'Propina al delivery de insumos', 15],
            ]);
        });
    }

    /** @param  array<int, array{0: string, 1: string, 2: string, 3: float}>  $rows */
    protected function addMovements(int $companyId, CashSession $session, array $rows): void
    {
        foreach ($rows as [$type, $method, $concept, $amount]) {
            CashMovement::create([
                'company_id' => $companyId,
                'cash_session_id' => $session->id,
                'type' => $type,
                'payment_method' => $method,
                'concept' => $concept,
                'amount' => $amount,
            ]);
        }
    }
}
