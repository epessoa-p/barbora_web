<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Company::where('tax_id', '1023456789')->first();
        $prueba = Company::where('tax_id', '9876543210')->first();

        if ($demo && $premium = Plan::where('slug', 'premium')->first()) {
            $demo->subscription()->updateOrCreate([], [
                'plan_id' => $premium->id,
                'status' => 'active',
                'current_period_end' => now()->addMonth(),
                'grace_days' => 3,
            ]);
        }

        if ($prueba && $basico = Plan::where('slug', 'basico')->first()) {
            $prueba->subscription()->updateOrCreate([], [
                'plan_id' => $basico->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays($basico->trial_days),
                'grace_days' => 3,
                // Caso real de override: el plan Básico permite 1 sucursal,
                // a esta empresa se le conceden 2 sin cambiarle el plan.
                'max_branches_override' => 2,
            ]);
        }
    }
}
