<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => 'Plan '.fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 0, 300),
            'billing_period' => 'monthly',
            'trial_days' => 14,
            'max_users' => null,
            'max_branches' => null,
            'max_products' => null,
            'features' => array_keys(Plan::MODULES),
            'active' => true,
            'sort_order' => 0,
        ];
    }

    /** Plan con todos los módulos y sin límites. */
    public function unlimited(): static
    {
        return $this->state(fn () => [
            'max_users' => null,
            'max_branches' => null,
            'max_products' => null,
            'features' => array_keys(Plan::MODULES),
        ]);
    }

    /**
     * @param  array<int, string>  $features
     */
    public function withFeatures(array $features): static
    {
        return $this->state(fn () => ['features' => $features]);
    }
}
