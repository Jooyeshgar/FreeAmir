<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use App\Models\OrgChart;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrgChartFactory extends Factory
{
    protected $model = OrgChart::class;

    public function definition(): array
    {
        return [
            'company_id' => FiscalYear::factory(),
            'title' => $this->faker->jobTitle(),
            'parent_id' => null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
