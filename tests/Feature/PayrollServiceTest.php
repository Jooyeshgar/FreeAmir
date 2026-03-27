<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DecreeBenefit;
use App\Models\Employee;
use App\Models\MonthlyAttendance;
use App\Models\OrgChart;
use App\Models\Payroll;
use App\Models\PayrollElement;
use App\Models\PayrollItem;
use App\Models\SalaryDecree;
use App\Models\TaxSlab;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * PayrollServiceTest
 *
 * Comprehensive tests for PayrollService::calculate() and ::createFromAttendance().
 *
 * Fixed daily wage used throughout: 600,000 (Tomans per day).
 * Work shift: 08:00–17:00 with 60 min break → 480 min/day → hourly wage = 75,000.
 * Standard month: 26 work days, 0 absent days → 26 prorated days.
 *
 * Gregorian dates are stored in the DB; Jalali dates are what the user sees.
 * We use convertToJalali() / formatDate() helpers just as in AttendanceLogTest.
 */
class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private const DAILY_WAGE = 600000.0;

    // shift: 08:00-17:00 with 60 min break → net 480 min → 8 h/day
    private const SHIFT_MINUTES = 480;

    private const HOURLY_WAGE = self::DAILY_WAGE / (self::SHIFT_MINUTES / 60); // 75000

    // insurance rates as in the service
    private const EMP_INS_RATE = 0.07;

    private const EMPLOYER_INS_RATE = 0.20;

    private PayrollService $service;

    private int $companyId;

    private Employee $employee;

    private WorkShift $shift;

    private OrgChart $orgChart;

    /** Payroll elements keyed by system_code */
    private array $elements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $user = User::factory()->create();
        $company->users()->attach($user);
        $user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'payrolls.*'])
        );
        $this->actingAs($user);
        request()->cookies->set('active-company-id', $this->companyId);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
        $this->orgChart = OrgChart::factory()->create(['company_id' => $this->companyId]);

        $this->shift = $this->makeShift(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $this->shift->id,
            'children_count' => 0,
        ]);

        $this->employee->load('workShift');
        $this->elements = $this->seedElements();
        $this->service = new PayrollService;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Create a standard 8-hour shift (08:00–17:00, 60 min break). */
    private function makeShift(array $overrides = []): WorkShift
    {
        return WorkShift::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'name' => 'Standard',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break' => 60,
            'float' => 0,
            'overtime_coefficient' => 1.4,
            'holiday_coefficient' => 1.5,
            'mission_coefficient' => 1.4,
            'undertime_coefficient' => 2.0,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Seed all system payroll elements needed by the service.
     * Returns them keyed by system_code.
     */
    private function seedElements(): array
    {
        $definitions = [
            ['system_code' => 'HOUSING_ALLOWANCE', 'category' => 'earning',   'calc_type' => 'fixed',   'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'FOOD_ALLOWANCE',    'category' => 'earning',   'calc_type' => 'fixed',   'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'CHILD_ALLOWANCE',   'category' => 'earning',   'calc_type' => 'fixed',   'is_taxable' => false, 'is_insurable' => false],
            ['system_code' => 'OVERTIME',          'category' => 'earning',   'calc_type' => 'formula', 'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'FRIDAY_PAY',        'category' => 'earning',   'calc_type' => 'formula', 'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'HOLIDAY_PAY',       'category' => 'earning',   'calc_type' => 'formula', 'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'MISSION_PAY',       'category' => 'earning',   'calc_type' => 'formula', 'is_taxable' => true,  'is_insurable' => true],
            ['system_code' => 'UNDERTIME',         'category' => 'deduction', 'calc_type' => 'formula', 'is_taxable' => false, 'is_insurable' => false],
            ['system_code' => 'ABSENCE_DEDUCTION', 'category' => 'deduction', 'calc_type' => 'formula', 'is_taxable' => false, 'is_insurable' => false],
            ['system_code' => 'INSURANCE_EMP',     'category' => 'deduction', 'calc_type' => 'formula', 'is_taxable' => false, 'is_insurable' => false],
            ['system_code' => 'INCOME_TAX',        'category' => 'deduction', 'calc_type' => 'formula', 'is_taxable' => false, 'is_insurable' => false],
        ];

        $keyed = [];
        foreach ($definitions as $def) {
            $element = PayrollElement::factory()->create(array_merge([
                'company_id' => $this->companyId,
                'title' => $def['system_code'],
            ], $def));
            $keyed[$def['system_code']] = $element;
        }

        return $keyed;
    }

    /**
     * Create tax slabs that cover any realistic gross amount.
     * Slabs (annual):
     *   0          – 500,000,000  → 10%
     *   500,000,000 – 1,000,000,000 → 15%
     *   1,000,000,000+ → 20%
     */
    private function seedTaxSlabs(): void
    {
        TaxSlab::factory()->create([
            'company_id' => $this->companyId,
            'income_to' => 500_000_000,
            'tax_rate' => 10,
        ]);
        TaxSlab::factory()->create([
            'company_id' => $this->companyId,
            'income_to' => 1_000_000_000,
            'tax_rate' => 15,
        ]);
        TaxSlab::factory()->create([
            'company_id' => $this->companyId,
            'income_to' => null,
            'tax_rate' => 20,
        ]);
    }

    /**
     * Build a SalaryDecree for the employee with no extra benefits.
     */
    private function makeDecree(array $overrides = []): SalaryDecree
    {
        return SalaryDecree::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'daily_wage' => self::DAILY_WAGE,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Build a MonthlyAttendance record with sensible defaults.
     * year/month are Jalali (1404 / 1 = Farvardin 1404 → ~March 2025).
     */
    private function makeAttendance(array $overrides = []): MonthlyAttendance
    {
        return MonthlyAttendance::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'year' => 1404,
            'month' => 1,
            'work_days' => 26,
            'present_days' => 26,
            'absent_days' => 0,
            'overtime' => 0,
            'undertime' => 0,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'friday' => 0,
            'holiday' => 0,
        ], $overrides));
    }

    /**
     * Attach a benefit element to a decree.
     */
    private function addBenefit(SalaryDecree $decree, string $systemCode, float $value): DecreeBenefit
    {
        return DecreeBenefit::factory()->create([
            'decree_id' => $decree->id,
            'element_id' => $this->elements[$systemCode]->id,
            'element_value' => $value,
        ]);
    }

    // -----------------------------------------------------------------------
    // 1. Base salary – no absences, no extras
    // -----------------------------------------------------------------------

    public function test_calculate_returns_correct_base_salary_with_no_absences(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $expectedBase = self::DAILY_WAGE * 26; // 15,600,000

        $this->assertEquals($expectedBase, $result['earnings']['base_salary']['amount']);
        $this->assertEquals($expectedBase, $result['gross_salary']);
    }

    // -----------------------------------------------------------------------
    // 2. Prorated base salary with absences
    // -----------------------------------------------------------------------

    public function test_calculate_prorates_base_salary_for_absent_days(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        // 26 work days, 2 absent
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 2]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $proratedDays = 24; // 26 - 2
        $expectedBase = self::DAILY_WAGE * $proratedDays;
        $expectedDeduction = self::DAILY_WAGE * 2;

        $this->assertEquals($expectedBase, $result['earnings']['base_salary']['amount']);
        $this->assertEquals($proratedDays, $result['prorated_days']);
        $this->assertArrayHasKey('ABSENCE_DEDUCTION', $result['deductions']);
        $this->assertEquals($expectedDeduction, $result['deductions']['ABSENCE_DEDUCTION']['amount']);
    }

    // -----------------------------------------------------------------------
    // 3. Fixed housing allowance (monthly – not prorated)
    // -----------------------------------------------------------------------

    public function test_calculate_includes_fixed_housing_allowance(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $housingAmount = 2_000_000.0;
        $this->addBenefit($decree, 'HOUSING_ALLOWANCE', $housingAmount);

        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        // system_code HOUSING_ALLOWANCE uses prorateAllowance; calc_type is 'fixed' so no proration
        $housingKey = 'HOUSING_ALLOWANCE_'.$this->elements['HOUSING_ALLOWANCE']->id;
        $this->assertArrayHasKey($housingKey, $result['earnings']);
        $this->assertEquals($housingAmount, $result['earnings'][$housingKey]['amount']);
    }

    // -----------------------------------------------------------------------
    // 4. Food allowance – daily calc_type gets prorated
    // -----------------------------------------------------------------------

    public function test_calculate_prorates_daily_food_allowance_on_absence(): void
    {
        $this->seedTaxSlabs();
        // Set food element to calc_type = 'daily' for proration
        $this->elements['FOOD_ALLOWANCE']->update(['calc_type' => 'daily']);

        $decree = $this->makeDecree();
        $monthlyFood = 2_600_000.0; // exactly 100,000/day over 26 days
        $this->addBenefit($decree, 'FOOD_ALLOWANCE', $monthlyFood);

        // 26 work days, 2 absent → 24 prorated
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 2]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $foodKey = 'FOOD_ALLOWANCE_'.$this->elements['FOOD_ALLOWANCE']->id;
        $expectedFood = round($monthlyFood / 26 * 24, 2); // 2,400,000

        $this->assertArrayHasKey($foodKey, $result['earnings']);
        $this->assertEquals($expectedFood, $result['earnings'][$foodKey]['amount']);
    }

    // -----------------------------------------------------------------------
    // 5. Overtime earnings
    // -----------------------------------------------------------------------

    public function test_calculate_computes_overtime_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $overtimeMinutes = 120; // 2 hours
        $attendance = $this->makeAttendance(['overtime' => $overtimeMinutes]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $hours = $overtimeMinutes / 60; // 2.0
        $coeff = 1.4;
        $expected = round($hours * self::HOURLY_WAGE * $coeff, 2);

        $this->assertArrayHasKey('overtime', $result['earnings']);
        $this->assertEquals($expected, $result['earnings']['overtime']['amount']);
        $this->assertEquals($hours, $result['earnings']['overtime']['unit_count']);
    }

    // -----------------------------------------------------------------------
    // 6. Friday premium earnings
    // -----------------------------------------------------------------------

    public function test_calculate_computes_friday_premium_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $fridayMinutes = 480; // 8 hours
        $attendance = $this->makeAttendance(['friday' => $fridayMinutes]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $hours = $fridayMinutes / 60;
        $coeff = 1.5;
        $expected = round($hours * self::HOURLY_WAGE * $coeff, 2);

        $this->assertArrayHasKey('friday', $result['earnings']);
        $this->assertEquals($expected, $result['earnings']['friday']['amount']);
    }

    // -----------------------------------------------------------------------
    // 7. Holiday premium earnings
    // -----------------------------------------------------------------------

    public function test_calculate_computes_holiday_premium_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $holidayMinutes = 240; // 4 hours
        $attendance = $this->makeAttendance(['holiday' => $holidayMinutes]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $hours = $holidayMinutes / 60;
        $coeff = 1.5;
        $expected = round($hours * self::HOURLY_WAGE * $coeff, 2);

        $this->assertArrayHasKey('holiday', $result['earnings']);
        $this->assertEquals($expected, $result['earnings']['holiday']['amount']);
    }

    // -----------------------------------------------------------------------
    // 8. Mission pay earnings
    // -----------------------------------------------------------------------

    public function test_calculate_computes_mission_pay_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $missionMinutes = 480;
        $attendance = $this->makeAttendance(['mission' => $missionMinutes]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $hours = $missionMinutes / 60;
        $coeff = 1.4;
        $expected = round($hours * self::HOURLY_WAGE * $coeff, 2);

        $this->assertArrayHasKey('mission', $result['earnings']);
        $this->assertEquals($expected, $result['earnings']['mission']['amount']);
    }

    // -----------------------------------------------------------------------
    // 9. Undertime deduction
    // -----------------------------------------------------------------------

    public function test_calculate_computes_undertime_deduction_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $undertimeMinutes = 60; // 1 hour late
        $attendance = $this->makeAttendance(['undertime' => $undertimeMinutes]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $hours = $undertimeMinutes / 60;
        $coeff = 2.0; // undertime_coefficient
        $expected = round($hours * self::HOURLY_WAGE * $coeff, 2);

        $this->assertArrayHasKey('undertime', $result['deductions']);
        $this->assertEquals($expected, $result['deductions']['undertime']['amount']);
    }

    // -----------------------------------------------------------------------
    // 10. Employee insurance (7%)
    // -----------------------------------------------------------------------

    public function test_calculate_deducts_employee_insurance_at_7_percent(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $expectedInsuranceBase = self::DAILY_WAGE * 26;
        $expectedInsurance = round($expectedInsuranceBase * self::EMP_INS_RATE, 2);

        $this->assertEquals($expectedInsuranceBase, $result['insurance_base']);
        $this->assertEquals($expectedInsurance, $result['employee_insurance']);
        $this->assertArrayHasKey('employee_insurance', $result['deductions']);
        $this->assertEquals($expectedInsurance, $result['deductions']['employee_insurance']['amount']);
    }

    // -----------------------------------------------------------------------
    // 11. Employer insurance (20%)
    // -----------------------------------------------------------------------

    public function test_calculate_returns_correct_employer_insurance(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $expectedEmployerInsurance = round($result['insurance_base'] * self::EMPLOYER_INS_RATE, 2);

        $this->assertEquals($expectedEmployerInsurance, $result['employer_insurance']);
    }

    // -----------------------------------------------------------------------
    // 12. Income tax requires configured slabs – throws when missing
    // -----------------------------------------------------------------------

    public function test_calculate_throws_when_tax_slabs_are_not_configured(): void
    {
        // No TaxSlab records → service should throw a ValidationException
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance();

        $this->expectException(ValidationException::class);

        $this->service->calculate($attendance, $decree, $this->companyId);
    }

    // -----------------------------------------------------------------------
    // 13. Income tax – first month of the year (no carry-over)
    // -----------------------------------------------------------------------

    public function test_calculate_computes_income_tax_for_first_month(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        // Full month, no absences
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0, 'month' => 1]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        // Gross = 26 × 600,000 = 15,600,000
        // thisMonthTaxBase = 15,600,000 (no exemptions for basic decree)
        // projectedAnnual = 15,600,000 * 12 = 187,200,000
        // progressive tax on 187,200,000 (below 500M slab at 10%) = 18,720,000
        // cumulativeTaxDue = (18,720,000 / 12) * 1 = 1,560,000
        // incomeTax this month = 1,560,000 - 0 (no previous) = 1,560,000

        $this->assertArrayHasKey('income_tax', $result['deductions']);
        $this->assertGreaterThan(0, $result['income_tax']);
        $this->assertEquals(round(1_560_000), $result['income_tax']);
    }

    // -----------------------------------------------------------------------
    // 14. Income tax – second month accumulates from first
    // -----------------------------------------------------------------------

    public function test_calculate_accumulates_income_tax_across_months(): void
    {
        $this->seedTaxSlabs();

        $decree = $this->makeDecree(['employee_id' => $this->employee->id]);
        $monthBase = self::DAILY_WAGE * 26; // 15,600,000

        // Persist a payroll for month 1 manually so month 2 picks it up
        Payroll::withoutGlobalScopes()->create([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'decree_id' => $decree->id,
            'monthly_attendance_id' => null,
            'year' => 1404,
            'month' => 1,
            'total_earnings' => $monthBase,
            'total_deductions' => 1_560_000,
            'net_payment' => $monthBase - 1_560_000,
            'employer_insurance' => 0,
            'tax_base_amount' => $monthBase,
            'income_tax_amount' => 1_560_000,
            'status' => 'approved',
        ]);

        $attendance2 = $this->makeAttendance(['month' => 2, 'work_days' => 26, 'absent_days' => 0]);
        $result = $this->service->calculate($attendance2, $decree, $this->companyId);

        // cumulativeTaxBase = 15,600,000 + 15,600,000 = 31,200,000
        // projectedAnnual = (31,200,000 / 2) * 12 = 187,200,000
        // projectedAnnualTax = 18,720,000
        // cumulativeTaxDue = (18,720,000 / 12) * 2 = 3,120,000
        // incomeTax this month = 3,120,000 - 1,560,000 = 1,560,000
        $this->assertEquals(round(1_560_000), $result['income_tax']);
    }

    // -----------------------------------------------------------------------
    // 15. Net payment calculation
    // -----------------------------------------------------------------------

    public function test_calculate_net_payment_equals_earnings_minus_deductions(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $this->addBenefit($decree, 'HOUSING_ALLOWANCE', 1_000_000);
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $expectedNet = $result['total_earnings'] - $result['total_deductions'];

        $this->assertEquals($expectedNet, $result['net_payment']);
        $this->assertGreaterThan(0, $result['net_payment']);
    }

    // -----------------------------------------------------------------------
    // 16. Result structure – required keys exist
    // -----------------------------------------------------------------------

    public function test_calculate_returns_all_expected_keys(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance();

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $requiredKeys = [
            'prorated_days', 'daily_wage', 'hourly_wage',
            'earnings', 'deductions', 'gross_salary',
            'insurance_base', 'employee_insurance', 'employer_insurance',
            'tax_base', 'income_tax',
            'total_earnings', 'total_deductions', 'net_payment',
            'items',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    // -----------------------------------------------------------------------
    // 17. Items array – earnings are positive, deductions are negative
    // -----------------------------------------------------------------------

    public function test_calculate_items_signs_are_correct(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 2, 'overtime' => 120]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($result['items'] as $item) {
            if ($item['calculated_amount'] > 0) {
                $positiveCount++;
            } elseif ($item['calculated_amount'] < 0) {
                $negativeCount++;
            }
        }

        $this->assertGreaterThan(0, $positiveCount, 'Expected at least one positive (earning) item');
        $this->assertGreaterThan(0, $negativeCount, 'Expected at least one negative (deduction) item');
    }

    // -----------------------------------------------------------------------
    // 18. createFromAttendance – persists Payroll and PayrollItems
    // -----------------------------------------------------------------------

    public function test_create_from_attendance_persists_payroll_record(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['year' => 1404, 'month' => 1]);

        $payroll = $this->service->createFromAttendance($attendance, $decree, $this->companyId);

        $this->assertInstanceOf(Payroll::class, $payroll);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'decree_id' => $decree->id,
            'monthly_attendance_id' => $attendance->id,
            'year' => 1404,
            'month' => 1,
            'status' => 'draft',
        ]);
    }

    // -----------------------------------------------------------------------
    // 19. createFromAttendance – persists PayrollItems with correct signs
    // -----------------------------------------------------------------------

    public function test_create_from_attendance_persists_payroll_items(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $this->addBenefit($decree, 'HOUSING_ALLOWANCE', 1_500_000);
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $payroll = $this->service->createFromAttendance($attendance, $decree, $this->companyId);

        $items = PayrollItem::where('payroll_id', $payroll->id)->get();

        $this->assertGreaterThan(0, $items->count());

        // At least one positive (earning) item
        $this->assertTrue($items->some(fn ($i) => $i->calculated_amount > 0));
        // At least one negative (deduction) item
        $this->assertTrue($items->some(fn ($i) => $i->calculated_amount < 0));
    }

    // -----------------------------------------------------------------------
    // 20. createFromAttendance – idempotent: re-run replaces old payroll
    // -----------------------------------------------------------------------

    public function test_create_from_attendance_replaces_existing_payroll_for_same_month(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['year' => 1404, 'month' => 3]);

        $first = $this->service->createFromAttendance($attendance, $decree, $this->companyId);
        $second = $this->service->createFromAttendance($attendance, $decree, $this->companyId);

        // Only the latest payroll should exist for this employee/year/month
        $count = Payroll::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('employee_id', $this->employee->id)
            ->where('year', 1404)
            ->where('month', 3)
            ->count();

        $this->assertEquals(1, $count);
        $this->assertNotEquals($first->id, $second->id);

        // Old payroll items must be gone
        $this->assertDatabaseMissing('payrolls', ['id' => $first->id]);
    }

    // -----------------------------------------------------------------------
    // 21. createFromAttendance – net payment stored in DB matches calculation
    // -----------------------------------------------------------------------

    public function test_create_from_attendance_stores_correct_net_payment(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $breakdown = $this->service->calculate($attendance, $decree, $this->companyId);
        $payroll = $this->service->createFromAttendance($attendance, $decree, $this->companyId);

        $this->assertEquals(
            round($breakdown['net_payment'], 2),
            round((float) $payroll->net_payment, 2)
        );
    }

    // -----------------------------------------------------------------------
    // 22. Custom (decree-level) deduction benefit is applied
    // -----------------------------------------------------------------------

    public function test_calculate_applies_custom_deduction_benefit(): void
    {
        $this->seedTaxSlabs();
        // Create a custom deduction element (not a system-reserved code)
        $customDeduction = PayrollElement::factory()->create([
            'company_id' => $this->companyId,
            'system_code' => 'LOAN_REPAYMENT',
            'category' => 'deduction',
            'calc_type' => 'fixed',
            'is_taxable' => false,
            'is_insurable' => false,
        ]);

        $decree = $this->makeDecree();
        DecreeBenefit::factory()->create([
            'decree_id' => $decree->id,
            'element_id' => $customDeduction->id,
            'element_value' => 500_000,
        ]);

        $attendance = $this->makeAttendance();

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $this->assertArrayHasKey('deduction_'.$customDeduction->id, $result['deductions']);
        $this->assertEquals(500_000, $result['deductions']['deduction_'.$customDeduction->id]['amount']);
    }

    // -----------------------------------------------------------------------
    // 23. Hourly wage is derived from shift duration
    // -----------------------------------------------------------------------

    public function test_calculate_hourly_wage_uses_shift_duration(): void
    {
        $this->seedTaxSlabs();
        // Shift: 08:00–16:00, 0 break → 480 min / 8 h → hourly = 600,000/8 = 75,000
        $shift6h = $this->makeShift([
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'break' => 0,
        ]);

        $employee6h = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => WorkSite::factory()->create(['company_id' => $this->companyId])->id,
            'work_shift_id' => $shift6h->id,
        ]);

        $decree6h = SalaryDecree::factory()->create([
            'company_id' => $this->companyId,
            'employee_id' => $employee6h->id,
            'daily_wage' => self::DAILY_WAGE,
            'is_active' => true,
        ]);

        $attendance6h = MonthlyAttendance::factory()->create([
            'company_id' => $this->companyId,
            'employee_id' => $employee6h->id,
            'year' => 1404,
            'month' => 1,
            'work_days' => 26,
            'absent_days' => 0,
            'overtime' => 0,
            'undertime' => 0,
            'mission' => 0,
            'paid_leave' => 0,
            'unpaid_leave' => 0,
            'friday' => 0,
            'holiday' => 0,
        ]);

        $result = $this->service->calculate($attendance6h, $decree6h, $this->companyId);

        // 6-hour shift: 360 min → hourly = 600,000 / 6 = 100,000
        $expectedHourly = round(self::DAILY_WAGE / 6, 4);
        $this->assertEquals($expectedHourly, $result['hourly_wage']);
    }

    // -----------------------------------------------------------------------
    // 24. Zero daily wage produces zero net payment (no negative net)
    // -----------------------------------------------------------------------

    public function test_calculate_with_zero_daily_wage_produces_zero_earnings(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree(['daily_wage' => 0]);
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $this->assertEquals(0.0, $result['gross_salary']);
        $this->assertEquals(0.0, $result['employee_insurance']);
        $this->assertEquals(0.0, $result['income_tax']);
        $this->assertEquals(0.0, $result['net_payment']);
    }

    // -----------------------------------------------------------------------
    // 25. Insurance base excludes non-insurable benefits
    // -----------------------------------------------------------------------

    public function test_insurance_base_excludes_non_insurable_benefits(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        // CHILD_ALLOWANCE is_insurable = false in seedElements()
        $this->elements['CHILD_ALLOWANCE']->update(['is_insurable' => false]);
        $this->addBenefit($decree, 'CHILD_ALLOWANCE', 500_000);

        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $baseSalaryOnly = self::DAILY_WAGE * 26;

        // Insurance base should only include base salary (child allowance is not insurable)
        $this->assertEquals($baseSalaryOnly, $result['insurance_base']);
    }

    // -----------------------------------------------------------------------
    // 26. Progressive tax bracket boundary (gross above first slab)
    // -----------------------------------------------------------------------

    public function test_calculate_applies_progressive_tax_across_brackets(): void
    {
        $this->seedTaxSlabs();
        // Use a very high daily wage so the projected annual income crosses the first slab
        $highDecree = $this->makeDecree(['daily_wage' => 50_000_000]);
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 0]);

        $result = $this->service->calculate($attendance, $highDecree, $this->companyId);

        // With daily_wage = 50M, monthly = 50M × 26 = 1,300,000,000
        // projected annual = 1,300,000,000 × 12 = 15,600,000,000 → clearly above all slabs
        // Tax must be positive and substantial
        $this->assertGreaterThan(0, $result['income_tax']);

        // Verify the overall tax rate on projected annual is > 10% (first slab rate)
        // because it crosses into higher brackets
        $monthlyTax = $result['income_tax'];
        $annualTax = $monthlyTax * 12;
        $monthlyGross = $result['total_earnings'];
        $annualGross = $monthlyGross * 12;

        $effectiveRate = $annualTax / $annualGross;
        $this->assertGreaterThan(0.10, $effectiveRate);
    }

    // -----------------------------------------------------------------------
    // 27. Payroll description contains the Jalali month name
    // -----------------------------------------------------------------------

    public function test_payroll_description_contains_jalali_month_name(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        // Month 6 = Shahrivar (شهریور) in Jalali
        $attendance = $this->makeAttendance(['year' => 1404, 'month' => 6]);

        $payroll = $this->service->createFromAttendance($attendance, $decree, $this->companyId);

        $jalaliMonthName = MonthlyAttendance::MONTH_NAMES[6]; // شهریور

        $this->assertStringContainsString($jalaliMonthName, $payroll->description);
        $this->assertStringContainsString('1404', $payroll->description);
    }

    // -----------------------------------------------------------------------
    // 28. Absence deduction unit_count and unit_rate are stored correctly
    // -----------------------------------------------------------------------

    public function test_absence_deduction_has_correct_unit_count_and_rate(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['work_days' => 26, 'absent_days' => 3]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $absence = $result['deductions']['ABSENCE_DEDUCTION'];

        $this->assertEquals(3, $absence['unit_count']);
        $this->assertEquals(self::DAILY_WAGE, $absence['unit_rate']);
        $this->assertEquals(self::DAILY_WAGE * 3, $absence['amount']);
    }

    // -----------------------------------------------------------------------
    // 29. Overtime element_id is linked to the correct PayrollElement
    // -----------------------------------------------------------------------

    public function test_overtime_earning_is_linked_to_overtime_element(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $attendance = $this->makeAttendance(['overtime' => 60]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $overtimeElementId = $this->elements['OVERTIME']->id;

        $this->assertEquals($overtimeElementId, $result['earnings']['overtime']['element_id']);
    }

    // -----------------------------------------------------------------------
    // 30. Multiple extras combine correctly in gross and net
    // -----------------------------------------------------------------------

    public function test_calculate_combines_multiple_extras_correctly(): void
    {
        $this->seedTaxSlabs();
        $decree = $this->makeDecree();
        $this->addBenefit($decree, 'HOUSING_ALLOWANCE', 1_000_000);
        $this->addBenefit($decree, 'FOOD_ALLOWANCE', 500_000);

        $attendance = $this->makeAttendance([
            'work_days' => 26,
            'absent_days' => 1,
            'overtime' => 120,  // 2 h × 75,000 × 1.4 = 210,000
            'undertime' => 60,  // 1 h × 75,000 × 2.0 = 150,000
        ]);

        $result = $this->service->calculate($attendance, $decree, $this->companyId);

        $baseSalary = self::DAILY_WAGE * 25;    // 25 prorated days
        $absenceDeduction = self::DAILY_WAGE * 1;
        $overtime = round(2 * self::HOURLY_WAGE * 1.4, 2);
        $undertime = round(1 * self::HOURLY_WAGE * 2.0, 2);

        // Just verify gross contains base + overtime (housing/food are also included)
        $this->assertEqualsWithDelta(
            $baseSalary + $overtime + 1_000_000 + 500_000,
            $result['total_earnings'],
            1.0,
            'Total earnings do not match expected sum'
        );

        // Verify deductions contain absence and undertime (plus statutory)
        $this->assertArrayHasKey('ABSENCE_DEDUCTION', $result['deductions']);
        $this->assertArrayHasKey('undertime', $result['deductions']);
        $this->assertEquals($absenceDeduction, $result['deductions']['ABSENCE_DEDUCTION']['amount']);
        $this->assertEquals($undertime, $result['deductions']['undertime']['amount']);
    }
}
