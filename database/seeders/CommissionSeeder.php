<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use App\Models\Company;
use App\Models\Personal;
use App\Models\Service;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Reglas de comisión de ejemplo, con las cuatro prioridades representadas para
 * que se vea cómo se resuelven.
 *
 * Va ANTES que SaleSeeder en el orden de siembra: las comisiones se devengan
 * al cobrar, así que sin reglas cargadas las ventas de la demo no generarían
 * ninguna.
 */
class CommissionSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();

        if (! $demo) {
            return;
        }

        app(Tenancy::class)->runFor($demo->id, function () use ($demo) {
            CommissionRule::query()->delete();

            $tania = Personal::where('full_name', 'Tania Molina')->first();
            $decoloracion = Service::where('name', 'Decoloración')->first();

            $rules = [
                // Prioridad 4: la base para todo el equipo.
                ['personal_id' => null, 'applies_to' => 'servicios', 'type' => 'porcentaje', 'value' => 40,
                 'notes' => 'Base del equipo sobre servicios'],
                ['personal_id' => null, 'applies_to' => 'productos', 'type' => 'porcentaje', 'value' => 10,
                 'notes' => 'La venta de producto comisiona menos que el servicio'],
            ];

            // Prioridad 3: un servicio concreto paga distinto a todo el mundo.
            if ($decoloracion) {
                $rules[] = ['personal_id' => null, 'service_id' => $decoloracion->id, 'applies_to' => 'servicio',
                            'type' => 'porcentaje', 'value' => 30,
                            'notes' => 'La decoloración lleva mucho material'];
            }

            // Prioridad 2: Tania tiene mejor trato general.
            if ($tania) {
                $rules[] = ['personal_id' => $tania->id, 'applies_to' => 'servicios',
                            'type' => 'porcentaje', 'value' => 45,
                            'notes' => 'Acordado por antigüedad'];

                // Prioridad 1: y aún mejor en decoloración, que es lo suyo.
                if ($decoloracion) {
                    $rules[] = ['personal_id' => $tania->id, 'service_id' => $decoloracion->id,
                                'applies_to' => 'servicio', 'type' => 'porcentaje', 'value' => 50,
                                'notes' => 'Especialista en color'];
                }
            }

            foreach ($rules as $rule) {
                CommissionRule::create($rule + ['company_id' => $demo->id, 'active' => true]);
            }
        });
    }
}
