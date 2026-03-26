<?php

namespace App\Services;

use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\SalaryDecree;
use App\Models\TaxSlab;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollService
{
    private const EMPLOYEE_INSURANCE_RATE = 0.07;

    private const EMPLOYER_INSURANCE_RATE = 0.20;

    private const CODE_CHILD = 'CHILD_ALLOWANCE';

    private const CODE_HOUSING = 'HOUSING_ALLOWANCE';

    private const CODE_FOOD = 'FOOD_ALLOWANCE';

    private const CODE_OVERTIME = 'OVERTIME';

    private const CODE_UNDERTIME = 'UNDERTIME';

    private const CODE_ABSENCE_DEDUCTION = 'ABSENCE_DEDUCTION';

    private const CODE_FRIDAY_PAY = 'FRIDAY_PAY';

    private const CODE_HOLIDAY_PAY = 'HOLIDAY_PAY';

    private const CODE_MISSION_PAY = 'MISSION_PAY';

    private float $dailyWage = 0.0;

    private float $hourlyWage = 0.0;

    private int $workDays = 1;

    private int $absentDays = 0;

    private int $proratedDays = 0;

    private mixed $workShift = null;

    private iterable $benefits = [];

    private Collection $elements;

    /** element_value from DecreeBenefit keyed by system_code */
    private array $benefitValues = [];

    private array $earnings = [];

    private array $deductions = [];

    private float $insuranceBase = 0.0;

    private float $insuranceRate = self::EMPLOYEE_INSURANCE_RATE;

    private float $taxBase = 0.0;

    private float $taxExemptions = 0.0;

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

        return $this->buildResult();
    }

    private function initState(MonthlyAttendance $attendance, SalaryDecree $decree): void
    {
        $this->dailyWage = (float) ($decree->daily_wage ?? 0);
        $this->workDays = max(1, (int) $attendance->work_days);
        $this->absentDays = (int) $attendance->absent_days;
        $this->proratedDays = max(0, $this->workDays - $this->absentDays);
        $this->workShift = $attendance->employee->workShift;
        $this->benefits = $decree->benefits;
        $this->hourlyWage = $this->resolveHourlyWage();

        $this->elements = $decree->benefits
            ->map(fn ($benefit) => $benefit->element)
            ->filter()
            ->keyBy('system_code');

        $this->benefitValues = $decree->benefits
            ->filter(fn ($benefit) => $benefit->element !== null)
            ->mapWithKeys(fn ($benefit) => [$benefit->element->system_code => (float) $benefit->element_value])
            ->all();

        $this->earnings = [];
        $this->deductions = [];
        $this->insuranceBase = 0.0;
        $this->taxBase = 0.0;
        $this->taxExemptions = 0.0;
    }

    private function computeBaseSalary(): void
    {
        $amount = $this->dailyWage * $this->proratedDays;

        $this->addEarning('base_salary', [
            'element_id' => null,
            'amount' => $amount,
            'unit_count' => $this->proratedDays,
            'unit_rate' => $this->dailyWage,
            'description' => __('Base salary (:days days × :rate/day)', [
                'days' => $this->proratedDays,
                'rate' => number_format($this->dailyWage),
            ]),
            'is_taxable' => true,
            'is_insurable' => true,
        ]);
    }

    private function computeDecreeBenefits(SalaryDecree $decree): void
    {
        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if (! $element || $element->category !== 'earning') {
                continue;
            }

            if (in_array($element->system_code, [
                self::CODE_OVERTIME, self::CODE_FRIDAY_PAY,
                self::CODE_HOLIDAY_PAY, self::CODE_MISSION_PAY,
            ], true)) {
                continue;
            }

            $raw = (float) $benefit->element_value;
            $amount = match ($element->system_code) {
                self::CODE_HOUSING, self::CODE_FOOD => $this->prorateAllowance($raw, $element->calc_type),
                self::CODE_CHILD => $this->proratedDays > 0
                    ? $raw * ($benefit->element->employee?->children_count ?? 0)
                    : 0.0,
                default => $raw,
            };

            $this->addEarning($element->system_code.'_'.$element->id, [
                'element_id' => $element->id,
                'amount' => $amount,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
                'is_taxable' => $element->is_taxable,
                'is_insurable' => $element->is_insurable,
            ]);
        }
    }

    private function computeDynamicEarnings(MonthlyAttendance $attendance): void
    {
        $this->addWageBasedEarning((int) ($attendance->overtime ?? 0), 'overtime', self::CODE_OVERTIME);
        $this->addWageBasedEarning((int) ($attendance->friday_hours ?? 0), 'friday', self::CODE_FRIDAY_PAY);
        $this->addWageBasedEarning((int) ($attendance->holiday_hours ?? 0), 'holiday', self::CODE_HOLIDAY_PAY);
        $this->addWageBasedEarning((int) ($attendance->mission_hours ?? 0), 'mission', self::CODE_MISSION_PAY);
    }

    private function computeDynamicDeductions(MonthlyAttendance $attendance): void
    {
        $this->addWageBasedDeduction((int) ($attendance->undertime ?? 0), 'undertime', self::CODE_UNDERTIME);

        if ($this->absentDays > 0) {
            $amount = $this->absentDays * $this->dailyWage;
            $element = $this->elements->get(self::CODE_ABSENCE_DEDUCTION);

            $this->addDeduction(self::CODE_ABSENCE_DEDUCTION, [
                'element_id' => $element?->id,
                'amount' => $amount,
                'unit_count' => $this->absentDays,
                'unit_rate' => $this->dailyWage,
                'description' => __('Absence deduction (:days days × :rate/day)', [
                    'days' => $this->absentDays,
                    'rate' => number_format($this->dailyWage),
                ]),
                'is_taxable' => $element?->is_taxable ?? true,
                'is_insurable' => $element?->is_insurable ?? true,
            ]);
        }
    }

    private function computeStatutoryDeductions(MonthlyAttendance $attendance, SalaryDecree $decree, int $companyId): void
    {
        $insuranceElement = $this->elements->get('INSURANCE_EMP');
        $this->insuranceRate = isset($this->benefitValues['INSURANCE_EMP']) ? (float) $this->benefitValues['INSURANCE_EMP'] / 100 : self::EMPLOYEE_INSURANCE_RATE;
        $employeeInsurance = round($this->insuranceBase * $this->insuranceRate, 2);

        if ($employeeInsurance > 0) {
            $this->addDeduction('employee_insurance', [
                'element_id' => $insuranceElement?->id,
                'amount' => $employeeInsurance,
                'unit_count' => null,
                'unit_rate' => $this->insuranceRate * 100,
                'description' => __('Employee social insurance'),
                'is_taxable' => $insuranceElement?->is_taxable ?? true,
                'is_insurable' => false,
            ]);
        }

        $totalEarnings = array_sum(array_column($this->earnings, 'amount'));
        $thisMonthTaxBase = max(0.0, $totalEarnings - $this->taxExemptions);

        $previousPayrolls = Payroll::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('employee_id', $attendance->employee_id)
            ->where('year', $attendance->year)
            ->where('month', '<', $attendance->month)
            ->whereIn('status', ['draft', 'approved', 'paid'])
            ->selectRaw('COALESCE(SUM(tax_base_amount), 0) as sum_tax_base')
            ->selectRaw('COALESCE(SUM(income_tax_amount), 0) as sum_income_tax')
            ->selectRaw('COUNT(*) as payroll_count')
            ->first();

        $prevCumulativeTaxBase = (float) ($previousPayrolls->sum_tax_base ?? 0);
        $prevCumulativeTaxPaid = (float) ($previousPayrolls->sum_income_tax ?? 0);
        $prevCount = (int) ($previousPayrolls->payroll_count ?? 0);

        $cumulativeTaxBase = $prevCumulativeTaxBase + $thisMonthTaxBase;
        $incomeTax = $this->calculateTax($cumulativeTaxBase, $prevCumulativeTaxPaid, $prevCount + 1);

        if ($incomeTax > 0) {
            $this->addDeduction('income_tax', [
                'element_id' => $this->findElementId('INCOME_TAX'),
                'amount' => $incomeTax,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => __('Income tax'),
                'is_taxable' => false,
                'is_insurable' => false,
            ]);
        }

        $this->taxBase = $thisMonthTaxBase;
    }

    private function computeCustomDeductions(SalaryDecree $decree): void
    {
        $skipCodes = ['INSURANCE_EMP', 'INSURANCE_EMP2', 'INCOME_TAX', self::CODE_UNDERTIME, self::CODE_ABSENCE_DEDUCTION];

        foreach ($decree->benefits as $benefit) {
            $element = $benefit->element;
            if (! $element || $element->category !== 'deduction' || in_array($element->system_code, $skipCodes, true)) {
                continue;
            }

            $this->addDeduction('deduction_'.$element->id, [
                'element_id' => $element->id,
                'amount' => (float) $benefit->element_value,
                'unit_count' => null,
                'unit_rate' => null,
                'description' => $element->title,
                'is_taxable' => $element->is_taxable,
                'is_insurable' => $element->is_insurable,
            ]);
        }
    }

    private function addEarning(string $key, array $data): void
    {
        $this->earnings[$key] = $data;

        if ($data['is_insurable'] ?? false) {
            $this->insuranceBase += $data['amount'];
        }

        if (! ($data['is_taxable'] ?? true)) {
            $this->taxBase += $data['amount'];
        }
    }

    private function addDeduction(string $key, array $data): void
    {
        $this->deductions[$key] = $data;

        if ($data['is_taxable'] ?? false) {
            $this->taxExemptions -= $data['amount'];
        }

        if ($data['is_insurable'] ?? false) {
            $this->insuranceBase -= $data['amount'];
        }
    }

    private function addWageBasedEarning(int $minutes, string $type, string $systemCode): void
    {
        if ($minutes <= 0) {
            return;
        }

        $element = $this->elements->get($systemCode);
        $coeff = $this->resolveCoefficient($type);
        $hours = $minutes / 60;
        $amount = round($hours * $this->hourlyWage * $coeff, 2);

        $typeLabel = match ($type) {
            'overtime' => __('Overtime'),
            'friday' => __('Friday Premium'),
            'holiday' => __('Holiday Premium'),
            'mission' => __('Mission Pay'),
            default => __(ucfirst($type)),
        };

        $this->addEarning($type, [
            'element_id' => $element?->id,
            'amount' => $amount,
            'unit_count' => $hours,
            'unit_rate' => round($this->hourlyWage * $coeff, 2),
            'description' => __(':label (:hours hrs × :rate × :coeff)', [
                'label' => $typeLabel,
                'hours' => number_format($hours, 2),
                'rate' => number_format($this->hourlyWage),
                'coeff' => $coeff,
            ]),
            'is_taxable' => $element?->is_taxable ?? true,
            'is_insurable' => $element?->is_insurable ?? true,
        ]);
    }

    private function addWageBasedDeduction(int $minutes, string $type, string $systemCode): void
    {
        if ($minutes <= 0) {
            return;
        }

        $element = $this->elements->get($systemCode);
        $coeff = $this->resolveCoefficient($type);
        $hours = $minutes / 60;
        $amount = round($hours * $this->hourlyWage * $coeff, 2);

        $this->addDeduction($type, [
            'element_id' => $element?->id,
            'amount' => $amount,
            'unit_count' => $hours,
            'unit_rate' => round($this->hourlyWage * $coeff, 2),
            'description' => __('Undertime (:hours hrs × :rate × :coeff)', [
                'hours' => number_format($hours, 2),
                'rate' => number_format($this->hourlyWage),
                'coeff' => $coeff,
            ]),
            'is_taxable' => $element?->is_taxable ?? false,
            'is_insurable' => $element?->is_insurable ?? false,
        ]);
    }

    private function resolveHourlyWage(): float
    {
        $shiftMinutes = $this->workShift?->duration ?? (8 * 60);

        return round($this->dailyWage / max(1, $shiftMinutes / 60), 4);
    }

    private function resolveCoefficient(string $type): float
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

    private function prorateAllowance(float $rawValue, string $calcType): float
    {
        if ($calcType === 'daily' && $this->workDays > 0) {
            return round($rawValue / $this->workDays * $this->proratedDays, 2);
        }

        return $rawValue;
    }

    private function calculateTax(float $cumulativeGross, float $cumulativeTaxPaid, int $monthNumber): float
    {
        if ($cumulativeGross <= 0) {
            return 0.0;
        }

        $slabs = TaxSlab::query()->orderByRaw('income_to IS NULL, income_to ASC')->get();

        if ($slabs->isEmpty()) {
            throw ValidationException::withMessages(['tax' => [__('Tax slabs are not configured.')]]);
        }

        $projectedAnnual = ($cumulativeGross / $monthNumber) * 12;
        $projectedAnnualTax = $this->progressiveTax($projectedAnnual, $slabs);
        $cumulativeTaxDue = ($projectedAnnualTax / 12) * $monthNumber;

        return round(max(0.0, $cumulativeTaxDue - $cumulativeTaxPaid));
    }

    private function progressiveTax(float $annualBase, Collection $slabs): float
    {
        $remaining = $annualBase;
        $tax = 0.0;
        $prevCeiling = 0.0;

        foreach ($slabs as $slab) {
            if ($remaining <= 0) {
                break;
            }

            $ceiling = $slab->income_to ?? PHP_FLOAT_MAX;
            $bandSize = $ceiling - $prevCeiling;

            if ($bandSize > 0) {
                $taxableInBand = min($remaining, $bandSize);
                $tax += $taxableInBand * ((float) $slab->tax_rate / 100);
                $remaining -= $taxableInBand;
            }

            $prevCeiling = $ceiling;
        }

        return $tax;
    }

    private function findElementId(string $systemCode): ?int
    {
        return $this->elements->get($systemCode)?->id;
    }

    private function buildResult(): array
    {
        $totalEarnings = array_sum(array_column($this->earnings, 'amount'));
        $totalDeductions = array_sum(array_column($this->deductions, 'amount'));
        $employerInsurance = round($this->insuranceBase * self::EMPLOYER_INSURANCE_RATE, 2);

        return [
            'prorated_days' => $this->proratedDays,
            'daily_wage' => $this->dailyWage,
            'hourly_wage' => $this->hourlyWage,
            'earnings' => $this->earnings,
            'deductions' => $this->deductions,
            'gross_salary' => $totalEarnings,
            'insurance_base' => $this->insuranceBase,
            'employee_insurance' => $this->deductions['employee_insurance']['amount'] ?? 0.0,
            'employer_insurance' => $employerInsurance,
            'tax_base' => $this->taxBase,
            'income_tax' => $this->deductions['income_tax']['amount'] ?? 0.0,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_payment' => $totalEarnings - $totalDeductions,
            'items' => $this->buildItemRows(),
        ];
    }

    private function buildItemRows(): array
    {
        $rows = [];

        foreach ($this->earnings as $entry) {
            $rows[] = [
                'element_id' => $entry['element_id'],
                'calculated_amount' => $entry['amount'],
                'unit_count' => $entry['unit_count'],
                'unit_rate' => $entry['unit_rate'],
                'description' => $entry['description'],
            ];
        }

        foreach ($this->deductions as $entry) {
            $rows[] = [
                'element_id' => $entry['element_id'],
                'calculated_amount' => -abs($entry['amount']),
                'unit_count' => $entry['unit_count'],
                'unit_rate' => $entry['unit_rate'],
                'description' => $entry['description'],
            ];
        }

        return $rows;
    }

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
            'tax_base_amount' => $breakdown['tax_base'],
            'income_tax_amount' => $breakdown['income_tax'],
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
