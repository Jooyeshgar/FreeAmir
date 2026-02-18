<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use Cookie;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvoiceGroupActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected int $nextInvoiceNumber = 2000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->companyId = Company::withoutGlobalScopes()->orderBy('id')->value('id') ?? 1;

        Cache::forever('active_company_id', $this->companyId);
        Cookie::queue('active-company-id', (string) $this->companyId);
        $_COOKIE['active-company-id'] = (string) $this->companyId;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $customerGroup = CustomerGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->firstOrFail();
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function createProduct(array $overrides = []): Product
    {
        $group = ProductGroup::withoutGlobalScopes()->where('company_id', $this->companyId)->firstOrFail();

        return Product::factory()->withGroup($group)->withSubjects()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    private function createInvoice(
        InvoiceType $type,
        array $items,
        bool $approved = true,
        ?int $number = null,
        ?string $date = null,
        ?int $returnedInvoiceId = null
    ): array {
        $number ??= ++$this->nextInvoiceNumber;

        $result = InvoiceService::createInvoice(
            $this->user,
            [
                'title' => strtoupper($type->value).' Invoice',
                'date' => $date ?? now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
                'returned_invoice_id' => $returnedInvoiceId,
            ],
            $items,
            $approved
        );

        $invoice = $this->findInvoice($result['invoice']->id);

        if ($approved && ! $invoice->status->isApproved()) {
            $this->changeInvoiceStatus($invoice, 'approved');
            $invoice = $this->findInvoice($invoice->id);
        }

        return [
            'document' => $result['document'],
            'invoice' => $invoice,
        ];
    }

    private function buy(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number, $date);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number, $date);
    }

    private function returnSell(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_SELL, $items, $approved, $number, $date, $returnedInvoiceId);
    }

    private function returnBuy(array $items, int $returnedInvoiceId, bool $approved = true, ?int $number = null, ?string $date = null): array
    {
        return $this->createInvoice(InvoiceType::RETURN_BUY, $items, $approved, $number, $date, $returnedInvoiceId);
    }

    private function productItem(Product $product, int $qty, float $unit): array
    {
        return [
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'quantity' => $qty,
            'unit' => $unit,
            'unit_discount' => 0,
            'vat' => 0,
        ];
    }

    protected function editInvoice(Invoice $invoice, array $newItems, bool $approved = true): array
    {
        $data = [
            'title' => $invoice->title,
            'date' => $invoice->date instanceof Carbon ? $invoice->date->toDateString() : (string) $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'document_number' => $invoice->document?->number,
            'number' => $invoice->number,
            'returned_invoice_id' => $invoice->returned_invoice_id,
        ];

        return $this->updateInvoice($invoice, $data, $newItems, $approved);
    }

    protected function updateInvoice(Invoice $invoice, array $data, array $items = [], bool $approved = false): array
    {
        return InvoiceService::updateInvoice($invoice->id, $data, $items, $approved);
    }

    protected function unapproveInvoice(Invoice $invoice): void
    {
        $invoice = $this->findInvoice($invoice->id);
        $svc = new InvoiceService;
        $svc->changeInvoiceStatus($invoice, 'unapproved');
        $invoice->refresh();
    }

    private function changeInvoiceStatus(Invoice $invoice, string $status): void
    {
        $invoice = $this->findInvoice($invoice->id);
        (new InvoiceService)->changeInvoiceStatus($invoice, $status);
    }

    private function findInvoice(int $invoiceId): Invoice
    {
        return Invoice::withoutGlobalScopes()->with('items')->findOrFail($invoiceId);
    }

    private function assertInvoiceStatus(int $invoiceId, InvoiceStatus $status): void
    {
        $this->assertSame($status, $this->findInvoice($invoiceId)->status);
    }

    public function test_group_action_and_approve_inactive_cover_return_invoices_and_approved_inactive_status(): void
    {
        $product = $this->createProduct(['quantity' => 500]);
        $baseDate = now()->startOfDay();

        $buyInv1 = $this->buy([$this->productItem($product, 100, 100)], true, 2101, $baseDate->toDateString())['invoice'];
        $buyInv2 = $this->buy([$this->productItem($product, 80, 100)], true, 2102, $baseDate->copy()->addDay()->toDateString())['invoice'];
        $sellInv = $this->sell([$this->productItem($product, 25, 120)], true, 2103, $baseDate->copy()->addDays(2)->toDateString())['invoice'];

        $returnBuyInv = $this->returnBuy([$this->productItem($product, 10, 100)], $buyInv2->id, true, 2104, $baseDate->copy()->addDays(3)->toDateString())['invoice'];
        $returnSellInv = $this->returnSell([$this->productItem($product, 5, 120)], $sellInv->id, true, 2105, $baseDate->copy()->addDays(4)->toDateString())['invoice'];

        $groupActionService = app(GroupActionService::class);
        $groupActionService->inactivateDependentInvoices($this->findInvoice($buyInv2->id));

        $this->assertInvoiceStatus($buyInv1->id, InvoiceStatus::APPROVED);
        $this->assertInvoiceStatus($buyInv2->id, InvoiceStatus::APPROVED_INACTIVE);
        $this->assertInvoiceStatus($sellInv->id, InvoiceStatus::APPROVED_INACTIVE);
        $this->assertInvoiceStatus($returnBuyInv->id, InvoiceStatus::APPROVED_INACTIVE);
        $this->assertInvoiceStatus($returnSellInv->id, InvoiceStatus::APPROVED_INACTIVE);

        $groupActionService->approveInactiveInvoices();

        $this->assertInvoiceStatus($buyInv1->id, InvoiceStatus::APPROVED);
        $this->assertInvoiceStatus($buyInv2->id, InvoiceStatus::APPROVED);
        $this->assertInvoiceStatus($sellInv->id, InvoiceStatus::APPROVED);
        $this->assertInvoiceStatus($returnBuyInv->id, InvoiceStatus::APPROVED);
        $this->assertInvoiceStatus($returnSellInv->id, InvoiceStatus::APPROVED);
    }

    public function test_single_status_changes_cover_pending_ready_rejected_approve_and_unapprove(): void
    {
        $product = $this->createProduct(['quantity' => 200]);

        $pendingBuy = $this->buy([$this->productItem($product, 20, 100)], false, 2201, now()->toDateString())['invoice'];
        $this->assertInvoiceStatus($pendingBuy->id, InvoiceStatus::PENDING);

        $this->changeInvoiceStatus($pendingBuy, 'approved');
        $this->assertInvoiceStatus($pendingBuy->id, InvoiceStatus::APPROVED);

        $this->changeInvoiceStatus($pendingBuy, 'unapproved');
        $this->assertInvoiceStatus($pendingBuy->id, InvoiceStatus::UNAPPROVED);

        $sellInvoice = $this->sell([$this->productItem($product, 5, 130)], false, 2202, now()->addDay()->toDateString())['invoice'];
        $this->assertInvoiceStatus($sellInvoice->id, InvoiceStatus::PRE_INVOICE);

        $this->changeInvoiceStatus($sellInvoice, 'ready_to_approve');
        $this->assertInvoiceStatus($sellInvoice->id, InvoiceStatus::READY_TO_APPROVE);

        $this->changeInvoiceStatus($sellInvoice, 'rejected');
        $this->assertInvoiceStatus($sellInvoice->id, InvoiceStatus::REJECTED);

        $this->changeInvoiceStatus($sellInvoice, 'approved');
        $this->assertInvoiceStatus($sellInvoice->id, InvoiceStatus::APPROVED);
    }

    public function test_status_change_is_blocked_for_original_buy_when_return_invoice_exists(): void
    {
        $product = $this->createProduct(['quantity' => 200]);
        $baseDate = now()->startOfDay();

        $buyInvoice = $this->buy([$this->productItem($product, 40, 100)], true, 2301, $baseDate->toDateString())['invoice'];

        $returnBuyInvoice = $this->returnBuy(
            [$this->productItem($product, 5, 100)],
            $buyInvoice->id,
            false,
            2302,
            $baseDate->copy()->addDay()->toDateString()
        )['invoice'];

        $decision = InvoiceService::getChangeStatusDecision($this->findInvoice($buyInvoice->id), 'unapproved');

        $this->assertTrue($decision->hasErrors());
        $this->assertFalse($decision->canProceed);
        $this->assertSame(
            __('invoices.status_change.blocked_by_return_invoice', ['invoice' => $returnBuyInvoice->number]),
            $decision->messages->first(fn ($m) => $m->type === 'error')?->text
        );
    }

    public function test_status_change_shows_warning_for_return_buy_linked_to_original_with_later_date(): void
    {
        $product = $this->createProduct(['quantity' => 200]);
        $baseDate = now()->startOfDay();

        $buyInvoice = $this->buy(
            [$this->productItem($product, 40, 100)],
            true,
            2311,
            $baseDate->copy()->addDays(3)->toDateString()
        )['invoice'];

        $returnBuyInvoice = $this->returnBuy(
            [$this->productItem($product, 4, 100)],
            $buyInvoice->id,
            false,
            2312,
            $baseDate->copy()->addDay()->toDateString()
        )['invoice'];

        $decision = InvoiceService::getChangeStatusDecision($this->findInvoice($returnBuyInvoice->id), 'approved');

        $this->assertFalse($decision->hasErrors());
        $this->assertTrue($decision->canProceed);
        $this->assertTrue($decision->hasWarning());
        $this->assertTrue($decision->needsConfirmation);
        $this->assertSame(
            __('invoices.status_change.warning_original_after_return', ['invoice' => $buyInvoice->number]),
            $decision->messages->first(fn ($m) => $m->type === 'warning')?->text
        );
    }
}
