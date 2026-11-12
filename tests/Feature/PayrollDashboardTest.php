<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $fiscalYearId;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->fiscalYearId = $fiscalYear->id;

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);

        $this->employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'first_name' => 'Amir',
            'last_name' => 'Payroll',
            'code' => 'EMP-HR-1',
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYearId]);
    }

    public function test_payroll_dashboard_permission_can_view_dashboard(): void
    {
        $this->grant('salary.payrolls.dashboard');
        $this->makePayroll();

        $response = $this->get(route('salary.payrolls.dashboard', [
            'year' => 1405,
            'month' => 12,
        ]));

        $response->assertOk();
        $response->assertSee('میز کار حقوق و دستمزد', false);
        $response->assertSee('Amir Payroll', false);
        $response->assertSee('درآمد کل ناخالص', false);
    }

    public function test_dashboard_requires_payroll_dashboard_permission(): void
    {
        $this->makePayroll();

        $response = $this->get(route('salary.payrolls.dashboard', [
            'year' => 1405,
            'month' => 12,
        ]));

        $response->assertForbidden();
    }

    private function makePayroll(array $overrides = []): Payroll
    {
        return Payroll::withoutGlobalScopes()->create(array_merge([
            'fiscal_year_id' => $this->fiscalYearId,
            'employee_id' => $this->employee->id,
            'year' => 1405,
            'month' => 12,
            'total_earnings' => 250_000_000,
            'total_deductions' => 42_500_000,
            'net_payment' => 207_500_000,
            'employer_insurance' => 57_500_000,
            'tax_base_amount' => 210_000_000,
            'income_tax_amount' => 25_000_000,
            'status' => PayrollStatus::PendingManagerApproval,
        ], $overrides));
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission])
        );
    }
}
