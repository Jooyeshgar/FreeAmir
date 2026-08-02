<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Middleware\CheckPermission;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Checkbook;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\ChequeService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Database\Schema\Blueprint;
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

    protected function setUp(): void
    {
        parent::setUp();

        $companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Cheque Test', 'fiscal_year' => 1405])->id;
        Cache::forever('active_company_id', $companyId);
        Cookie::queue('active-company-id', (string) $companyId);
        $_COOKIE['active-company-id'] = (string) $companyId;

        DB::table('subjects')->insert([
            ['id' => 1, 'code' => '010', 'name' => 'Banks', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 6, 'code' => '013', 'name' => 'Notes receivable', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 22, 'code' => '020', 'name' => 'Notes payable', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 67, 'code' => '014', 'name' => 'Notes in collection', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 44, 'code' => '013001', 'name' => 'Notes receivable detail', 'parent_id' => 6, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 46, 'code' => '020001', 'name' => 'Notes payable detail', 'parent_id' => 22, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 68, 'code' => '014001', 'name' => 'Notes in collection detail', 'parent_id' => 67, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 201, 'code' => '012001001', 'name' => 'Customer subject', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 202, 'code' => '012001002', 'name' => 'Vendor subject', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 203, 'code' => '010001', 'name' => 'Bank account subject', 'parent_id' => 1, 'type' => 'both', 'company_id' => $companyId],
        ]);

        if (DB::getDriverName() === 'sqlite'
            && ! in_array(Schema::getColumnType('customers', 'type'), ['integer', 'tinyint', 'smallint'], true)) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedTinyInteger('type')->default(CustomerType::INDIVIDUAL->value)->change();
            });
        }

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::create(['company_id' => $companyId, 'name' => 'Customer', 'subject_id' => 201]);
        $this->vendor = Customer::create(['company_id' => $companyId, 'name' => 'Vendor', 'subject_id' => 202]);

        $this->bank = Bank::create(['name' => 'Test Bank', 'company_id' => $companyId]);
        $this->account = BankAccount::create([
            'bank_id' => $this->bank->id,
            'name' => 'Main account',
            'number' => '123456',
            'type' => 1,
            'subject_id' => 203,
        ]);
        $this->service = app(ChequeService::class);
    }

    public function test_received_cheque_posts_balanced_registration_deposit_and_clearance_documents(): void
    {
        $cheque = $this->receivedCheque();

        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $this->assertCount(1, $cheque->histories);
        $this->assertBalanced($cheque->histories->first()->document);

        $cheque = $this->service->transition($cheque, $this->user, 'deposit', [
            'bank_account_id' => $this->account->id,
            'version' => $cheque->version,
        ]);
        $this->assertSame(ChequeType::DEPOSITED, $cheque->status);
        $this->assertSame($this->account->id, $cheque->bank_account_id);

        $cheque = $this->service->transition($cheque, $this->user, 'clear', ['version' => $cheque->version]);
        $this->assertSame(ChequeType::CLEARED, $cheque->status);
        $this->assertCount(3, $cheque->histories);
        $cheque->histories->each(fn ($history) => $this->assertBalanced($history->document));

        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertNull($payment->invoice_id);
        $this->assertSame('cheque', $payment->method);
        $this->assertSame('inbound', $payment->direction);
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
        $cheque = $this->service->transition($cheque, $this->user, 'endorse', ['party_id' => $this->vendor->id]);

        $this->assertSame(ChequeType::ENDORSED, $cheque->status);
        $this->assertSame($this->vendor->id, $cheque->endorsed_to_id);
        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertSame($this->vendor->id, $payment->payee_id);
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
        $this->assertSame('outbound', $payment->direction);
        $this->assertSame($this->vendor->id, $payment->payee_id);
        $this->assertBalanced($payment->document);
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
        $this->expectException(ValidationException::class);
        $this->service->transition($this->receivedCheque(), $this->user, 'clear');
    }

    public function test_checkbook_leaf_range_generates_serial_and_prevents_reuse(): void
    {
        $checkbook = Checkbook::create([
            'bank_account_id' => $this->account->id,
            'title' => 'Main book',
            'serial_prefix' => 'A-',
            'start_leaf_number' => 100,
            'end_leaf_number' => 102,
            'next_leaf_number' => 100,
            'is_active' => true,
        ]);
        $data = array_merge($this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor), [
            'serial' => null,
            'bank_account_id' => $this->account->id,
            'checkbook_id' => $checkbook->id,
            'checkbook_leaf_number' => 100,
        ]);

        $cheque = $this->service->register($this->user, $data);

        $this->assertSame('A-100', $cheque->serial);
        $this->assertSame(101, $checkbook->fresh()->next_leaf_number);

        $this->expectException(ValidationException::class);
        $data['sayad_number'] = (string) $this->sayadSequence++;
        $this->service->register($this->user, $data);
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

    public function test_cheque_classifications_use_regular_integer_columns(): void
    {
        foreach (['status', 'direction', 'purpose'] as $column) {
            $this->assertContains(Schema::getColumnType('cheques', $column), ['smallint', 'integer']);
        }

        foreach (['from_status', 'to_status'] as $column) {
            $this->assertContains(Schema::getColumnType('cheque_histories', $column), ['smallint', 'integer']);
        }
    }

    public function test_checkbook_controller_creates_an_active_book_and_rejects_a_duplicate_title(): void
    {
        $this->withoutMiddleware();
        $data = [
            'bank_account_id' => $this->account->id,
            'title' => 'Main HTTP book',
            'serial_prefix' => 'HTTP-',
            'start_leaf_number' => 1,
            'end_leaf_number' => 10,
            'is_active' => '1',
        ];

        $this->post(route('checkbooks.store'), $data)->assertRedirect(route('checkbooks.index'));

        $checkbook = Checkbook::where('title', $data['title'])->firstOrFail();
        $this->assertTrue($checkbook->is_active);
        $this->assertSame(1, $checkbook->next_leaf_number);

        $this->post(route('checkbooks.store'), $data)->assertSessionHasErrors('title');
    }

    public function test_cheque_translations_are_loaded_from_json_catalogs(): void
    {
        $originalLocale = app()->getLocale();

        app()->setLocale('en');
        $this->assertSame('Cheque Management', __('cheques title'));
        $this->assertSame('Received', ChequeType::REGISTERED->label());

        app()->setLocale('fa');
        $this->assertSame('مدیریت چک‌ها', __('cheques title'));
        $this->assertSame('دریافت‌شده', ChequeType::REGISTERED->label());

        app()->setLocale($originalLocale);
        $this->assertFileDoesNotExist(lang_path('en/cheques.php'));
        $this->assertFileDoesNotExist(lang_path('fa/cheques.php'));
    }

    public function test_untransacted_cheque_can_be_updated_and_its_accounting_document_is_rebuilt(): void
    {
        $cheque = $this->receivedCheque();
        $oldDocumentId = $cheque->histories->first()->document_id;
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer, '2026-03-01', 2500);
        $data['sayad_number'] = $cheque->sayad_number;
        $data['serial'] = 'UPDATED-1';
        $data['version'] = $cheque->version;

        $updated = $this->service->update($cheque, $this->user, $data);

        $this->assertSame('UPDATED-1', $updated->serial);
        $this->assertSame('2500.00', $updated->amount);
        $this->assertSame('2026-03-01', $updated->due_date->toDateString());
        $this->assertSame(ChequeType::REGISTERED, $updated->status);
        $this->assertSame(2, $updated->version);
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
        $data['version'] = $cheque->version;

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

        $this->service->delete($cheque, $this->user, $cheque->version);

        $this->assertNull(Cheque::withoutGlobalScopes()->find($chequeId));
        $this->assertSame(0, DB::table('cheque_histories')->where('cheque_id', $chequeId)->count());
        $this->assertSame(0, Payment::whereIn('id', $paymentIds)->count());
        $this->assertSame(0, Document::whereIn('id', $documentIds)->count());
    }

    public function test_sell_invoice_is_paid_by_received_cheque_without_duplicate_accounting_document(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        $cheque = $this->service->register(
            $this->user,
            $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000),
        );
        $payment = $invoice->payments()->firstOrFail();
        $history = $cheque->histories()->firstOrFail();

        $this->assertSame(ChequeType::REGISTERED, $cheque->status);
        $this->assertSame($cheque->id, $payment->cheque_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertSame('cheque', $payment->method);
        $this->assertSame('inbound', $payment->direction);
        $this->assertSame($history->document_id, $payment->document_id);
        $this->assertSame($history->payment_id, $payment->id);
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_buy_invoice_is_paid_by_issued_cheque(): void
    {
        $invoice = $this->invoice(InvoiceType::BUY, $this->vendor, 1000);

        $cheque = $this->service->register(
            $this->user,
            $this->invoiceChequeData($invoice, ChequeType::PAYABLE, 1000),
        );
        $payment = $invoice->payments()->firstOrFail();

        $this->assertSame(ChequeType::ISSUED, $cheque->status);
        $this->assertSame('outbound', $payment->direction);
        $this->assertSame($invoice->customer_id, $payment->payee_id);
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_invoice_cheque_overpayment_rolls_back_cheque_and_payment(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        try {
            $this->service->register(
                $this->user,
                $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1001),
            );
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
        $cheque = $this->service->register(
            $this->user,
            $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000),
        );
        $payment = $invoice->payments()->firstOrFail();
        $oldDocumentId = $payment->document_id;
        $data = $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 600);
        $data['sayad_number'] = $cheque->sayad_number;
        $data['version'] = $cheque->version;

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
        $cheque = $this->service->register(
            $this->user,
            $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000),
        );
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;

        app(PaymentService::class)->deletePayment($payment);

        $this->assertNull($payment->fresh()->invoice_id);
        $this->assertNotNull(Document::find($documentId));
        $this->assertTrue($invoice->fresh()->status->isApproved());

        $payment->update(['invoice_id' => $invoice->id]);
        app(PaymentService::class)->syncInvoiceStatus($invoice);
        $this->assertTrue($invoice->fresh()->status->isPaid());

        $this->service->delete($cheque, $this->user, $cheque->version);

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
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('Pay by cheque'));

        $response = $this->post(route('invoices.payments.store-cheque', $invoice), [
            'amount' => 1000,
            'issue_date' => toEnglish(formatDate($invoice->date)),
            'due_date' => toEnglish(formatDate($invoice->date->copy()->addMonth())),
            'serial' => 'HTTP-CHEQUE',
            'sayad_number' => (string) $this->sayadSequence++,
            'description' => 'HTTP invoice cheque',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'method' => 'cheque',
            'direction' => 'inbound',
        ]);
        $this->assertTrue($invoice->fresh()->status->isPaid());
    }

    public function test_deleting_invoice_detaches_but_preserves_its_cheque_and_accounting_document(): void
    {
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register(
            $this->user,
            $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000),
        );
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;

        $invoice->delete();

        $this->assertNotNull($cheque->fresh());
        $this->assertNull($payment->fresh()->invoice_id);
        $this->assertNotNull(Document::find($documentId));
        $this->assertSame($payment->id, $cheque->histories()->firstOrFail()->payment_id);
    }

    public function test_cheque_management_pages_render(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $received = $this->receivedCheque();
        $issued = $this->issuedCheque();

        foreach ([
            route('cheques.index'),
            route('cheques.create'),
            route('cheques.edit', $received),
            route('cheques.show', $received),
            route('cheques.report'),
            route('checkbooks.index'),
            route('checkbooks.create'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_customer_show_lists_original_and_endorsed_cheques_only(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $originalCheque = $this->receivedCheque();
        $endorsedCheque = $this->service->register(
            $this->user,
            $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->vendor),
        );
        $endorsedCheque = $this->service->transition($endorsedCheque, $this->user, 'endorse', [
            'party_id' => $this->customer->id,
        ]);
        $unrelatedCheque = $this->issuedCheque();

        $response = $this->get(route('customers.show', $this->customer));

        $response->assertOk()
            ->assertViewIs('customers.show')
            ->assertViewHas('cheques', function ($cheques) use ($originalCheque, $endorsedCheque, $unrelatedCheque) {
                return $cheques->total() === 2
                    && $cheques->contains('id', $originalCheque->id)
                    && $cheques->contains('id', $endorsedCheque->id)
                    && ! $cheques->contains('id', $unrelatedCheque->id);
            })
            ->assertSee(localizeNumber($originalCheque->serial))
            ->assertSee(localizeNumber($endorsedCheque->serial))
            ->assertDontSee(localizeNumber($unrelatedCheque->serial))
            ->assertSee(__('cheques role original_party'))
            ->assertSee(__('cheques role endorsee'));
    }

    private function receivedCheque(ChequeType $purpose = ChequeType::SETTLEMENT, string $dueDate = '2026-02-01', float $amount = 1000): Cheque
    {
        return $this->service->register($this->user, $this->data(ChequeType::RECEIVABLE, $purpose, $this->customer, $dueDate, $amount));
    }

    private function issuedCheque(): Cheque
    {
        return $this->service->register($this->user, array_merge($this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor), [
            'bank_account_id' => $this->account->id,
        ]));
    }

    private function data(ChequeType $direction, ChequeType $purpose, Customer $party, string $dueDate = '2026-02-01', float $amount = 1000): array
    {
        return [
            'direction' => $direction->value,
            'purpose' => $purpose->value,
            'amount' => $amount,
            'issue_date' => '2026-01-01',
            'due_date' => $dueDate,
            'serial' => 'S-'.$this->sayadSequence,
            'sayad_number' => (string) $this->sayadSequence++,
            'party_id' => $party->id,
            'bank_account_id' => null,
            'description' => 'Test cheque',
        ];
    }

    private function invoice(InvoiceType $type, Customer $party, float $amount): Invoice
    {
        if (DB::getDriverName() === 'sqlite'
            && ! in_array(Schema::getColumnType('invoices', 'status'), ['integer', 'tinyint', 'smallint'], true)) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unsignedTinyInteger('invoice_type')->default(InvoiceType::SELL->value)->change();
                $table->unsignedTinyInteger('status')->default(InvoiceStatus::PENDING->value)->change();
            });
        }

        return Invoice::create([
            'number' => (string) $this->sayadSequence++,
            'date' => '2026-01-01',
            'creator_id' => $this->user->id,
            'customer_id' => $party->id,
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
