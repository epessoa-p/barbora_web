<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basico',
                'description' => 'Una barbería de un solo local que quiere agenda y cobrar.',
                'price' => 49.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 3,
                'max_branches' => 1,
                'max_products' => 50,
                'features' => ['agenda', 'caja', 'clientes'],
                'sort_order' => 1,
                'active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Cadena pequeña que vende productos y paga comisiones a sus barberos.',
                'price' => 99.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => 10,
                'max_branches' => 3,
                'max_products' => 500,
                'features' => ['agenda', 'caja', 'clientes', 'pos', 'comisiones', 'estadisticas'],
                'sort_order' => 2,
                'active' => true,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Todo incluido, sin límites: inventario real y reservas online.',
                'price' => 189.00,
                'billing_period' => 'monthly',
                'trial_days' => 14,
                'max_users' => null,
                'max_branches' => null,
                'max_products' => null,
                'features' => array_keys(Plan::MODULES),
                'sort_order' => 3,
                'active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
