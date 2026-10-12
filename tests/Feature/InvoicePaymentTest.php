<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Middleware\CheckPermission;
use App\Models\AncillaryCost;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\FiscalYearService;
use App\Services\PaymentService;
use App\Services\ProductService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $fiscalYearId;

    protected int $nextInvoiceNumber = 8000;

    protected PaymentService $paymentService;

    protected ?int $cashBoxSubjectId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentService = app(PaymentService::class);

        $this->fiscalYearId = FiscalYear::factory()->create(['year' => 1405])->id;

        Cache::forever('active_fiscal_year_id', $this->fiscalYearId);
        Cookie::queue('active-fiscal-year-id', (string) $this->fiscalYearId);
        $_COOKIE['active-fiscal-year-id'] = (string) $this->fiscalYearId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->importSubjects($this->fiscalYearId);
        $this->importConfigs($this->fiscalYearId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'fiscal_year_id' => $this->fiscalYearId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'fiscal_year_id' => $this->fiscalYearId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
    }

    private function cashSubjectId(): int
    {
        if ($this->cashBoxSubjectId !== null) {
            return $this->cashBoxSubjectId;
        }

        $cashBook = Subject::withoutGlobalScopes()->find((int) config('amir.cash_book'));
        $box = Subject::factory()->withParent($cashBook)->create([
            'name' => 'صندوق',
            'fiscal_year_id' => $this->fiscalYearId,
        ]);

        return $this->cashBoxSubjectId = (int) $box->id;
    }

    private function bankAccountSubjectId(): int
    {
        $bank = new Bank(['name' => 'بانک نمونه']);
        $bank->fiscal_year_id = $this->fiscalYearId;
        $bank->save();

        $account = BankAccount::factory()->withSubject()->create([
            'fiscal_year_id' => $this->fiscalYearId,
            'bank_id' => $bank->id,
        ]);

        return (int) $account->subject_id;
    }

    private function feeSubjectId(): int
    {
        $subject = Subject::factory()->withParent(Subject::findOrFail((int) config('amir.cost')))->create([
            'name' => 'کارمزد بانک',
            'fiscal_year_id' => $this->fiscalYearId,
        ]);
        Config::create([
            'key' => 'bank_service_fee', 'value' => (string) $subject->id,
            'desc' => 'کارمزد بانک', 'type' => 3, 'category' => 1, 'fiscal_year_id' => $this->fiscalYearId,
        ]);

        return (int) $subject->id;
    }

    private function approvedSell(float $unit, int $qty): Invoice
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, $qty, $unit * 0.5)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, $qty, $unit)], true, ++$this->nextInvoiceNumber)['invoice'];

        return $this->findInvoice($sell->id);
    }

    private function recordPayment(Invoice $invoice, array $data): Payment
    {
        $decision = $this->paymentService->createPayment($this->user, $invoice, $data);
        $this->assertFalse($decision->hasErrors(), $decision->messages->pluck('text')->implode(' '));

        return $invoice->payments()->latest('id')->firstOrFail();
    }

    public function test_unpaid_when_no_payments(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $this->assertTrue($sell->status->isApproved());
        $this->assertEqualsWithDelta(0, $this->paymentService->paidAmount($sell), 0.01);
        $this->assertEqualsWithDelta(2000, $this->paymentService->remainingAmount($sell), 0.01);
    }

    public function test_partial_payment_posts_balanced_document_and_updates_status(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $payment = $this->recordPayment($sell, [
            'amount' => 800,
            'subject_id' => $this->cashSubjectId(),
        ]);

        $this->assertNotNull($payment->document_id);

        $values = $payment->document->transactions->pluck('value');
        $this->assertEqualsWithDelta(0, $values->sum(), 0.01);

        $customerSubjectId = $sell->customer->subject->id;
        $customerTx = $payment->document->transactions->firstWhere('subject_id', $customerSubjectId);
        $this->assertGreaterThan(0, $customerTx->value);
        $this->assertEqualsWithDelta(800, $customerTx->value, 0.01);

        $sell = $this->findInvoice($sell->id);
        $this->assertTrue($sell->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(800, $this->paymentService->paidAmount($sell), 0.01);
        $this->assertEqualsWithDelta(1200, $this->paymentService->remainingAmount($sell), 0.01);
    }

    public function test_payment_date_can_be_earlier_than_invoice_date(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $paymentDate = $sell->date->copy()->subDay()->toDateString();

        $payment = $this->recordPayment($sell, [
            'amount' => 800,
            'subject_id' => $this->cashSubjectId(),
            'date' => $paymentDate,
        ]);

        $this->assertSame($paymentDate, $payment->date->toDateString());
        $this->assertSame($paymentDate, $payment->document->date->toDateString());
    }

    public function test_full_payment_marks_invoice_paid(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $this->recordPayment($sell, ['amount' => 1200, 'subject_id' => $this->cashSubjectId()]);
        $this->recordPayment($sell, ['amount' => 800, 'subject_id' => $this->cashSubjectId()]);

        $sell = $this->findInvoice($sell->id);
        $this->assertTrue($sell->status->isPaid());
        $this->assertEqualsWithDelta(0, $this->paymentService->remainingAmount($sell), 0.01);
    }

    public function test_buy_invoice_payable_amount_includes_approved_ancillary_costs(): void
    {
        $product = $this->createProduct();
        $buy = $this->findInvoice($this->buy([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        AncillaryCost::create([
            'number' => 1,
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'fiscal_year_id' => $this->fiscalYearId,
            'date' => $buy->date,
            'type' => 1,
            'amount' => 300,
            'vat' => 0,
            'status' => InvoiceStatus::APPROVED,
        ]);

        AncillaryCost::create([
            'number' => 2,
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'fiscal_year_id' => $this->fiscalYearId,
            'date' => $buy->date,
            'type' => 2,
            'amount' => 500,
            'vat' => 0,
            'status' => InvoiceStatus::UNAPPROVED,
        ]);

        $this->assertEqualsWithDelta(2300, $this->paymentService->payableAmount($buy), 0.01);
        $this->assertEqualsWithDelta(2300, $this->paymentService->remainingAmount($buy), 0.01);

        $this->recordPayment($buy, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->findInvoice($buy->id)->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(300, $this->paymentService->remainingAmount($buy), 0.01);

        $this->recordPayment($this->findInvoice($buy->id), ['amount' => 300, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->findInvoice($buy->id)->status->isPaid());
    }

    public function test_approving_new_ancillary_cost_resyncs_paid_buy_invoice_status(): void
    {
        $product = $this->createProduct();
        $buy = $this->findInvoice($this->buy([$this->productItem($product, 2, 1000)], true, ++$this->nextInvoiceNumber)['invoice']->id);

        $this->recordPayment($buy, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->findInvoice($buy->id)->status->isPaid());

        AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $buy->id,
            'customer_id' => $this->customer->id,
            'fiscal_year_id' => $this->fiscalYearId,
            'date' => $buy->date->toDateString(),
            'type' => 'Shipping',
            'amount' => 300,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'amount' => 300],
            ],
        ], true);

        $buy = $this->findInvoice($buy->id);
        $this->assertTrue($buy->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(300, $this->paymentService->remainingAmount($buy), 0.01);
    }

    public function test_payment_cannot_exceed_remaining(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $decision = $this->paymentService->createPayment($this->user, $sell, ['amount' => 2500, 'subject_id' => $this->cashSubjectId()]);

        $this->assertTrue($decision->hasErrors());
        $this->assertCount(0, $sell->payments()->get());
    }

    public function test_payment_rejected_for_invalid_settlement_subject(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $decision = $this->paymentService->createPayment($this->user, $sell, [
            'amount' => 1000,
            'subject_id' => 99999999,
        ]);

        $this->assertTrue($decision->hasErrors());
        $this->assertCount(0, $sell->payments()->get());
    }

    public function test_payment_can_settle_against_a_non_bank_or_cash_subject(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $subject = Subject::factory()->withParent($sell->customer->subject->parent)->create(['name' => 'حساب تسویه عمومی']);
        $payment = $this->recordPayment($sell, ['amount' => 1000, 'subject_id' => $subject->id]);

        $this->assertSame($subject->id, $payment->settlement_subject_id);
        $this->assertEqualsWithDelta(-1000, $payment->document->transactions->firstWhere('subject_id', $subject->id)->value, 0.01);
        $this->assertEqualsWithDelta(0, $payment->document->transactions->sum('value'), 0.01);
        $this->assertTrue($sell->fresh()->status->isPartiallyPaid());
    }

    public function test_payment_rejects_another_fiscal_year_subject(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $fiscalYear = FiscalYear::factory()->create();
        $subject = Subject::factory()->create(['fiscal_year_id' => $fiscalYear->id]);
        $decision = $this->paymentService->createPayment($this->user, $sell, ['amount' => 1000, 'subject_id' => $subject->id]);

        $this->assertTrue($decision->hasErrors());
        $this->assertCount(0, $sell->payments()->get());
    }

    public function test_payment_rejects_a_root_subject(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $subject = Subject::whereNull('parent_id')->firstOrFail();
        $decision = $this->paymentService->createPayment($this->user, $sell, ['amount' => 1000, 'subject_id' => $subject->id]);

        $this->assertTrue($decision->hasErrors());
        $this->assertCount(0, $sell->payments()->get());
    }

    public function test_settlement_subjects_are_limited_and_search_all_company_subjects(): void
    {
        $parent = Subject::whereNull('parent_id')->firstOrFail();
        for ($i = 0; $i < 30; $i++) {
            Subject::factory()->withParent($parent)->create();
        }
        $subject = Subject::factory()->withParent($parent)->create(['name' => 'Unique settlement subject']);
        $fiscalYear = FiscalYear::factory()->create();
        $otherParent = Subject::factory()->create(['fiscal_year_id' => $fiscalYear->id, 'is_permanent' => true]);
        Subject::factory()->withParent($otherParent)->create(['name' => $subject->name, 'fiscal_year_id' => $fiscalYear->id]);

        $initial = $this->paymentService->settlementSubjects();
        $this->assertCount(25, $initial);
        $this->assertFalse($initial->contains('id', $parent->id));
        $this->assertFalse($initial->contains('id', $subject->id));
        $this->assertSame([$subject->id], $this->paymentService->settlementSubjects('Unique settlement')->modelKeys());
        $this->assertSame([$subject->id], $this->paymentService->settlementSubjects($subject->formattedCode())->modelKeys());
    }

    public function test_settlement_search_uses_its_own_permission_and_returns_remote_results(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $subject = Subject::factory()->withParent(Subject::whereNull('parent_id')->firstOrFail())->create(['name' => 'Remote settlement subject']);
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.payments.search-subjects'));

        $this->getJson(route('invoices.payments.search-subjects', [$sell, 'q' => 'Remote settlement']))
            ->assertOk()->assertJsonPath('0.options.0.id', $subject->id);

        $this->user->revokePermissionTo('invoices.payments.search-subjects');
        $this->getJson(route('invoices.payments.search-subjects', [$sell, 'q' => 'Remote settlement']))->assertForbidden();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_deleting_payment_reverses_document_and_restores_status(): void
    {
        $this->feeSubjectId();
        $sell = $this->approvedSell(1000, 2);

        $payment = $this->recordPayment($sell, ['amount' => 2000, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId()]);
        $documentId = $payment->document_id;
        $transactionIds = $payment->document->transactions->pluck('id');

        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());

        $this->paymentService->deletePayment($payment);

        $this->assertNull(Payment::withoutGlobalScopes()->find($payment->id));
        $this->assertNull(Document::withoutGlobalScopes()->find($documentId));
        $this->assertSame(0, Transaction::whereIn('id', $transactionIds)->count());
        $this->assertTrue($this->findInvoice($sell->id)->status->isApproved());
    }

    public function test_payments_are_deleted_when_their_invoice_is_deleted(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->recordPayment($sell, ['amount' => 500, 'subject_id' => $this->cashSubjectId()]);
        $this->assertNotNull(Payment::find($payment->id));

        Invoice::withoutGlobalScopes()->findOrFail($sell->id)->delete();

        $this->assertNull(Payment::find($payment->id));
    }

    public function test_payment_rejected_for_unapproved_invoice(): void
    {
        $product = $this->createProduct();
        $sell = $this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice'];
        $invoice = $this->findInvoice($sell->id);

        $decision = $this->paymentService->createPayment($this->user, $invoice, ['amount' => 100, 'subject_id' => $this->cashSubjectId()]);

        $this->assertTrue($decision->hasErrors());
        $this->assertCount(0, $invoice->payments()->get());
    }

    public function test_record_payment_decision_reports_reasons(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->assertFalse($this->paymentService->validateInvoicePayment($sell)->hasErrors());

        $product = $this->createProduct();
        $draft = $this->findInvoice($this->sell([$this->productItem($product, 1, 1000)], false, ++$this->nextInvoiceNumber)['invoice']->id);
        $this->assertTrue($this->paymentService->validateInvoicePayment($draft)->hasErrors());

        $this->recordPayment($sell, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->paymentService->validateInvoicePayment($this->findInvoice($sell->id))->hasErrors());
    }

    public function test_bank_account_settlement_posts_to_its_subject_and_counts_as_paid(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $bankSubjectId = $this->bankAccountSubjectId();

        $payment = $this->recordPayment($sell, [
            'amount' => 2000,
            'subject_id' => $bankSubjectId,
        ]);

        $this->assertNotNull($payment->document_id);
        $bankTx = $payment->document->transactions->firstWhere('subject_id', $bankSubjectId);
        $this->assertNotNull($bankTx);
        $this->assertLessThan(0, $bankTx->value);

        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
    }

    public function test_payments_are_exported_and_imported_with_the_fiscal_year(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $this->feeSubjectId();
        $this->recordPayment($sell, ['amount' => 800, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId()]);

        $sections = [
            FiscalYearSection::SUBJECTS->value,
            FiscalYearSection::CUSTOMERS->value,
            FiscalYearSection::PRODUCTS->value,
            FiscalYearSection::DOCUMENTS->value,
            FiscalYearSection::INVOICES->value,
        ];

        $exportData = FiscalYearService::exportData($this->fiscalYearId, $sections);
        $this->assertArrayHasKey('payments', $exportData);
        $this->assertCount(1, $exportData['payments']);

        $newFiscalYear = FiscalYearService::importData($exportData, [
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);

        $importedSell = Invoice::withoutGlobalScopes()->where('fiscal_year_id', $newFiscalYear->id)->where('invoice_type', InvoiceType::SELL)->first();
        $importedPayments = Payment::where('invoice_id', $importedSell->id)->get();
        $this->assertCount(1, $importedPayments);

        $importedPayment = $importedPayments->first();
        $this->assertEqualsWithDelta(800, (float) $importedPayment->amount, 0.01);
        $this->assertNotNull($importedPayment->document_id);
        $this->assertSame('2.00', $importedPayment->bank_service_fee);
    }

    public function test_return_sell_payment_refunds_customer_with_reversed_signs(): void
    {
        $product = $this->createProduct();
        $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber);
        $sell = $this->sell([$this->productItem($product, 4, 1000)], true, ++$this->nextInvoiceNumber)['invoice'];
        $returnSell = $this->returnSell([$this->productItem($product, 2, 1000)], $sell->id, true, ++$this->nextInvoiceNumber)['invoice'];
        $returnSell = $this->findInvoice($returnSell->id);

        $payment = $this->recordPayment($returnSell, [
            'amount' => 2000,
            'subject_id' => $this->cashSubjectId(),
        ]);

        $values = $payment->document->transactions->pluck('value');
        $this->assertEqualsWithDelta(0, $values->sum(), 0.01);

        $customerSubjectId = $returnSell->customer->subject->id;
        $customerTx = $payment->document->transactions->firstWhere('subject_id', $customerSubjectId);
        $this->assertLessThan(0, $customerTx->value);

        $this->assertTrue($this->findInvoice($returnSell->id)->status->isPaid());
    }

    public function test_paid_buy_invoice_still_counts_for_cost_of_goods(): void
    {
        $product = $this->createProduct();
        $buy = $this->buy([$this->productItem($product, 10, 500)], true, ++$this->nextInvoiceNumber)['invoice'];

        $productService = app(ProductService::class);
        $this->assertEqualsWithDelta(500, $productService->lastApprovedBuyInvoiceItemCOG($this->findProduct($product->id)), 0.01);

        $this->recordPayment($this->findInvoice($buy->id), [
            'amount' => (float) $this->findInvoice($buy->id)->amount,
            'subject_id' => $this->cashSubjectId(),
        ]);
        $this->assertTrue($this->findInvoice($buy->id)->status->isPaid());

        $this->assertEqualsWithDelta(500, $productService->lastApprovedBuyInvoiceItemCOG($this->findProduct($product->id)), 0.01);
    }

    public function test_partial_then_remaining_payment_walks_to_paid_and_back(): void
    {
        $sell = $this->approvedSell(1000, 2);

        $first = $this->recordPayment($sell, ['amount' => 800, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPartiallyPaid());

        $this->recordPayment($this->findInvoice($sell->id), ['amount' => 1200, 'subject_id' => $this->cashSubjectId()]);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());

        $this->paymentService->deletePayment($first);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPartiallyPaid());
    }

    public function test_removing_payment_document_keeps_payment_and_reverts_status(): void
    {
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->recordPayment($sell, ['amount' => 2000, 'subject_id' => $this->cashSubjectId()]);
        $documentId = $payment->document_id;
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());

        $this->paymentService->removePaymentDocument($payment);

        $this->assertNull(Document::withoutGlobalScopes()->find($documentId));
        $kept = Payment::find($payment->id);
        $this->assertNotNull($kept);
        $this->assertNull($kept->document_id);
        $this->assertNotNull($kept->settlement_subject_id);
        $this->assertTrue($this->findInvoice($sell->id)->status->isApproved());
    }

    public function test_recreating_payment_document_restores_paid_status(): void
    {
        $expenseSubjectId = $this->feeSubjectId();
        $sell = $this->approvedSell(1000, 2);
        $payment = $this->recordPayment($sell, ['amount' => 2000, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId()]);
        $this->paymentService->removePaymentDocument($payment);
        $this->assertTrue($this->findInvoice($sell->id)->status->isApproved());

        $this->paymentService->createPaymentDocument($this->user, $payment->fresh());

        $payment->refresh();
        $this->assertNotNull($payment->document_id);
        $this->assertNotNull($payment->document);
        $this->assertSame('2.00', $payment->bank_service_fee);
        $this->assertEquals(-2, $payment->document->transactions->where('subject_id', $expenseSubjectId)->sum('value'));
        $this->assertEqualsWithDelta(0, $payment->document->transactions->sum('value'), 0.01);
        $this->assertTrue($this->findInvoice($sell->id)->status->isPaid());
    }

    public function test_fee_is_separate_from_settlement_for_sell_invoice_with_cash(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::SELL, 'cash');
    }

    public function test_fee_is_separate_from_settlement_for_sell_invoice_with_bank(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::SELL, 'bank');
    }

    public function test_fee_is_separate_from_settlement_for_buy_invoice_with_cash(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::BUY, 'cash');
    }

    public function test_fee_is_separate_from_settlement_for_buy_invoice_with_bank(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::BUY, 'bank');
    }

    public function test_fee_is_separate_from_settlement_for_return_sell_invoice_with_cash(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::RETURN_SELL, 'cash');
    }

    public function test_fee_is_separate_from_settlement_for_return_sell_invoice_with_bank(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::RETURN_SELL, 'bank');
    }

    public function test_fee_is_separate_from_settlement_for_return_buy_invoice_with_cash(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::RETURN_BUY, 'cash');
    }

    public function test_fee_is_separate_from_settlement_for_return_buy_invoice_with_bank(): void
    {
        $this->assertFeeIsSeparateFromSettlement(InvoiceType::RETURN_BUY, 'bank');
    }

    private function assertFeeIsSeparateFromSettlement(InvoiceType $type, string $method): void
    {
        $expenseSubjectId = $this->feeSubjectId();
        $settlementSubjectId = $method === 'cash' ? $this->cashSubjectId() : $this->bankAccountSubjectId();
        $invoice = Invoice::create([
            'number' => ++$this->nextInvoiceNumber, 'date' => now()->toDateString(),
            'creator_id' => $this->user->id, 'customer_id' => $this->customer->id,
            'amount' => 100, 'subtraction' => 0, 'vat' => 0,
            'invoice_type' => $type, 'status' => InvoiceStatus::APPROVED,
        ]);
        $payment = $this->recordPayment($invoice, [
            'amount' => 100, 'bank_service_fee' => 2, 'subject_id' => $settlementSubjectId,
        ]);

        $this->assertSame('2.00', $payment->bank_service_fee);
        $this->assertSame('100.00', $payment->amount);
        $this->assertTrue($invoice->fresh()->status->isPaid());
        $this->assertEquals(100, $this->paymentService->paidAmount($invoice));
        $transactions = $payment->document->transactions;
        $this->assertEqualsWithDelta(0, $transactions->sum('value'), 0.001);
        $this->assertEquals(-2, $transactions->where('subject_id', $expenseSubjectId)->sum('value'));
        $customerCredit = in_array($type, [InvoiceType::SELL, InvoiceType::RETURN_BUY]) ? 100 : -100;
        $this->assertEquals($customerCredit, $transactions->where('subject_id', $invoice->customer->subject_id)->sum('value'));
        $this->assertEquals(-$customerCredit + 2, $transactions->where('subject_id', $settlementSubjectId)->sum('value'));
    }

    public function test_fee_does_not_allow_overpayment_and_does_not_reduce_partial_balance(): void
    {
        $this->feeSubjectId();
        $invoice = $this->approvedSell(100, 1);
        $data = ['subject_id' => $this->cashSubjectId(), 'amount' => 101, 'bank_service_fee' => 2];
        $before = Document::count();
        $this->assertTrue($this->paymentService->createPayment($this->user, $invoice, $data)->hasErrors());
        $this->assertSame($before, Document::count());
        $this->assertSame(0, $invoice->payments()->count());

        $data['amount'] = 40;
        $this->recordPayment($invoice, $data);
        $this->assertEquals(60, $this->paymentService->remainingAmount($invoice));
        $this->assertTrue($invoice->fresh()->status->isPartiallyPaid());
    }

    public function test_negative_fee_is_rejected_by_the_service_without_posting(): void
    {
        $invoice = $this->approvedSell(100, 1);
        $before = Document::count();
        $decision = $this->paymentService->createPayment($this->user, $invoice, [
            'subject_id' => $this->cashSubjectId(), 'amount' => 100, 'bank_service_fee' => -2,
        ]);
        $this->assertTrue($decision->hasErrors());
        $this->assertSame($before, Document::count());
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_http_normalizes_blank_fee(): void
    {
        $this->assertHttpNormalizesFee('', '0.00');
    }

    public function test_http_normalizes_zero_fee(): void
    {
        $this->assertHttpNormalizesFee('0', '0.00');
    }

    public function test_http_normalizes_persian_fee(): void
    {
        $this->assertHttpNormalizesFee('۲', '2.00');
    }

    public function test_http_normalizes_persian_decimal_fee(): void
    {
        $this->assertHttpNormalizesFee('۲٫۵۰', '2.50');
    }

    private function assertHttpNormalizesFee(string $fee, string $expected): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->feeSubjectId();
        $invoice = $this->approvedSell(100, 1);
        $this->post(route('invoices.payments.store', $invoice), [
            'subject_id' => $this->cashSubjectId(), 'amount' => '۱۰۰', 'bank_service_fee' => $fee,
        ])->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $payment = $invoice->payments()->firstOrFail();
        $this->assertSame($expected, $payment->bank_service_fee);
        $this->assertCount($expected === '0.00' ? 2 : 4, $payment->document->transactions);
    }

    public function test_http_rejects_negative_fee(): void
    {
        $this->assertHttpRejectsInvalidFee('-۲');
    }

    public function test_http_rejects_text_fee(): void
    {
        $this->assertHttpRejectsInvalidFee('invalid');
    }

    public function test_http_rejects_array_fee(): void
    {
        $this->assertHttpRejectsInvalidFee(['2']);
    }

    private function assertHttpRejectsInvalidFee(mixed $fee): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $invoice = $this->approvedSell(100, 1);
        $this->post(route('invoices.payments.store', $invoice), [
            'subject_id' => $this->cashSubjectId(), 'amount' => 100, 'bank_service_fee' => $fee,
        ])->assertSessionHasErrors('bank_service_fee');
        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_positive_fee_requires_an_expense_subject_in_the_active_fiscal_year(): void
    {
        $invoice = $this->approvedSell(100, 1);
        $expenseSubjectId = $this->feeSubjectId();
        $otherYear = FiscalYear::factory()->create([
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);
        $otherYear = FiscalYear::factory()->create([
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);
        Subject::withoutGlobalScopes()->whereKey($expenseSubjectId)->update(['fiscal_year_id' => $otherYear->id]);
        // A cached setting from another year must never bypass fiscal-year scoping.
        config(['amir.bank_service_fee' => $expenseSubjectId]);
        $decision = $this->paymentService->createPayment($this->user, $invoice, [
            'subject_id' => $this->cashSubjectId(), 'amount' => 100, 'bank_service_fee' => 2,
        ]);
        $this->assertTrue($decision->hasErrors());
        $this->assertSame(0, $invoice->payments()->count());

        Config::where('key', 'bank_service_fee')->delete();
        $decision = $this->paymentService->createPayment($this->user, $invoice, [
            'subject_id' => $this->cashSubjectId(), 'amount' => 100, 'bank_service_fee' => 2,
        ]);
        $this->assertTrue($decision->hasErrors());
    }

    public function test_fee_uses_the_current_year_setting_even_when_cached_setting_is_from_another_year(): void
    {
        $expenseSubjectId = $this->feeSubjectId();
        $otherYear = FiscalYear::factory()->create([
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);
        $otherYear = FiscalYear::factory()->create([
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);
        $otherSubject = Subject::factory()->create(['fiscal_year_id' => $otherYear->id, 'parent_id' => null]);
        Config::create([
            'fiscal_year_id' => $otherYear->id, 'key' => 'bank_service_fee', 'value' => (string) $otherSubject->id,
            'desc' => 'Other year fee', 'type' => 3, 'category' => 1,
        ]);
        config(['amir.bank_service_fee' => $otherSubject->id]);
        $invoice = $this->approvedSell(100, 1);
        $payment = $this->recordPayment($invoice, [
            'amount' => 100, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId(),
        ]);
        $this->assertEquals(-2, $payment->document->transactions->where('subject_id', $expenseSubjectId)->sum('value'));
        $this->assertCount(0, $payment->document->transactions->where('subject_id', $otherSubject->id));
    }

    public function test_invoice_page_renders_fee_input_and_recorded_fee(): void
    {
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.show'), Permission::findOrCreate('invoices.payments.store'),
        ]);
        $this->feeSubjectId();
        $invoice = $this->approvedSell(100, 1);
        $this->recordPayment($invoice, [
            'amount' => 40, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId(),
        ]);
        $this->get(route('invoices.show', $invoice))->assertOk()
            ->assertSee(__('Bank service fee'))
            ->assertSee('name="bank_service_fee"', false)
            ->assertSee('id="invoice_payment_fee"', false);
    }

    public function test_recreating_fee_payment_document_rejects_overpayment_after_a_new_payment(): void
    {
        $this->feeSubjectId();
        $invoice = $this->approvedSell(100, 1);
        $data = ['amount' => 100, 'bank_service_fee' => 2, 'subject_id' => $this->cashSubjectId()];
        $original = $this->recordPayment($invoice, $data);
        $this->paymentService->removePaymentDocument($original);
        $this->recordPayment($invoice->fresh(), $data);

        $decision = $this->paymentService->createPaymentDocument($this->user, $original->fresh());
        $this->assertTrue($decision->hasErrors());
        $this->assertNull($original->fresh()->document_id);
        $this->assertEquals(100, $this->paymentService->paidAmount($invoice));
    }
}
