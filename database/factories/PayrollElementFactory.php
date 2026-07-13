<?php

namespace Database\Factories;

use App\Enums\PayrollElementCalcType;
use App\Enums\PayrollElementCategory;
use App\Enums\PayrollElementSystemCode;
use App\Models\Company;
use App\Models\PayrollElement;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollElementFactory extends Factory
{
    protected $model = PayrollElement::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'title' => $this->faker->words(3, true),
            'system_code' => $this->faker->randomElement(PayrollElementSystemCode::cases()),
            'category' => $this->faker->randomElement(PayrollElementCategory::cases()),
            'calc_type' => $this->faker->randomElement(PayrollElementCalcType::cases()),
            'formula' => null,
            'default_amount' => $this->faker->optional()->randomFloat(2, 100_000, 5_000_000),
            'is_taxable' => $this->faker->boolean(),
            'is_insurable' => $this->faker->boolean(),
            'show_in_payslip' => true,
            'is_system_locked' => false,
            'gl_account_code' => $this->faker->optional()->numerify('####'),
        ];
    }
}
