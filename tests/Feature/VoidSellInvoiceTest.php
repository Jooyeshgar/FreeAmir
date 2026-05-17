<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\InvoiceService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\InvoiceTestHelper;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class VoidSellInvoiceTest extends TestCase
{
    use InvoiceTestHelper, RefreshDatabase, SeederHelper;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 7000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyId = Company::firstOrCreate(['id' => 1], ['name' => 'Test Company', 'fiscal_year' => 1405])->id;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'invoices.index']),
            Permission::firstOrCreate(['name' => 'invoices.void']),
            Permission::firstOrCreate(['name' => 'invoices.void-form']),
            Permission::firstOrCreate(['name' => 'invoices.show']),
        ]);

        $this->actingAs($this->user);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        ProductGroup::factory()->withSubjects()->create(['name' => 'عمومی', 'vat' => 10, 'company_id' => $this->companyId]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['name' => 'عمومی', 'description' => 'گروه مشتریان عمومی', 'company_id' => $this->companyId]);

        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_void_route_validates_required_fields(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7001, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7002, '2026-07-02')['invoice'];
        $response = $this->from(route('invoices.show', $sell))->post(route('invoices.void', $sell), []);

        $response->assertSessionHasErrors(['date', 'invoice_number']);
    }

    public function test_approved_sell_invoice_can_be_voided_and_restores_inventory(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7011, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7012, '2026-07-02')['invoice'];

        $product = $this->findProduct($product->id);
        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);

        $response = $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7013]); // 2026-07-03

        $response->assertRedirect(route('invoices.show', $sell));

        $sell = $this->findInvoice($sell->id);
        $voidInvoice = $sell->voidInvoice()->with('document', 'items')->first();
        $product = $this->findProduct($product->id);

        $this->assertNotNull($voidInvoice);
        $this->assertSame(InvoiceType::VOID, $voidInvoice->invoice_type);
        $this->assertSame(InvoiceStatus::APPROVED, $voidInvoice->status);
        $this->assertSame($sell->id, $voidInvoice->returned_invoice_id);
        $this->assertNotNull($voidInvoice->document_id);
        $this->assertEquals(10, $product->quantity);

        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
        $this->assertTrue($voidInvoice->voidedInvoice()->exists());
    }

    public function test_void_invoice_keeps_original_sell_cost_snapshot_on_items(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7021, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7022, '2026-07-02')['invoice'];
        $sellItem = $this->findInvoiceItem($sell, $product);

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7023]); // 2026-07-03

        $voidInvoice = $this->findInvoice($sell->voidInvoice()->firstOrFail()->id);
        $voidItem = $this->findInvoiceItem($voidInvoice, $product);

        $this->assertEqualsWithDelta($sellItem->cog_after, $voidItem->cog_after, 0.01);
        $this->assertEqualsWithDelta(6, $voidItem->quantity_at, 0.01);
    }

    public function test_unapproving_void_invoice_reapplies_the_original_sell_inventory_effect(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7031, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7032, '2026-07-02')['invoice'];

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7033]); // 2026-07-03

        $voidInvoice = $this->findInvoice($sell->voidInvoice()->firstOrFail()->id);
        $this->unapproveInvoice($voidInvoice);

        $product = $this->findProduct($product->id);
        $voidInvoice = $this->findInvoice($voidInvoice->id);

        $this->assertSame(InvoiceStatus::UNAPPROVED, $voidInvoice->status);
        $this->assertNull($voidInvoice->document_id);
        $this->assertEquals(6, $product->quantity);
        $this->assertEqualsWithDelta(100, $product->average_cost, 0.01);
    }

    public function test_sell_invoice_can_be_voided_from_show_page(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7041, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7042, '2026-07-02')['invoice'];

        $this->get(route('invoices.show', $sell))->assertOk()->assertSee(route('invoices.void-form', $sell));

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7043])->assertRedirect(); // 2026-07-03
    }

    public function test_sell_index_can_filter_only_voided_sell_invoices(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 20, 100)], true, 7044, '2026-07-01');
        $voidedSell = $this->sell([$this->productItem($product, 4, 180)], true, 7045, '2026-07-02')['invoice'];
        $regularSell = $this->sell([$this->productItem($product, 3, 185)], true, 7046, '2026-07-03')['invoice'];

        $this->post(route('invoices.void', $voidedSell), ['date' => '1405/04/13', 'invoice_number' => 7047])->assertRedirect(); // 2026-07-04

        $response = $this->get(route('invoices.index', ['invoice_type' => 'sell', 'voided' => '1']));
        $buyIndexResponse = $this->get(route('invoices.index', ['invoice_type' => 'buy']));

        $response->assertOk();
        $response->assertSee((string) formatDocumentNumber($voidedSell->number));
        $response->assertDontSee((string) formatDocumentNumber($regularSell->number));
        $response->assertSee(__('Voided'));

        $buyIndexResponse->assertOk();
        $buyIndexResponse->assertDontSee(__('Voided'));
    }

    public function test_voiding_is_blocked_for_unapproved_sell_invoice(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7051, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], false, 7052, '2026-07-02')['invoice'];

        $response = $this->from(route('invoices.show', $sell))->post(route('invoices.void', $sell), ['date' => '2026-07-03', 'invoice_number' => 7053]);

        $response->assertRedirect(route('invoices.show', $sell));
        $response->assertSessionHas('error', __('Invoice must be approved before voiding.'));
        $this->assertFalse($this->findInvoice($sell->id)->voidInvoice()->exists());
    }

    public function test_voiding_is_blocked_for_non_sell_invoice(): void
    {
        $product = $this->createProduct();

        $buy = $this->buy([$this->productItem($product, 10, 100)], true, 7061, '2026-07-01')['invoice'];

        $response = $this->from(route('invoices.show', $buy))->post(route('invoices.void', $buy), ['date' => '2026-07-02', 'invoice_number' => 7062]);

        $response->assertRedirect(route('invoices.show', $buy));
        $response->assertSessionHas('error', __('Only sell invoices are eligible for voiding.'));
        $this->assertDatabaseMissing('invoices', ['invoice_type' => InvoiceType::VOID->value, 'returned_invoice_id' => $buy->id]);
    }

    public function test_voiding_is_blocked_when_return_sell_exists(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7071, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 7072, '2026-07-02')['invoice'];
        $this->returnSell([$this->productItem($product, 1, 180)], $sell->id, true, 7073, '2026-07-03');

        $response = $this->from(route('invoices.show', $sell))->post(route('invoices.void', $sell), ['date' => '1405/04/13', 'invoice_number' => 7074]); // 2026-07-04

        $response->assertRedirect(route('invoices.show', $sell));
        $response->assertSessionHas('error', __('Only sales invoices that have not been returned are eligible for voiding.'));
        $this->assertFalse($this->findInvoice($sell->id)->voidInvoice()->exists());
    }

    public function test_voiding_is_blocked_when_invoice_has_already_been_voided(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7081, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 7082, '2026-07-02')['invoice'];

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7083]); // 2026-07-03

        $response = $this->from(route('invoices.show', $sell))->post(route('invoices.void', $sell), ['date' => '1405/04/13', 'invoice_number' => 7084]); // 2026-07-04

        $response->assertRedirect(route('invoices.show', $sell));
        $response->assertSessionHas('error', __('Invoice has voided already.'));

        $voidInvoicesCount = Invoice::withoutGlobalScopes()->where('invoice_type', InvoiceType::VOID)->where('returned_invoice_id', $sell->id)->count();

        $this->assertSame(1, $voidInvoicesCount);
    }

    public function test_voiding_is_blocked_when_void_date_is_before_original_invoice_date(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7091, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 7092, '2026-07-03')['invoice'];

        $response = $this->from(route('invoices.show', $sell))->post(route('invoices.void', $sell), ['date' => '2026-07-02', 'invoice_number' => 7093]);

        $response->assertRedirect(route('invoices.show', $sell));
        $response->assertSessionHas('error', __('Void invoice date cannot be earlier than the invoice date.'));
        $this->assertFalse($this->findInvoice($sell->id)->voidInvoice()->exists());
    }

    public function test_original_sell_invoice_cannot_be_unapproved_while_void_invoice_exists(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7101, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 7102, '2026-07-02')['invoice'];

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7103]); // 2026-07-03

        $decision = InvoiceService::getChangeStatusDecision($this->findInvoice($sell->id), 'unapproved');

        $this->assertTrue($decision->hasErrors());
        $this->assertFalse($decision->canProceed);
        $this->assertStringContainsString(
            (string) $sell->voidInvoice()->firstOrFail()->number,
            $decision->messages->first(fn ($message) => $message->type === 'error')?->text ?? ''
        );
    }

    public function test_void_form_is_blocked_for_ineligible_sell_invoice_from_ui_flow(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7111, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], false, 7112, '2026-07-02')['invoice'];

        $response = $this->from(route('invoices.show', $sell))->get(route('invoices.void-form', $sell));

        $response->assertRedirect(route('invoices.show', $sell));
        $response->assertSessionHas('error', __('Invoice must be approved before voiding.'));
    }

    public function test_show_page_disables_void_action_for_sell_invoice_with_return(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7121, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 5, 180)], true, 7122, '2026-07-02')['invoice'];
        $this->returnSell([$this->productItem($product, 1, 180)], $sell->id, true, 7123, '2026-07-03');

        $this->get(route('invoices.show', $sell))
            ->assertOk()->assertSee(__('Only approved sell invoices without return invoices can be voided.'))
            ->assertDontSee(route('invoices.void-form', $sell), false);
    }

    public function test_show_pages_display_bidirectional_void_links_for_manual_navigation(): void
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, 7131, '2026-07-01');
        $sell = $this->sell([$this->productItem($product, 4, 180)], true, 7132, '2026-07-02')['invoice'];

        $this->post(route('invoices.void', $sell), ['date' => '1405/04/12', 'invoice_number' => 7133]); // 2026-07-03

        $sell = $this->findInvoice($sell->id);
        $voidInvoice = $sell->voidInvoice()->firstOrFail();

        $this->get(route('invoices.show', $sell))
            ->assertOk()->assertSee(__('This invoice is voided.'))
            ->assertSee(route('invoices.show', $voidInvoice), false)->assertSee(__('Invoice has voided already.'));

        $this->get(route('invoices.show', $voidInvoice))
            ->assertOk()->assertSee(__('The void invoice of sell invoice number #:number', ['number' => formatDocumentNumber($sell->number)]))
            ->assertSee(route('invoices.show', $sell), false);
    }
}
