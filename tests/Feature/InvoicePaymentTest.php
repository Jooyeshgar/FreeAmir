<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Enums\InvoiceType;
use App\Exceptions\InvoiceServiceException;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\FiscalYearService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 8000;

    protected PaymentService $paymentService;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    /**
     * A selectable cash box created under the cash_book root (roots themselves
     * are grouping headers and are not selectable settlement subjects).
     */
    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create([
            'name' => 'صندوق',
            'company_id' => $this->companyId,
        ]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    /**
     * Create a bank account with its own ledger subject and return that subject id.
     */
    private function bankAccountSubjectId(): int
    {
        $bank = new Bank(['name' => 'بانک نمونه']);
        $bank->company_id = $this->companyId;
        $bank->save();

        $account = BankAccount::factory()->withSubject()->create([
            'company_id' => $this->companyId,
            'bank_id' => $bank->id,
        ]);

        return (int) $account->subject_id;
    }

    /**
     * Create an approved sell invoice with a known total (no VAT/discount).
     */
    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    public function test_unpaid_when_no_payments(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        $this->assertSame('unpaid', $sell->paymentStatus());
        $this->assertEqualsWithDelta(0, $sell->paidAmount(), 0.01);
        $this->assertEqualsWithDelta(2000, $sell->remainingAmount(), 0.01);
    }

    public function test_partial_payment_posts_balanced_document_and_updates_status(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        $payment = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 800,
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertNotNull($payment->document_id);

        // The receipt document must be balanced (debit == credit).
        $values = $payment->document->transactions->pluck('value');
        $this->assertEqualsWithDelta(0, $values->sum(), 0.01);

        // Sell: customer credited (+), cash debited (-).
        $customerSubjectId = $sell->customer->subject->id;
        $customerTx = $payment->document->transactions->firstWhere('subject_id', $customerSubjectId);
        $this->assertGreaterThan(0, $customerTx->value);
        $this->assertEqualsWithDelta(800, $customerTx->value, 0.01);

        $sell = $this->findInvoice($sell->id);
        $this->assertSame('partially_paid', $sell->paymentStatus());
        $this->assertEqualsWithDelta(800, $sell->paidAmount(), 0.01);
        $this->assertEqualsWithDelta(1200, $sell->remainingAmount(), 0.01);
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        $this->paymentService->createPayment($this->user, $sell, ['amount' => 1200, 'subject_id' => $this->cashSubjectId()]);
        $this->paymentService->createPayment($this->user, $sell, ['amount' => 800, 'subject_id' => $this->cashSubjectId()]);

        $sell = $this->findInvoice($sell->id);
        $this->assertSame('paid', $sell->paymentStatus());
        $this->assertEqualsWithDelta(0, $sell->remainingAmount(), 0.01);
    }

    public function test_payment_cannot_exceed_remaining(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        $this->expectException(InvoiceServiceException::class);
        $this->paymentService->createPayment($this->user, $sell, ['amount' => 2500, 'subject_id' => $this->cashSubjectId()]);
    }

    public function test_payment_rejected_for_invalid_settlement_subject(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        // The customer subject is not a bank/cash settlement subject.
        $this->expectException(InvoiceServiceException::class);
        $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 1000,
            'subject_id' => $sell->customer->subject->id,
        ]);
    }

    public function test_deleting_payment_reverses_document_and_restores_status(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000

        $payment = $this->paymentService->createPayment($this->user, $sell, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $documentId = $payment->document_id;

        $this->assertSame('paid', $this->findInvoice($sell->id)->paymentStatus());

        $this->paymentService->deletePayment($payment);

        $this->assertNull(Payment::withoutGlobalScopes()->find($payment->id));
        $this->assertNull(Document::withoutGlobalScopes()->find($documentId));
        $this->assertSame('unpaid', $this->findInvoice($sell->id)->paymentStatus());
    }

    public function test_payments_are_deleted_when_their_invoice_is_deleted(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000
        $payment = $this->paymentService->createPayment($this->user, $sell, ['amount' => 500, 'subject_id' => $this->cashSubjectId()]);
        $this->assertNotNull(Payment::find($payment->id));

        Invoice::withoutGlobalScopes()->findOrFail($sell->id)->delete();

        $this->assertNull(Payment::find($payment->id));
    }

    public function test_payment_rejected_for_unapproved_invoice(): void
    {
        $product = $this->createProduct();
        $sell = $this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice'];

        $this->expectException(InvoiceServiceException::class);
        $this->paymentService->createPayment($this->user, $this->findInvoice($sell->id), ['amount' => 100, 'subject_id' => $this->cashSubjectId()]);
    }

    public function test_record_payment_decision_reports_reasons(): void
    {
        // Approved, unpaid sell → recordable, no errors.
        $sell = $this->approvedSell(1000, 2);
        $this->assertFalse($this->paymentService->validateInvoicePayment($sell)->hasErrors());

        // Unapproved invoice → blocked with a reason.
        $product = $this->createProduct();
        $draft = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice']->id);
        $this->assertTrue($this->paymentService->validateInvoicePayment($draft)->hasErrors());

        // Fully paid invoice → blocked with a reason.
        $this->paymentService->createPayment($this->user, $sell, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->paymentService->validateInvoicePayment($this->findInvoice($sell->id))->hasErrors());
    }

    public function test_bank_account_settlement_posts_to_its_subject_and_counts_as_paid(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000
        $bankSubjectId = $this->bankAccountSubjectId();

        $payment = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 2000,
            'subject_id' => $bankSubjectId,
        ]);

        $this->assertNotNull($payment->document_id);
        $bankTx = $payment->document->transactions->firstWhere('subject_id', $bankSubjectId);
        $this->assertNotNull($bankTx);
        // Sell receipt: bank debited (cash in => negative value).
        $this->assertLessThan(0, $bankTx->value);

        $this->assertSame('paid', $this->findInvoice($sell->id)->paymentStatus());
    }

    public function test_payments_are_exported_and_imported_with_the_fiscal_year(): void
    {
        $sell = $this->approvedSell(1000, 2); // total 2000
        $this->paymentService->createPayment($this->user, $sell, ['amount' => 800, 'subject_id' => $this->cashSubjectId()]);

        $sections = [
            FiscalYearSection::SUBJECTS->value,
            FiscalYearSection::CUSTOMERS->value,
            FiscalYearSection::PRODUCTS->value,
            FiscalYearSection::DOCUMENTS->value,
            FiscalYearSection::INVOICES->value,
        ];

        $exportData = FiscalYearService::exportData($this->companyId, $sections);
        $this->assertArrayHasKey('payments', $exportData);
        $this->assertCount(1, $exportData['payments']);

        $newCompany = FiscalYearService::importData($exportData, [
            'name' => 'Next Fiscal Year',
            'fiscal_year' => 1406,
        ]);

        // Payments are scoped through their invoice (no company_id column).
        $importedSell = Invoice::withoutGlobalScopes()
            ->where('company_id', $newCompany->id)
            ->where('invoice_type', InvoiceType::SELL)
            ->first();

        $importedPayments = Payment::where('invoice_id', $importedSell->id)->get();
        $this->assertCount(1, $importedPayments);

        $importedPayment = $importedPayments->first();
        $this->assertEqualsWithDelta(800, (float) $importedPayment->amount, 0.01);
        $this->assertNotNull($importedPayment->document_id);
    }

    public function test_return_sell_payment_refunds_customer_with_reversed_signs(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 4, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $returnSell = $this->returnSell([$this->productItem($product, 2, 1000)], $sell->id, true, ++$this->nextInvoiceNumber)['invoice'];

        $returnSell = $this->findInvoice($returnSell->id);

        $payment = $this->paymentService->createPayment($this->user, $returnSell, [
            'amount' => 2000,
            'subject_id' => $this->cashSubjectId(),
        ]);

        // Refund: customer debited (negative), cash credited (positive). Balanced.
        $values = $payment->document->transactions->pluck('value');
        $this->assertEqualsWithDelta(0, $values->sum(), 0.01);

        $customerSubjectId = $returnSell->customer->subject->id;
        $customerTx = $payment->document->transactions->firstWhere('subject_id', $customerSubjectId);
        $this->assertLessThan(0, $customerTx->value);

        $this->assertSame('paid', $this->findInvoice($returnSell->id)->paymentStatus());
    }
}
