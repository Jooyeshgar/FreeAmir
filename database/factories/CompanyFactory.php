<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

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
        return $this->afterCreating(function (Company $company) {
            $user = User::first() ?? User::factory()->create();
            $user->companies()->attach($company->id);
        });
    }
}
