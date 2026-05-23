<?php

namespace Database\Seeders;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollStatusHistory;
use App\Models\PersonnelRequest;
use App\Models\SalaryDecree;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::withoutGlobalScopes()->first()?->id;

        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        $nowYear = (int) toEnglish(jdate('Y', Carbon::now()->timestamp));
        $nowMonth = (int) toEnglish(jdate('m', Carbon::now()->timestamp));

        foreach ($employees as $employee) {
            $decree = SalaryDecree::withoutGlobalScopes()->where('company_id', 1)->where('employee_id', $employee->id)
                ->where('is_active', true)->with('benefits.element')->first();

            if (! $decree) {
                continue;
            }

            $dailyWage = (float) ($decree->daily_wage ?? 0);
            $hourlyWage = $dailyWage / 8;

            $decreeElements = $decree->benefits->map(fn ($b) => $b->element)->filter()->keyBy('system_code');

            $benefitValues = $decree->benefits->filter(fn ($b) => $b->element !== null)
                ->mapWithKeys(fn ($b) => [$b->element->system_code => (float) $b->element_value])->all();

            $housing = $benefitValues['HOUSING_ALLOWANCE'] ?? 30_000_000;
            $food = $benefitValues['FOOD_ALLOWANCE'] ?? 30_000_000;

            $insuranceRate = isset($benefitValues['INSURANCE_EMP']) ? $benefitValues['INSURANCE_EMP'] / 100 : 0.07;

            $attendances = MonthlyAttendance::withoutGlobalScopes()->where('company_id', 1)->where('employee_id', $employee->id)->get();

            foreach ($attendances as $attendance) {
                $monthsAgo = ($nowYear - $attendance->year) * 12 + $nowMonth - $attendance->month;

                $status = match (true) {
                    $monthsAgo <= 0 => PayrollStatus::PendingManagerApproval,
                    $monthsAgo === 1 => PayrollStatus::Approved,
                    default => PayrollStatus::Paid,
                };

                $workDays = max(1, (int) $attendance->work_days);
                $absentDays = (int) $attendance->absent_days;
                $proratedDays = max(0, $workDays - $absentDays);

                $baseSalary = $dailyWage * $proratedDays;

                $overtimeHours = (int) $attendance->overtime / 60;
                $overtimeAmt = round($overtimeHours * $hourlyWage * 1.4, 2);

                $autoOtHours = (int) $attendance->auto_overtime / 60;
                $autoOtAmt = round($autoOtHours * $hourlyWage * 1.4, 2);

                $fridayHours = (int) $attendance->friday / 60;
                $fridayAmt = round($fridayHours * $hourlyWage * 1.5, 2);

                $holidayHours = (int) $attendance->holiday / 60;
                $holidayAmt = round($holidayHours * $hourlyWage * 2.0, 2);

                $missionHours = (int) $attendance->mission / 60;
                $missionAmt = round($missionHours * $hourlyWage * 1.25, 2);

                $totalEarnings = $baseSalary + $housing + $food
                    + $overtimeAmt + $autoOtAmt + $fridayAmt + $holidayAmt + $missionAmt;

                $undertimeHours = (int) $attendance->undertime / 60;
                $undertimeAmt = round($undertimeHours * $hourlyWage * 2.0, 2);

                $absenceAmt = $absentDays * $dailyWage;

                $insurableBase = $baseSalary + $housing + $food + $overtimeAmt + $autoOtAmt + $fridayAmt + $holidayAmt + $missionAmt;
                $employeeInsurance = round($insurableBase * $insuranceRate, 2);
                $employerInsurance = round($insurableBase * 0.20, 2);

                $taxBase = max(0.0, $totalEarnings - $employeeInsurance);
                $incomeTax = round($taxBase * 0.10, 2);

                $totalDeductions = $employeeInsurance + $incomeTax + $undertimeAmt + $absenceAmt;
                $netPayment = $totalEarnings - $totalDeductions;

                $issueDate = $attendance->start_date ? Carbon::parse($attendance->start_date)->addDays(($attendance->duration ?? 30) - 1) : Carbon::now();

                $payroll = Payroll::withoutGlobalScopes()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'year' => $attendance->year,
                        'month' => $attendance->month,
                    ],
                    [
                        'company_id' => 1,
                        'decree_id' => $decree->id,
                        'monthly_attendance_id' => $attendance->id,
                        'total_earnings' => $totalEarnings,
                        'total_deductions' => $totalDeductions,
                        'net_payment' => $netPayment,
                        'employer_insurance' => $employerInsurance,
                        'tax_base_amount' => $taxBase,
                        'income_tax_amount' => $incomeTax,
                        'issue_date' => $issueDate,
                        'status' => $status->value,
                        'accounting_voucher_id' => null,
                        'description' => __('Demo payroll'),
                    ]
                );

                PayrollItem::where('payroll_id', $payroll->id)->delete();

                $items = [];

                $items[] = [
                    'element_id' => null,
                    'calculated_amount' => $baseSalary,
                    'unit_count' => $proratedDays,
                    'unit_rate' => $dailyWage,
                    'description' => 'حقوق پایه',
                ];

                if ($housing > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('HOUSING_ALLOWANCE')?->id,
                        'calculated_amount' => $housing,
                        'unit_count' => null,
                        'unit_rate' => null,
                        'description' => $decreeElements->get('HOUSING_ALLOWANCE')?->title ?? 'حق مسکن',
                    ];
                }

                if ($food > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('FOOD_ALLOWANCE')?->id,
                        'calculated_amount' => $food,
                        'unit_count' => null,
                        'unit_rate' => null,
                        'description' => $decreeElements->get('FOOD_ALLOWANCE')?->title ?? 'حق خواروبار',
                    ];
                }

                if ($overtimeAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('OVERTIME')?->id,
                        'calculated_amount' => $overtimeAmt,
                        'unit_count' => $overtimeHours,
                        'unit_rate' => round($hourlyWage * 1.4, 2),
                        'description' => 'اضافه کاری',
                    ];
                }

                if ($autoOtAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('AUTO_OVERTIME')?->id,
                        'calculated_amount' => $autoOtAmt,
                        'unit_count' => $autoOtHours,
                        'unit_rate' => round($hourlyWage * 1.4, 2),
                        'description' => 'اضافه کاری اتوماتیک',
                    ];
                }

                if ($fridayAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('FRIDAY_PAY')?->id,
                        'calculated_amount' => $fridayAmt,
                        'unit_count' => $fridayHours,
                        'unit_rate' => round($hourlyWage * 1.5, 2),
                        'description' => 'جمعه کاری',
                    ];
                }

                if ($holidayAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('HOLIDAY_PAY')?->id,
                        'calculated_amount' => $holidayAmt,
                        'unit_count' => $holidayHours,
                        'unit_rate' => round($hourlyWage * 2.0, 2),
                        'description' => 'تعطیل کاری',
                    ];
                }

                if ($missionAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('MISSION_PAY')?->id,
                        'calculated_amount' => $missionAmt,
                        'unit_count' => $missionHours,
                        'unit_rate' => round($hourlyWage * 1.25, 2),
                        'description' => 'ماموریت',
                    ];
                }

                if ($employeeInsurance > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('INSURANCE_EMP')?->id,
                        'calculated_amount' => -$employeeInsurance,
                        'unit_count' => null,
                        'unit_rate' => $benefitValues['INSURANCE_EMP'] ?? 7.0,
                        'description' => 'بیمه سهم کارمند',
                    ];
                }

                if ($incomeTax > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('INCOME_TAX')?->id,
                        'calculated_amount' => -$incomeTax,
                        'unit_count' => null,
                        'unit_rate' => 10.0,
                        'description' => 'مالیات حقوق',
                    ];
                }

                if ($undertimeAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('UNDERTIME')?->id,
                        'calculated_amount' => -$undertimeAmt,
                        'unit_count' => $undertimeHours,
                        'unit_rate' => round($hourlyWage * 2.0, 2),
                        'description' => 'کسری کار',
                    ];
                }

                if ($absenceAmt > 0) {
                    $items[] = [
                        'element_id' => $decreeElements->get('ABSENCE_DEDUCTION')?->id,
                        'calculated_amount' => -$absenceAmt,
                        'unit_count' => $absentDays,
                        'unit_rate' => $dailyWage,
                        'description' => 'کسری غیبت',
                    ];
                }

                foreach ($items as $item) {
                    PayrollItem::create(['payroll_id' => $payroll->id, ...$item]);
                }

                $this->linkPersonnelRequests($payroll, $attendance);
                $this->seedStatusHistory($payroll, $status, $issueDate, $adminId);
            }
        }
    }

    private function seedStatusHistory(Payroll $payroll, PayrollStatus $status, Carbon $issueDate, ?int $adminId): void
    {
        PayrollStatusHistory::where('payroll_id', $payroll->id)->delete();

        $transitions = [];

        $transitions[] = [
            'from_status' => PayrollStatus::Draft->value,
            'to_status' => PayrollStatus::PendingManagerApproval->value,
            'changed_by' => $adminId,
            'changed_at' => $issueDate->copy()->addDay(),
            'note' => null,
        ];

        if ($status === PayrollStatus::Approved || $status === PayrollStatus::Paid) {
            $transitions[] = [
                'from_status' => PayrollStatus::PendingManagerApproval->value,
                'to_status' => PayrollStatus::Approved->value,
                'changed_by' => $adminId,
                'changed_at' => $issueDate->copy()->addDays(3),
                'note' => null,
            ];
        }

        if ($status === PayrollStatus::Paid) {
            $transitions[] = [
                'from_status' => PayrollStatus::Approved->value,
                'to_status' => PayrollStatus::Paid->value,
                'changed_by' => $adminId,
                'changed_at' => $issueDate->copy()->addDays(7),
                'note' => null,
            ];
        }

        foreach ($transitions as $t) {
            PayrollStatusHistory::create(['payroll_id' => $payroll->id, ...$t]);
        }
    }

    private function linkPersonnelRequests(Payroll $payroll, MonthlyAttendance $attendance): void
    {
        [$gy, $gm, $gd] = jalali_to_gregorian($attendance->year, $attendance->month, 1);
        $periodStart = Carbon::createFromDate($gy, $gm, $gd)->startOfDay();

        $nextJMonth = $attendance->month === 12 ? 1 : $attendance->month + 1;
        $nextJYear = $attendance->month === 12 ? $attendance->year + 1 : $attendance->year;
        [$gy2, $gm2, $gd2] = jalali_to_gregorian($nextJYear, $nextJMonth, 1);
        $periodEnd = Carbon::createFromDate($gy2, $gm2, $gd2)->subDay()->endOfDay();

        PersonnelRequest::withoutGlobalScopes()->where('company_id', 1)->where('employee_id', $payroll->employee_id)
            ->whereBetween('start_date', [$periodStart, $periodEnd])->update(['payroll_id' => $payroll->id]);
    }
}
