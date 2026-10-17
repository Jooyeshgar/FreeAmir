<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AncillaryCostService;
use App\Services\FiscalYearMutationGuard;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ClosedFiscalYearMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $closedYear;

    private FiscalYear $fiscalYear;

    private Invoice $invoice;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->closedYear = FiscalYear::factory()->create([
            'company_id' => $company->id,
            'closed_at' => now(),
        ]);
        Cache::forever('active_fiscal_year_id', $this->closedYear->id);

        $this->invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'fiscal_year_id' => $this->closedYear->id,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'date' => now()->toDateString(),
            'number' => 1,
            'amount' => 100,
            'vat' => 10,
        ]));

        $this->fiscalYear = FiscalYear::factory()->create(['closed_at' => now()]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);

        foreach (['hr.employees.*', 'hr.organization-units.*', 'salary.payroll-elements.*'] as $permission) {
            $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
        }

        $workSite = WorkSite::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $workShift = WorkShift::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $this->employee = Employee::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'work_site_id' => $workSite->id,
            'work_shift_id' => $workShift->id,
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-fiscal-year-id' => $this->fiscalYear->id]);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
    }

    public function test_guard_rejects_a_closed_invoice_year(): void
    {
        $this->expectException(ValidationException::class);
        app(FiscalYearMutationGuard::class)->ensureOpen();
    }

    public function test_nested_invoice_mutations_are_guarded_before_writing(): void
    {
        $user = User::factory()->create();

        foreach ([
            fn () => app(PaymentService::class)->createPayment($user, $this->invoice, ['amount' => 1, 'subject_id' => 1]),
            fn () => InvoiceService::deleteInvoice($this->invoice->id),
            fn () => AncillaryCostService::createAncillaryCost($user, ['fiscal_year_id' => $this->closedYear->id, 'invoice_id' => $this->invoice->id]),
            fn () => app(MoadianService::class)->moadianStatus('reference', $this->invoice),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A closed-year mutation unexpectedly completed.');
            } catch (ValidationException $exception) {
                $this->assertSame(__('This fiscal year is already closed.'), $exception->errors()['fiscal_year'][0]);
            }
        }

        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
    }

    public function test_closed_year_rejects_employee_edit_get_and_mutations(): void
    {
        $message = __('This fiscal year is already closed.');

        $this->get(route('hr.employees.edit', $this->employee))
            ->assertSessionHasErrors('fiscal_year', $message);

        $this->post(route('hr.employees.store'), [])
            ->assertSessionHasErrors('fiscal_year', $message);

        $this->put(route('hr.employees.update', $this->employee), [])
            ->assertSessionHasErrors('fiscal_year', $message);

        $this->delete(route('hr.employees.destroy', $this->employee))
            ->assertSessionHasErrors('fiscal_year', $message);

        $this->assertDatabaseHas('employees', ['id' => $this->employee->id]);
    }

    public function test_closed_year_rejects_organization_and_payroll_element_creation(): void
    {
        $message = __('This fiscal year is already closed.');

        $this->post(route('hr.organization-units.store'), [])
            ->assertSessionHasErrors('fiscal_year', $message);
        $this->post(route('salary.payroll-elements.store'), [])
            ->assertSessionHasErrors('fiscal_year', $message);

        $this->assertDatabaseCount('organization_units', 0);
        $this->assertDatabaseCount('payroll_elements', 0);
    }

    public function test_closed_year_keeps_history_readable_and_disables_employee_controls(): void
    {
        $this->get(route('hr.employees.index'))
            ->assertOk()
            ->assertSee('disabled', false)
            ->assertSee(__('This fiscal year is already closed.'))
            ->assertSee($this->employee->last_name);
    }
}
