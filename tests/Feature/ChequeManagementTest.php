<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Middleware\CheckPermission;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\ChequeHistory;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ChequeService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChequeManagementTest extends TestCase
{
    use RefreshDatabase;

    private ChequeService $service;

    private User $user;

    private Customer $customer;

    private Customer $vendor;

    private Bank $bank;

    private BankAccount $account;

    private int $sayadSequence = 1000000000000000;

    private int $invoiceSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Cheque Test', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $companyId);
        Cookie::queue('active-company-id', (string) $companyId);
        $_COOKIE['active-company-id'] = (string) $companyId;

        DB::table('subjects')->insert([
            ['id' => 1, 'code' => '010', 'name' => 'Banks', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 6, 'code' => '013', 'name' => 'Notes receivable', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 22, 'code' => '020', 'name' => 'Notes payable', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 67, 'code' => '014', 'name' => 'Notes in collection', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 44, 'code' => '013001', 'name' => 'Notes receivable detail', 'parent_id' => 6, 'type' => 3, 'company_id' => $companyId],
            ['id' => 46, 'code' => '020001', 'name' => 'Notes payable detail', 'parent_id' => 22, 'type' => 3, 'company_id' => $companyId],
            ['id' => 68, 'code' => '014001', 'name' => 'Notes in collection detail', 'parent_id' => 67, 'type' => 3, 'company_id' => $companyId],
            ['id' => 201, 'code' => '012001001', 'name' => 'Customer subject', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 202, 'code' => '012001002', 'name' => 'Vendor subject', 'parent_id' => null, 'type' => 3, 'company_id' => $companyId],
            ['id' => 203, 'code' => '010001', 'name' => 'Bank account subject', 'parent_id' => 1, 'type' => 3, 'company_id' => $companyId],
        ]);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::create(['company_id' => $companyId, 'name' => 'Customer', 'subject_id' => 201]);
        $this->vendor = Customer::create(['company_id' => $companyId, 'name' => 'Vendor', 'subject_id' => 202]);

        $this->bank = Bank::create(['name' => 'Test Bank', 'company_id' => $companyId]);
        $this->account = BankAccount::create(['bank_id' => $this->bank->id, 'name' => 'Main account', 'number' => '123456', 'type' => 1, 'subject_id' => 203]);
        $this->service = app(ChequeService::class);
    }

    public function test_received_cheque_posts_balanced_registration_deposit_and_clearance_documents(): void
    {
        $cheque = $this->receivedCheque();

        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $this->assertCount(1, $cheque->histories);
        $this->assertBalanced($cheque->histories->first()->document);

        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $this->assertSame(ChequeType::DEPOSITED, $cheque->status);
        $this->assertSame($this->account->id, $cheque->bank_account_id);

        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $this->assertSame(ChequeType::CLEARED, $cheque->status);
        $this->assertCount(3, $cheque->histories);
        $cheque->histories->each(fn ($history) => $this->assertBalanced($history->document));

        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertNull($payment->invoice_id);
        $this->assertSame(ChequeType::RECEIVABLE, $payment->cheque->direction);
        $this->assertSame($cheque->sayad_number, $payment->reference_number);
    }

    public function test_revert_is_rejected_when_history_model_has_no_reversal_fields(): void
    {
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $cheque = $this->service->transition($cheque, $this->user, 'clear');

        $this->expectException(ValidationException::class);
        $this->service->transition($cheque, $this->user, 'revert');
    }

    public function test_received_cheque_can_be_endorsed_to_vendor_as_a_cheque_payment(): void
    {
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'endorse', ['account_side_id' => $this->vendor->id]);

        $this->assertSame(ChequeType::ENDORSED, $cheque->status);
        $this->assertSame($this->vendor->id, $cheque->endorsed_to_id);
        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertTrue($payment->cheque->is($cheque));
        $this->assertBalanced($payment->document);
    }

    public function test_issued_cheque_posts_notes_payable_then_clears_through_bank_payment(): void
    {
        $cheque = $this->issuedCheque();
        $this->assertSame(ChequeType::ISSUED, $cheque->status);
        $this->assertBalanced($cheque->histories->first()->document);

        $cheque = $this->service->transition($cheque, $this->user, 'clear');

        $this->assertSame(ChequeType::CLEARED, $cheque->status);
        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertSame(ChequeType::PAYABLE, $payment->cheque->direction);
        $this->assertSame($this->vendor->id, $payment->cheque->customer_id);
        $this->assertBalanced($payment->document);
    }

    public function test_payable_cheque_can_optionally_belong_to_a_chequebook(): void
    {
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'PAY',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 100,
        ]);

        $linkedData = [
            ...$this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor),
            'bank_account_id' => $this->account->id,
            'chequebook_id' => $chequebook->id,
        ];
        unset($linkedData['serial']);

        $linked = $this->service->register($this->user, $linkedData);
        $unlinked = $this->issuedCheque();

        $this->assertTrue($linked->chequebook->is($chequebook));
        $this->assertNull($linked->serial);
        $this->assertTrue($chequebook->cheques()->whereKey($linked->id)->exists());
        $this->assertNull($unlinked->chequebook_id);
    }

    public function test_receivable_cheque_cannot_belong_to_a_chequebook(): void
    {
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'PAY',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 100,
        ]);

        $this->expectException(ValidationException::class);
        $this->service->register($this->user, [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            'chequebook_id' => $chequebook->id,
        ]);
    }

    public function test_guarantee_cheque_is_off_balance_until_executed(): void
    {
        $cheque = $this->receivedCheque(ChequeType::GUARANTEE);

        $this->assertSame(ChequeType::GUARANTEE_RECEIVED, $cheque->status);
        $this->assertNull($cheque->histories->first()->document_id);
        $this->assertSame(0, Document::count());

        $cheque = $this->service->transition($cheque, $this->user, 'execute');

        $this->assertSame(ChequeType::SETTLEMENT, $cheque->purpose);
        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $this->assertBalanced($cheque->histories()->reorder()->latest('id')->firstOrFail()->document);
    }

    public function test_invalid_lifecycle_transition_is_rejected(): void
    {
        try {
            $this->service->transition($this->receivedCheque(), $this->user, 'clear');
            $this->fail('Expected an invalid cheque transition to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('This action is not allowed in the current status.')], $exception->errors()['status']);
        }
    }

    public function test_http_validation_requires_exactly_sixteen_sayad_digits(): void
    {
        $this->withoutMiddleware();
        $response = $this->post(route('cheques.store'), array_merge(
            $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            ['sayad_number' => '123456789012345'],
        ));

        $response->assertSessionHasErrors('sayad_number');
        $this->assertSame(0, Cheque::count());
    }

    public function test_http_cheque_dates_accept_jalali_input_store_gregorian_and_render_jalali(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        app()->setLocale('fa');

        $response = $this->post(route('cheques.store'), [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            'issue_date' => '۱۴۰۴/۱۰/۱۱',
            'due_date' => '۱۴۰۴/۱۱/۱۲',
        ]);

        $cheque = Cheque::latest('id')->firstOrFail();
        $response->assertRedirect(route('cheques.show', $cheque));
        $this->assertSame('2026-01-01', $cheque->getRawOriginal('write_date'));
        $this->assertSame('2026-02-01', $cheque->getRawOriginal('due_date'));
        $this->get(route('cheques.show', $cheque))->assertOk()->assertSee('۱۴۰۴/۱۰/۱۱')->assertSee('۱۴۰۴/۱۱/۱۲');
        $this->get(route('cheques.edit', $cheque))->assertOk()->assertSee('value="1404/10/11"', false)->assertSee('value="1404/11/12"', false);
    }

    public function test_http_cheque_validation_rejects_invalid_jalali_date(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        app()->setLocale('fa');

        $this->post(route('cheques.store'), [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            'issue_date' => '۱۴۰۴/۱۲/۳۰',
            'due_date' => '۱۴۰۵/۰۱/۰۱',
        ])->assertSessionHasErrors('issue_date');

        $this->assertSame(0, Cheque::count());
    }

    public function test_http_receivable_cheque_excludes_chequebook_input(): void
    {
        $this->withoutMiddleware();
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'PAY',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 100,
        ]);

        $response = $this->post(route('cheques.store'), [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            'chequebook_id' => $chequebook->id,
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull(Cheque::firstOrFail()->chequebook_id);
    }

    public function test_cheque_classifications_use_regular_integer_columns(): void
    {
        foreach (['status', 'direction', 'purpose'] as $column) {
            $this->assertContains(Schema::getColumnType('cheques', $column), ['smallint', 'integer']);
        }

        foreach (['from_status', 'to_status'] as $column) {
            $this->assertContains(Schema::getColumnType('cheque_histories', $column), ['smallint', 'integer']);
        }

        $this->assertTrue(Schema::hasColumns('chequebooks', [
            'company_id',
            'bank_account_id',
            'serial_prefix',
            'first_leaf',
            'last_leaf',
            'next_leaf',
            'desc',
        ]));
        $this->assertTrue(Schema::hasColumn('cheques', 'chequebook_id'));
    }

    public function test_cheque_translations_are_loaded_from_json_catalogs(): void
    {
        $originalLocale = app()->getLocale();

        app()->setLocale('en');
        $this->assertSame('Cheque Management', __('Cheque Management'));
        $this->assertSame('Cheque number', __('Cheque number'));
        $this->assertSame('Chequebook', __('Chequebook'));
        $this->assertSame('Received', ChequeType::REGISTERED->label());

        app()->setLocale('fa');
        $this->assertSame('مدیریت چک‌ها', __('Cheque Management'));
        $this->assertSame('شماره چک', __('Cheque number'));
        $this->assertSame('دسته‌چک', __('Chequebook'));
        $this->assertSame('دریافت‌شده', ChequeType::REGISTERED->label());

        app()->setLocale($originalLocale);
        $this->assertFileDoesNotExist(lang_path('en/cheques.php'));
        $this->assertFileDoesNotExist(lang_path('fa/cheques.php'));
    }

    public function test_cheque_validation_messages_use_sentence_translation_keys(): void
    {
        $originalLocale = app()->getLocale();

        app()->setLocale('en');
        $this->assertSame('A bank account is required.', __('A bank account is required.'));

        app()->setLocale('fa');
        $this->assertSame('انتخاب حساب بانکی الزامی است.', __('A bank account is required.'));

        app()->setLocale($originalLocale);

        $faTranslations = json_decode(file_get_contents(lang_path('fa.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('A bank account is required.', $faTranslations);
    }

    public function test_untransacted_cheque_can_be_updated_and_its_accounting_document_is_rebuilt(): void
    {
        $cheque = $this->receivedCheque();
        $oldDocumentId = $cheque->histories->first()->document_id;
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer, '2026-03-01', 2500);
        $data['sayad_number'] = $cheque->sayad_number;
        $data['serial'] = 'UPDATED-1';
        $data['cheque_number'] = '987654';

        $updated = $this->service->update($cheque, $this->user, $data);

        $this->assertSame('UPDATED-1', $updated->serial);
        $this->assertSame('987654', $updated->cheque_number);
        $this->assertSame('2500.00', $updated->amount);
        $this->assertSame('2026-03-01', $updated->due_date->toDateString());
        $this->assertSame(ChequeType::REGISTERED, $updated->status);
        $this->assertNull(Document::find($oldDocumentId));
        $this->assertCount(1, $updated->histories);
        $this->assertBalanced($updated->histories->first()->document);
    }

    public function test_cheque_cannot_be_updated_after_a_lifecycle_action(): void
    {
        $cheque = $this->service->transition($this->receivedCheque(), $this->user, 'deposit', [
            'bank_account_id' => $this->account->id,
        ]);
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer);

        $this->expectException(ValidationException::class);
        $this->service->update($cheque, $this->user, $data);
    }

    public function test_deleting_cheque_removes_its_payments_history_and_accounting_documents(): void
    {
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $documentIds = $cheque->histories()->pluck('document_id')->filter();
        $paymentIds = $cheque->payments()->pluck('id');
        $chequeId = $cheque->id;

        $this->service->delete($cheque);

        $this->assertNull(Cheque::withoutGlobalScopes()->find($chequeId));
        $this->assertSame(0, DB::table('cheque_histories')->where('cheque_id', $chequeId)->count());
        $this->assertSame(0, Payment::whereIn('id', $paymentIds)->count());
        $this->assertSame(0, Document::whereIn('id', $documentIds)->count());
    }

    public function test_sell_invoice_is_paid_by_received_cheque_without_duplicate_accounting_document(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $history = $cheque->histories()->firstOrFail();

        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $this->assertSame($cheque->id, $payment->cheque_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame(ChequeType::RECEIVABLE, $payment->cheque->direction);
        $this->assertSame($history->document_id, $payment->document_id);
        $this->assertSame($history->payment_id, $payment->id);
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_deleting_invoice_cheque_payment_removes_cheque_and_accounting_document(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;

        $this->delete(route('invoices.payments.destroy', [$invoice, $payment]))->assertRedirect(route('invoices.show', $invoice));

        $this->assertNull(Payment::find($payment->id));
        $this->assertNull(Cheque::withoutGlobalScopes()->find($cheque->id));
        $this->assertNull(Document::withoutGlobalScopes()->find($documentId));
        $this->assertTrue($invoice->fresh()->status->isApproved());
    }

    public function test_buy_invoice_is_paid_by_issued_cheque(): void
    {
        $invoice = $this->invoice(InvoiceType::BUY, $this->vendor, 1000);

        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::PAYABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();

        $this->assertSame(ChequeType::ISSUED, $cheque->status);
        $this->assertSame(ChequeType::PAYABLE, $payment->cheque->direction);
        $this->assertSame($invoice->customer_id, $payment->cheque->customer_id);
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_invoice_cheque_overpayment_rolls_back_cheque_and_payment(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        try {
            $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1001));
            $this->fail('Expected invoice cheque overpayment to be rejected.');
        } catch (ValidationException) {
            $this->assertSame(0, Cheque::count());
            $this->assertSame(0, $invoice->payments()->count());
            $this->assertTrue($invoice->fresh()->status->isApproved());
        }
    }

    public function test_updating_invoice_cheque_rebuilds_payment_document_and_invoice_balance(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $oldDocumentId = $payment->document_id;
        $data = $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 600);
        $data['sayad_number'] = $cheque->sayad_number;

        $cheque = $this->service->update($cheque, $this->user, $data);
        $payment->refresh();

        $this->assertSame('600.00', $payment->amount);
        $this->assertSame($cheque->histories()->firstOrFail()->document_id, $payment->document_id);
        $this->assertNotSame($oldDocumentId, $payment->document_id);
        $this->assertNull(Document::find($oldDocumentId));
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPartiallyPaid());
        $this->assertEqualsWithDelta(400, app(PaymentService::class)->remainingAmount($invoice), 0.01);
    }

    public function test_unlinking_invoice_cheque_preserves_it_while_deleting_it_restores_invoice_status(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;

        app(PaymentService::class)->deletePayment($payment);

        $this->assertNull($payment->fresh()->invoice_id);
        $this->assertNotNull(Document::find($documentId));
        $this->assertTrue($invoice->fresh()->status->isApproved());

        $payment->update(['invoice_id' => $invoice->id]);
        app(PaymentService::class)->syncInvoiceStatus($invoice);
        $this->assertTrue($invoice->fresh()->status->isPaid());

        $this->service->delete($cheque);

        $this->assertNull(Payment::find($payment->id));
        $this->assertNull(Document::find($documentId));
        $this->assertTrue($invoice->fresh()->status->isApproved());
    }

    public function test_invoice_show_can_register_a_cheque_payment_through_http(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.payments.store'),
            Permission::findOrCreate('invoices.payments.store-cheque'),
        ]);
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'PAY',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 100,
        ]);
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee(__('Pay by cheque'));

        $response = $this->post(route('invoices.payments.store-cheque', $invoice), [
            'amount' => 1000,
            'issue_date' => toEnglish(formatDate($invoice->date)),
            'due_date' => toEnglish(formatDate($invoice->date->copy()->addMonth())),
            'serial' => 'HTTP-CHEQUE',
            'cheque_number' => 'HTTP-123',
            'sayad_number' => (string) $this->sayadSequence++,
            'chequebook_id' => $chequebook->id,
            'description' => 'HTTP invoice cheque',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id]);
        $cheque = $invoice->payments()->firstOrFail()->cheque;
        $this->assertSame(ChequeType::RECEIVABLE, $cheque->direction);
        $this->assertNull($cheque->chequebook_id);
        $this->assertSame($invoice->date->toDateString(), $cheque->write_date->toDateString());
        $this->assertSame($invoice->date->copy()->addMonth()->toDateString(), $cheque->due_date->toDateString());
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_invoice_cheque_modal_has_stable_date_fields_and_cheque_number(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.payments.store'),
            Permission::findOrCreate('invoices.payments.store-cheque'),
        ]);
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('name="cheque_number"', false)
            ->assertSee('id="cheque_issue_date"', false)
            ->assertSee('id="cheque_due_date"', false)
            ->assertSee('preventScroll: true', false);
    }

    public function test_buy_invoice_can_be_paid_with_a_cheque_from_an_optional_chequebook(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.payments.store'),
            Permission::findOrCreate('invoices.payments.store-cheque'),
        ]);
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'INVOICE',
            'first_leaf' => 500,
            'last_leaf' => 549,
            'next_leaf' => 500,
        ]);
        $invoice = $this->invoice(InvoiceType::BUY, $this->vendor, 1000);

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('name="chequebook_id"', false);

        $response = $this->post(route('invoices.payments.store-cheque', $invoice), [
            'amount' => 1000,
            'issue_date' => toEnglish(formatDate($invoice->date)),
            'due_date' => toEnglish(formatDate($invoice->date->copy()->addMonth())),
            'serial' => 'INVOICE-500',
            'cheque_number' => '500',
            'sayad_number' => (string) $this->sayadSequence++,
            'bank_account_id' => $this->account->id,
            'chequebook_id' => $chequebook->id,
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertTrue($invoice->payments()->firstOrFail()->cheque->chequebook->is($chequebook));
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_deleting_invoice_removes_its_cheque_payment_histories_and_accounting_documents(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;
        $historyIds = $cheque->histories()->pluck('id');
        InvoiceService::deleteInvoice($invoice->id);

        $this->assertNull($cheque->fresh());
        $this->assertNull($payment->fresh());
        $this->assertNull(Document::find($documentId));
        $this->assertSame(0, ChequeHistory::whereIn('id', $historyIds)->count());
    }

    public function test_cheque_management_pages_render(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $received = $this->receivedCheque();

        foreach ([
            route('cheques.index'),
            route('cheques.create'),
            route('cheques.edit', $received),
            route('cheques.show', $received),
            route('cheques.report'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_chequebook_crud_routes_and_deletion_preserve_associated_cheques(): void
    {
        $this->withoutMiddleware(CheckPermission::class);

        $response = $this->post(route('cheques.chequebooks.store'), [
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'CRUD',
            'first_leaf' => '۱۰۰',
            'last_leaf' => '۱۴۹',
            'description' => 'Primary payable chequebook',
        ]);

        $chequebook = Chequebook::firstOrFail();
        $response->assertRedirect(route('cheques.chequebooks.show', $chequebook));
        $this->assertSame(100, $chequebook->next_leaf);

        $cheque = $this->service->register($this->user, [
            ...$this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor),
            'bank_account_id' => $this->account->id,
            'chequebook_id' => $chequebook->id,
        ]);

        foreach ([
            route('cheques.chequebooks.index'),
            route('cheques.chequebooks.show', $chequebook),
            route('cheques.chequebooks.edit', $chequebook),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->put(route('cheques.chequebooks.update', $chequebook), [
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'UPDATED',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 101,
        ])->assertRedirect(route('cheques.chequebooks.show', $chequebook));

        $this->assertSame('UPDATED', $chequebook->fresh()->serial_prefix);
        $this->delete(route('cheques.chequebooks.destroy', $chequebook))->assertRedirect(route('cheques.chequebooks.index'));

        $this->assertNull(Chequebook::find($chequebook->id));
        $this->assertNotNull($cheque->fresh());
        $this->assertNull($cheque->fresh()->chequebook_id);
    }

    public function test_cheque_index_renders_and_applies_all_supported_filters(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $matching = $this->receivedCheque(ChequeType::GUARANTEE, '2026-02-10', 1500);
        $this->receivedCheque(ChequeType::SETTLEMENT, '2026-02-10', 1500);
        $this->issuedCheque();

        $dueDate = toEnglish(formatDate($matching->due_date));
        $filters = [
            'q' => $matching->cheque_number,
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::GUARANTEE->value,
            'status' => ChequeType::GUARANTEE_RECEIVED->value,
            'amount_min' => 1400,
            'amount_max' => 1600,
            'due_from' => $dueDate,
            'due_to' => $dueDate,
            'customer_id' => $this->customer->id,
        ];

        $this->get(route('cheques.index', $filters))
            ->assertOk()
            ->assertViewHas('cheques', fn ($cheques) => $cheques->total() === 1 && $cheques->first()->is($matching))
            ->assertSee('name="purpose"', false)
            ->assertSee(route('cheques.report', $filters));
    }

    public function test_cheque_form_renders_constraints_and_conditional_bank_account_controls(): void
    {
        $this->withoutMiddleware(CheckPermission::class);

        $this->get(route('cheques.create', ['direction' => ChequeType::PAYABLE->value]))
            ->assertOk()
            ->assertSee(__('Register cheque'))
            ->assertSee('x-model="direction"', false)
            ->assertSee('searchSelect({', false)
            ->assertSee('name="account_side_id"', false)
            ->assertSee('name="bank_account_id"', false)
            ->assertSee('name="cheque_number"', false)
            ->assertSee(':disabled="direction !==', false)
            ->assertSee('minlength="16"', false)
            ->assertSee('maxlength="16"', false)
            ->assertSee('maxlength="1000"', false);
    }

    public function test_customer_show_lists_original_and_endorsed_cheques_only(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $originalCheque = $this->receivedCheque();
        $endorsedCheque = $this->service->register($this->user, $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->vendor));
        $endorsedCheque = $this->service->transition($endorsedCheque, $this->user, 'endorse', ['account_side_id' => $this->customer->id]);
        $unrelatedCheque = $this->issuedCheque();

        $response = $this->get(route('customers.show', $this->customer));

        $response->assertOk()->assertViewIs('customers.show')->assertViewHas('cheques', function ($cheques) use ($originalCheque, $endorsedCheque, $unrelatedCheque) {
            return $cheques->total() === 2 && $cheques->contains('id', $originalCheque->id) && $cheques->contains('id', $endorsedCheque->id) && ! $cheques->contains('id', $unrelatedCheque->id);
        })
            ->assertSee(localizeNumber($originalCheque->cheque_number))
            ->assertSee(localizeNumber($endorsedCheque->cheque_number))
            ->assertDontSee(localizeNumber($unrelatedCheque->cheque_number))
            ->assertSee(__('Original account side'))
            ->assertSee(__('Endorsee'));
    }

    private function receivedCheque(ChequeType $purpose = ChequeType::SETTLEMENT, string $dueDate = '2026-02-01', float $amount = 1000): Cheque
    {
        return $this->service->register($this->user, $this->data(ChequeType::RECEIVABLE, $purpose, $this->customer, $dueDate, $amount));
    }

    private function issuedCheque(): Cheque
    {
        return $this->service->register($this->user, array_merge($this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor), ['bank_account_id' => $this->account->id]));
    }

    private function data(ChequeType $direction, ChequeType $purpose, Customer $accountSide, string $dueDate = '2026-02-01', float $amount = 1000): array
    {
        return [
            'direction' => $direction->value,
            'purpose' => $purpose->value,
            'amount' => $amount,
            'issue_date' => '2026-01-01',
            'due_date' => $dueDate,
            'serial' => 'S-'.$this->sayadSequence,
            'cheque_number' => 'N-'.$this->sayadSequence,
            'sayad_number' => (string) $this->sayadSequence++,
            'account_side_id' => $accountSide->id,
            'bank_account_id' => null,
            'description' => 'Test cheque',
        ];
    }

    private function invoice(InvoiceType $type, Customer $accountSide, float $amount): Invoice
    {
        return Invoice::create([
            'number' => (string) $this->invoiceSequence++,
            'date' => '2026-01-01',
            'creator_id' => $this->user->id,
            'customer_id' => $accountSide->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => 'Cheque invoice',
            'invoice_type' => $type,
            'status' => InvoiceStatus::APPROVED,
        ]);
    }

    private function invoiceChequeData(Invoice $invoice, ChequeType $direction, float $amount): array
    {
        return [
            ...$this->data(
                $direction,
                ChequeType::SETTLEMENT,
                $invoice->customer,
                $invoice->date->copy()->addMonth()->toDateString(),
                $amount,
            ),
            'invoice_id' => $invoice->id,
            'issue_date' => $invoice->date->toDateString(),
            'bank_account_id' => $direction === ChequeType::PAYABLE ? $this->account->id : null,
        ];
    }

    private function assertBalanced(?Document $document): void
    {
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);
        $this->assertEqualsWithDelta(0, $document->transactions()->sum('value'), 0.001);
    }
}
