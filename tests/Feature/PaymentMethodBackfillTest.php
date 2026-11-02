<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las barberías que se quedaron sin métodos de pago (creadas antes de la tabla
 * o cargadas por SQL) reciben los de por defecto; las demás no se tocan.
 */
class PaymentMethodBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_rellena_las_barberias_sin_metodos(): void
    {
        $sinMetodos = $this->companyWithPlan();
        $conMetodos = $this->companyWithPlan();

        DB::table('payment_methods')->where('company_id', $sinMetodos->id)->delete();
        DB::table('payment_methods')->where('company_id', $conMetodos->id)->where('slug', '<>', 'efectivo')->delete();

        $migration = require database_path('migrations/2026_11_02_000001_metodos_de_pago_por_defecto.php');
        $migration->up();

        $slugs = fn (int $id) => PaymentMethod::withoutGlobalScopes()->where('company_id', $id)->pluck('slug')->sort()->values()->all();

        $this->assertSame(['efectivo', 'qr', 'tarjeta', 'transferencia'], $slugs($sinMetodos->id));
        $this->assertSame(['efectivo'], $slugs($conMetodos->id), 'La que ya tiene alguno no se toca.');

        // Correrla otra vez no duplica nada.
        $migration->up();
        $this->assertCount(4, $slugs($sinMetodos->id));
    }
}
