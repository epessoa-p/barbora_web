<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
            'grace_days' => 3,
        ];
    }

    public function onTrial(): static
    {
        return $this->state(fn () => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'current_period_end' => null,
        ]);
    }

    /** Vencida pero dentro de la gracia: entra, no escribe. */
    public function inGrace(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'current_period_end' => now()->subDay(),
            'grace_days' => 3,
        ]);
    }

    /** Vencida y pasada la gracia: no entra. */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'active',
            'current_period_end' => now()->subDays(30),
            'grace_days' => 3,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
