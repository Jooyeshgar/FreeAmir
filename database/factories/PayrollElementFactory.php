<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PayrollElement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollElementFactory extends Factory
{
    protected $model = PayrollElement::class;

    public function definition(): array
    {
        return [
            'company_id'      => Company::factory(),
            'title'           => $this->faker->words(3, true),
            'system_code'     => $this->faker->randomElement([
                'CHILD_ALLOWANCE', 'HOUSING_ALLOWANCE', 'FOOD_ALLOWANCE', 'MARRIAGE_ALLOWANCE',
                'OVERTIME', 'FRIDAY_PAY', 'HOLIDAY_PAY', 'MISSION_PAY',
                'INSURANCE_EMP', 'INSURANCE_EMP2', 'UNEMPLOYMENT_INS',
                'INCOME_TAX', 'ABSENCE_DEDUCTION', 'OTHER',
            ]),
            'category'        => $this->faker->randomElement(['earning', 'deduction']),
            'calc_type'       => $this->faker->randomElement(['fixed', 'formula', 'percentage']),
            'formula'         => null,
            'default_amount'  => $this->faker->optional()->randomFloat(2, 100_000, 5_000_000),
            'is_taxable'      => $this->faker->boolean(),
            'is_insurable'    => $this->faker->boolean(),
            'show_in_payslip' => true,
            'is_system_locked' => false,
            'gl_account_code' => $this->faker->optional()->numerify('####'),
        ];
    }
}
