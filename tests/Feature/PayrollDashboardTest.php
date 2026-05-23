<?php

namespace Tests\Feature;

use App\Enums\PayrollStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $companyId;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'first_name' => 'Amir',
            'last_name' => 'Payroll',
            'code' => 'EMP-HR-1',
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
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
            'company_id' => $this->companyId,
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
