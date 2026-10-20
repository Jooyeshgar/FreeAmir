<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PersonnelRequestType;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\MonthlyAttendance;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\SalaryDecree;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AncillaryCostService;
use App\Services\AttendanceService;
use App\Services\FiscalYearMutationGuard;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            'device_id' => 'DEV-001',
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

    public function test_payroll_creation_rejects_closed_target_year_when_an_open_year_is_active(): void
    {
        $openYear = FiscalYear::factory()->create([
            'company_id' => $this->closedYear->company_id,
            'year' => $this->closedYear->year + 1,
        ]);
        config(['active-fiscal-year-id' => $openYear->id]);

        // This test only needs fiscal-year ownership to verify validation, not persisted attendance or salary decree records.
        $attendance = new MonthlyAttendance(['fiscal_year_id' => $this->closedYear->id]);
        $decree = new SalaryDecree(['fiscal_year_id' => $this->closedYear->id]);

        try {
            app(PayrollService::class)->createFromAttendance($attendance, $decree, $this->closedYear->id);
            $this->fail('Payroll creation unexpectedly accepted a closed target year.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('This fiscal year is already closed.'),
                $exception->errors()['fiscal_year'][0]
            );
        }
    }

    public function test_payroll_creation_rejects_attendance_or_decree_from_another_year(): void
    {
        $targetYear = FiscalYear::factory()->create([
            'company_id' => $this->closedYear->company_id,
            'year' => $this->closedYear->year + 1,
        ]);

        $otherOpenYear = FiscalYear::factory()->create([
            'company_id' => $this->closedYear->company_id,
            'year' => $this->closedYear->year + 2,
        ]);

        config(['active-fiscal-year-id' => $targetYear->id]);

        // The test isolates the fiscal-year consistency validation without requiring unrelated database records.
        $attendance = new MonthlyAttendance(['fiscal_year_id' => $otherOpenYear->id]);
        $decree = new SalaryDecree(['fiscal_year_id' => $targetYear->id]);

        try {
            app(PayrollService::class)->createFromAttendance($attendance, $decree, $targetYear->id);
            $this->fail('Payroll creation unexpectedly accepted records from different fiscal years.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('Payroll attendance and salary decree must belong to the target fiscal year.'),
                $exception->errors()['fiscal_year'][0]
            );
        }
    }

    public function test_record_recalculations_reject_closed_owning_year_when_an_open_year_is_active(): void
    {
        $openYear = FiscalYear::factory()->create([
            'company_id' => $this->closedYear->company_id,
            'year' => $this->closedYear->year + 1,
        ]);
        config(['active-fiscal-year-id' => $openYear->id]);

        // Recalculation must reject records belonging to a closed year before attempting database mutations.
        foreach ([
            fn () => app(PayrollService::class)->recalculateTotals(new Payroll(['fiscal_year_id' => $this->closedYear->id])),
            fn () => app(AttendanceService::class)->recalculateLog(new AttendanceLog(['fiscal_year_id' => $this->closedYear->id])),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A record recalculation unexpectedly accepted a closed owning year.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    __('This fiscal year is already closed.'),
                    $exception->errors()['fiscal_year'][0]
                );
            }
        }
    }

    public function test_personnel_request_log_sync_rejects_closed_request_before_writing_when_an_open_year_is_active(): void
    {
        $openYear = FiscalYear::factory()->create([
            'company_id' => $this->fiscalYear->company_id,
            'year' => $this->fiscalYear->year + 1,
        ]);
        config(['active-fiscal-year-id' => $openYear->id]);

        $logDate = '2025-01-06';
        $request = PersonnelRequest::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
            'request_type' => PersonnelRequestType::LEAVE_WITHOUT_PAY_HOURLY,
            'start_date' => "$logDate 08:00:00",
            'end_date' => "$logDate 10:00:00",
        ]);

        try {
            app(AttendanceService::class)->syncPersonnelRequestLogs($request);
            $this->fail('Personnel-request log sync unexpectedly accepted a closed owning year.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('This fiscal year is already closed.'),
                $exception->errors()['fiscal_year'][0]
            );
        }

        $this->assertDatabaseMissing('attendance_logs', [
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
            'log_date' => $logDate,
        ]);
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

    public function test_closed_year_allows_read_only_attendance_import_preview(): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'attendance.attendance-logs.import.preview']));

        $file = UploadedFile::fake()->createWithContent('attendance.tsv', "DEV-001\t2025-01-06 08:00:00\tignored\t0\tignored\tignored\nDEV-001\t2025-01-06 17:00:00\tignored\t1\tignored\tignored\n");

        $this->post(route('attendance.attendance-logs.import.preview'), [
            'import_type' => 'device_tsv',
            'duplicate_mode' => 'ignore',
            'file' => $file,
        ])
            ->assertOk()
            ->assertSessionDoesntHaveErrors('fiscal_year');
    }
}
