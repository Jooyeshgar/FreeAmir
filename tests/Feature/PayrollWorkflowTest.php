<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $companyId;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
        ]);
    }

    public function test_draft_payroll_can_be_submitted_for_manager_approval_with_transition_permission(): void
    {
        $this->grant('salary.payrolls.transition.draft-to-pending-manager-approval');
        $payroll = $this->makePayroll(['status' => Payroll::STATUS_DRAFT]);

        $response = $this->patch(route('salary.payrolls.transition.draft-to-pending-manager-approval', $payroll), [
            'note' => 'Ready for review',
        ]);

        $response->assertRedirect(route('salary.payrolls.show', $payroll));
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_PENDING_MANAGER_APPROVAL,
        ]);
        $this->assertDatabaseHas('payroll_status_histories', [
            'payroll_id' => $payroll->id,
            'from_status' => Payroll::STATUS_DRAFT,
            'to_status' => Payroll::STATUS_PENDING_MANAGER_APPROVAL,
            'changed_by' => $this->user->id,
            'note' => 'Ready for review',
        ]);
    }

    public function test_pending_payroll_can_be_approved_with_approval_permission(): void
    {
        $this->grant('salary.payrolls.transition.pending-manager-approval-to-approved');
        $payroll = $this->makePayroll(['status' => Payroll::STATUS_PENDING_MANAGER_APPROVAL]);

        $response = $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll));

        $response->assertRedirect(route('salary.payrolls.show', $payroll));
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_APPROVED,
        ]);
    }

    public function test_general_payroll_wildcard_permission_is_not_enough_to_change_status(): void
    {
        $this->grant('salary.payrolls.*');
        $payroll = $this->makePayroll(['status' => Payroll::STATUS_PENDING_MANAGER_APPROVAL]);

        $response = $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll));

        $response->assertForbidden();
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_PENDING_MANAGER_APPROVAL,
        ]);
        $this->assertDatabaseMissing('payroll_status_histories', [
            'payroll_id' => $payroll->id,
            'to_status' => Payroll::STATUS_APPROVED,
        ]);
    }

    public function test_invalid_transition_is_rejected_even_with_transition_permission(): void
    {
        $this->grant('salary.payrolls.transition.pending-manager-approval-to-approved');
        $payroll = $this->makePayroll(['status' => Payroll::STATUS_DRAFT]);

        $response = $this->patch(route('salary.payrolls.transition.pending-manager-approval-to-approved', $payroll));

        $response->assertUnprocessable();
        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => Payroll::STATUS_DRAFT,
        ]);
    }

    private function makePayroll(array $overrides = []): Payroll
    {
        return Payroll::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'year' => 1405,
            'month' => 1,
            'total_earnings' => 10_000_000,
            'total_deductions' => 1_000_000,
            'net_payment' => 9_000_000,
            'employer_insurance' => 2_000_000,
            'tax_base_amount' => 9_000_000,
            'income_tax_amount' => 500_000,
            'status' => Payroll::STATUS_DRAFT,
        ], $overrides));
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => $permission])
        );
    }
}
