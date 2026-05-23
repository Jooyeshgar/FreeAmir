<?php

namespace Database\Seeders;

use App\Models\DecreeBenefit;
use App\Models\Employee;
use App\Models\PayrollElement;
use App\Models\SalaryDecree;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SalaryDecreeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $elements = PayrollElement::withoutGlobalScopes()->where('company_id', 1)->get()->keyBy('system_code');
        if ($elements->isEmpty()) {
            return;
        }

        $wageTiers = [12_000_000, 15_000_000, 18_000_000, 21_000_000, 25_000_000];

        $benefitTemplate = [
            'HOUSING_ALLOWANCE' => 40_000_000,
            'FOOD_ALLOWANCE' => 30_000_000,
            'INSURANCE_EMP' => 7,
            'OVERTIME' => null,
            'AUTO_OVERTIME' => null,
            'FRIDAY_PAY' => null,
            'HOLIDAY_PAY' => null,
            'MISSION_PAY' => null,
            'ABSENCE_DEDUCTION' => null,
            'UNDERTIME' => null,
            'INCOME_TAX' => null,
        ];

        foreach ($employees as $employee) {
            $dailyWage = $wageTiers[$employee->id % count($wageTiers)];

            $decree = SalaryDecree::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => 1,
                    'employee_id' => $employee->id,
                ],
                [
                    'name' => 'حکم کارگزینی',
                    'start_date' => Carbon::now()->subYear()->startOfYear()->toDateString(),
                    'end_date' => null,
                    'daily_wage' => $dailyWage,
                    'description' => __('Salary Decree'),
                    'is_active' => true,
                ]
            );

            foreach ($benefitTemplate as $code => $value) {
                $element = $elements->get($code);
                if (! $element) {
                    continue;
                }

                DecreeBenefit::updateOrCreate(
                    ['decree_id' => $decree->id, 'element_id' => $element->id],
                    ['element_value' => $value]
                );
            }
        }
    }
}
