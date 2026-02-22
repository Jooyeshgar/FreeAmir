<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkSiteFactory extends Factory
{
    protected $model = WorkSite::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->company(),
            'code' => strtoupper($this->faker->unique()->lexify('WS-???')),
            'address' => $this->faker->optional()->address(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
