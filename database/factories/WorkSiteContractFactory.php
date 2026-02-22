<?php

namespace Database\Factories;

use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkSiteContractFactory extends Factory
{
    protected $model = WorkSiteContract::class;

    public function definition(): array
    {
        return [
            'work_site_id' => WorkSite::factory(),
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->bothify('C-###'),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
