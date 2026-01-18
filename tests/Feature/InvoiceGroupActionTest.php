<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\AncillaryCostService;
use App\Services\GroupActionService;
use App\Services\InvoiceService;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGroupActionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        Cookie::queue('active-company-id', $company->id);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->withGroup()->withSubject()->create();
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::factory()->withGroup()->withSubjects()->create(array_merge(['company_id' => $this->companyId], $overrides));
    }

    private function createInvoice(InvoiceType $type, array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        $number ??= random_int(1000, 9999);

        return InvoiceService::createInvoice(
            $this->user,
            [
                'title' => $type === InvoiceType::BUY ? 'Buy Invoice' : 'Sell Invoice',
                'date' => $date ?? now()->toDateString(),
                'invoice_type' => $type,
                'customer_id' => $this->customer->id,
                'document_number' => $number,
                'number' => $number,
            ],
            $items,
            $approved
        );
    }

    private function buy(array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        return $this->createInvoice(InvoiceType::BUY, $items, $approved, $number, $date);
    }

    private function sell(array $items, bool $approved = true, ?int $number = null, $date = null): array
    {
        return $this->createInvoice(InvoiceType::SELL, $items, $approved, $number, $date);
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
            'date' => $invoice->date,
            'invoice_type' => $invoice->invoice_type,
            'customer_id' => $invoice->customer_id,
            'document_number' => $invoice->document_number,
            'number' => $invoice->number,
        ];

        return $this->updateInvoice($invoice, $data, $newItems, $approved);
    }

    protected function updateInvoice(Invoice $invoice, array $data, array $items = [], bool $approved = false): array
    {
        return InvoiceService::updateInvoice($invoice->id, $data, $items, $approved);
    }

    protected function unapproveInvoice(Invoice $invoice): void
    {
        $svc = new InvoiceService;
        $svc->changeInvoiceStatus($invoice, 'unapproved');
        $invoice->refresh();
    }

    public function test_approve_inactive_invoices()
    {
        $product = $this->createProduct();

        $this->buy([$this->productItem($product, 10, 100)], true, random_int(1000, 1999), now())['invoice'];
        $buyInv2 = $this->buy([$this->productItem($product, 20, 100)], true, random_int(2000, 2999), now()->addDays(1))['invoice'];
        $buyInv3 = $this->buy([$this->productItem($product, 30, 100)], true, random_int(3000, 3999), now()->addDays(2))['invoice'];

        $ancillaryCost1 = $this->createAncillaryCost($product, $buyInv3, 200, true, now()->addDays(2));
        $buyInv3->refresh();

        $buyInv4 = $this->buy([$this->productItem($product, 40, 100)], true, random_int(4000, 4999), now()->addDays(3))['invoice'];

        $ancillaryCost2 = $this->createAncillaryCost($product, $buyInv4, 200, true, now()->addDays(3));
        $buyInv4->refresh();

        $sellInv1 = $this->sell([$this->productItem($product, 10, 10)], true, random_int(2000, 2999), now()->addDays(5))['invoice'];
        $sellInv2 = $this->sell([$this->productItem($product, 10, 10)], true, random_int(3000, 3999), now()->addDays(6))['invoice'];

        $groupActionService = new GroupActionService;

        [$invoicesConflicts, $ancillaryCostsConflicts, $productsConflicts] = $groupActionService->findAllConflictsRecursively($buyInv2);

        // conflicts: buyInv3, ancillaryCost1, buyInv4, ancillaryCost2, sellInv1, sellInv2
        $this->assertEquals($buyInv3->id, $invoicesConflicts[1]->id);
        $this->assertEquals($ancillaryCost1->id, $ancillaryCostsConflicts[1]->id);
        $this->assertEquals($buyInv4->id, $invoicesConflicts[2]->id);
        $this->assertEquals($ancillaryCost2->id, $ancillaryCostsConflicts[0]->id);
        $this->assertEquals($sellInv1->id, $invoicesConflicts[3]->id);
        $this->assertEquals($sellInv2->id, $invoicesConflicts[4]->id);

        $groupActionService->groupAction($buyInv2, app(InvoiceService::class), app(AncillaryCostService::class));

        $this->assertEquals(InvoiceStatus::APPROVED, $buyInv2->status);
        $this->assertEquals(InvoiceStatus::APPROVED_INACTIVE, $buyInv3->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED_INACTIVE, $buyInv4->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED_INACTIVE, $sellInv1->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED_INACTIVE, $sellInv2->refresh()->status);

        $this->unapproveInvoice($buyInv2);
        $this->assertEquals(InvoiceStatus::UNAPPROVED, $buyInv2->refresh()->status);

        $this->editInvoice($buyInv2, [$this->productItem($product, 15, 100)], true);
        $this->assertEquals(InvoiceStatus::APPROVED, $buyInv2->refresh()->status);

        $groupActionService->approveInactiveInvoices(app(InvoiceService::class), app(AncillaryCostService::class));

        $this->assertEquals(InvoiceStatus::APPROVED, $buyInv3->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED, $buyInv4->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED, $sellInv1->refresh()->status);
        $this->assertEquals(InvoiceStatus::APPROVED, $sellInv2->refresh()->status);
    }

    protected function createAncillaryCost(Product $product, Invoice $invoice, float $amount, bool $approved = true, $date = null): AncillaryCost
    {
        $ancillaryCost = AncillaryCostService::createAncillaryCost($this->user, [
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'company_id' => $this->companyId,
            'date' => $date ?? now(),
            'type' => 'Shipping',
            'amount' => $amount,
            'vatPrice' => 0,
            'ancillaryCosts' => [
                ['product_id' => $product->id, 'type' => 'Shipping', 'amount' => $amount],
            ],
        ], $approved)['ancillaryCost'];

        return $ancillaryCost;
    }
}
