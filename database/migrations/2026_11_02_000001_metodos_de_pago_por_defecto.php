<?php

use App\Models\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los métodos de pago por defecto para las barberías que no tienen
     * ninguno.
     *
     * CompanyObserver se los pone a cada barbería al crearla, pero las que ya
     * existían cuando se creó la tabla (o las que entraron por SQL) se quedaron
     * sin ninguno: el cobro salía sin métodos en la web y en la app. Solo
     * añade; una barbería que ya tiene alguno (aunque esté de baja) no se toca.
     */
    public function up(): void
    {
        $companies = DB::table('companies')
            ->whereNotExists(fn ($q) => $q->from('payment_methods')
                ->whereColumn('payment_methods.company_id', 'companies.id'))
            ->pluck('id');

        foreach ($companies as $companyId) {
            foreach (PaymentMethod::DEFAULTS as $index => $method) {
                DB::table('payment_methods')->insert([
                    'company_id' => $companyId,
                    'name' => mb_strtoupper($method['name']),
                    'slug' => $method['slug'],
                    'counts_as_cash' => $method['counts_as_cash'],
                    'requires_reference' => $method['requires_reference'],
                    'active' => true,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** Solo añade datos que la barbería puede haber usado ya: no se deshace. */
    public function down(): void
    {
    }
};
