<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Sucursal '.fake()->unique()->numerify('####'),
            'code' => fake()->unique()->bothify('SUC-#####'),
            'phone' => fake()->numerify('+591 # ###-####'),
            'address' => fake()->streetAddress(),
            'active' => true,
        ];
    }
}
