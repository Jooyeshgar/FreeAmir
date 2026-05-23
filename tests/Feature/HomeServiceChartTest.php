<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HomeService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class HomeServiceChartTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;
        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId, 'active-company-fiscal-year' => 1405]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_warehouse_chart_excludes_invoice_items_from_next_fiscal_year(): void
    {
        $product = $this->makeProduct();
        $invoice = $this->makeInvoice(jalali_to_gregorian(1406, 1, 1, '-'));
        $this->makeInvoiceItem($invoice, $product, quantityAt: 99);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(0, array_sum($result), 'An invoice from the next fiscal year must not appear in the current year warehouse chart');
    }

    public function test_warehouse_chart_includes_invoice_item_in_correct_month(): void
    {
        $product = $this->makeProduct();
        $invoice = $this->makeInvoice(jalali_to_gregorian(1405, 6, 15, '-'));
        $this->makeInvoiceItem($invoice, $product, quantityAt: 42);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(42, $result['شهریور']);
        $this->assertSame(0, $result['مهر']);
    }

    public function test_warehouse_chart_keeps_only_latest_invoice_per_product_per_month(): void
    {
        $product = $this->makeProduct();

        $earlier = $this->makeInvoice(jalali_to_gregorian(1405, 3, 1, '-'));
        $this->makeInvoiceItem($earlier, $product, quantityAt: 100);

        $later = $this->makeInvoice(jalali_to_gregorian(1405, 3, 20, '-'));
        $this->makeInvoiceItem($later, $product, quantityAt: 55);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(55, $result['خرداد'], 'Only the latest invoice_item snapshot per product per month is counted');
    }

    public function test_warehouse_chart_sums_latest_quantity_at_across_products_in_same_month(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        $invoiceA = $this->makeInvoice(jalali_to_gregorian(1405, 2, 10, '-'));
        $this->makeInvoiceItem($invoiceA, $productA, quantityAt: 30);

        $invoiceB = $this->makeInvoice(jalali_to_gregorian(1405, 2, 20, '-'));
        $this->makeInvoiceItem($invoiceB, $productB, quantityAt: 70);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(100, $result['اردیبهشت']);
    }

    public function test_warehouse_chart_excludes_service_invoice_items(): void
    {
        $serviceGroup = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $service = Service::factory()->withGroup($serviceGroup)->withSubject()->create(['company_id' => $this->companyId]);

        $invoice = $this->makeInvoice(jalali_to_gregorian(1405, 5, 1, '-'));
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Service::class,
            'itemable_id' => $service->id,
            'quantity' => 1,
            'unit_price' => 500,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 500,
            'quantity_at' => 88,
            'cog_after' => 0,
        ]);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(0, array_sum($result), 'Service invoice items must not appear in the warehouse chart');
    }

    public function test_warehouse_chart_distributes_items_across_multiple_months(): void
    {
        $product = $this->makeProduct();

        $this->makeInvoiceItem($this->makeInvoice(jalali_to_gregorian(1405, 1, 5, '-')), $product, quantityAt: 10);
        $this->makeInvoiceItem($this->makeInvoice(jalali_to_gregorian(1405, 7, 5, '-')), $product, quantityAt: 20);
        $this->makeInvoiceItem($this->makeInvoice(jalali_to_gregorian(1405, 12, 1, '-')), $product, quantityAt: 30);

        $result = $this->service()->getMonthlyWarehouse();

        $this->assertSame(10, $result['فروردین']);
        $this->assertSame(0, $result['اردیبهشت']);
        $this->assertSame(20, $result['مهر']);
        $this->assertSame(30, $result['اسفند']);
    }

    public function test_sell_amount_per_products_filters_to_approved_sell_invoices_only(): void
    {
        $product = $this->makeProduct();

        $sell = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 1000);
        $this->makeInvoiceItem($sell, $product, amount: 1000);

        $unapproved = $this->makeInvoice(jalali_to_gregorian(1405, 4, 2, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED, amount: 500);
        $this->makeInvoiceItem($unapproved, $product, amount: 500);

        $buy = $this->makeInvoice(jalali_to_gregorian(1405, 4, 3, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 300);
        $this->makeInvoiceItem($buy, $product, amount: 300);

        $result = $this->service()->getSellAmountPerProducts();
        $productRow = $result->firstWhere('name', $product->name);

        $this->assertNotNull($productRow);
        $this->assertSame(1000, $productRow['amount']);
    }

    public function test_sell_amount_per_products_other_is_zero_when_five_or_fewer_products(): void
    {
        foreach (range(1, 3) as $i) {
            $p = $this->makeProduct();
            $inv = $this->makeInvoice(jalali_to_gregorian(1405, 3, $i, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: $i * 100);
            $this->makeInvoiceItem($inv, $p, amount: $i * 100);
        }

        $result = $this->service()->getSellAmountPerProducts();
        $other = $result->firstWhere('name', __('Other'));

        $this->assertNotNull($other);
        $this->assertEquals(0, $other['amount']);
    }

    public function test_sell_amount_per_products_other_captures_remainder_beyond_top_five(): void
    {
        $amounts = [600, 500, 400, 300, 200, 100];
        $total = array_sum($amounts);

        foreach ($amounts as $idx => $amt) {
            $p = $this->makeProduct();
            $inv = $this->makeInvoice(jalali_to_gregorian(1405, 3, $idx + 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: $amt);
            $this->makeInvoiceItem($inv, $p, amount: $amt);
        }

        $result = $this->service()->getSellAmountPerProducts();
        $other = $result->firstWhere('name', __('Other'));

        $this->assertEquals(100, $other['amount']);
        $this->assertEquals($total, $result->sum('amount'));
    }

    public function test_popular_products_ranks_by_quantity_on_approved_sell_invoices(): void
    {
        $low = $this->makeProduct();
        $high = $this->makeProduct();

        $invLow = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED);
        $this->makeInvoiceItem($invLow, $low, quantity: 2);

        $invHigh = $this->makeInvoice(jalali_to_gregorian(1405, 4, 2, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED);
        $this->makeInvoiceItem($invHigh, $high, quantity: 10);

        $result = $this->service()->popularProductsAndServices();

        $this->assertEquals($high->id, $result->first()['id']);
        $this->assertSame(10, $result->first()['quantity']);
        $this->assertSame(2, $result->last()['quantity']);
    }

    public function test_popular_products_excludes_unapproved_invoices(): void
    {
        $product = $this->makeProduct();

        $unapproved = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED);
        $this->makeInvoiceItem($unapproved, $product, quantity: 50);

        $result = $this->service()->popularProductsAndServices();

        $this->assertCount(0, $result);
    }

    public function test_popular_products_excludes_buy_invoices(): void
    {
        $product = $this->makeProduct();

        $buy = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED);
        $this->makeInvoiceItem($buy, $product, quantity: 50);

        $result = $this->service()->popularProductsAndServices();

        $this->assertCount(0, $result);
    }

    public function test_popular_products_accumulates_quantity_across_invoices(): void
    {
        $product = $this->makeProduct();

        foreach ([5, 3, 7] as $idx => $qty) {
            $inv = $this->makeInvoice(jalali_to_gregorian(1405, 4, $idx + 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED);
            $this->makeInvoiceItem($inv, $product, quantity: $qty);
        }

        $result = $this->service()->popularProductsAndServices();

        $this->assertCount(1, $result);
        $this->assertSame(15, $result->first()['quantity']);
    }

    public function test_total_buy_amount_sums_approved_buy_invoices(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 1, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 400);
        $this->makeInvoice(jalali_to_gregorian(1405, 6, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 600);

        $this->assertSame(1000.0, $this->service()->totalBuyAmount());
    }

    public function test_total_buy_amount_excludes_unapproved_buy_invoices(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 3, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 300);
        $this->makeInvoice(jalali_to_gregorian(1405, 3, 2, '-'), InvoiceType::BUY, InvoiceStatus::UNAPPROVED, amount: 9999);

        $this->assertSame(300.0, $this->service()->totalBuyAmount());
    }

    public function test_total_buy_amount_excludes_sell_invoices(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 3, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, amount: 200);
        $this->makeInvoice(jalali_to_gregorian(1405, 3, 2, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, amount: 9999);

        $this->assertSame(200.0, $this->service()->totalBuyAmount());
    }

    public function test_total_buy_amount_is_zero_with_no_approved_buy_invoices(): void
    {
        $this->assertSame(0.0, $this->service()->totalBuyAmount());
    }

    public function test_monthly_products_stat_places_transaction_in_correct_month(): void
    {
        $product = $this->makeProduct();
        $inventorySubject = Subject::withoutGlobalScopes()->find($product->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 5, 10, '-'));
        Transaction::create([
            'value' => 800,
            'subject_id' => $inventorySubject->id,
            'document_id' => $doc->id,
            'user_id' => $this->user->id,
            'desc' => 'inventory in',
        ]);

        $result = $this->service()->getMonthlyProductsStat();

        $this->assertSame(800, $result['مرداد']);
        $this->assertSame(0, $result['تیر']);
        $this->assertSame(0, $result['شهریور']);
    }

    public function test_monthly_products_stat_aggregates_across_multiple_products(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        $subjectA = Subject::withoutGlobalScopes()->find($productA->inventory_subject_id);
        $subjectB = Subject::withoutGlobalScopes()->find($productB->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 9, 5, '-'));

        Transaction::create(['value' => 300, 'subject_id' => $subjectA->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'a']);
        Transaction::create(['value' => 700, 'subject_id' => $subjectB->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'b']);

        $result = $this->service()->getMonthlyProductsStat();

        $this->assertSame(1000, $result['آذر']);
    }

    public function test_monthly_products_stat_excludes_transactions_outside_fiscal_year(): void
    {
        $product = $this->makeProduct();
        $subject = Subject::withoutGlobalScopes()->find($product->inventory_subject_id);

        $before = $this->makeDocument('2026-01-01');
        Transaction::create(['value' => 500, 'subject_id' => $subject->id, 'document_id' => $before->id, 'user_id' => $this->user->id, 'desc' => 'before']);

        $after = $this->makeDocument('2027-04-01');
        Transaction::create(['value' => 500, 'subject_id' => $subject->id, 'document_id' => $after->id, 'user_id' => $this->user->id, 'desc' => 'after']);

        $result = $this->service()->getMonthlyProductsStat();

        $this->assertSame(0, array_sum($result));
    }

    public function test_monthly_products_stat_is_all_zeros_when_no_products_exist(): void
    {
        $result = $this->service()->getMonthlyProductsStat();

        $this->assertCount(12, $result);
        $this->assertSame(0, array_sum($result));
    }

    public function test_balance_for_subject_ids_returns_zero_sum_when_running_balance_is_zero(): void
    {
        $subjectId = 1;

        $docBefore = $this->makeDocument('2025-12-01');
        Transaction::create(['value' => 1000, 'subject_id' => $subjectId, 'document_id' => $docBefore->id, 'user_id' => $this->user->id, 'desc' => 'in']);

        $docWithin = $this->makeDocument('2026-04-01');
        Transaction::create(['value' => -1000, 'subject_id' => $subjectId, 'document_id' => $docWithin->id, 'user_id' => $this->user->id, 'desc' => 'out']);

        $response = $this->service()->balanceForSubjectIds([$subjectId], 4, false);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['sum'], 'sum must be 0 when running balance is zero, not the non-zero initial balance');
    }

    public function test_balance_for_subject_ids_response_shape_is_correct(): void
    {
        $response = $this->service()->balanceForSubjectIds([1], 1, false);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datas', $data);
        $this->assertArrayHasKey('sum', $data);
        $this->assertArrayHasKey('start_date', $data);
        $this->assertArrayHasKey('end_date', $data);
        $this->assertCount(count($data['labels']), $data['datas']);
    }

    public function test_balance_for_subject_ids_inverts_values_when_inverse_flag_is_set(): void
    {
        $subjectId = 1;

        $doc = $this->makeDocument('2026-04-01');
        Transaction::create(['value' => -500, 'subject_id' => $subjectId, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'credit']);

        $normal = json_decode($this->service()->balanceForSubjectIds([$subjectId], 4, false)->getContent(), true);
        $inverted = json_decode($this->service()->balanceForSubjectIds([$subjectId], 4, true)->getContent(), true);

        $this->assertNotEmpty($normal['datas']);
        foreach ($normal['datas'] as $idx => $value) {
            $this->assertSame($value * -1, $inverted['datas'][$idx]);
        }
    }

    public function test_total_warehouse_value_sums_inventory_subject_balances(): void
    {
        $product = $this->makeProduct();
        $inventorySubject = Subject::withoutGlobalScopes()->find($product->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 5, 10, '-'));
        Transaction::create([
            'value' => 1500,
            'subject_id' => $inventorySubject->id,
            'document_id' => $doc->id,
            'user_id' => $this->user->id,
            'desc' => 'inventory value',
        ]);

        $this->assertSame(1500.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_aggregates_across_multiple_products(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        $subjectA = Subject::withoutGlobalScopes()->find($productA->inventory_subject_id);
        $subjectB = Subject::withoutGlobalScopes()->find($productB->inventory_subject_id);

        $doc = $this->makeDocument(jalali_to_gregorian(1405, 3, 1, '-'));
        Transaction::create(['value' => 600, 'subject_id' => $subjectA->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'a']);
        Transaction::create(['value' => 400, 'subject_id' => $subjectB->id, 'document_id' => $doc->id, 'user_id' => $this->user->id, 'desc' => 'b']);

        $this->assertSame(1000.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_is_zero_when_no_products_exist(): void
    {
        $this->assertSame(0.0, $this->service()->totalWarehouseValue());
    }

    public function test_total_warehouse_value_skips_products_without_inventory_subject(): void
    {
        $product = $this->makeProduct();
        $product->update(['inventory_subject_id' => null]);

        $this->assertSame(0.0, $this->service()->totalWarehouseValue());
    }

    private function service(): HomeService
    {
        return new HomeService(new SubjectService);
    }

    private function makeProduct(): Product
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);

        return Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->companyId]);
    }

    private function makeInvoice(
        string $date,
        InvoiceType $type = InvoiceType::BUY,
        InvoiceStatus $status = InvoiceStatus::APPROVED,
        float $amount = 100,
    ): Invoice {
        return Invoice::create([
            'number' => Invoice::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'invoice_type' => $type,
            'status' => $status,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => 'test',
        ]);
    }

    private function makeInvoiceItem(
        Invoice $invoice,
        Product $product,
        int $quantityAt = 0,
        float $quantity = 1,
        float $amount = 100,
    ): InvoiceItem {
        return InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $amount,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $amount,
            'quantity_at' => $quantityAt,
            'cog_after' => 0,
        ]);
    }

    private function makeDocument(string $date): Document
    {
        return Document::create([
            'number' => Document::withoutGlobalScopes()->max('number') + 1,
            'date' => $date,
            'creator_id' => $this->user->id,
            'title' => 'test',
            'company_id' => $this->companyId,
        ]);
    }
}
