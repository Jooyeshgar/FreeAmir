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

    private const CODE_UNDERTIME = 'UNDERTIME';

    private const CODE_ABSENCE_DEDUCTION = 'ABSENCE_DEDUCTION';

    private const CODE_FRIDAY_PAY = 'FRIDAY_PAY';

    private const CODE_HOLIDAY_PAY = 'HOLIDAY_PAY';

    private const CODE_MISSION_PAY = 'MISSION_PAY';

    // ── Per-calculation state (set once per calculate() call) ─────────────
    private float $dailyWage = 0.0;

    private float $hourlyWage = 0.0;

    private int $workDays = 1;

    private int $absentDays = 0;

    private int $proratedDays = 0;

    private mixed $workShift = null;

    private iterable $benefits = [];

    private array $earnings = [];

    private array $deductions = [];

    private float $childAllowance = 0.0;

    private float $housingAmount = 0.0;

    private float $groceryAmount = 0.0;

    // ─────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Calculate, persist, and return a Payroll with all its PayrollItems.
     *
     * @return Payroll The freshly created (or replaced) Payroll model.
     */
    public function createFromAttendance(MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): Payroll
    {
        $attendance->loadMissing(['employee.workShift']);
        $decree->loadMissing('benefits.element');

        return DB::transaction(function () use ($attendance, $decree, $companyId) {
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
     */
    public function calculate(MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): array
    {
        $attendance->loadMissing(['employee.workShift']);
        $decree->loadMissing('benefits.element');

        $this->initState($attendance, $decree);

        $this->computeBaseSalary();
        $this->computeDecreeBenefits($decree);
        $this->computeDynamicEarnings($attendance);
        $this->computeDynamicDeductions($attendance);
        $this->computeStatutoryDeductions($attendance, $decree, $companyId);
        $this->computeCustomDeductions($decree);

        return $this->buildResult($attendance, $companyId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // State initialization
    // ─────────────────────────────────────────────────────────────────────

    private function initState(MonthlyAttendance $attendance, SalaryDecree $decree): void
    {
        $this->dailyWage = (float) ($decree->daily_wage ?? 0);
        $this->workDays = max(1, (int) $attendance->work_days);
        $this->absentDays = (int) $attendance->absent_days;
        $this->proratedDays = max(0, $this->workDays - $this->absentDays);
        $this->workShift = $attendance->employee->workShift;
        $this->benefits = $decree->benefits;
        $this->hourlyWage = $this->resolveHourlyWage();

        $this->earnings = [];
        $this->deductions = [];
        $this->childAllowance = 0.0;
        $this->housingAmount = 0.0;
        $this->groceryAmount = 0.0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Earning computations
    // ─────────────────────────────────────────────────────────────────────

    private function computeBaseSalary(): void
    {
        $basePay = $this->dailyWage * $this->proratedDays;

        $this->earnings['base_salary'] = [
            'element_id' => null,
            'amount' => $basePay,
            'unit_count' => $this->proratedDays,
            'unit_rate' => $this->dailyWage,
            'description' => __('Base salary (:days days × :rate/day)', [
                'days' => $this->proratedDays,
                'rate' => number_format($this->dailyWage),
            ]),
        ];
    }

    private function computeDecreeBenefits(SalaryDecree $decree): void
    {
        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if ($element === null || $element->category !== 'earning') {
                continue;
            }

            // Skip dynamic types — computed separately from attendance data
            if (in_array($element->system_code, [self::CODE_OVERTIME, self::CODE_FRIDAY_PAY, self::CODE_HOLIDAY_PAY, self::CODE_MISSION_PAY, self::CODE_ABSENCE_DEDUCTION], true)) {
                continue;
            }

            $raw = (float) $benefit->element_value;

            $amount = match ($element->system_code) {
                self::CODE_HOUSING, self::CODE_FOOD => $this->prorateAllowance(
                    $raw,
                    $element->calc_type,
                ),
                self::CODE_CHILD => $this->proratedDays > 0
                    ? $raw * ($benefit->element->employee?->children_count ?? 0)
                    : 0.0,
                default => $raw,
            };

            match ($element->system_code) {
                self::CODE_HOUSING => $this->housingAmount = $amount,
                self::CODE_FOOD => $this->groceryAmount = $amount,
                self::CODE_CHILD => $this->childAllowance = $amount,
                default => null,
            };

            $this->earnings[$element->system_code.'_'.$element->id] = [
                'element_id' => $element->id,
                'amount' => $amount,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
            ];
        }
    }

    private function computeDynamicEarnings(MonthlyAttendance $attendance): void
    {
        $this->addWageBasedItem((int) ($attendance->overtime ?? 0), 'overtime', self::CODE_OVERTIME, 'earning');
        $this->addWageBasedItem((int) ($attendance->friday_hours ?? 0), 'friday', self::CODE_FRIDAY_PAY, 'earning');
        $this->addWageBasedItem((int) ($attendance->holiday_hours ?? 0), 'holiday', self::CODE_HOLIDAY_PAY, category: 'earning');
        $this->addWageBasedItem((int) ($attendance->mission_hours ?? 0), 'mission', self::CODE_MISSION_PAY, category: 'earning');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Deduction computations
    // ─────────────────────────────────────────────────────────────────────

    private function computeDynamicDeductions(MonthlyAttendance $attendance): void
    {
        // Undertime (delay / early leave)
        $this->addWageBasedItem(
            minutes: (int) ($attendance->undertime ?? 0),
            type: 'undertime',
            systemCode: self::CODE_UNDERTIME,
            category: 'deduction',
        );

        // Absence deduction
        if ($this->absentDays > 0) {
            $amount = round($this->absentDays * $this->dailyWage, 2);
            $this->deductions[self::CODE_ABSENCE_DEDUCTION] = [
                'element_id' => $this->findElementId(self::CODE_ABSENCE_DEDUCTION),
                'amount' => $amount,
                'unit_count' => $this->absentDays,
                'unit_rate' => $this->dailyWage,
                'description' => __('Absence deduction (:days days × :rate/day)', [
                    'days' => $this->absentDays,
                    'rate' => number_format($this->dailyWage),
                ]),
            ];
        }
    }

    private function computeStatutoryDeductions(MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): void
    {
        $basePay = $this->earnings['base_salary']['amount'] ?? 0.0;

        $overtimeAmount = $this->earnings['overtime']['amount'] ?? 0.0;
        $fridayAmount = $this->earnings['friday']['amount'] ?? 0.0;
        $holidayAmount = $this->earnings['holiday']['amount'] ?? 0.0;
        $missionAmount = $this->earnings['mission']['amount'] ?? 0.0;

        $insuranceBase = $basePay + $this->housingAmount + $this->groceryAmount + $overtimeAmount + $fridayAmount + $holidayAmount + $missionAmount;

        $employeeInsurance = round($insuranceBase * self::EMPLOYEE_INSURANCE_RATE, 2);

        if ($employeeInsurance > 0) {
            $this->deductions['employee_insurance'] = [
                'element_id' => $this->findElementId('INSURANCE_EMP'),
                'amount' => $employeeInsurance,
                'unit_count' => null,
                'unit_rate' => self::EMPLOYEE_INSURANCE_RATE,
                'description' => __('Employee social insurance (7%)'),
            ];
        }

        // Tax base: total earnings − child allowance − employee insurance
        $totalEarnings = array_sum(array_column($this->earnings, 'amount'));
        $taxBase = max(0.0, $totalEarnings - $this->childAllowance - $employeeInsurance);
        $incomeTax = $this->calculateTax($taxBase, $attendance->year, $companyId);

        if ($incomeTax > 0) {
            $this->deductions['income_tax'] = [
                'element_id' => $this->findElementId('INCOME_TAX'),
                'amount' => $incomeTax,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => __('Income tax'),
            ];
        }

        // Store insurance values for the final result (employer insurance is not a deduction)
        $this->insuranceBase = $insuranceBase;
        $this->employeeInsurance = $employeeInsurance;
        $this->employerInsurance = round($insuranceBase * self::EMPLOYER_INSURANCE_RATE, 2);
        $this->taxBase = $taxBase;
        $this->incomeTax = $incomeTax;
    }

    private function computeCustomDeductions(SalaryDecree $decree): void
    {
        $skipCodes = ['INSURANCE_EMP', 'INSURANCE_EMP2', 'INCOME_TAX', self::CODE_UNDERTIME, self::CODE_ABSENCE_DEDUCTION];

        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if ($element === null || $element->category !== 'deduction') {
                continue;
            }

            if (in_array($element->system_code, $skipCodes, true)) {
                continue;
            }

            $this->deductions['deduction_'.$element->id] = [
                'element_id' => $element->id,
                'amount' => (float) $benefit->element_value,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Result builder
    // ─────────────────────────────────────────────────────────────────────

    private function buildResult(MonthlyAttendance $attendance, int $companyId): array
    {
        $totalEarnings = array_sum(array_column($this->earnings, 'amount'));
        $totalDeductions = array_sum(array_column($this->deductions, 'amount'));
        $netPayment = $totalEarnings - $totalDeductions;

        $items = $this->buildItemRows();

        return [
            'prorated_days' => $this->proratedDays,
            'daily_wage' => $this->dailyWage,
            'hourly_wage' => $this->hourlyWage,
            'earnings' => $this->earnings,
            'deductions' => $this->deductions,
            'gross_salary' => $totalEarnings,
            'insurance_base' => $this->insuranceBase,
            'employee_insurance' => $this->employeeInsurance,
            'employer_insurance' => $this->employerInsurance,
            'tax_base' => $this->taxBase,
            'income_tax' => $this->incomeTax,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_payment' => $netPayment,
            'items' => $items,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Shared calculation helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Unified method for any time-based wage calculation (overtime, undertime,
     * friday pay, holiday pay, mission pay). Adds the result to either
     * $this->earnings or $this->deductions based on category.
     */
    private function addWageBasedItem(int $minutes, string $type, string $systemCode, string $category = 'earning'): void
    {
        if ($minutes <= 0) {
            return;
        }

        $coeff = $this->resolveCoefficient($type);
        $hours = $minutes / 60;
        $amount = round($hours * $this->hourlyWage * $coeff, 2);

        $typeLabel = match ($type) {
            'overtime' => __('Overtime'),
            'undertime' => __('Undertime'),
            'friday' => __('Friday Premium'),
            'holiday' => __('Holiday Premium'),
            'mission' => __('Mission Pay'),
            default => __(ucfirst($type)),
        };

        $entry = [
            'element_id' => $this->findElementId($systemCode),
            'amount' => $amount,
            'unit_count' => $hours,
            'unit_rate' => round($this->hourlyWage * $coeff, 2),
            'description' => __(':label (:hours hrs × :rate × :coeff)', [
                'label' => $typeLabel,
                'hours' => number_format($hours, 2),
                'rate' => number_format($this->hourlyWage),
                'coeff' => $coeff,
            ]),
        ];

        if ($category === 'deduction') {
            $this->deductions[$type] = $entry;
        } else {
            $this->earnings[$type] = $entry;
        }
    }

    /**
     * Derive the hourly wage from the WorkShift's useful-hours definition.
     * Falls back to daily_wage / 8 when no shift is assigned.
     */
    private function resolveHourlyWage(): float
    {
        $shiftMinutes = $this->workShift?->duration ?? (8 * 60);

        if ($shiftMinutes <= 0) {
            $shiftMinutes = 8 * 60;
        }

        return round($this->dailyWage / ($shiftMinutes / 60), 4);
    }

    /**
     * Resolve a coefficient value from the WorkShift based on the coefficient type.
     */
    private function resolveCoefficient(string $type = 'overtime'): float
    {
        return match ($type) {
            'undertime' => (float) ($this->workShift?->undertime_coefficient ?? 2.0),
            'friday' => (float) ($this->workShift?->friday_coefficient ?? 1.5),
            'holiday' => (float) ($this->workShift?->holiday_coefficient ?? 2.0),
            'mission' => (float) ($this->workShift?->mission_coefficient ?? 1.25),
            'overtime' => (float) ($this->workShift?->overtime_coefficient ?? 1.4),
            default => 1.0,
        };
    }

    /**
     * Prorate a monthly allowance when calc_type = 'daily'.
     * A 'fixed' or 'percentage' allowance is returned as-is.
     */
    private function prorateAllowance(float $rawValue, string $calcType): float
    {
        if ($calcType === 'daily' && $this->workDays > 0) {
            return round($rawValue / $this->workDays * $this->proratedDays, 2);
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
            return round($this->fallbackTax($annualBase) / 12, 2);
        }

        return round($this->progressiveTax($annualBase, $slabs) / 12, 2);
    }

    /**
     * Apply Iranian progressive tax bands.
     * Each slab has income_from, income_to (null = unlimited), and tax_rate (%).
     * The first slab typically carries an annual_exemption threshold.
     */
    private function progressiveTax(float $annualBase, Collection $slabs): float
    {
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
    private function findElementId(string $systemCode): ?int
    {
        foreach ($this->benefits as $benefit) {
            if ($benefit->element?->system_code === $systemCode) {
                return $benefit->element->id;
            }
        }

        return null;
    }

    /**
     * Convert the earnings and deductions maps into the flat list that maps
     * directly to PayrollItem columns. Deductions have negative calculated_amount.
     */
    private function buildItemRows(): array
    {
        $rows = [];

        foreach ($this->earnings as $entry) {
            $rows[] = [
                'element_id' => $entry['element_id'],
                'calculated_amount' => $entry['amount'],
                'unit_count' => $entry['unit_count'] ?? null,
                'unit_rate' => $entry['unit_rate'] ?? null,
                'description' => $entry['description'],
            ];
        }

        foreach ($this->deductions as $entry) {
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
    private function persist(array $breakdown, MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): Payroll
    {
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
