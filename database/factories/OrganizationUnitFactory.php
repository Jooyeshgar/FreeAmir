<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationUnitFactory extends Factory
{
    protected $model = OrganizationUnit::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->bothify('UNIT-###')),
            'parent_id' => null,
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
