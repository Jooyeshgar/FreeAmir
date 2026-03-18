<?php

namespace App\Services;

use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryDecree;
use App\Models\TaxSlab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PayrollService
 *
 * Calculates and persists an Iranian-labor-law-compliant payroll from a
 * MonthlyAttendance record and the employee's active SalaryDecree.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * Calculation overview
 * ──────────────────────────────────────────────────────────────────────────
 *  Shift normal minutes  = end_time − start_time − break  (WorkShift::duration)
 *  Normal hourly wage    = daily_wage / (shift_normal_minutes / 60)
 *  Prorated days         = work_days − absent_days  (paid leave NOT deducted)
 *
 *  Earnings
 *    Base salary         = prorated_days × daily_wage
 *    Housing / Grocery   = if calc_type = 'daily': element_value / work_days × prorated_days
 *                          otherwise              : element_value (full fixed amount)
 *    Overtime pay        = (overtime_minutes / 60) × hourly_wage × overtime_coefficient
 *    Child allowance     = fixed per-child amount; NOT prorated (unless prorated_days = 0)
 *    Other earnings      = element_value as-is
 *
 *  Deductions (statutory)
 *    Insurance base      = base_salary + housing + grocery + overtime
 *                          (child allowance is EXEMPT per Iranian SS law)
 *    Employee insurance  = insurance_base × 0.07
 *    Tax base            = total_gross − child_allowance − employee_insurance
 *    Income tax          = progressive slab calculation via TaxSlab table;
 *                          falls back to flat 10 % on excess above annual_exemption
 *                          when no slabs are configured for the payroll year
 *
 *  Employer insurance (stored for reporting, not subtracted from net)
 *    = insurance_base × 0.20  (standard Iranian SS employer share)
 *
 * All line items are stored as PayrollItem rows for full traceability.
 * ──────────────────────────────────────────────────────────────────────────
 */
class PayrollService
{
    // ── Iranian Social-Security statutory rates ───────────────────────────
    private const EMPLOYEE_INSURANCE_RATE = 0.07;

    private const EMPLOYER_INSURANCE_RATE = 0.20;

    // ── Fallback flat tax rate (used when no TaxSlab rows exist) ──────────
    private const FALLBACK_TAX_RATE = 0.10;

    // ── system_code constants ─────────────────────────────────────────────
    private const CODE_CHILD = 'CHILD_ALLOWANCE';

    private const CODE_HOUSING = 'HOUSING_ALLOWANCE';

    private const CODE_FOOD = 'FOOD_ALLOWANCE';

    private const CODE_OVERTIME = 'OVERTIME';

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calculate, persist, and return a Payroll with all its PayrollItems.
     *
     * @return Payroll The freshly created (or replaced) Payroll model.
     */
    public function createFromAttendance(
        MonthlyAttendance $attendance,
        SalaryDecree $decree,
        int $companyId,
    ): Payroll {
        $attendance->loadMissing(['employee.workShift']);
        $decree->loadMissing('benefits.element');

        return DB::transaction(function () use ($attendance, $decree, $companyId) {
            // Replace any existing draft for the same employee / period
            Payroll::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('employee_id', $attendance->employee_id)
                ->where('year', $attendance->year)
                ->where('month', $attendance->month)
                ->delete();

            $breakdown = $this->calculate($attendance, $decree, $companyId);

            return $this->persist($breakdown, $attendance, $decree, $companyId);
        });
    }

    /**
     * Return a full salary breakdown without persisting anything.
     * Useful for previews and unit tests.
     *
     * @return array{
     *   prorated_days: float,
     *   daily_wage: float,
     *   hourly_wage: float,
     *   earnings: array<string, array{element_id: int|null, amount: float, unit_count: float|null, unit_rate: float|null, description: string}>,
     *   gross_salary: float,
     *   insurance_base: float,
     *   employee_insurance: float,
     *   employer_insurance: float,
     *   tax_base: float,
     *   income_tax: float,
     *   total_earnings: float,
     *   total_deductions: float,
     *   net_payment: float,
     *   items: list<array{element_id: int|null, calculated_amount: float, unit_count: float|null, unit_rate: float|null, description: string}>
     * }
     */
    public function calculate(MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): array
    {
        $attendance->loadMissing(['employee.workShift']);
        $decree->loadMissing('benefits.element');

        $dailyWage = (float) ($decree->daily_wage ?? 0);
        $workDays = max(1, (int) $attendance->work_days);   // denominator guard
        $absentDays = (int) $attendance->absent_days;
        $proratedDays = max(0, $workDays - $absentDays);

        $hourlyWage = $this->resolveHourlyWage($attendance->employee->workShift, $dailyWage);

        // ── Earnings ──────────────────────────────────────────────────────
        $earnings = [];

        // 1. Base salary
        $basePay = $dailyWage * $proratedDays;
        $earnings['base_salary'] = [
            'element_id' => null,
            'amount' => $basePay,
            'unit_count' => $proratedDays,
            'unit_rate' => $dailyWage,
            'description' => __('Base salary (:days days × :rate/day)', [
                'days' => $proratedDays,
                'rate' => number_format($dailyWage),
            ]),
        ];

        // 2. Decree benefits (earnings)
        $childAllowance = 0.0;
        $housingAmount = 0.0;
        $groceryAmount = 0.0;
        $overtimeAmount = 0.0;

        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if ($element === null || $element->category !== 'earning') {
                continue;
            }

            $raw = (float) $benefit->element_value;

            $amount = match ($element->system_code) {
                self::CODE_HOUSING, self::CODE_FOOD => $this->prorateAllowance(
                    $raw,
                    $element->calc_type,
                    $workDays,
                    $proratedDays,
                ),
                self::CODE_CHILD => $proratedDays > 0
                    ? $raw * ($attendance->employee->children_count ?? 0)
                    : 0.0,
                self::CODE_OVERTIME => 0.0, // Computed separately below
                default => $raw,
            };

            // Store component totals for insurance/tax base later
            match ($element->system_code) {
                self::CODE_HOUSING => $housingAmount = $amount,
                self::CODE_FOOD => $groceryAmount = $amount,
                self::CODE_CHILD => $childAllowance = $amount,
                default => null,
            };

            if ($element->system_code === self::CODE_OVERTIME) {
                continue; // handled after this loop
            }

            $earnings[$element->system_code.'_'.$element->id] = [
                'element_id' => $element->id,
                'amount' => $amount,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
            ];
        }

        // 3. Overtime — driven by attendance minutes, not a fixed decree value
        $overtimeMinutes = (int) ($attendance->overtime ?? 0);
        if ($overtimeMinutes > 0) {
            $overtimeCoeff = $this->resolveOvertimeCoefficient($attendance->employee->workShift);
            $overtimeHours = $overtimeMinutes / 60;
            $overtimeAmount = round($overtimeHours * $hourlyWage * $overtimeCoeff, 2);

            $earnings['overtime'] = [
                'element_id' => $this->findElementId($decree->benefits, self::CODE_OVERTIME),
                'amount' => $overtimeAmount,
                'unit_count' => $overtimeHours,
                'unit_rate' => round($hourlyWage * $overtimeCoeff, 2),
                'description' => __('Overtime (:hours hrs × :rate × :coeff)', [
                    'hours' => number_format($overtimeHours, 2),
                    'rate' => number_format($hourlyWage),
                    'coeff' => $overtimeCoeff,
                ]),
            ];
        }

        // ── Gross & insurance base ────────────────────────────────────────
        $totalEarnings = array_sum(array_column($earnings, 'amount'));

        // Insurance base = base + housing + grocery + overtime (child exempt)
        $insuranceBase = $basePay + $housingAmount + $groceryAmount + $overtimeAmount;
        $employeeInsurance = round($insuranceBase * self::EMPLOYEE_INSURANCE_RATE, 2);
        $employerInsurance = round($insuranceBase * self::EMPLOYER_INSURANCE_RATE, 2);

        // ── Tax ───────────────────────────────────────────────────────────
        // Tax base: gross − child allowance − employee insurance deduction
        $taxBase = max(0.0, $totalEarnings - $childAllowance - $employeeInsurance);
        $incomeTax = $this->calculateTax($taxBase, $attendance->year, $companyId);

        // ── Build statutory deduction items ───────────────────────────────
        $deductions = [];

        if ($employeeInsurance > 0) {
            $deductions['employee_insurance'] = [
                'element_id' => $this->findSystemElementId($decree->benefits, 'INSURANCE_EMP'),
                'amount' => $employeeInsurance,
                'unit_count' => null,
                'unit_rate' => self::EMPLOYEE_INSURANCE_RATE,
                'description' => __('Employee social insurance (7%)'),
            ];
        }

        if ($incomeTax > 0) {
            $deductions['income_tax'] = [
                'element_id' => $this->findSystemElementId($decree->benefits, 'INCOME_TAX'),
                'amount' => $incomeTax,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => __('Income tax'),
            ];
        }

        // Also include any explicit deduction elements from the decree
        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if ($element === null || $element->category !== 'deduction') {
                continue;
            }
            // Skip statutory ones already computed above
            if (in_array($element->system_code, ['INSURANCE_EMP', 'INSURANCE_EMP2', 'INCOME_TAX'], true)) {
                continue;
            }

            $amount = (float) $benefit->element_value;
            $deductions['deduction_'.$element->id] = [
                'element_id' => $element->id,
                'amount' => $amount,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
            ];
        }

        $totalDeductions = array_sum(array_column($deductions, 'amount'));
        $netPayment = $totalEarnings - $totalDeductions;

        // ── Flatten to PayrollItem-shaped rows ────────────────────────────
        $items = $this->buildItemRows($earnings, $deductions);

        return [
            'prorated_days' => $proratedDays,
            'daily_wage' => $dailyWage,
            'hourly_wage' => $hourlyWage,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross_salary' => $totalEarnings,
            'insurance_base' => $insuranceBase,
            'employee_insurance' => $employeeInsurance,
            'employer_insurance' => $employerInsurance,
            'tax_base' => $taxBase,
            'income_tax' => $incomeTax,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_payment' => $netPayment,
            'items' => $items,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Derive the hourly wage from the WorkShift's useful-hours definition.
     * Falls back to daily_wage / 8 when no shift is assigned.
     */
    private function resolveHourlyWage(mixed $workShift, float $dailyWage): float
    {
        $shiftMinutes = $workShift?->duration ?? (8 * 60);

        if ($shiftMinutes <= 0) {
            $shiftMinutes = 8 * 60;
        }

        return round($dailyWage / ($shiftMinutes / 60), 4);
    }

    /**
     * Fetch the overtime multiplier from the shift, default 1.4 (Iranian law minimum).
     */
    private function resolveOvertimeCoefficient(mixed $workShift): float
    {
        return (float) ($workShift?->overtime_coefficient ?? 1.4);
    }

    /**
     * Prorate a monthly allowance when calc_type = 'daily'.
     * A 'fixed' or 'percentage' allowance is returned as-is.
     */
    private function prorateAllowance(
        float $rawValue,
        string $calcType,
        int $workDays,
        int $proratedDays,
    ): float {
        if ($calcType === 'daily' && $workDays > 0) {
            return round($rawValue / $workDays * $proratedDays, 2);
        }

        return $rawValue;
    }

    /**
     * Progressive tax calculation using TaxSlab rows for the payroll year.
     * The slabs store *annual* bands; the monthly taxable base is annualised,
     * tax is computed, then divided back to a monthly figure.
     *
     * Falls back to a flat FALLBACK_TAX_RATE on excess above annual_exemption
     * when no slabs are configured.
     */
    private function calculateTax(float $monthlyTaxBase, int $year, int $companyId): float
    {
        if ($monthlyTaxBase <= 0) {
            return 0.0;
        }

        $annualBase = $monthlyTaxBase * 12;

        $slabs = TaxSlab::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->orderBy('slab_order')
            ->get();

        if ($slabs->isEmpty()) {
            return $this->fallbackTax($annualBase) / 12;
        }

        return $this->progressiveTax($annualBase, $slabs) / 12;
    }

    /**
     * Apply Iranian progressive tax bands.
     * Each slab has income_from, income_to (null = unlimited), and tax_rate (%).
     * The first slab typically carries an annual_exemption threshold.
     */
    private function progressiveTax(float $annualBase, Collection $slabs): float
    {
        // Honour annual_exemption from the first slab if present
        $exemption = (float) ($slabs->first()?->annual_exemption ?? 0);
        $taxableBase = max(0.0, $annualBase - $exemption);

        if ($taxableBase <= 0) {
            return 0.0;
        }

        $annualTax = 0.0;
        $remaining = $taxableBase;

        foreach ($slabs as $slab) {
            if ($remaining <= 0) {
                break;
            }

            $from = max(0.0, (float) $slab->income_from - $exemption);
            $to = $slab->income_to !== null
                ? max(0.0, (float) $slab->income_to - $exemption)
                : PHP_FLOAT_MAX;

            $bandSize = max(0.0, $to - $from);
            $inBand = min($remaining, $bandSize);
            $annualTax += $inBand * ((float) $slab->tax_rate / 100);
            $remaining -= $inBand;
        }

        return round($annualTax, 2);
    }

    /**
     * Simple flat-rate fallback: 10 % on income above an annual exemption.
     * The exemption figure is taken from the first available TaxSlab row for
     * any year, or defaults to 0 when the table is completely empty.
     */
    private function fallbackTax(float $annualBase): float
    {
        $exemption = (float) (TaxSlab::withoutGlobalScopes()
            ->orderBy('year', 'desc')
            ->value('annual_exemption') ?? 0);

        $taxable = max(0.0, $annualBase - $exemption);

        return round($taxable * self::FALLBACK_TAX_RATE, 2);
    }

    /**
     * Return the element_id for the first benefit whose element has the given system_code.
     */
    private function findElementId(iterable $benefits, string $systemCode): ?int
    {
        foreach ($benefits as $benefit) {
            if ($benefit->element?->system_code === $systemCode) {
                return $benefit->element->id;
            }
        }

        return null;
    }

    /**
     * Alias kept for clarity at call sites that deal with deduction elements.
     */
    private function findSystemElementId(iterable $benefits, string $systemCode): ?int
    {
        return $this->findElementId($benefits, $systemCode);
    }

    /**
     * Convert the earnings and deductions maps into the flat list that maps
     * directly to PayrollItem columns. Deductions have negative calculated_amount.
     *
     * @return list<array{element_id: int|null, calculated_amount: float, unit_count: float|null, unit_rate: float|null, description: string}>
     */
    private function buildItemRows(array $earnings, array $deductions): array
    {
        $rows = [];

        foreach ($earnings as $entry) {
            $rows[] = [
                'element_id' => $entry['element_id'],
                'calculated_amount' => $entry['amount'],
                'unit_count' => $entry['unit_count'] ?? null,
                'unit_rate' => $entry['unit_rate'] ?? null,
                'description' => $entry['description'],
            ];
        }

        foreach ($deductions as $entry) {
            $rows[] = [
                'element_id' => $entry['element_id'],
                'calculated_amount' => -abs($entry['amount']),
                'unit_count' => $entry['unit_count'] ?? null,
                'unit_rate' => $entry['unit_rate'] ?? null,
                'description' => $entry['description'],
            ];
        }

        return $rows;
    }

    /**
     * Persist the Payroll and its PayrollItem rows inside the current transaction.
     */
    private function persist(
        array $breakdown,
        MonthlyAttendance $attendance,
        SalaryDecree $decree,
        int $companyId,
    ): Payroll {
        $payroll = Payroll::create([
            'company_id' => $companyId,
            'employee_id' => $attendance->employee_id,
            'decree_id' => $decree->id,
            'monthly_attendance_id' => $attendance->id,
            'year' => $attendance->year,
            'month' => $attendance->month,
            'total_earnings' => $breakdown['total_earnings'],
            'total_deductions' => $breakdown['total_deductions'],
            'net_payment' => $breakdown['net_payment'],
            'employer_insurance' => $breakdown['employer_insurance'],
            'status' => 'draft',
            'description' => __('Payroll for :month :year', [
                'month' => $attendance->month_name,
                'year' => $attendance->year,
            ]),
        ]);

        foreach ($breakdown['items'] as $item) {
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
    }
}
