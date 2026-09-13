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
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CompanyOverviewService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class CompanyOverviewServiceTest extends TestCase
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

    public function test_warehouse_chart_excludes_items_outside_the_fiscal_year(): void
    {
        $product = $this->makeProduct();
        $invoice = $this->makeInvoice(jalali_to_gregorian(1406, 1, 1, '-'));
        $this->makeInvoiceItem($invoice, $product, quantityAt: 99);

        $this->assertSame(0, array_sum($this->service()->getMonthlyWarehouse()));
    }

    public function test_warehouse_chart_uses_latest_product_snapshot_per_month(): void
    {
        $product = $this->makeProduct();

        $earlier = $this->makeInvoice(jalali_to_gregorian(1405, 3, 1, '-'));
        $this->makeInvoiceItem($earlier, $product, quantityAt: 100);

        $later = $this->makeInvoice(jalali_to_gregorian(1405, 3, 20, '-'));
        $this->makeInvoiceItem($later, $product, quantityAt: 55);

        $this->assertSame(55, $this->service()->getMonthlyWarehouse()['خرداد']);
    }

    public function test_sell_amount_per_products_uses_only_approved_sales(): void
    {
        $product = $this->makeProduct();

        $sell = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, 1000);
        $this->makeInvoiceItem($sell, $product, amount: 1000);

        $unapproved = $this->makeInvoice(jalali_to_gregorian(1405, 4, 2, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED, 500);
        $this->makeInvoiceItem($unapproved, $product, amount: 500);

        $buy = $this->makeInvoice(jalali_to_gregorian(1405, 4, 3, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, 300);
        $this->makeInvoiceItem($buy, $product, amount: 300);

        $row = $this->service()->getSellAmountPerProducts()->firstWhere('name', $product->name);

        $this->assertNotNull($row);
        $this->assertSame(1000, $row['amount']);
    }

    public function test_sell_amount_per_products_groups_values_beyond_top_five(): void
    {
        $amounts = [600, 500, 400, 300, 200, 100];

        foreach ($amounts as $index => $amount) {
            $product = $this->makeProduct();
            $invoice = $this->makeInvoice(jalali_to_gregorian(1405, 3, $index + 1, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, $amount);
            $this->makeInvoiceItem($invoice, $product, amount: $amount);
        }

        $result = $this->service()->getSellAmountPerProducts();

        $this->assertEquals(100, $result->firstWhere('name', __('Other'))['amount']);
        $this->assertEquals(array_sum($amounts), $result->sum('amount'));
    }

    public function test_buy_amount_per_products_uses_only_approved_purchases(): void
    {
        $product = $this->makeProduct();

        $buy = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, 1000);
        $this->makeInvoiceItem($buy, $product, amount: 1000);

        $unapproved = $this->makeInvoice(jalali_to_gregorian(1405, 4, 2, '-'), InvoiceType::BUY, InvoiceStatus::UNAPPROVED, 500);
        $this->makeInvoiceItem($unapproved, $product, amount: 500);

        $sell = $this->makeInvoice(jalali_to_gregorian(1405, 4, 3, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, 300);
        $this->makeInvoiceItem($sell, $product, amount: 300);

        $row = $this->service()->getBuyAmountPerProducts()->firstWhere('name', $product->name);

        $this->assertNotNull($row);
        $this->assertSame(1000, $row['amount']);
    }

    public function test_popular_products_rank_approved_sales_by_quantity(): void
    {
        $low = $this->makeProduct();
        $high = $this->makeProduct();

        $lowInvoice = $this->makeInvoice(jalali_to_gregorian(1405, 4, 1, '-'), InvoiceType::SELL);
        $this->makeInvoiceItem($lowInvoice, $low, quantity: 2);

        $highInvoice = $this->makeInvoice(jalali_to_gregorian(1405, 4, 2, '-'), InvoiceType::SELL);
        $this->makeInvoiceItem($highInvoice, $high, quantity: 10);

        $unapprovedInvoice = $this->makeInvoice(jalali_to_gregorian(1405, 4, 3, '-'), InvoiceType::SELL, InvoiceStatus::UNAPPROVED);
        $this->makeInvoiceItem($unapprovedInvoice, $low, quantity: 100);

        $result = $this->service()->popularProductsAndServices();

        $this->assertSame($high->id, $result->first()['id']);
        $this->assertSame(10, $result->first()['quantity']);
        $this->assertSame(2, $result->last()['quantity']);
    }

    public function test_total_buy_amount_uses_only_approved_or_settled_purchases(): void
    {
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 1, '-'), InvoiceType::BUY, InvoiceStatus::APPROVED, 100);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 2, '-'), InvoiceType::BUY, InvoiceStatus::PAID, 300);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 3, '-'), InvoiceType::BUY, InvoiceStatus::UNAPPROVED, 9000);
        $this->makeInvoice(jalali_to_gregorian(1405, 2, 4, '-'), InvoiceType::SELL, InvoiceStatus::APPROVED, 8000);

        $this->assertSame(400.0, $this->service()->totalBuyAmount());
    }

    public function test_monthly_product_stat_places_and_aggregates_transactions(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();
        $subjectA = Subject::withoutGlobalScopes()->find($productA->inventory_subject_id);
        $subjectB = Subject::withoutGlobalScopes()->find($productB->inventory_subject_id);
        $document = $this->makeDocument(jalali_to_gregorian(1405, 9, 5, '-'));

        Transaction::create(['value' => 300, 'subject_id' => $subjectA->id, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'a']);
        Transaction::create(['value' => 700, 'subject_id' => $subjectB->id, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'b']);

        $this->assertSame(1000, $this->service()->getMonthlyProductsStat()['آذر']);
    }

    public function test_monthly_product_stat_excludes_transactions_outside_fiscal_year(): void
    {
        $product = $this->makeProduct();
        $subject = Subject::withoutGlobalScopes()->find($product->inventory_subject_id);

        $before = $this->makeDocument('2026-01-01');
        Transaction::create(['value' => 500, 'subject_id' => $subject->id, 'document_id' => $before->id, 'user_id' => $this->user->id, 'desc' => 'before']);

        $after = $this->makeDocument('2027-04-01');
        Transaction::create(['value' => 500, 'subject_id' => $subject->id, 'document_id' => $after->id, 'user_id' => $this->user->id, 'desc' => 'after']);

        $this->assertSame(0, array_sum($this->service()->getMonthlyProductsStat()));
    }

    public function test_balance_response_uses_final_running_balance_and_expected_shape(): void
    {
        $subjectId = 1;
        $before = $this->makeDocument('2025-12-01');
        Transaction::create(['value' => 1000, 'subject_id' => $subjectId, 'document_id' => $before->id, 'user_id' => $this->user->id, 'desc' => 'in']);

        $within = $this->makeDocument('2026-04-01');
        Transaction::create(['value' => -1000, 'subject_id' => $subjectId, 'document_id' => $within->id, 'user_id' => $this->user->id, 'desc' => 'out']);

        $data = $this->balanceData([$subjectId], 4, false);

        $this->assertSame(0, $data['sum']);
        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datas', $data);
        $this->assertArrayHasKey('start_date', $data);
        $this->assertArrayHasKey('end_date', $data);
        $this->assertCount(count($data['labels']), $data['datas']);
    }

    public function test_balance_response_can_invert_chart_values(): void
    {
        $subjectId = 1;
        $document = $this->makeDocument('2026-04-01');
        Transaction::create(['value' => -500, 'subject_id' => $subjectId, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'credit']);

        $normal = $this->balanceData([$subjectId], 4, false);
        $inverted = $this->balanceData([$subjectId], 4, true);

        foreach ($normal['datas'] as $index => $value) {
            $this->assertSame($value * -1, $inverted['datas'][$index]);
        }
    }

    public function test_total_warehouse_value_sums_inventory_subject_balances(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();
        $subjectA = Subject::withoutGlobalScopes()->find($productA->inventory_subject_id);
        $subjectB = Subject::withoutGlobalScopes()->find($productB->inventory_subject_id);
        $document = $this->makeDocument(jalali_to_gregorian(1405, 3, 1, '-'));

        Transaction::create(['value' => 600, 'subject_id' => $subjectA->id, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'a']);
        Transaction::create(['value' => 400, 'subject_id' => $subjectB->id, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'b']);

        $this->assertSame(1000.0, $this->service()->totalWarehouseValue());
    }

    private function service(): CompanyOverviewService
    {
        return new CompanyOverviewService(new SubjectService);
    }

    private function balanceData(array $subjectIds, int $duration, bool $inverse): array
    {
        return json_decode($this->service()->balanceForSubjectIds($subjectIds, $duration, $inverse)->getContent(), true);
    }

    private function makeProduct(): Product
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);

        return Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->companyId]);
    }

    private function makeInvoice(string $date, InvoiceType $type = InvoiceType::BUY, InvoiceStatus $status = InvoiceStatus::APPROVED, float $amount = 100): Invoice
    {
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

    private function makeInvoiceItem(Invoice $invoice, Product $product, int $quantityAt = 0, float $quantity = 1, float $amount = 100): InvoiceItem
    {
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
