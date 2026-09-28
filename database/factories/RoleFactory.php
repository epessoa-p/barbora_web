<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Por defecto crea un rol del SISTEMA (company_id null).
 * Para un rol propio de una empresa: Role::factory()->ownedBy($company).
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->jobTitle();

        return [
            'company_id' => null,
            'name' => $name,
            'slug' => Str::slug($name, '_') . '_' . $this->faker->unique()->numberBetween(1, 999999),
            'description' => $this->faker->sentence(),
        ];
    }

    public function ownedBy($company): static
    {
        return $this->state(fn () => [
            'company_id' => is_object($company) ? $company->id : $company,
        ]);
    }
}
