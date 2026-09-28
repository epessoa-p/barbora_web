<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'service_category_id' => null,
            'name' => 'Servicio '.fake()->unique()->numerify('###'),
            'duration_minutes' => fake()->randomElement([20, 30, 45, 60]),
            'price' => fake()->randomFloat(2, 20, 200),
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
