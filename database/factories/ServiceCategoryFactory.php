<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Categoría '.fake()->unique()->numerify('###'),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
            'active' => true,
        ];
    }
}
