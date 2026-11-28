<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class MoadianSentInvoiceSafeguardTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $fiscalYearId;

    protected int $nextInvoiceNumber = 9000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->app->setLocale('en');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->fiscalYearId = FiscalYear::factory()->create(['year' => 1405])->id;
        config(['active-fiscal-year-id' => $this->fiscalYearId]);
        Cache::forever('active_fiscal_year_id', $this->fiscalYearId);
        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYearId]);
        $this->user = User::factory()->create();
        $this->user->fiscalYears()->attach($this->fiscalYearId);
        foreach (['show', 'edit', 'update', 'destroy', 'change-status', 'approve', 'conflicts', 'group-action'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate('invoices.'.$permission));
        }
        $this->actingAs($this->user);
        $this->importSubjects($this->fiscalYearId);
        $this->importConfigs($this->fiscalYearId);
        ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $group = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
    }

    private function markSent(Invoice $invoice, string $status = 'SUCCESS'): Invoice
    {
        $invoice->moadianHistories()->create(['data' => ['status' => $status]]);

        return $invoice->fresh();
    }

    private function snapshot(): array
    {
        $state = [];
        foreach (['invoices', 'invoice_items', 'documents', 'transactions', 'products', 'warehouse_product_stocks', 'payments'] as $table) {
            // Touching a row's updated_at is not a data change.
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => Arr::except((array) $row, ['updated_at']))->toJson();
        }

        return $state;
    }

    public function test_sent_helper_treats_success_and_unknown_as_sent_and_only_success_blocks_resending(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 1, 100)])['invoice'];

        $this->assertFalse($invoice->isSentToMoadian());
        $invoice->moadianHistories()->create(['data' => ['status' => 'FAILED']]);
        $this->assertFalse($invoice->fresh()->isSentToMoadian());
        $invoice->moadianHistories()->create(['data' => ['status' => 'unknown']]);
        $this->assertTrue($invoice->fresh()->isSentToMoadian());
        $this->assertFalse($invoice->fresh()->hasMoadianSuccess());
        $invoice->moadianHistories()->create(['data' => ['status' => 'SUCCESS']]);
        $this->assertTrue($invoice->fresh()->hasMoadianSuccess());
    }

    #[DataProvider('sentStatuses')]
    public function test_edit_update_and_delete_are_refused_for_a_sent_invoice(string $moadianStatus): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice'], $moadianStatus);
        $before = $this->snapshot();

        $this->get(route('invoices.edit', $invoice))->assertSessionHas('error', 'This invoice was sent to Moadian and cannot be edited.');

        $number = ++$this->nextInvoiceNumber;
        $payload = [
            'title' => 'Changed title',
            'date' => now()->toDateString(),
            'invoice_type' => InvoiceType::SELL->valueName(),
            'customer_id' => $this->customer->id,
            'document_number' => $number,
            'invoice_number' => $number,
            'invoice_id' => $invoice->id,
            'approve' => '1',
            'warehouse_id' => $product->warehouseStocks()->value('warehouse_id'),
            'transactions' => [['item_id' => 'product-'.$product->id, 'quantity' => 1, 'unit' => 100, 'off' => 0, 'vat' => 0, 'total' => 100]],
        ];
        $this->put(route('invoices.update', $invoice), $payload)->assertSessionHas('error', 'This invoice was sent to Moadian and cannot be edited.');
        $this->delete(route('invoices.destroy', $invoice).'?confirm=1')->assertSessionHas('error', 'This invoice was sent to Moadian and cannot be deleted.');

        $this->assertSame($before, $this->snapshot());
    }

    public static function sentStatuses(): array
    {
        return ['success' => ['SUCCESS'], 'unknown' => ['UNKNOWN']];
    }

    public function test_failed_moadian_invoice_can_still_be_deleted(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)], approved: false)['invoice'];
        $invoice->moadianHistories()->create(['data' => ['status' => 'FAILED']]);

        $this->delete(route('invoices.destroy', $invoice))->assertSessionHas('info');
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_conflicts_page_and_group_action_refuse_a_sent_target(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $purchase = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $this->sell([$this->productItem($product, 2, 150)]);
        $this->markSent($purchase);
        $before = $this->snapshot();

        $this->get(route('invoices.conflicts', $purchase))->assertRedirect(route('invoices.show', $purchase))->assertSessionHas('error');
        $this->post(route('invoices.group-action', $purchase))->assertRedirect(route('invoices.show', $purchase))->assertSessionHas('error');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(InvoiceStatus::APPROVED, $purchase->fresh()->status);
    }

    public function test_group_action_inactivates_a_sent_dependent_by_status_only(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0, 'oversell' => true]);
        $purchase = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $sent = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice']);
        $unsent = $this->sell([$this->productItem($product, 1, 150)])['invoice'];
        $sentDocumentId = $sent->document_id;
        $sentCog = (float) $sent->items()->firstOrFail()->cog_after;
        $sentQuantityAt = $sent->items()->firstOrFail()->quantity_at;

        $conflicts = $this->get(route('invoices.conflicts', $purchase))->assertOk();
        $conflicts->assertSeeText('product stock and cost of goods are not adjusted');

        $this->post(route('invoices.group-action', $purchase))->assertRedirect(route('invoices.show', $purchase));

        $sent->refresh();
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $sent->status);
        $this->assertSame($sentDocumentId, $sent->document_id);
        $this->assertDatabaseHas('documents', ['id' => $sentDocumentId]);
        $this->assertEqualsWithDelta($sentCog, $sent->items()->firstOrFail()->cog_after, 0.001);
        $this->assertEquals($sentQuantityAt, $sent->items()->firstOrFail()->quantity_at);

        // Unsent invoices keep the full reversal.
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $unsent->fresh()->status);
        $this->assertNull($unsent->fresh()->document_id);
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $purchase->fresh()->status);
        $this->assertNull($purchase->fresh()->document_id);
    }

    public function test_group_action_is_refused_on_the_server_when_a_conflicting_product_cannot_oversell(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0, 'oversell' => false]);
        // An earlier unapproved sale without stock conflicts with the later purchase, and the product cannot oversell.
        $this->sell([$this->productItem($product, 5, 150)], approved: false);
        $purchase = $this->buy([$this->productItem($product, 5, 100)], approved: false)['invoice'];
        $this->assertFalse(app(GroupActionService::class)->canResolveConflicts($purchase));

        $before = $this->snapshot();
        $this->post(route('invoices.group-action', $purchase))->assertRedirect(route('invoices.conflicts', $purchase))->assertSessionHas('error');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_detail_page_round_trips_status_only_inactivation_for_a_sent_invoice(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice'], 'UNKNOWN');
        $url = route('invoices.show', $invoice);
        $deactivate = route('invoices.approve', $invoice);
        $before = $this->snapshot();
        $item = $invoice->items()->firstOrFail();
        $cogAfter = (float) $item->cog_after;
        $quantityAt = $item->quantity_at;
        $averageCost = (float) $product->fresh()->average_cost;

        $this->get($url)->assertOk()
            ->assertSee($deactivate, false)
            ->assertDontSee(route('invoices.edit', $invoice), false)
            ->assertDontSee(route('invoices.change-status', [$invoice, 'unapproved']), false)
            ->assertSeeText('product stock and cost of goods are not adjusted');

        $this->from($url)->post($deactivate)->assertSessionHas('success');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->status);
        $this->assertNotNull($invoice->document_id);

        $activate = route('invoices.change-status', [$invoice, 'approved']);
        $this->get($url)->assertOk()->assertSee($activate, false);
        $this->post($activate)->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertSame($before, $this->snapshot());
        $this->assertEqualsWithDelta($cogAfter, $invoice->items()->firstOrFail()->cog_after, 0.001);
        $this->assertEquals($quantityAt, $invoice->items()->firstOrFail()->quantity_at);
        $this->assertEqualsWithDelta($averageCost, $product->fresh()->average_cost, 0.001);
    }

    public function test_sent_invoice_cannot_be_fully_unapproved(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice']);
        $before = $this->snapshot();

        $this->post(route('invoices.change-status', [$invoice, 'unapproved']))->assertSessionHas('error');
        $this->assertSame($before, $this->snapshot());

        $this->expectException(ValidationException::class);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'unapproved');
    }

    public function test_paid_sent_invoice_needs_confirmation_and_reactivation_rederives_paid_status(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $cashBook = Subject::withoutGlobalScopes()->findOrFail((int) config('amir.cash_book'));
        $cashSubject = Subject::factory()->withParent($cashBook)->create(['name' => 'صندوق', 'fiscal_year_id' => $this->fiscalYearId]);
        $decision = app(PaymentService::class)->createPayment($this->user, $invoice, [
            'amount' => (float) $invoice->amount,
            'subject_id' => $cashSubject->id,
        ]);
        $this->assertFalse($decision->hasErrors(), $decision->toText());
        $payment = $invoice->payments()->sole();
        $invoice = $this->markSent($invoice);
        $this->assertSame(InvoiceStatus::PAID, $invoice->status);
        $before = $this->snapshot();
        $deactivate = route('invoices.approve', $invoice);

        $this->post($deactivate)->assertSessionHas('warning');
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);

        $this->post($deactivate.'?confirm=1')->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        $this->assertNotNull($payment->fresh()->document_id);

        $this->post(route('invoices.change-status', [$invoice, 'approved']))->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_legacy_sent_invoice_without_document_is_reactivated_through_the_normal_approve_flow(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        // Legacy state: fully deactivated before the UNKNOWN history existed (document deleted, stock reversed).
        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved_inactive');
        $invoice = $this->markSent($invoice, 'UNKNOWN');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->status);
        $this->assertNull($invoice->document_id);

        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNotNull($invoice->document_id);
    }

    public function test_bulk_approve_inactive_reactivates_a_sent_invoice_by_status_only(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice']);
        (new InvoiceService)->changeInvoiceStatus($invoice, 'approved_inactive');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        $documentId = $invoice->fresh()->document_id;
        $before = $this->snapshot();
        $before['invoices'] = DB::table('invoices')->orderBy('id')->get()->map(fn ($row) => Arr::except((array) $row, ['updated_at', 'status']))->toJson();

        app(GroupActionService::class)->approveInactiveInvoices();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertSame($documentId, $invoice->document_id);
        $after = $this->snapshot();
        $after['invoices'] = DB::table('invoices')->orderBy('id')->get()->map(fn ($row) => Arr::except((array) $row, ['updated_at', 'status']))->toJson();
        $this->assertSame($before, $after);
    }

    public function test_service_refuses_update_and_delete_of_a_sent_invoice(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->markSent($this->sell([$this->productItem($product, 2, 150)])['invoice']);
        $before = $this->snapshot();

        try {
            InvoiceService::deleteInvoice($invoice->id);
            $this->fail('Deleting a sent invoice must be refused.');
        } catch (ValidationException) {
        }

        try {
            InvoiceService::updateInvoice($invoice->id, ['invoice_type' => $invoice->invoice_type->valueName()]);
            $this->fail('Updating a sent invoice must be refused.');
        } catch (ValidationException) {
        }

        $this->assertSame($before, $this->snapshot());
    }
}
