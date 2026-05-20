<?php

namespace Database\Factories;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $totalEarnings = $this->faker->randomFloat(2, 15_000_000, 80_000_000);
        $taxBaseAmount = $this->faker->randomFloat(2, 0, $totalEarnings);
        $incomeTaxAmount = $this->faker->randomFloat(2, 0, min($taxBaseAmount, $totalEarnings * 0.15));
        $otherDeductions = $this->faker->randomFloat(2, 0, $totalEarnings * 0.2);
        $totalDeductions = round($incomeTaxAmount + $otherDeductions, 2);

        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->create(['company_id' => $attributes['company_id']])->id,
            'decree_id' => null,
            'monthly_attendance_id' => null,
            'year' => $this->faker->numberBetween(1401, 1405),
            'month' => $this->faker->numberBetween(1, 12),
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_payment' => round($totalEarnings - $totalDeductions, 2),
            'employer_insurance' => $this->faker->randomFloat(2, 0, $totalEarnings * 0.23),
            'tax_base_amount' => $taxBaseAmount,
            'income_tax_amount' => $incomeTaxAmount,
            'issue_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'status' => $this->faker->randomElement(PayrollStatus::cases())->value,
            'accounting_voucher_id' => null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
