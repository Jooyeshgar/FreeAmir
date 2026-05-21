<?php

namespace Database\Seeders;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollDemoSeeder extends Seeder
{
    /**
     * Seed a few months of payrolls per employee, mixing statuses so the demo
     * shows draft, pending-manager-approval, approved and paid records side by
     * side. Jalali year/month values are derived from the issue date via
     * `jdate()`.
     */
    public function run(): void
    {
        $employees = Employee::withoutGlobalScopes()->where('company_id', 1)->get();
        if ($employees->isEmpty()) {
            return;
        }

        // Status pattern across the last 4 months (oldest → newest):
        // paid → paid → approved → pending_manager_approval (i.e., waiting for confirmation).
        $statusByOffset = [
            3 => PayrollStatus::Paid,
            2 => PayrollStatus::Paid,
            1 => PayrollStatus::Approved,
            0 => PayrollStatus::PendingManagerApproval,
        ];

        foreach ($employees as $employee) {
            foreach ($statusByOffset as $monthsAgo => $status) {
                $issueDate = Carbon::now()->subMonthsNoOverflow($monthsAgo)->endOfMonth();
                $year = (int) toEnglish(jdate('Y', $issueDate->timestamp));
                $month = (int) toEnglish(jdate('m', $issueDate->timestamp));

                $totalEarnings = random_int(35_000_000, 75_000_000);
                $incomeTax = (int) round($totalEarnings * 0.10);
                $insurance = (int) round($totalEarnings * 0.07);
                $otherDeductions = random_int(0, 2_000_000);
                $totalDeductions = $incomeTax + $insurance + $otherDeductions;
                $netPayment = $totalEarnings - $totalDeductions;

                Payroll::withoutGlobalScopes()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'company_id' => 1,
                        'decree_id' => null,
                        'monthly_attendance_id' => null,
                        'total_earnings' => $totalEarnings,
                        'total_deductions' => $totalDeductions,
                        'net_payment' => $netPayment,
                        'employer_insurance' => (int) round($totalEarnings * 0.23),
                        'tax_base_amount' => $totalEarnings,
                        'income_tax_amount' => $incomeTax,
                        'issue_date' => $issueDate,
                        'status' => $status->value,
                        'accounting_voucher_id' => null,
                        'description' => __('Demo payroll'),
                    ]
                );
            }
        }
    }
}
