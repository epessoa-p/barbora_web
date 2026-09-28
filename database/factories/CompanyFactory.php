<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => 'Barbería '.fake()->unique()->lastName(),
            'tax_id' => fake()->unique()->numerify('##########'),
            'tax_id_label' => 'NIT',
            'country' => 'BO',
            'currency' => 'BOB',
            'timezone' => 'America/La_Paz',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->numerify('+591 # ###-####'),
            'address' => fake()->streetAddress(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
