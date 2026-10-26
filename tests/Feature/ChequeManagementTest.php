<?php

namespace Tests\Feature;

use App\Enums\ChequeType;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Http\Middleware\CheckPermission;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cheque;
use App\Models\Chequebook;
use App\Models\ChequeHistory;
use App\Models\Config;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChequeService;
use App\Services\FiscalYearService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
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

    private int $fiscalYearId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->fiscalYearId = FiscalYear::factory()->create(['year' => 1405])->id;
        Cache::forever('active_fiscal_year_id', $this->fiscalYearId);
        Cookie::queue('active-fiscal-year-id', (string) $this->fiscalYearId);
        $_COOKIE['active-fiscal-year-id'] = (string) $this->fiscalYearId;

        DB::table('subjects')->insert([
            ['id' => 1, 'code' => '010', 'name' => 'Banks', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 6, 'code' => '013', 'name' => 'Documents receivable', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 22, 'code' => '020', 'name' => 'Documents payable', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 67, 'code' => '014', 'name' => 'Documents in collection', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 44, 'code' => '013001', 'name' => 'Documents receivable detail', 'parent_id' => 6, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 46, 'code' => '020001', 'name' => 'Documents payable detail', 'parent_id' => 22, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 68, 'code' => '014001', 'name' => 'Documents in collection detail', 'parent_id' => 67, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 201, 'code' => '012001001', 'name' => 'Customer subject', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 202, 'code' => '012001002', 'name' => 'Vendor subject', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 203, 'code' => '010001', 'name' => 'Bank account subject', 'parent_id' => 1, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
        ]);

        foreach ([
            'cheque_documents_receivable' => 44,
            'cheque_documents_in_collection' => 68,
            'cheque_documents_payable' => 46,
        ] as $key => $subjectId) {
            Config::create([
                'key' => $key,
                'value' => (string) $subjectId,
                'desc' => $key,
                'type' => 3,
                'category' => 1,
                'fiscal_year_id' => $this->fiscalYearId,
            ]);
            config(['amir.'.$key => (string) $subjectId]);
        }

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Customer', 'type' => CustomerType::INDIVIDUAL, 'subject_id' => 201]);
        $this->vendor = Customer::create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Vendor', 'type' => CustomerType::INDIVIDUAL, 'subject_id' => 202]);

        $this->bank = Bank::create(['name' => 'Test Bank', 'fiscal_year_id' => $this->fiscalYearId]);
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
        $depositHistory = $cheque->histories()->latest('id')->firstOrFail();
        $this->assertSame(ChequeType::REGISTERED, $depositHistory->from_status);
        $this->assertSame(ChequeType::DEPOSITED, $depositHistory->to_status);

        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $this->assertSame(ChequeType::CLEARED, $cheque->status);
        $this->assertCount(3, $cheque->histories);
        $clearHistory = $cheque->histories()->latest('id')->firstOrFail();
        $this->assertSame(ChequeType::DEPOSITED, $clearHistory->from_status);
        $this->assertSame(ChequeType::CLEARED, $clearHistory->to_status);
        $cheque->histories->each(fn ($history) => $this->assertBalanced($history->document));

        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertNull($payment->invoice_id);
        $this->assertSame(ChequeType::RECEIVABLE, $payment->cheque->direction);
        $this->assertSame($cheque->sayad_number, $payment->reference_number);
    }

    public function test_cheque_postings_use_configured_subjects_instead_of_fixed_codes(): void
    {
        DB::table('subjects')->insert([
            ['id' => 301, 'code' => '091001', 'name' => 'Configured receivable', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 302, 'code' => '091002', 'name' => 'Configured in collection', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
            ['id' => 303, 'code' => '091003', 'name' => 'Configured payable', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $this->fiscalYearId],
        ]);

        foreach ([
            'cheque_documents_receivable' => 301,
            'cheque_documents_in_collection' => 302,
            'cheque_documents_payable' => 303,
        ] as $key => $subjectId) {
            Config::where('key', $key)->update(['value' => (string) $subjectId]);
            config(['amir.'.$key => (string) $subjectId]);
        }

        $received = $this->receivedCheque();
        $this->assertTrue($received->histories->first()->document->transactions->contains('subject_id', 301));

        $received = $this->service->transition($received, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $depositDocument = $received->histories()->latest('id')->firstOrFail()->document;
        $this->assertTrue($depositDocument->transactions->contains('subject_id', 301));
        $this->assertTrue($depositDocument->transactions->contains('subject_id', 302));

        $issued = $this->issuedCheque();
        $this->assertTrue($issued->histories->first()->document->transactions->contains('subject_id', 303));
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
        $cheque = $this->service->transition($cheque, $this->user, 'endorse', ['customer_id' => $this->vendor->id]);

        $this->assertSame(ChequeType::ENDORSED, $cheque->status);
        $this->assertSame($this->vendor->id, $cheque->endorsed_to_id);
        $payment = Payment::where('cheque_id', $cheque->id)->firstOrFail();
        $this->assertTrue($payment->cheque->is($cheque));
        $this->assertBalanced($payment->document);
    }

    public function test_cheque_show_offers_all_active_year_subjects_for_transfer(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo(Permission::findOrCreate('cheques.transition'));
        $cheque = $this->receivedCheque();
        $foreignSubject = Subject::factory()->create([
            'fiscal_year_id' => FiscalYear::factory()->create()->id,
        ]);

        $this->get(route('cheques.show', $cheque))
            ->assertOk()
            ->assertViewHas('subjects', function ($subjects) use ($foreignSubject) {
                $roots = collect($subjects);
                $this->assertSame([203], array_column($roots->firstWhere('id', 1)['children'], 'id'));
                $this->assertSame([44], array_column($roots->firstWhere('id', 6)['children'], 'id'));
                $this->assertSame('013001', $roots->firstWhere('id', 6)['children'][0]['code']);
                $this->assertTrue($roots->contains('id', 202));

                return ! $roots->contains('id', $foreignSubject->id);
            })
            ->assertSee('padding-inline-start: ${opt.depth * 14}px', false)
            ->assertSee('name="subject_id"', false)
            ->assertDontSee('name="customer_id"', false);
    }

    public function test_cheque_transfer_restores_selected_subject_after_validation_error(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo(Permission::findOrCreate('cheques.transition'));
        $cheque = $this->receivedCheque();

        $this->withSession(['_old_input' => ['subject_id' => '203']])
            ->get(route('cheques.show', $cheque))
            ->assertOk()
            ->assertViewHas('selectedSubject', fn ($subject) => $subject->id === 203
                && $subject->name === 'Bank account subject');
    }

    public function test_received_cheque_can_be_transferred_to_a_non_customer_subject(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $cheque = $this->receivedCheque();

        $this->post(route('cheques.transition', [$cheque, 'endorse']), ['subject_id' => 203])
            ->assertSessionHasNoErrors()->assertRedirect(route('cheques.show', $cheque));

        $cheque->refresh();
        $this->assertSame(ChequeType::ENDORSED, $cheque->status);
        $this->assertNull($cheque->endorsed_to_id);
        $payment = $cheque->histories()->latest('id')->firstOrFail()->payment;
        $this->assertNull($payment->payer_id);
        $this->assertEquals(203, $payment->settlement_subject_id);
        $this->assertEquals(-1000, $payment->document->transactions->where('subject_id', 203)->sum('value'));
        $this->assertEquals(1000, $payment->document->transactions->where('subject_id', 44)->sum('value'));
        $this->assertBalanced($payment->document);
        $this->get(route('cheques.show', $cheque))->assertOk()
            ->assertViewHas('endorsedSubject', fn ($subject) => $subject->id === 203)
            ->assertSee(route('subjects.show', 203));
    }

    public function test_transfer_to_customer_subject_preserves_customer_tracking(): void
    {
        $cheque = $this->service->transition($this->receivedCheque(), $this->user, 'endorse', ['subject_id' => 202]);

        $this->assertSame($this->vendor->id, $cheque->endorsed_to_id);
        $payment = $cheque->histories()->latest('id')->firstOrFail()->payment;
        $this->assertEquals($this->vendor->id, $payment->payer_id);
        $this->assertEquals(202, $payment->settlement_subject_id);
        $this->assertBalanced($payment->document);
    }

    public function test_transfer_rejects_missing_unknown_and_foreign_subjects_without_accounting_changes(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $cheque = $this->receivedCheque();
        $foreignSubject = Subject::factory()->create([
            'fiscal_year_id' => FiscalYear::factory()->create()->id,
        ]);
        $documentCount = Document::count();

        foreach ([null, 999999, $foreignSubject->id] as $subjectId) {
            $this->post(route('cheques.transition', [$cheque, 'endorse']), ['subject_id' => $subjectId])
                ->assertSessionHasErrors('subject_id');
            $this->assertSame(ChequeType::REGISTERED, $cheque->fresh()->status);
            $this->assertSame($documentCount, Document::count());
            $this->assertSame(1, $cheque->histories()->count());
            $this->assertSame(0, $cheque->payments()->count());
        }

        try {
            $this->service->transition($cheque, $this->user, 'endorse', ['subject_id' => $foreignSubject->id]);
            $this->fail('A foreign subject must also be rejected by the service.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
        }
        $this->assertSame($documentCount, Document::count());
    }

    public function test_account_side_subject_must_belong_to_cheque_company(): void
    {
        $otherFiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        DB::table('subjects')->insert([
            'id' => 999,
            'code' => '012001999',
            'name' => 'Foreign customer subject',
            'parent_id' => null,
            'type' => 3,
            'fiscal_year_id' => $otherFiscalYear->id,
        ]);
        DB::table('customers')->where('id', $this->customer->id)->update(['subject_id' => 999]);

        try {
            $this->receivedCheque();
            $this->fail('Expected a cross-company account-side subject to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertSame(0, Cheque::count());
        $this->assertSame(0, Document::count());
    }

    public function test_issued_cheque_posts_documents_payable_then_clears_through_bank_payment(): void
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
        $this->assertSame('100', $linked->cheque_number);
        $this->assertNull($linked->serial);
        $this->assertSame(101, $chequebook->fresh()->next_leaf);
        $this->assertTrue($chequebook->cheques()->whereKey($linked->id)->exists());
        $this->assertNull($unlinked->chequebook_id);
    }

    public function test_chequebook_leaf_allocation_is_sequential_and_rejects_exhaustion(): void
    {
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'PAY',
            'first_leaf' => 100,
            'last_leaf' => 101,
            'next_leaf' => 100,
        ]);
        $data = [
            ...$this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor),
            'bank_account_id' => $this->account->id,
            'chequebook_id' => $chequebook->id,
        ];

        $first = $this->service->register($this->user, $data);
        $data['sayad_number'] = (string) $this->sayadSequence++;
        $second = $this->service->register($this->user, $data);

        $this->assertSame('100', $first->cheque_number);
        $this->assertSame('101', $second->cheque_number);
        $this->assertSame(102, $chequebook->fresh()->next_leaf);

        $data['sayad_number'] = $first->sayad_number;
        $updated = $this->service->update($first, $this->user, $data);
        $this->assertSame('100', $updated->cheque_number);
        $this->assertSame(102, $chequebook->fresh()->next_leaf);

        $data['sayad_number'] = (string) $this->sayadSequence++;
        try {
            $this->service->register($this->user, $data);
            $this->fail('An exhausted chequebook accepted another cheque.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('chequebook_id', $exception->errors());
        }

        $this->assertSame(2, $chequebook->cheques()->count());
        $this->assertSame(102, $chequebook->fresh()->next_leaf);
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
        $this->assertSame('2026-01-01', $cheque->write_date->toDateString());
        $this->assertSame('2026-02-01', $cheque->due_date->toDateString());
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
            'fiscal_year_id',
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
        $this->assertEquals(-2500, $updated->histories->first()->document->transactions->where('subject_id', 44)->sum('value'));
        $this->assertBalanced($updated->histories->first()->document);
    }

    public function test_editing_a_cheque_after_deposit_and_clear_rewrites_every_lifecycle_document(): void
    {
        $cheque = $this->receivedCheque();
        $initialDocumentId = $cheque->histories()->firstOrFail()->document_id;
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $depositDocumentId = $cheque->histories()->latest('id')->firstOrFail()->document_id;
        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $clearHistory = $cheque->histories()->latest('id')->firstOrFail();
        $clearDocumentId = $clearHistory->document_id;
        $historyIds = $cheque->histories()->pluck('id')->all();
        $paymentId = $clearHistory->payment_id;
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer, '2026-03-01', 2500);
        $data['sayad_number'] = $cheque->sayad_number;
        $data['bank_account_id'] = $this->account->id;

        $updated = $this->service->update($cheque, $this->user, $data);
        $histories = $updated->histories()->with('document.transactions')->orderBy('id')->get();

        $this->assertSame(ChequeType::CLEARED, $updated->status);
        $this->assertSame('2500.00', $updated->amount);
        $this->assertSame($historyIds, $histories->pluck('id')->all());
        $this->assertSame([$initialDocumentId, $depositDocumentId, $clearDocumentId], $histories->pluck('document_id')->all());
        $this->assertEquals(-2500, $histories[0]->document->transactions->where('subject_id', 44)->sum('value'));
        $this->assertEquals(-2500, $histories[1]->document->transactions->where('subject_id', 68)->sum('value'));
        $this->assertEquals(-2500, $histories[2]->document->transactions->where('subject_id', $this->account->subject_id)->sum('value'));
        $this->assertSame('2500.00', Payment::findOrFail($paymentId)->amount);
        foreach ($histories->pluck('document') as $document) {
            $this->assertBalanced($document);
        }
    }

    public function test_cheque_view_can_edit_a_cheque_after_a_lifecycle_action(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo(Permission::findOrCreate('cheques.edit'));
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $documentId = $cheque->histories()->whereNotNull('from_status')->firstOrFail()->document_id;

        $this->get(route('cheques.show', $cheque))->assertOk()->assertSee(route('cheques.edit', $cheque));
        $this->put(route('cheques.update', $cheque), [
            'title' => 'Updated received cheque',
            'direction' => ChequeType::RECEIVABLE->value,
            'purpose' => ChequeType::SETTLEMENT->value,
            'amount' => '۳۵۰۰',
            'issue_date' => '1405/01/01',
            'due_date' => '1405/02/01',
            'sayad_number' => $cheque->sayad_number,
            'serial' => 'VIEW-EDIT',
            'cheque_number' => 'VIEW-3500',
            'customer_id' => $this->customer->id,
            'bank_service_fee' => '۰',
            'description' => 'Updated through cheque view',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cheques.show', $cheque));

        $cheque->refresh();
        $history = $cheque->histories()->whereNotNull('from_status')->firstOrFail();
        $this->assertSame(ChequeType::DEPOSITED, $cheque->status);
        $this->assertSame('3500.00', $cheque->amount);
        $this->assertSame($documentId, $history->document_id);
        $this->assertSame(-3500.0, (float) $history->document->transactions->where('subject_id', 68)->sum('value'));
        $this->assertBalanced($history->document);
    }

    public function test_editing_an_executed_guarantee_preserves_its_initial_purpose_and_rebuilds_execution(): void
    {
        $cheque = $this->service->register($this->user, $this->data(ChequeType::RECEIVABLE, ChequeType::GUARANTEE, $this->customer));
        $cheque = $this->service->transition($cheque, $this->user, 'execute');
        $history = $cheque->histories()->whereNotNull('from_status')->firstOrFail();
        $documentId = $history->document_id;
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::GUARANTEE, $this->customer, '2026-03-01', 2500);
        $data['sayad_number'] = $cheque->sayad_number;

        $updated = $this->service->update($cheque, $this->user, $data);
        $initialHistory = $updated->histories()->whereNull('from_status')->firstOrFail();
        $executionHistory = $updated->histories()->whereNotNull('from_status')->firstOrFail();

        $this->assertSame(ChequeType::REGISTERED, $updated->status);
        $this->assertSame(ChequeType::SETTLEMENT, $updated->purpose);
        $this->assertSame(ChequeType::GUARANTEE_RECEIVED, $initialHistory->to_status);
        $this->assertSame(ChequeType::GUARANTEE_RECEIVED, $executionHistory->from_status);
        $this->assertSame(ChequeType::REGISTERED, $executionHistory->to_status);
        $this->assertSame($documentId, $executionHistory->document_id);
        $this->assertEquals(-2500, $executionHistory->document->transactions->where('subject_id', 44)->sum('value'));
        $this->assertBalanced($executionHistory->document);
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

    public function test_invoice_show_can_edit_a_cheque_after_lifecycle_actions_and_rebuild_the_flow(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.payments.update'));
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $historyDocumentIds = $cheque->histories()->orderBy('id')->pluck('document_id')->all();
        $clearPayment = $cheque->histories()->whereNotNull('from_status')->where('to_status', ChequeType::CLEARED)->firstOrFail()->payment;

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('data-cheque-payment-id="'.$payment->id.'"', false);
        $this->get(route('cheques.edit', $cheque))->assertOk();
        $this->put(route('invoices.payments.update', [$invoice, $payment]), [
            'title' => 'Corrected cheque title',
            'amount' => '۶۰۰',
            'bank_service_fee' => '۰',
            'issue_date' => toEnglish(formatDate($invoice->date)),
            'due_date' => toEnglish(formatDate($invoice->date->copy()->addMonth())),
            'sayad_number' => $cheque->sayad_number,
            'serial' => 'EDITED-INVOICE-CHEQUE',
            'cheque_number' => 'EDITED-600',
            'bank_account_id' => $this->account->id,
            'description' => 'Corrected invoice cheque',
        ])->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));

        $payment->refresh();
        $clearPayment->refresh();
        $cheque->refresh();
        $histories = $cheque->histories()->with('document.transactions')->orderBy('id')->get();

        $this->assertSame('600.00', $cheque->amount);
        $this->assertSame('600.00', $payment->amount);
        $this->assertSame('600.00', $clearPayment->amount);
        $this->assertSame($historyDocumentIds, $histories->pluck('document_id')->all());
        $this->assertSame('Corrected cheque title', $histories[1]->document->title);
        $this->assertEquals(-600, $histories[1]->document->transactions->where('subject_id', 68)->sum('value'));
        $this->assertEquals(-600, $histories[2]->document->transactions->where('subject_id', $this->account->subject_id)->sum('value'));
        foreach ($histories->pluck('document') as $document) {
            $this->assertBalanced($document);
        }
        $this->assertTrue($invoice->fresh()->status->isPartiallyPaid());
    }

    public function test_deleting_invoice_cheque_payment_removes_all_cheque_records_and_restores_invoice_status(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $cheque = $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 1000));
        $payment = $invoice->payments()->firstOrFail();
        $documentId = $payment->document_id;
        $transactionIds = $payment->document->transactions()->pluck('id');
        $historyIds = $cheque->histories()->pluck('id');

        $this->delete(route('invoices.payments.destroy', [$invoice, $payment]))->assertRedirect(route('invoices.show', $invoice));

        $this->assertNull(Payment::find($payment->id));
        $this->assertNull(Cheque::withoutGlobalScopes()->find($cheque->id));
        $this->assertSame(0, ChequeHistory::whereIn('id', $historyIds)->count());
        $this->assertNull(Document::find($documentId));
        $this->assertSame(0, Transaction::whereIn('id', $transactionIds)->count());
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
            'title' => 'HTTP invoice cheque title',
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
        $this->assertSame('HTTP invoice cheque title', $cheque->title);
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

        $response = $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('name="cheque_number"', false)
            ->assertSee('id="cheque_payment_fee"', false)
            ->assertSee('id="cheque_issue_date"', false)
            ->assertSee('id="cheque_due_date"', false)
            ->assertSee('preventScroll: true', false);

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//dialog[@id="cheque_payment_modal"]')->length);
        $this->assertSame(0, $xpath->query('//dialog[@id="cheque_edit_modal"]')->length);
        $fields = $xpath->query('//dialog[@id="cheque_payment_modal"]//input[@name and not(@type="hidden")] | //dialog[@id="cheque_payment_modal"]//select[@name] | //dialog[@id="cheque_payment_modal"]//textarea[@name]');
        $this->assertSame([
            'amount', 'bank_service_fee', 'sayad_number', 'cheque_number',
            'issue_date', 'due_date', 'bank_id', 'bank_account_id', 'serial', 'title', 'description',
        ], array_map(fn ($field) => $field->getAttribute('name'), iterator_to_array($fields)));
        $response->assertSee('invoice-cheque-create.window', false)
            ->assertSee('invoice-cheque-edit.window', false);
    }

    public function test_shared_invoice_cheque_modal_restores_input_for_both_create_and_edit(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.payments.store'),
            Permission::findOrCreate('invoices.payments.store-cheque'),
            Permission::findOrCreate('invoices.payments.update'),
        ]);
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 1000);
        $this->service->register($this->user, $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 500));
        $payment = $invoice->payments()->firstOrFail();

        foreach (['create', 'edit'] as $mode) {
            $this->withSession(['_old_input' => [
                'cheque_payment_mode' => $mode,
                'cheque_payment_id' => $mode === 'edit' ? $payment->id : '',
                'amount' => '۶۰۰',
                'bank_service_fee' => '۱۰',
                'issue_date' => '۱۴۰۵/۰۷/۰۱',
                'due_date' => '۱۴۰۵/۰۸/۰۱',
                'sayad_number' => '1234567890123456',
                'cheque_number' => 'RETRY-123',
                'serial' => 'RETRY-SERIAL',
                'bank_account_id' => (string) $this->account->id,
                'description' => 'Keep my changes',
            ]])->get(route('invoices.show', $invoice))->assertOk()
                ->assertViewHas('chequePaymentModalState', function (array $state) use ($mode, $payment) {
                    $this->assertTrue($state['restore']);
                    $this->assertSame($mode === 'edit', $state['editing']);
                    $this->assertSame($mode === 'edit' ? $payment->id : '', $state['paymentId']);
                    $this->assertSame($mode === 'edit' ? '1000' : '500', $state['availableAmount']);
                    $this->assertSame('۶۰۰', $state['amount']);
                    $this->assertSame('۱۰', $state['bankServiceFee']);
                    $this->assertSame('۱۴۰۵/۰۷/۰۱', $state['issueDate']);
                    $this->assertSame('۱۴۰۵/۰۸/۰۱', $state['dueDate']);
                    $this->assertSame('1234567890123456', $state['sayadNumber']);
                    $this->assertSame('RETRY-123', $state['chequeNumber']);
                    $this->assertSame('RETRY-SERIAL', $state['serial']);
                    $this->assertSame((string) $this->account->id, $state['bankAccountId']);
                    $this->assertSame('Keep my changes', $state['description']);

                    return true;
                });
        }
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

        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee('name="bank_account_id"', false)
            ->assertSee('name="chequebook_id"', false)->assertDontSee('name="bank_id"', false)
            ->assertSee(__('If you select a chequebook, it must belong to the selected bank account.'));

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

        $response = $this->post(route('chequebooks.store'), [
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'CRUD',
            'first_leaf' => '۱۰۰',
            'last_leaf' => '۱۴۹',
            'description' => 'Primary payable chequebook',
        ]);

        $chequebook = Chequebook::firstOrFail();
        $response->assertRedirect(route('chequebooks.show', $chequebook));
        $this->assertSame(100, $chequebook->next_leaf);

        $cheque = $this->service->register($this->user, [
            ...$this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor),
            'bank_account_id' => $this->account->id,
            'chequebook_id' => $chequebook->id,
        ]);

        foreach ([
            route('chequebooks.index'),
            route('chequebooks.show', $chequebook),
            route('chequebooks.edit', $chequebook),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->put(route('chequebooks.update', $chequebook), [
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'UPDATED',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 101,
        ])->assertRedirect(route('chequebooks.show', $chequebook));

        $this->assertSame('UPDATED', $chequebook->fresh()->serial_prefix);
        $this->delete(route('chequebooks.destroy', $chequebook))->assertRedirect(route('chequebooks.index'));

        $this->assertNull(Chequebook::find($chequebook->id));
        $this->assertNotNull($cheque->fresh());
        $this->assertNull($cheque->fresh()->chequebook_id);
    }

    public function test_chequebook_index_applies_supported_filters(): void
    {
        $this->withoutMiddleware(CheckPermission::class);

        $matching = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'ACTIVE-BOOK',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 110,
        ]);
        Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'FINISHED-BOOK',
            'first_leaf' => 200,
            'last_leaf' => 249,
            'next_leaf' => 250,
        ]);

        $response = $this->get(route('chequebooks.index', [
            'serial_prefix' => 'ACTIVE',
            'bank_account_id' => $this->account->id,
            'availability' => 'available',
        ]));

        $response->assertOk();
        $response->assertViewHas('chequebooks', function ($chequebooks) use ($matching) {
            return $chequebooks->total() === 1 && $chequebooks->first()->is($matching);
        });
        $response->assertViewHas('bankAccounts', fn ($bankAccounts) => $bankAccounts->contains($this->account));
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
            ->assertSee(route('cheques.create', ['direction' => ChequeType::RECEIVABLE->value]))
            ->assertSee(route('cheques.create', ['direction' => ChequeType::PAYABLE->value]))
            ->assertSee(__('Receive cheque'))
            ->assertSee(__('Issue cheque'))
            ->assertSee(route('cheques.report', $filters));
    }

    public function test_cheque_form_renders_constraints_and_conditional_bank_account_controls(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $chequebook = Chequebook::create([
            'bank_account_id' => $this->account->id,
            'serial_prefix' => 'FORM',
            'first_leaf' => 100,
            'last_leaf' => 149,
            'next_leaf' => 100,
        ]);

        $this->get(route('cheques.create', ['direction' => ChequeType::PAYABLE->value, 'bank_account_id' => $this->account->id, 'chequebook_id' => $chequebook->id]))
            ->assertOk()
            ->assertSee(__('Issue cheque'))
            ->assertSee('x-model="direction"', false)
            ->assertSee('searchSelect({', false)
            ->assertSee('name="customer_id"', false)
            ->assertSee('name="bank_account_id"', false)
            ->assertSee('name="chequebook_id"', false)
            ->assertSee('value="'.$chequebook->id.'" selected', false)
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
        $endorsedCheque = $this->service->transition($endorsedCheque, $this->user, 'endorse', ['customer_id' => $this->customer->id]);
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
            'customer_id' => $accountSide->id,
            'bank_account_id' => null,
            'description' => 'Test cheque',
        ];
    }

    private function feeSubject(): int
    {
        $subjectId = DB::table('subjects')->insertGetId([
            'fiscal_year_id' => getActiveFiscalYear(), 'code' => '040010', 'name' => 'کارمزد بانک', 'type' => 1, 'parent_id' => null,
        ]);
        Config::create([
            'fiscal_year_id' => getActiveFiscalYear(), 'key' => 'bank_service_fee', 'value' => (string) $subjectId,
            'desc' => 'کارمزد بانک', 'type' => 3, 'category' => 1,
        ]);

        return (int) $subjectId;
    }

    public function test_invoice_cheque_fee_posts_to_expense_and_customer_without_changing_face_amount_sell(): void
    {
        $this->assertInvoiceChequeFeePosting(InvoiceType::SELL, ChequeType::RECEIVABLE);
    }

    public function test_invoice_cheque_fee_posts_to_expense_and_customer_without_changing_face_amount_buy(): void
    {
        $this->assertInvoiceChequeFeePosting(InvoiceType::BUY, ChequeType::PAYABLE);
    }

    public function test_invoice_cheque_fee_posts_to_expense_and_customer_without_changing_face_amount_return_sell(): void
    {
        $this->assertInvoiceChequeFeePosting(InvoiceType::RETURN_SELL, ChequeType::PAYABLE);
    }

    public function test_invoice_cheque_fee_posts_to_expense_and_customer_without_changing_face_amount_return_buy(): void
    {
        $this->assertInvoiceChequeFeePosting(InvoiceType::RETURN_BUY, ChequeType::RECEIVABLE);
    }

    private function assertInvoiceChequeFeePosting(InvoiceType $type, ChequeType $direction): void
    {
        $expenseId = $this->feeSubject();
        $invoice = $this->invoice($type, $this->customer, 100);
        $data = [...$this->invoiceChequeData($invoice, $direction, 100), 'bank_service_fee' => 2];
        if ($direction === ChequeType::RECEIVABLE) {
            $data['bank_account_id'] = null;
        }
        $cheque = $this->service->register($this->user, $data);
        $payment = $invoice->payments()->firstOrFail();
        $transactions = $payment->document->transactions;
        $feeHistory = $cheque->histories()->firstOrFail();
        $this->assertBalanced($payment->document);
        $this->assertBalanced($feeHistory->document);
        $this->assertSame('2.00', $feeHistory->bank_service_fee);
        $this->assertSame('2.00', $payment->bank_service_fee);
        $this->assertEquals(100, (float) $cheque->amount);
        $this->assertEquals(100, (float) $payment->amount);
        $this->assertEquals(-2, $feeHistory->document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertCount(4, $feeHistory->document->transactions);
        $this->assertEquals(0, $transactions->where('subject_id', $this->account->subject_id)->sum('value'));
        $expectedCustomer = ($direction === ChequeType::RECEIVABLE ? 100 : -100) + 2;
        $this->assertEquals($expectedCustomer, $transactions->where('subject_id', $this->customer->subject_id)->sum('value'));
        $this->assertEquals($direction === ChequeType::RECEIVABLE ? -100 : 100, $transactions->where('subject_id', $payment->settlement_subject_id)->sum('value'));
        $this->assertTrue($invoice->fresh()->status->isPaid());
        $this->assertSame(1, $cheque->histories()->count());
    }

    public function test_updating_cheque_preserves_fee_when_edit_form_omits_it(): void
    {
        $expenseId = $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $data = [...$this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 100), 'bank_service_fee' => 2];
        $cheque = $this->service->register($this->user, $data);
        unset($data['bank_service_fee']);
        $data['amount'] = 60;
        $this->service->update($cheque, $this->user, $data);
        $payment = $invoice->payments()->firstOrFail();
        $this->assertSame('2.00', $payment->bank_service_fee);
        $this->assertEquals(-2, $cheque->histories()->firstOrFail()->document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertBalanced($payment->document);
        $this->assertTrue($invoice->fresh()->status->isPartiallyPaid());
        $this->assertEquals(40, app(PaymentService::class)->remainingAmount($invoice));
    }

    public function test_cheque_fee_is_not_posted_again_at_deposit_or_clearance_and_is_removed_with_payment(): void
    {
        $expenseId = $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $cheque = $this->service->register($this->user, [
            ...$this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 100), 'bank_service_fee' => 2,
            'bank_account_id' => $this->account->id,
        ]);
        $payment = $invoice->payments()->firstOrFail();
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $documentIds = $cheque->histories()->pluck('document_id')->filter();
        $this->assertSame(1, Transaction::whereIn('document_id', $documentIds)->where('subject_id', $expenseId)->count());
        $this->assertEquals(100, app(PaymentService::class)->paidAmount($invoice));
        app(PaymentService::class)->deletePayment($payment);
        $this->assertSame(0, Transaction::whereIn('document_id', $documentIds)->count());
        $this->assertTrue($invoice->fresh()->status->isApproved());
    }

    public function test_invalid_invoice_cheque_fee_or_overpayment_leaves_no_records_negative(): void
    {
        $this->assertInvalidInvoiceChequeLeavesNoRecords(-2, 100);
    }

    public function test_invalid_invoice_cheque_fee_or_overpayment_leaves_no_records_overpayment(): void
    {
        $this->assertInvalidInvoiceChequeLeavesNoRecords(2, 101);
    }

    private function assertInvalidInvoiceChequeLeavesNoRecords(float $fee, float $amount): void
    {
        $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $before = Document::count();
        try {
            $this->service->register($this->user, [
                ...$this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, $amount), 'bank_service_fee' => $fee,
            ]);
            $this->fail('Expected invalid cheque payment to fail.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, $invoice->payments()->count());
            $this->assertSame(0, Cheque::count());
            $this->assertSame($before, Document::count());
        }
    }

    public function test_invoice_cheque_http_accepts_optional_fee_blank(): void
    {
        $this->assertInvoiceChequeHttpAcceptsFee('', '0.00');
    }

    public function test_invoice_cheque_http_accepts_optional_fee_persian(): void
    {
        $this->assertInvoiceChequeHttpAcceptsFee('۲', '2.00');
    }

    private function assertInvoiceChequeHttpAcceptsFee(string $fee, string $expected): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $data = $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 100);
        $data['issue_date'] = toEnglish(formatDate($invoice->date));
        $data['due_date'] = toEnglish(formatDate($invoice->date->copy()->addMonth()));
        $this->post(route('invoices.payments.store-cheque', $invoice), [...$data, 'bank_service_fee' => $fee])
            ->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame($expected, $invoice->payments()->firstOrFail()->bank_service_fee);
    }

    private function chequeAtStage(string $stage): Cheque
    {
        $cheque = match ($stage) {
            'issued' => $this->issuedCheque(),
            'guarantee' => $this->receivedCheque(ChequeType::GUARANTEE),
            default => $this->receivedCheque(),
        };
        if (in_array($stage, ['deposited', 'cleared'])) {
            $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        }
        if ($stage === 'cleared') {
            $cheque = $this->service->transition($cheque, $this->user, 'clear');
        }

        $cheque->update(['bank_account_id' => $this->account->id]);

        return $cheque->fresh();
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_deposit(): void
    {
        $this->assertChequeStageFeePosting('received', 'deposit');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_clear_received(): void
    {
        $this->assertChequeStageFeePosting('deposited', 'clear');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_bounce_received(): void
    {
        $this->assertChequeStageFeePosting('deposited', 'bounce');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_endorse(): void
    {
        $this->assertChequeStageFeePosting('received', 'endorse');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_return(): void
    {
        $this->assertChequeStageFeePosting('received', 'return');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_clear_issued(): void
    {
        $this->assertChequeStageFeePosting('issued', 'clear');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_bounce_issued(): void
    {
        $this->assertChequeStageFeePosting('issued', 'bounce');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_cancel_issued(): void
    {
        $this->assertChequeStageFeePosting('issued', 'cancel');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_execute_guarantee(): void
    {
        $this->assertChequeStageFeePosting('guarantee', 'execute');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_cancel_guarantee(): void
    {
        $this->assertChequeStageFeePosting('guarantee', 'cancel');
    }

    public function test_each_cheque_stage_accepts_and_posts_its_own_fee_later_fee(): void
    {
        $this->assertChequeStageFeePosting('cleared', 'fee');
    }

    private function assertChequeStageFeePosting(string $stage, string $action): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $expenseId = $this->feeSubject();
        $cheque = $this->chequeAtStage($stage);
        $previousStatus = $cheque->status;
        $paymentCount = $cheque->payments()->count();
        $this->post(route('cheques.transition', [$cheque, $action]), [
            'bank_service_fee' => '۲٬۸۸۰٫۵۰',
            'bank_account_id' => $this->account->id,
            'customer_id' => $this->vendor->id,
            'date' => toEnglish(formatDate('2026-02-01')),
        ])->assertSessionHasNoErrors()->assertRedirect(route('cheques.show', $cheque));

        $history = $cheque->histories()->latest('id')->firstOrFail();
        $this->assertSame('2880.50', $history->bank_service_fee);
        $this->assertSame('2026-02-01', $history->document->date->toDateString());
        $this->assertBalanced($history->document);
        $this->assertEquals(-2880.5, $history->document->transactions->where('subject_id', $expenseId)->sum('value'));
        $expectedCustomer = 2880.5 + match ($action) {
            'return' => -1000,
            'execute' => 1000,
            'bounce' => $stage === 'issued' ? 1000 : 0,
            'cancel' => $stage === 'issued' ? 1000 : 0,
            default => 0,
        };
        $this->assertEquals($expectedCustomer, $history->document->transactions->where('subject_id', $cheque->customer->subject_id)->sum('value'));
        $expectedBank = $action === 'clear' ? ($stage === 'issued' ? 1000 : -1000) : 0;
        $this->assertEquals($expectedBank, $history->document->transactions->where('subject_id', $this->account->subject_id)->sum('value'));
        $this->assertEquals(1000, (float) $cheque->fresh()->amount);
        if ($action === 'fee') {
            $this->assertSame($previousStatus, $cheque->fresh()->status);
            $this->assertSame($paymentCount, $cheque->payments()->count());
        }
    }

    public function test_standalone_guarantee_registration_records_fee_without_settlement(): void
    {
        $this->feeSubject();
        $cheque = $this->service->register($this->user, [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::GUARANTEE, $this->customer),
            'bank_service_fee' => 2,
            'bank_account_id' => $this->account->id,
        ]);
        $history = $cheque->histories()->firstOrFail();
        $this->assertSame('2.00', $history->bank_service_fee);
        $this->assertNotNull($history->document_id);
        $this->assertSame(0, $cheque->payments()->count());
        $this->assertBalanced($history->document);
    }

    public function test_editing_guarantee_fee_replaces_its_document_and_zero_removes_it(): void
    {
        $expenseId = $this->feeSubject();
        $data = [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::GUARANTEE, $this->customer),
            'bank_service_fee' => 2,
            'bank_account_id' => $this->account->id,
        ];
        $cheque = $this->service->register($this->user, $data);
        $firstDocumentId = $cheque->histories()->firstOrFail()->document_id;
        $cheque = $this->service->update($cheque, $this->user, [...$data, 'bank_service_fee' => 3]);
        $history = $cheque->histories()->firstOrFail();
        $this->assertDatabaseMissing('documents', ['id' => $firstDocumentId]);
        $this->assertSame('3.00', $history->bank_service_fee);
        $this->assertEquals(-3, $history->document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertBalanced($history->document);
        $secondDocumentId = $history->document_id;
        $cheque = $this->service->update($cheque, $this->user, [...$data, 'bank_service_fee' => 0]);
        $this->assertDatabaseMissing('documents', ['id' => $secondDocumentId]);
        $this->assertNull($cheque->histories()->firstOrFail()->document_id);
        $this->assertSame('0.00', $cheque->histories()->firstOrFail()->bank_service_fee);
        $this->assertSame(0, $cheque->payments()->count());
    }

    public function test_http_registration_and_transition_reject_invalid_fees_negative(): void
    {
        $this->assertHttpRejectsInvalidFee(-1);
    }

    public function test_http_registration_and_transition_reject_invalid_fees_text(): void
    {
        $this->assertHttpRejectsInvalidFee('invalid');
    }

    public function test_http_registration_and_transition_reject_invalid_fees_array(): void
    {
        $this->assertHttpRejectsInvalidFee(['1']);
    }

    public function test_http_registration_and_transition_reject_invalid_fees_too_large(): void
    {
        $this->assertHttpRejectsInvalidFee('10000000000000000');
    }

    private function assertHttpRejectsInvalidFee(mixed $fee): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer);
        $data['issue_date'] = toEnglish(formatDate($data['issue_date']));
        $data['due_date'] = toEnglish(formatDate($data['due_date']));

        $this->post(route('cheques.store'), [...$data, 'bank_service_fee' => $fee])
            ->assertSessionHasErrors('bank_service_fee');
        $this->assertSame(0, Cheque::count());

        $cheque = $this->receivedCheque();
        $this->post(route('cheques.transition', [$cheque, 'deposit']), [
            'bank_account_id' => $this->account->id,
            'bank_service_fee' => $fee,
        ])->assertSessionHasErrors('bank_service_fee');
        $this->assertSame(ChequeType::REGISTERED, $cheque->fresh()->status);
        $this->assertSame(1, $cheque->histories()->count());
    }

    public function test_service_registration_accepts_a_localized_bank_service_fee(): void
    {
        $this->feeSubject();
        $cheque = $this->service->register($this->user, [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer),
            'bank_service_fee' => ' ۱٬۲۳۴٫۵۶ ',
            'bank_account_id' => $this->account->id,
        ]);

        $history = $cheque->histories()->firstOrFail();
        $this->assertSame('1234.56', $history->bank_service_fee);
        $this->assertBalanced($history->document);
    }

    public function test_invalid_fee_rolls_back_the_transition_negative(): void
    {
        $this->assertInvalidFeeRollsBackTransition(-1);
    }

    public function test_invalid_fee_rolls_back_the_transition_text(): void
    {
        $this->assertInvalidFeeRollsBackTransition('invalid');
    }

    public function test_invalid_fee_rolls_back_the_transition_array(): void
    {
        $this->assertInvalidFeeRollsBackTransition(['1']);
    }

    public function test_invalid_fee_rolls_back_the_transition_too_large(): void
    {
        $this->assertInvalidFeeRollsBackTransition('10000000000000000');
    }

    private function assertInvalidFeeRollsBackTransition(mixed $fee): void
    {
        $cheque = $this->receivedCheque();
        $beforeDocuments = Document::count();
        try {
            $this->service->transition($cheque, $this->user, 'deposit', [
                'bank_account_id' => $this->account->id,
                'bank_service_fee' => $fee,
            ]);
            $this->fail('Expected invalid fee to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_service_fee', $exception->errors());
            $this->assertSame(ChequeType::REGISTERED, $cheque->fresh()->status);
            $this->assertSame(1, $cheque->histories()->count());
            $this->assertSame($beforeDocuments, Document::count());
        }
    }

    public function test_missing_fee_expense_configuration_rolls_back_transition(): void
    {
        $cheque = $this->receivedCheque();
        $beforeDocuments = Document::count();
        try {
            $this->service->transition($cheque, $this->user, 'deposit', [
                'bank_account_id' => $this->account->id,
                'bank_service_fee' => 2,
            ]);
            $this->fail('Expected missing fee configuration to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_service_fee', $exception->errors());
            $this->assertSame(ChequeType::REGISTERED, $cheque->fresh()->status);
            $this->assertSame(1, $cheque->histories()->count());
            $this->assertSame($beforeDocuments, Document::count());
        }
    }

    public function test_fees_accumulate_across_bounce_and_redeposit_without_affecting_invoice(): void
    {
        $expenseId = $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $cheque = $this->service->register($this->user, [
            ...$this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 100),
            'bank_service_fee' => 1,
        ]);
        foreach (['deposit' => 2, 'bounce' => 3] as $action => $fee) {
            $cheque = $this->service->transition($cheque, $this->user, $action, [
                'bank_account_id' => $this->account->id,
                'bank_service_fee' => $fee,
            ]);
        }
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $cheque = $this->service->transition($cheque, $this->user, 'clear', [
            'bank_service_fee' => 4,
        ]);
        $this->assertEquals(10, $cheque->histories()->sum('bank_service_fee'));
        $this->assertEquals(100, app(PaymentService::class)->paidAmount($invoice));
        $this->assertTrue($invoice->fresh()->status->isPaid());
        $documentIds = $cheque->histories()->pluck('document_id')->filter();
        $this->assertEquals(-10, Transaction::whereIn('document_id', $documentIds)->where('subject_id', $expenseId)->sum('value'));
        app(PaymentService::class)->deletePayment($invoice->payments()->firstOrFail());
        $this->assertSame(0, Document::whereIn('id', $documentIds)->count());
        $this->assertSame(0, Transaction::whereIn('document_id', $documentIds)->count());
    }

    public function test_registration_form_and_http_store_accept_a_standalone_fee(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->feeSubject();
        $this->get(route('cheques.create'))->assertOk()
            ->assertSee('name="bank_service_fee"', false)
            ->assertDontSee('name="fee_bank_account_id"', false);
        $data = $this->data(ChequeType::PAYABLE, ChequeType::SETTLEMENT, $this->vendor);
        $data['issue_date'] = toEnglish(formatDate($data['issue_date']));
        $data['due_date'] = toEnglish(formatDate($data['due_date']));
        $this->post(route('cheques.store'), [
            ...$data, 'bank_service_fee' => '۲', 'bank_account_id' => $this->account->id,
        ])->assertSessionHasNoErrors();
        $cheque = Cheque::latest('id')->firstOrFail();
        $this->assertSame('2.00', $cheque->histories()->firstOrFail()->bank_service_fee);
        $this->assertBalanced($cheque->histories()->firstOrFail()->document);
    }

    public function test_fee_forms_are_available_for_each_action_and_after_clearance(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $this->user->givePermissionTo(Permission::findOrCreate('cheques.transition'));
        $cheque = $this->receivedCheque();
        $this->get(route('cheques.show', $cheque))->assertOk()
            ->assertSee('id="cheque_deposit_fee"', false)
            ->assertSee('id="cheque_endorse_fee"', false)
            ->assertSee('id="cheque_return_fee"', false)
            ->assertSee('id="cheque_fee_fee"', false);
        $cheque = $this->service->transition($cheque, $this->user, 'deposit', ['bank_account_id' => $this->account->id]);
        $this->get(route('cheques.show', $cheque))->assertOk()
            ->assertSee('id="cheque_clear_fee"', false)
            ->assertSee('id="cheque_bounce_fee"', false);
        $cheque = $this->service->transition($cheque, $this->user, 'clear');
        $this->get(route('cheques.show', $cheque))->assertOk()->assertSee('id="cheque_fee_fee"', false);
    }

    public function test_fee_rejects_customer_subject_from_another_fiscal_year_of_the_same_company(): void
    {
        $this->feeSubject();
        $cheque = $this->receivedCheque();
        $otherFiscalYear = FiscalYear::factory()->create([
            'company_id' => FiscalYear::findOrFail($this->fiscalYearId)->company_id,
            'year' => 1406,
        ]);
        DB::table('subjects')->where('id', $this->customer->subject_id)->update(['fiscal_year_id' => $otherFiscalYear->id]);
        $beforeDocuments = Document::count();
        try {
            $this->service->transition($cheque, $this->user, 'fee', ['bank_service_fee' => 2]);
            $this->fail('Expected a foreign customer subject to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
            $this->assertSame(1, $cheque->histories()->count());
            $this->assertSame($beforeDocuments, Document::count());
        }
    }

    public function test_zero_fee_needs_no_account_or_expense_configuration(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $cheque = $this->receivedCheque();
        $this->post(route('cheques.transition', [$cheque, 'deposit']), [
            'bank_service_fee' => '', 'bank_account_id' => $this->account->id,
        ])->assertSessionHasNoErrors();
        $history = $cheque->histories()->latest('id')->firstOrFail();
        $this->assertSame('0.00', $history->bank_service_fee);
        $this->assertCount(2, $history->document->transactions);
    }

    public function test_received_cheque_registration_posts_fee_to_customer_without_a_bank_account(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $expenseId = $this->feeSubject();
        $data = $this->data(ChequeType::RECEIVABLE, ChequeType::SETTLEMENT, $this->customer);
        $data['issue_date'] = toEnglish(formatDate($data['issue_date']));
        $data['due_date'] = toEnglish(formatDate($data['due_date']));

        $this->post(route('cheques.store'), [...$data, 'bank_service_fee' => '۲'])->assertSessionHasNoErrors();

        $cheque = Cheque::latest('id')->firstOrFail();
        $this->assertNull($cheque->bank_account_id);
        $document = $cheque->histories()->firstOrFail()->document;
        $this->assertBalanced($document);
        $this->assertEquals(-2, $document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertEquals(1002, $document->transactions->where('subject_id', $this->customer->subject_id)->sum('value'));
    }

    public function test_invoice_received_cheque_submission_posts_fee_to_customer_without_a_bank_account(): void
    {
        $this->withoutMiddleware(CheckPermission::class);
        $expenseId = $this->feeSubject();
        $invoice = $this->invoice(InvoiceType::SELL, $this->customer, 100);
        $data = $this->invoiceChequeData($invoice, ChequeType::RECEIVABLE, 100);
        unset($data['bank_account_id']);
        $data['issue_date'] = toEnglish(formatDate($data['issue_date']));
        $data['due_date'] = toEnglish(formatDate($data['due_date']));

        $this->post(route('invoices.payments.store-cheque', $invoice), [...$data, 'bank_service_fee' => '۲'])
            ->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));

        $payment = $invoice->payments()->firstOrFail();
        $this->assertNull($payment->cheque->bank_account_id);
        $this->assertEquals(100, $payment->amount);
        $this->assertEquals(100, app(PaymentService::class)->paidAmount($invoice));
        $this->assertTrue($invoice->fresh()->status->isPaid());
        $this->assertBalanced($payment->document);
        $this->assertEquals(-2, $payment->document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertEquals(102, $payment->document->transactions->where('subject_id', $this->customer->subject_id)->sum('value'));
        $this->assertEquals(0, $payment->document->transactions->where('subject_id', $this->account->subject_id)->sum('value'));
    }

    public function test_positive_fee_uses_customer_without_a_related_bank_account(): void
    {
        $expenseId = $this->feeSubject();
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'fee', ['bank_service_fee' => 2]);

        $this->assertNull($cheque->bank_account_id);
        $document = $cheque->histories()->latest('id')->firstOrFail()->document;
        $this->assertBalanced($document);
        $this->assertEquals(-2, $document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertEquals(2, $document->transactions->where('subject_id', $this->customer->subject_id)->sum('value'));
    }

    public function test_fee_uses_original_customer_when_cheque_is_transferred_to_another_subject(): void
    {
        $expenseId = $this->feeSubject();
        $cheque = $this->receivedCheque();
        $cheque = $this->service->transition($cheque, $this->user, 'endorse', [
            'subject_id' => $this->vendor->subject_id,
            'bank_service_fee' => 2,
        ]);

        $document = $cheque->histories()->latest('id')->firstOrFail()->document;
        $this->assertBalanced($document);
        $this->assertEquals(-2, $document->transactions->where('subject_id', $expenseId)->sum('value'));
        $this->assertEquals(2, $document->transactions->where('subject_id', $this->customer->subject_id)->sum('value'));
        $this->assertEquals(-1000, $document->transactions->where('subject_id', $this->vendor->subject_id)->sum('value'));
    }

    public function test_fee_history_export_and_import_uses_existing_document_reference(): void
    {
        $this->feeSubject();
        $cheque = $this->service->register($this->user, [
            ...$this->data(ChequeType::RECEIVABLE, ChequeType::GUARANTEE, $this->customer),
            'bank_service_fee' => 2,
            'bank_account_id' => $this->account->id,
        ]);
        $history = $cheque->histories()->firstOrFail();
        $export = FiscalYearService::exportData(getActiveFiscalYear(), ['cheques']);
        $this->assertSame('2.00', $export['cheque_histories'][0]['bank_service_fee']);
        $this->assertArrayNotHasKey('fee_bank_account_id', $export['cheque_histories'][0]);
        $this->assertArrayNotHasKey('fee_document_id', $export['cheque_histories'][0]);
        $replacement = $this->receivedCheque(ChequeType::GUARANTEE);
        $newDocument = $history->document->replicate();
        $newDocument->save();
        $method = new \ReflectionMethod(FiscalYearService::class, '_importChequeHistories');
        $method->invoke(null, $export['cheque_histories'], getActiveFiscalYear(),
            [$cheque->id => $replacement->id], [$history->document_id => $newDocument->id], []);
        $imported = $replacement->histories()->latest('id')->firstOrFail();
        $this->assertSame('2.00', $imported->bank_service_fee);
        $this->assertSame($newDocument->id, $imported->document_id);
    }

    public function test_valid_fees_are_normalized_and_rounded_omitted(): void
    {
        $this->assertFeeNormalization([], 0.0);
    }

    public function test_valid_fees_are_normalized_and_rounded_null(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => null], 0.0);
    }

    public function test_valid_fees_are_normalized_and_rounded_blank(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => ''], 0.0);
    }

    public function test_valid_fees_are_normalized_and_rounded_zero(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => 0], 0.0);
    }

    public function test_valid_fees_are_normalized_and_rounded_rounded(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => '12.345'], 12.35);
    }

    public function test_valid_fees_are_normalized_and_rounded_english_separators(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => '1,234.56'], 1234.56);
    }

    public function test_valid_fees_are_normalized_and_rounded_persian_separators(): void
    {
        $this->assertFeeNormalization(['bank_service_fee' => ' ۱٬۲۳۴٫۵۶ '], 1234.56);
    }

    private function assertFeeNormalization(array $data, float $expected): void
    {
        $this->assertSame($expected, app(ChequeService::class)->validateBankServiceFee($data));
    }

    public function test_invalid_fees_raise_a_field_validation_error_negative(): void
    {
        $this->assertInvalidFeeValidationError(-1);
    }

    public function test_invalid_fees_raise_a_field_validation_error_text(): void
    {
        $this->assertInvalidFeeValidationError('invalid');
    }

    public function test_invalid_fees_raise_a_field_validation_error_array(): void
    {
        $this->assertInvalidFeeValidationError(['1']);
    }

    public function test_invalid_fees_raise_a_field_validation_error_upper_limit(): void
    {
        $this->assertInvalidFeeValidationError('10000000000000000');
    }

    public function test_invalid_fees_raise_a_field_validation_error_infinity(): void
    {
        $this->assertInvalidFeeValidationError(INF);
    }

    public function test_invalid_fees_raise_a_field_validation_error_not_a_number(): void
    {
        $this->assertInvalidFeeValidationError(NAN);
    }

    private function assertInvalidFeeValidationError(mixed $fee): void
    {
        try {
            app(ChequeService::class)->validateBankServiceFee(['bank_service_fee' => $fee]);
            $this->fail('Expected invalid fee to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_service_fee', $exception->errors());
        }
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
            'bank_account_id' => $this->account->id,
        ];
    }

    private function assertBalanced(?Document $document): void
    {
        $this->assertNotNull($document);
        $this->assertNotNull($document->approved_at);
        $this->assertEqualsWithDelta(0, $document->transactions()->sum('value'), 0.001);
    }
}
