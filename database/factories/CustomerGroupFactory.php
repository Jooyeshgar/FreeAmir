<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    public function definition()
    {
        session(['active-company-id' => 1]);

        return [
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'company_id' => session('active-company-id'),
        ];
    }
}
