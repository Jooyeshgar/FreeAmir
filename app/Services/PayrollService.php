<?php

namespace App\Services;

use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryDecree;
use Illuminate\Support\Facades\DB;

/**
 * PayrollService
 *
 * Creates a Payroll record from a MonthlyAttendance and a SalaryDecree.
 *
 * Calculation logic:
 *  - Base pay  : decree.daily_wage × attendance.present_days
 *  - Benefits  : each DecreeBenefit element_value is summed as an earning
 *  - Overtime  : overtime_minutes ÷ (shift_minutes_per_day / 8) × hourly_rate × 1.4  (if element exists)
 *  - Absence   : daily_wage × absent_days  (deduction)
 *
 * All figures are stored as separate PayrollItem rows for full traceability.
 */
class PayrollService
{
    /**
     * Generate and persist a payroll for the given attendance + decree.
     */
    public function createFromAttendance(
        MonthlyAttendance $attendance,
        SalaryDecree $decree,
        int $companyId
    ): Payroll {
        $attendance->loadMissing(['employee.workShift']);
        $decree->loadMissing('benefits.element');

        return DB::transaction(function () use ($attendance, $decree, $companyId) {
            // ── Delete any existing draft payroll for the same period ──────────
            Payroll::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('employee_id', $attendance->employee_id)
                ->where('year', $attendance->year)
                ->where('month', $attendance->month)
                ->delete();

            // ── Base daily wage ───────────────────────────────────────────────
            $dailyWage = (float) ($decree->daily_wage ?? 0);

            // ── Earnings ──────────────────────────────────────────────────────
            $totalEarnings = 0.0;
            $items = [];

            // Base salary from present days
            $basePay = $dailyWage * $attendance->present_days;
            $totalEarnings += $basePay;

            $items[] = [
                'element_id' => null,
                'calculated_amount' => $basePay,
                'unit_count' => $attendance->present_days,
                'unit_rate' => $dailyWage,
                'description' => __('Base salary (:days days × :rate/day)', [
                    'days' => $attendance->present_days,
                    'rate' => number_format($dailyWage),
                ]),
            ];

            // Benefits from decree
            foreach ($decree->benefits as $benefit) {
                $element = $benefit->element;
                if ($element === null) {
                    continue;
                }

                $amount = (float) $benefit->element_value;

                if ($element->category === 'earning') {
                    $totalEarnings += $amount;
                    $items[] = [
                        'element_id' => $element->id,
                        'calculated_amount' => $amount,
                        'unit_count' => null,
                        'unit_rate' => null,
                        'description' => $element->title,
                    ];
                }
            }

            // ── Deductions ────────────────────────────────────────────────────
            $totalDeductions = 0.0;

            // Absence deduction
            if ($attendance->absent_days > 0) {
                $absenceDeduction = $dailyWage * $attendance->absent_days;
                $totalDeductions += $absenceDeduction;
                $items[] = [
                    'element_id' => null,
                    'calculated_amount' => -$absenceDeduction,
                    'unit_count' => $attendance->absent_days,
                    'unit_rate' => $dailyWage,
                    'description' => __('Absence deduction (:days days)', ['days' => $attendance->absent_days]),
                ];
            }

            // Decree deduction elements
            foreach ($decree->benefits as $benefit) {
                $element = $benefit->element;
                if ($element === null) {
                    continue;
                }

                $amount = (float) $benefit->element_value;

                if ($element->category === 'deduction') {
                    $totalDeductions += $amount;
                    $items[] = [
                        'element_id' => $element->id,
                        'calculated_amount' => -$amount,
                        'unit_count' => null,
                        'unit_rate' => null,
                        'description' => $element->title,
                    ];
                }
            }

            $netPayment = $totalEarnings - $totalDeductions;

            // ── Create Payroll ─────────────────────────────────────────────────
            $payroll = Payroll::create([
                'company_id' => $companyId,
                'employee_id' => $attendance->employee_id,
                'decree_id' => $decree->id,
                'monthly_attendance_id' => $attendance->id,
                'year' => $attendance->year,
                'month' => $attendance->month,
                'total_earnings' => $totalEarnings,
                'total_deductions' => $totalDeductions,
                'net_payment' => $netPayment,
                'employer_insurance' => 0,
                'status' => 'draft',
                'description' => __('Payroll for :month :year', [
                    'month' => $attendance->month_name,
                    'year' => $attendance->year,
                ]),
            ]);

            // ── Create PayrollItems ────────────────────────────────────────────
            foreach ($items as $item) {
                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'element_id' => $item['element_id'],
                    'calculated_amount' => $item['calculated_amount'],
                    'unit_count' => $item['unit_count'],
                    'unit_rate' => $item['unit_rate'],
                    'description' => $item['description'],
                ]);
            }

            return $payroll;
        });
    }
}
