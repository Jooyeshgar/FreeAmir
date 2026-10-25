<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoiceActivationTest extends TestCase
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
        foreach (['invoices.show', 'invoices.change-status', 'invoices.approve'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission));
        }
        $this->actingAs($this->user);
        $this->importSubjects($this->fiscalYearId);
        $this->importConfigs($this->fiscalYearId);
        ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $group = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
        $this->customer = Customer::factory()->withGroup($group)->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
    }

    public static function invoiceTypes(): array
    {
        return array_map(fn ($type) => [$type], [InvoiceType::BUY, InvoiceType::SELL, InvoiceType::RETURN_BUY, InvoiceType::RETURN_SELL]);
    }

    #[DataProvider('invoiceTypes')]
    public function test_detail_actions_round_trip_accounting_and_inventory(InvoiceType $type): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $purchase = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $invoice = match ($type) {
            InvoiceType::BUY => $purchase,
            InvoiceType::SELL => $this->sell([$this->productItem($product, 5, 150)])['invoice'],
            InvoiceType::RETURN_BUY => $this->returnBuy([$this->productItem($product, 5, 100)], $purchase->id)['invoice'],
            InvoiceType::RETURN_SELL => $this->returnSell([$this->productItem($product, 2, 150)], $this->sell([$this->productItem($product, 5, 150)])['invoice']->id)['invoice'],
        };
        $quantity = (float) $product->fresh()->quantity;
        $averageCost = (float) $product->fresh()->average_cost;
        $stock = $product->warehouseStocks()->firstOrFail();
        $oldDocumentId = $invoice->document_id;
        $url = route('invoices.show', $invoice);
        $deactivate = route('invoices.approve', $invoice);

        $unapprove = route('invoices.change-status', [$invoice, 'unapproved']);
        $this->get($url)->assertOk()
            ->assertSee($deactivate, false)->assertSeeText('Approve inactive')
            ->assertSee($unapprove, false)->assertSeeText('Unapprove');
        $this->from($url)->post($deactivate.'?confirm=1')->assertRedirect($url)->assertSessionHas('success', 'Invoice deactivated successfully.');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
        $this->assertDatabaseMissing('documents', ['id' => $oldDocumentId]);
        $this->assertDatabaseMissing('transactions', ['document_id' => $oldDocumentId]);
        $this->assertNotEquals($quantity, (float) $product->fresh()->quantity);

        $activate = route('invoices.change-status', [$invoice, 'approved']);
        $this->get($url)->assertOk()->assertSee($activate, false)->assertSeeText('Activate')->assertDontSee($deactivate, false)->assertDontSee($unapprove, false);
        $this->post($activate.'?confirm=1')->assertRedirect($url)->assertSessionHas('success', 'Invoice activated successfully.');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertNotNull($invoice->document_id);
        $this->assertNotEquals($oldDocumentId, $invoice->document_id);
        $this->assertEqualsWithDelta(0, $invoice->document->transactions()->sum('value'), 0.01);
        $this->assertEqualsWithDelta($quantity, $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta($averageCost, $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta($stock->quantity, $stock->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta($stock->average_cost, $stock->fresh()->average_cost, 0.001);
    }

    public static function retainedPayments(): array
    {
        return [
            'no payments' => [0, InvoiceStatus::APPROVED],
            'partial payment' => [0.5, InvoiceStatus::PARTIALLY_PAID],
            'full payment' => [1, InvoiceStatus::PAID],
        ];
    }

    #[DataProvider('retainedPayments')]
    public function test_detail_reactivation_restores_settlement_status(float $paidFraction, InvoiceStatus $expectedStatus): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $payment = null;
        $oldPaymentDocumentId = null;

        if ($paidFraction > 0) {
            $cashBook = Subject::withoutGlobalScopes()->findOrFail((int) config('amir.cash_book'));
            $cashSubject = Subject::factory()->withParent($cashBook)->create([
                'name' => 'صندوق',
                'fiscal_year_id' => $this->fiscalYearId,
            ]);
            $decision = app(PaymentService::class)->createPayment($this->user, $invoice, [
                'amount' => (float) $invoice->amount * $paidFraction,
                'subject_id' => $cashSubject->id,
            ]);
            $this->assertFalse($decision->hasErrors(), $decision->toText());
            $payment = $invoice->payments()->sole();
            $oldPaymentDocumentId = $payment->document_id;
            $this->assertNotNull($oldPaymentDocumentId);
        }
        $this->assertSame($expectedStatus, $invoice->fresh()->status);
        $quantity = (float) $product->fresh()->quantity;

        // Retained payments also occur when dependency resolution deactivates an invoice.
        app(GroupActionService::class)->inactivateDependentInvoices($invoice->fresh('items'));
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
        if ($payment) {
            $this->assertNull($payment->fresh()->document_id);
            $this->assertDatabaseMissing('documents', ['id' => $oldPaymentDocumentId]);
        }

        $url = route('invoices.show', $invoice);
        $activate = route('invoices.change-status', [$invoice, 'approved']);
        $this->get($url)->assertOk()->assertSee($activate, false)->assertSeeText('Activate');
        $this->from($url)->post($activate.'?confirm=1')
            ->assertRedirect($url)->assertSessionHas('success', 'Invoice activated successfully.');

        $invoice->refresh();
        $this->assertSame($expectedStatus, $invoice->status);
        $this->assertNotNull($invoice->document_id);
        $this->assertEqualsWithDelta(0, $invoice->document->transactions()->sum('value'), 0.01);
        $this->assertEqualsWithDelta($quantity, $product->fresh()->quantity, 0.001);
        $this->assertSame($payment ? 1 : 0, $invoice->payments()->count());
        if ($payment) {
            $payment->refresh();
            $this->assertNotNull($payment->document_id);
            $this->assertNotEquals($oldPaymentDocumentId, $payment->document_id);
            $this->assertEqualsWithDelta((float) $invoice->amount * $paidFraction, $payment->amount, 0.01);
            $this->assertEqualsWithDelta(0, $payment->document->transactions()->sum('value'), 0.01);
        }
    }

    public function test_unapprove_remains_available_alongside_deactivation(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $url = route('invoices.show', $invoice);

        $this->from($url)->post(route('invoices.change-status', [$invoice, 'unapproved']))
            ->assertRedirect($url)->assertSessionHas('success', 'Invoice unapproved successfully.');

        $this->assertSame(InvoiceStatus::UNAPPROVED, $invoice->fresh()->status);
        $this->get($url)->assertOk()
            ->assertSeeText('Approve')
            ->assertDontSeeText('Activate')
            ->assertDontSeeText('Deactivate')
            ->assertDontSee(route('invoices.change-status', [$invoice, 'approved_inactive']), false);
    }

    public function test_inactive_approval_uses_approval_permission_and_rejects_other_states(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $this->user->revokePermissionTo('invoices.change-status');
        $url = route('invoices.approve', $invoice);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($url, false);
        $this->post($url)->assertSessionHas('success', 'Invoice deactivated successfully.');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);

        $invoice->refresh();
        foreach (InvoiceStatus::cases() as $status) {
            if ($status->isApproved()) {
                continue;
            }
            $invoice->update(['status' => $status]);
            $documentId = $invoice->document_id;
            $this->post($url)->assertSessionHas('error', 'Invalid status action.');
            $this->assertSame($status, $invoice->fresh()->status);
            $this->assertSame($documentId, $invoice->fresh()->document_id);
        }
    }

    public function test_single_and_bulk_reactivation_restore_only_previously_approved_ancillary_costs(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $data = [
            'invoice_id' => $invoice->id, 'customer_id' => $this->customer->id,
            'fiscal_year_id' => $this->fiscalYearId, 'date' => $invoice->date->toDateString(),
            'type' => 'Shipping', 'amount' => 200, 'vatPrice' => 0,
            'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 200]],
        ];
        $approved = AncillaryCostService::createAncillaryCost($this->user, $data, true)['ancillaryCost'];
        $draft = AncillaryCostService::createAncillaryCost($this->user, $data, false)['ancillaryCost'];
        $this->assertSame(InvoiceStatus::APPROVED, $approved->status);
        $averageCost = (float) $product->fresh()->average_cost;

        foreach ([false, true] as $bulk) {
            $this->post(route('invoices.approve', $invoice))->assertSessionHas('success');
            $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $approved->fresh()->status);
            $this->assertNull($approved->fresh()->document_id);
            $this->assertSame(InvoiceStatus::UNAPPROVED, $draft->fresh()->status);
            if ($bulk) {
                app(GroupActionService::class)->approveInactiveInvoices();
            } else {
                $this->post(route('invoices.change-status', [$invoice, 'approved']))->assertSessionHas('success');
            }
            $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
            $this->assertSame(InvoiceStatus::APPROVED, $approved->fresh()->status);
            $this->assertNotNull($approved->fresh()->document_id);
            $this->assertSame(InvoiceStatus::UNAPPROVED, $draft->fresh()->status);
            $this->assertEqualsWithDelta($averageCost, $product->fresh()->average_cost, 0.001);
        }
    }

    public static function costReactivationPaths(): array
    {
        return ['individual' => [false], 'bulk' => [true]];
    }

    #[DataProvider('costReactivationPaths')]
    public function test_reactivation_restores_own_inactive_costs_in_date_order(bool $bulk): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $createInactiveCost = function ($invoice, $product, string $date) {
            $cost = AncillaryCostService::createAncillaryCost($this->user, [
                'invoice_id' => $invoice->id,
                'customer_id' => $this->customer->id,
                'fiscal_year_id' => $this->fiscalYearId,
                'date' => $date,
                'type' => 'Shipping', 'amount' => 200, 'vatPrice' => 0,
                'ancillaryCosts' => [['product_id' => $product->id, 'amount' => 200]],
            ], false)['ancillaryCost'];
            $cost->update(['status' => InvoiceStatus::APPROVED_INACTIVE]);

            return $cost;
        };

        // Creation order deliberately differs from the required approval order.
        $lateCost = $createInactiveCost($invoice, $product, $invoice->date->copy()->addDay()->toDateString());
        $earlyCost = $createInactiveCost($invoice, $product, $invoice->date->toDateString());
        $otherProduct = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $otherInvoice = $this->buy([$this->productItem($otherProduct, 10, 100)])['invoice'];
        $otherCost = $createInactiveCost($otherInvoice, $otherProduct, $otherInvoice->date->toDateString());

        $this->post(route('invoices.approve', $invoice))->assertSessionHas('success');
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        if ($bulk) {
            app(GroupActionService::class)->approveInactiveInvoices();
        } else {
            $this->post(route('invoices.change-status', [$invoice, 'approved']))->assertSessionHas('success');
            $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $otherCost->fresh()->status);
            $this->assertNull($otherCost->fresh()->document_id);
        }

        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
        foreach ([$earlyCost, $lateCost] as $cost) {
            $this->assertSame(InvoiceStatus::APPROVED, $cost->fresh()->status);
            $this->assertNotNull($cost->fresh()->document_id);
            $this->assertEqualsWithDelta(0, $cost->fresh()->document->transactions()->sum('value'), 0.01);
        }
        $this->assertLessThan($lateCost->fresh()->document_id, $earlyCost->fresh()->document_id);
        $this->assertEqualsWithDelta(120, $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(120, $product->warehouseStocks()->firstOrFail()->average_cost, 0.001);
    }

    public function test_inactive_purchase_reverses_multiple_costs_and_restores_prior_inventory_value(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $prior = $this->buy([$this->productItem($product, 10, 100)])['invoice'];
        $createCost = function ($invoice, int $amount) use ($product) {
            return AncillaryCostService::createAncillaryCost($this->user, [
                'invoice_id' => $invoice->id, 'customer_id' => $this->customer->id,
                'fiscal_year_id' => $this->fiscalYearId, 'date' => $invoice->date->toDateString(),
                'type' => 'Shipping', 'amount' => $amount, 'vatPrice' => 0,
                'ancillaryCosts' => [['product_id' => $product->id, 'amount' => $amount]],
            ], true)['ancillaryCost'];
        };
        $priorCost = $createCost($prior, 100);
        $invoice = $this->buy([$this->productItem($product, 20, 200)])['invoice'];
        $costs = collect([$createCost($invoice, 200), $createCost($invoice, 100)]);
        $documents = $costs->pluck('document_id')->push($invoice->document_id);
        $stock = $product->warehouseStocks()->firstOrFail();
        $this->assertEqualsWithDelta(180, $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(180, $stock->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(180, $invoice->items()->firstOrFail()->cog_after, 0.001);

        $this->post(route('invoices.approve', $invoice))->assertSessionHas('success');

        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        foreach ($costs as $cost) {
            $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $cost->fresh()->status);
            $this->assertNull($cost->fresh()->document_id);
        }
        foreach ($documents as $documentId) {
            $this->assertDatabaseMissing('documents', ['id' => $documentId]);
            $this->assertDatabaseMissing('transactions', ['document_id' => $documentId]);
        }
        $this->assertSame(InvoiceStatus::APPROVED, $priorCost->fresh()->status);
        $this->assertEqualsWithDelta(10, $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(10, $stock->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(110, $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(110, $stock->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(110, $invoice->items()->firstOrFail()->cog_after, 0.001);
        $this->assertEqualsWithDelta(110, $prior->items()->firstOrFail()->cog_after, 0.001);

        $this->post(route('invoices.change-status', [$invoice, 'approved']))->assertSessionHas('success');
        $this->assertEqualsWithDelta(30, $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(30, $stock->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(180, $product->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(180, $stock->fresh()->average_cost, 0.001);
        $this->assertEqualsWithDelta(180, $invoice->items()->firstOrFail()->cog_after, 0.001);
    }

    public function test_dependencies_and_return_links_block_deactivation_without_side_effects(): void
    {
        $product = $this->createProduct();
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $dependent = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $quantity = (float) $product->fresh()->quantity;
        $documentId = $invoice->document_id;
        $url = route('invoices.approve', $invoice);
        $this->post($url.'?confirm=1')->assertSessionHas('error');
        $this->assertSame($quantity, (float) $product->fresh()->quantity);
        $this->unapproveInvoice($dependent);
        $this->returnBuy([$this->productItem($product, 1, 100)], $invoice->id, false);
        $this->post($url.'?confirm=1')->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
        $this->assertSame($documentId, $invoice->fresh()->document_id);
        $this->assertEqualsWithDelta($quantity + 2, $product->fresh()->quantity, 0.001);
    }

    public function test_approve_inactive_preserves_conflicts_without_changing_dependent_invoices(): void
    {
        $product = $this->createProduct(['quantity' => 10, 'average_cost' => 100]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $dependent = $this->sell([$this->productItem($product, 1, 150)])['invoice'];

        $documentId = $invoice->document_id;
        $this->post(route('invoices.approve', $invoice))->assertSessionHas('error');

        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
        $this->assertSame(InvoiceStatus::APPROVED, $dependent->fresh()->status);
        $this->assertSame($documentId, $invoice->fresh()->document_id);
        $this->assertSame(7.0, (float) $product->fresh()->quantity);
    }

    public function test_resolve_conflicts_option_deactivates_selected_invoice_and_dependencies(): void
    {
        $this->user->givePermissionTo([
            Permission::findOrCreate('invoices.conflicts'),
            Permission::findOrCreate('invoices.group-action'),
        ]);
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $dependent = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $later = $this->sell([$this->productItem($product, 1, 150)])['invoice'];
        $documents = [$invoice->document_id, $dependent->document_id, $later->document_id];
        $detailsUrl = route('invoices.show', $invoice);
        $conflictsUrl = route('invoices.conflicts', $invoice);
        $resolveUrl = route('invoices.group-action', $invoice);

        $this->get($detailsUrl)->assertOk()
            ->assertSee($conflictsUrl, false)
            ->assertSeeText('Resolve conflicts and deactivate invoice');
        $this->get($conflictsUrl)->assertOk()
            ->assertSee($resolveUrl, false)
            ->assertSeeText('Resolve conflicts and deactivate invoice');

        $this->post($resolveUrl)->assertRedirect($detailsUrl);

        foreach ([$invoice, $dependent, $later] as $affected) {
            $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $affected->fresh()->status);
            $this->assertNull($affected->fresh()->document_id);
        }
        foreach ($documents as $documentId) {
            $this->assertDatabaseMissing('documents', ['id' => $documentId]);
            $this->assertDatabaseMissing('transactions', ['document_id' => $documentId]);
        }
        $this->assertEqualsWithDelta(0, $product->fresh()->quantity, 0.001);
        $this->assertEqualsWithDelta(0, $product->warehouseStocks()->firstOrFail()->quantity, 0.001);
    }

    public function test_resolve_conflicts_option_requires_existing_permission_and_open_year(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.conflicts'));
        Permission::findOrCreate('invoices.group-action');
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $dependent = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $conflictsUrl = route('invoices.conflicts', $invoice);
        $resolveUrl = route('invoices.group-action', $invoice);

        $this->get($conflictsUrl)->assertOk()->assertSee($resolveUrl, false);
        $this->post($resolveUrl)->assertForbidden();

        $this->user->givePermissionTo('invoices.group-action');
        FiscalYear::findOrFail($this->fiscalYearId)->update(['closed_at' => now()]);
        $this->get($conflictsUrl)->assertOk()
            ->assertSeeText('Resolve conflicts and deactivate invoice')
            ->assertSee('disabled', false);
        $this->postJson($resolveUrl)->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        foreach ([$invoice, $dependent] as $affected) {
            $this->assertSame(InvoiceStatus::APPROVED, $affected->fresh()->status);
            $this->assertNotNull($affected->fresh()->document_id);
        }
        $this->assertEqualsWithDelta(18, $product->fresh()->quantity, 0.001);
    }

    public function test_invalid_source_states_cannot_be_marked_approved_inactive(): void
    {
        $product = $this->createProduct();
        $invoice = $this->buy([$this->productItem($product, 20, 100)], false)['invoice'];
        foreach (InvoiceStatus::cases() as $status) {
            $invoice->update(['status' => $status]);
            $this->post(route('invoices.change-status', [$invoice, 'approved_inactive']).'?confirm=1')->assertSessionHas('error');
            $this->assertSame($status, $invoice->fresh()->status);
        }
    }

    public function test_permissions_closed_year_and_fiscal_scope_protect_both_actions(): void
    {
        $product = $this->createProduct();
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        $deactivate = route('invoices.approve', $invoice);
        $activate = route('invoices.change-status', [$invoice, 'approved']);
        $this->user->revokePermissionTo('invoices.change-status');
        $this->user->revokePermissionTo('invoices.approve');
        $this->get(route('invoices.show', $invoice))->assertDontSee($deactivate, false);
        foreach ([$deactivate, $activate] as $url) {
            $this->post($url)->assertForbidden();
        }
        $this->user->givePermissionTo(['invoices.change-status', 'invoices.approve']);
        FiscalYear::findOrFail($this->fiscalYearId)->update(['closed_at' => now()]);
        foreach ([$deactivate, $activate] as $url) {
            $this->postJson($url)->assertUnprocessable()->assertJsonValidationErrors('fiscal_year');
        }
        FiscalYear::findOrFail($this->fiscalYearId)->update(['closed_at' => null]);
        $otherYear = FiscalYear::factory()->create();
        $invoice->update(['fiscal_year_id' => $otherYear->id]);
        foreach ([$deactivate, $activate] as $url) {
            $this->post($url)->assertNotFound();
        }
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
    }

    public function test_successful_moadian_submission_blocks_deactivation(): void
    {
        $product = $this->createProduct(['quantity' => 10]);
        $invoice = $this->sell([$this->productItem($product, 2, 150)])['invoice'];
        $invoice->moadianHistories()->create(['data' => ['status' => 'SUCCESS']]);
        $url = route('invoices.approve', $invoice);
        $this->get(route('invoices.show', $invoice))->assertOk()->assertDontSee($url, false);
        $this->post($url)->assertSessionHas('error');
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->fresh()->status);
    }

    public function test_failed_reactivation_rolls_back_invoice_accounting_and_stock(): void
    {
        $product = $this->createProduct(['quantity' => 0, 'average_cost' => 0]);
        $invoice = $this->buy([$this->productItem($product, 20, 100)])['invoice'];
        app(InvoiceService::class)->changeInvoiceStatus($invoice, 'approved_inactive');
        $documentCount = Document::count();
        $this->mock(AncillaryCostService::class)->shouldReceive('getChangeStatusValidation')
            ->andReturn(['allowed' => false, 'reason' => 'Cannot restore this cost']);
        $invoice->ancillaryCosts()->create([
            'fiscal_year_id' => $this->fiscalYearId, 'customer_id' => $this->customer->id,
            'number' => 1, 'date' => $invoice->date, 'type' => 1,
            'amount' => 200, 'vat' => 0, 'status' => InvoiceStatus::APPROVED_INACTIVE,
        ]);
        try {
            app(InvoiceService::class)->changeInvoiceStatus($invoice->fresh(), 'approved');
            $this->fail('Expected reactivation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ancillary_cost', $exception->errors());
        }
        $this->assertSame(InvoiceStatus::APPROVED_INACTIVE, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->document_id);
        $this->assertSame($documentCount, Document::count());
        $this->assertEqualsWithDelta(0, $product->fresh()->quantity, 0.001);
    }
}
