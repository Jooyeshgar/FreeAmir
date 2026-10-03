<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company,
            'address' => $this->faker->address,
            'postal_code' => $this->faker->postcode,
            'phone_number' => $this->faker->phoneNumber,
            'fiscal_year' => $this->faker->randomElement([1400, 1401, 1402, 1403]),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (FiscalYear $company) {
            $user = User::first() ?? User::factory()->create();
            $user->companies()->syncWithoutDetaching([$company->id]);
        });
    }
}
