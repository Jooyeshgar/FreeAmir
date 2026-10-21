<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\FiscalYearMutationGuard;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\MoadianService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClosedFiscalYearMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $closedYear;

    private Invoice $invoice;

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
    }

    public function test_guard_rejects_a_closed_invoice_year(): void
    {
        $this->expectException(ValidationException::class);
        app(FiscalYearMutationGuard::class)->ensureOpen($this->invoice);
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
