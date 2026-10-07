<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalYearFactory extends Factory
{
    protected $model = FiscalYear::class;

    public function definition()
    {
        return [
            'year' => $this->faker->randomElement([1400, 1401, 1402, 1403]),
            'company_id' => Company::factory()->create()->id,
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (FiscalYear $fiscalYear) {
            $user = User::first() ?? User::factory()->create();
            $user->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
        });
    }
}
