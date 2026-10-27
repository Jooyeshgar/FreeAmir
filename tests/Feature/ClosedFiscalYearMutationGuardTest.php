<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PayrollStatus;
use App\Enums\PersonnelRequestType;
use App\Enums\ThursdayStatus;
use App\Models\AncillaryCost;
use App\Models\AttendanceLog;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\MonthlyAttendance;
use App\Models\OrganizationUnit;
use App\Models\OrgChart;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\PersonnelRequest;
use App\Models\SalaryDecree;
use App\Models\User;
use App\Models\WorkShift;
use App\Models\WorkSite;
use App\Services\AncillaryCostService;
use App\Services\AttendanceService;
use App\Services\FiscalYearMutationGuard;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_closed_year_action_links_are_inert_and_explain_why_they_are_disabled(): void
    {
        $createUrl = route('hr.organization-units.create');

        $response = $this->get(route('hr.organization-units.index'));

        $response->assertOk()
            ->assertDontSee('href="'.$createUrl.'"', false)
            ->assertSee('role="link" aria-disabled="true" tabindex="-1"', false)
            ->assertSee('title="'.__('This fiscal year is already closed.').'"', false)
            ->assertSee(__('This fiscal year is already closed.'));
    }

    public function test_list_controllers_supply_the_active_year_closure_state(): void
    {
        // Explicitly set the enum because the legacy schema default is still a name.
        $this->employee->workShift->update(['thursday_status' => ThursdayStatus::FULL_DAY]);
        $routes = [
            'hr.employees.index', 'hr.organization-units.index', 'hr.org-charts.index',
            'hr.personnel-requests.index', 'attendance.attendance-logs.index',
            'attendance.monthly-attendances.index', 'attendance.work-shifts.index',
            'salary.salary-decrees.index', 'salary.payroll-elements.index',
            'salary.tax-slabs.index', 'salary.public-holidays.index',
            'salary.work-sites.index', 'salary.work-site-contracts.index',
        ];

        foreach ($routes as $route) {
            $this->user->givePermissionTo(Permission::findOrCreate(preg_replace('/\.[^.]+$/', '.*', $route)));
        }

        foreach ([true, false] as $closed) {
            $this->fiscalYear->update(['closed_at' => $closed ? now() : null]);

            foreach ($routes as $route) {
                $response = $this->get(route($route))->assertOk()
                    ->assertViewHas('fiscalYearClosed', $closed);

                if ($closed) {
                    $response->assertSee(__('This fiscal year is already closed.'));
                } else {
                    $response->assertDontSee('aria-disabled="true"', false);
                }
            }
        }
    }

    public function test_detail_and_portal_controllers_supply_the_active_year_closure_state(): void
    {
        $this->employee->workShift->update(['thursday_status' => ThursdayStatus::FULL_DAY]);
        $this->employee->update(['user_id' => $this->user->id]);
        $this->user->unsetRelation('employee');
        foreach (['attendance.attendance-logs.*', 'attendance.monthly-attendances.*', 'salary.payrolls.*', 'salary.payroll-items.*', 'employee-portal.*'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission));
        }

        $attendance = MonthlyAttendance::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
            'duration' => 30,
        ]);
        $log = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id,
        ]);
        $decree = SalaryDecree::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
        ]);
        $payroll = Payroll::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id,
            'decree_id' => $decree->id,
            'status' => PayrollStatus::Draft,
        ]);
        $item = $payroll->items()->create(['calculated_amount' => 100, 'description' => 'Test item']);
        $pages = [
            route('attendance.attendance-logs.show', $log),
            route('attendance.monthly-attendances.show', $attendance),
            route('salary.payrolls.show', $payroll),
            route('employee-portal.employee.show'),
            route('employee-portal.personnel-requests.index'),
            route('employee-portal.monthly-attendances.show', $attendance),
            route('employee-portal.payrolls.show', $payroll),
        ];

        foreach ([true, false] as $closed) {
            $this->fiscalYear->update(['closed_at' => $closed ? now() : null]);

            foreach ($pages as $page) {
                $this->get($page)->assertOk()->assertViewHas('fiscalYearClosed', $closed);
            }
        }

        $this->get(route('salary.payroll-items.edit', $item))->assertOk()
            ->assertViewHas('fiscalYearClosed', false);
    }

    public function test_historical_detail_mutation_controls_reflect_year_closure(): void
    {
        $this->employee->workShift->update(['thursday_status' => ThursdayStatus::FULL_DAY]);
        foreach (['hr.org-charts.*', 'attendance.attendance-logs.*', 'attendance.monthly-attendances.*'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission));
        }
        $unit = OrganizationUnit::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $root = OrgChart::factory()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $child = OrgChart::factory()->create(['fiscal_year_id' => $this->fiscalYear->id, 'parent_id' => $root->id]);
        $attendance = MonthlyAttendance::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id, 'employee_id' => $this->employee->id,
            'start_date' => '2025-01-06', 'duration' => 1,
        ]);
        $log = AttendanceLog::factory()->create([
            'fiscal_year_id' => $this->fiscalYear->id, 'employee_id' => $this->employee->id,
            'monthly_attendance_id' => $attendance->id, 'log_date' => '2025-01-06',
        ]);
        $pages = [
            [route('hr.employees.show', $this->employee), [route('hr.employees.edit', $this->employee), route('hr.employees.edit', $this->employee)], []],
            [route('hr.organization-units.show', $unit), [route('hr.organization-units.edit', $unit)], []],
            [route('hr.org-charts.show', $root), [
                route('hr.org-charts.create', ['parent_id' => $root->id]), route('hr.org-charts.edit', $root),
                route('hr.org-charts.create', ['parent_id' => $child->id]), route('hr.org-charts.edit', $child),
            ], [route('hr.org-charts.destroy', $root), route('hr.org-charts.destroy', $child)]],
            [route('attendance.monthly-attendances.show', $attendance), [
                route('attendance.monthly-attendances.edit', $attendance), route('attendance.attendance-logs.edit', $log), route('salary.salary-decrees.create', ['employee' => $this->employee]),
            ], [route('attendance.attendance-logs.recalculate', $log)]],
        ];

        foreach ([true, false] as $closed) {
            $this->fiscalYear->update(['closed_at' => $closed ? now() : null]);
            foreach ($pages as [$url, $links, $forms]) {
                $response = $this->get($url)->assertOk()->assertViewHas('fiscalYearClosed', $closed);
                $document = new \DOMDocument;
                @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
                $xpath = new \DOMXPath($document);
                foreach (array_count_values($links) as $link => $count) {
                    $this->assertSame($closed ? 0 : $count, $xpath->query('//a[@href="'.$link.'"]')->length, $link);
                }
                foreach ($forms as $action) {
                    $buttons = $xpath->query('//form[@action="'.$action.'"]//button[@type="submit"]');
                    $this->assertCount(1, $buttons, $action);
                    $this->assertSame($closed, $buttons->item(0)->hasAttribute('disabled'), $action);
                    if ($closed) {
                        $this->assertSame(__('This fiscal year is already closed.'), $buttons->item(0)->getAttribute('title'));
                    }
                }
                $disabledLinks = $xpath->query('//a[@aria-disabled="true"]');
                $this->assertCount($closed ? count($links) : 0, $disabledLinks, $url);
                foreach ($disabledLinks as $link) {
                    $this->assertFalse($link->hasAttribute('href'));
                    $this->assertSame('-1', $link->getAttribute('tabindex'));
                    $this->assertSame(__('This fiscal year is already closed.'), $link->getAttribute('title'));
                }
            }
        }
    }

    public function test_delete_payment_rejects_its_closed_owning_year(): void
    {
        $this->eachPaymentOperationRejectsItsClosedOwningYear('deletePayment');
    }

    public function test_create_payment_document_rejects_its_closed_owning_year(): void
    {
        $this->eachPaymentOperationRejectsItsClosedOwningYear('createPaymentDocument');
    }

    public function test_remove_payment_document_rejects_its_closed_owning_year(): void
    {
        $this->eachPaymentOperationRejectsItsClosedOwningYear('removePaymentDocument');
    }

    private function eachPaymentOperationRejectsItsClosedOwningYear(string $operation): void
    {
        $payment = Payment::create([
            'invoice_id' => $this->invoice->id, 'amount' => 10,
            'creator_id' => $this->user->id, 'date' => now()->toDateString(),
        ]);
        $openYear = FiscalYear::factory()->create(['company_id' => $this->closedYear->company_id, 'year' => $this->closedYear->year + 1]);
        config(['active-fiscal-year-id' => $openYear->id]);
        $payment = Payment::findOrFail($payment->id);
        $this->assertNull($payment->invoice);

        try {
            $service = app(PaymentService::class);
            if ($operation === 'createPaymentDocument') {
                $service->$operation($this->user, $payment);
            } else {
                $service->$operation($payment);
            }
            $this->fail('Closed-year payment operation unexpectedly completed.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('This fiscal year is already closed.'), $exception->errors()['fiscal_year'][0]);
        }
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'invoice_id' => $this->invoice->id, 'document_id' => null]);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('transactions', 0);

        $this->closedYear->update(['closed_at' => null]);
        $this->assertSame($this->closedYear->id, app(FiscalYearMutationGuard::class)->ensureOpen($payment)->id);
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

    public function test_closed_records_cannot_be_mutated_while_an_open_year_is_selected(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create(['invoice_id' => $this->invoice->id, 'amount' => 10, 'creator_id' => $user->id, 'date' => now()->toDateString()]);
        $cost = AncillaryCost::create([
            'invoice_id' => $this->invoice->id, 'fiscal_year_id' => $this->closedYear->id,
            'type' => 1, 'amount' => 10, 'date' => now()->toDateString(),
            'customer_id' => Customer::factory()->create()->id,
            'status' => InvoiceStatus::UNAPPROVED,
        ]);
        $document = new Document(['fiscal_year_id' => $this->closedYear->id]);
        $cheque = new Cheque;
        $cheque->fiscal_year_id = $this->closedYear->id;
        $openYear = FiscalYear::factory()->create(['company_id' => $this->closedYear->company_id, 'year' => $this->closedYear->year + 1]);
        config(['active-fiscal-year-id' => $openYear->id]);
        Http::preventStrayRequests();

        $mutations = [
            'invoice update' => fn () => InvoiceService::updateInvoice($this->invoice->id, []),
            'invoice delete' => fn () => InvoiceService::deleteInvoice($this->invoice->id),
            'invoice status' => fn () => app(InvoiceService::class)->changeInvoiceStatus($this->invoice, 'ready_to_approve'),
            'invoice void' => fn () => app(InvoiceService::class)->voidInvoice($this->invoice, $user, now()->toDateString(), 2),
            'group inactivation' => fn () => app(GroupActionService::class)->inactivateDependentInvoices($this->invoice),
            'payment create' => fn () => app(PaymentService::class)->createPayment($user, $this->invoice, []),
            'payment delete' => fn () => app(PaymentService::class)->deletePayment($payment),
            'payment document create' => fn () => app(PaymentService::class)->createPaymentDocument($user, $payment),
            'payment document remove' => fn () => app(PaymentService::class)->removePaymentDocument($payment),
            'cheque payment' => fn () => app(PaymentService::class)->saveChequePayment($user, $this->invoice, $cheque, $document, 1),
            'standalone cheque payment' => fn () => app(PaymentService::class)->saveChequePayment($user, null, $cheque, $document, 1),
            'ancillary create' => fn () => AncillaryCostService::createAncillaryCost($user, ['invoice_id' => $this->invoice->id, 'fiscal_year_id' => $openYear->id]),
            'ancillary update' => fn () => AncillaryCostService::updateAncillaryCost($user, $cost, []),
            'ancillary delete' => fn () => AncillaryCostService::deleteAncillaryCost($cost),
            'ancillary status' => fn () => app(AncillaryCostService::class)->changeAncillaryCostStatus($cost, 'approve'),
            'Moadian submission' => fn () => app(MoadianService::class)->sendInvoice($this->invoice),
            'Moadian status persistence' => fn () => app(MoadianService::class)->moadianStatus('reference', $this->invoice),
        ];

        foreach ($mutations as $name => $mutation) {
            try {
                $mutation();
                $this->fail($name.' unexpectedly completed.');
            } catch (ValidationException $exception) {
                $this->assertSame(__('This fiscal year is already closed.'), $exception->errors()['fiscal_year'][0], $name);
            }
        }

        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id, 'status' => InvoiceStatus::APPROVED->value]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ancillary_costs', 1);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('transactions', 0);
        Http::assertNothingSent();
    }

    public function test_open_invoice_status_can_change_while_a_closed_year_is_selected(): void
    {
        $openYear = FiscalYear::factory()->create(['company_id' => $this->closedYear->company_id, 'year' => $this->closedYear->year + 1]);
        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'fiscal_year_id' => $openYear->id, 'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PRE_INVOICE, 'date' => now()->toDateString(),
            'number' => 2, 'amount' => 100, 'vat' => 0,
        ]));
        config(['active-fiscal-year-id' => $this->closedYear->id]);

        app(InvoiceService::class)->changeInvoiceStatus($invoice, 'ready_to_approve');

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => InvoiceStatus::READY_TO_APPROVE->value]);
    }
}
