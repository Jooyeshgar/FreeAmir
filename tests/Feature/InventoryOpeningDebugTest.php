<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\InventoryTurnoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

/** Independently specified stock/ledger reconciliation, not the existing end-average valuation contract. */
class InventoryOpeningDebugTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        $year = FiscalYear::factory()->create(['year' => 1405]);
        config(['active-fiscal-year-id' => $year->id]);
        $this->importSubjects($year->id);
        $this->importConfigs($year->id);
        $user = User::factory()->create(['name' => 'مریم احمدی']);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $year->id]);
        $customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['name' => 'رضا محمدی', 'fiscal_year_id' => $year->id]);
        $group = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $year->id]);
        $this->product = Product::factory()->withGroup($group)->withSubjects()->create(['fiscal_year_id' => $year->id, 'name' => 'کابل برق', 'quantity' => 12, 'average_cost' => 162.5]);
        $warehouse = Warehouse::create(['name' => 'انبار اصلی', 'code' => 'MAIN', 'fiscal_year_id' => $year->id]);
        $this->product->warehouses()->attach($warehouse->id, ['quantity' => 12, 'average_cost' => 162.5]);
        foreach ([[InvoiceType::BUY, '2026-04-01', 10, -1000, 100], [InvoiceType::SELL, '2026-04-05', 4, 400, 100], [InvoiceType::BUY, '2026-04-15', 10, -2000, 162.5], [InvoiceType::SELL, '2026-04-18', 4, 650, 162.5]] as $index => [$type, $date, $quantity, $inventoryValue, $average]) {
            $document = Document::factory()->create(['fiscal_year_id' => $year->id, 'number' => $index + 1, 'date' => $date, 'creator_id' => $user->id, 'approved_at' => now(), 'approver_id' => $user->id]);
            $invoice = Invoice::create(['number' => $index + 1, 'date' => $date, 'invoice_type' => $type, 'status' => InvoiceStatus::APPROVED, 'customer_id' => $customer->id, 'creator_id' => $user->id, 'document_id' => $document->id, 'warehouse_id' => $warehouse->id, 'subtraction' => 0, 'vat' => 0, 'amount' => abs($inventoryValue)]);
            InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'itemable_type' => Product::class, 'itemable_id' => $this->product->id, 'quantity' => $quantity, 'unit_price' => abs($inventoryValue) / $quantity, 'cog_after' => $average, 'discount' => 0, 'vat' => 0, 'amount' => abs($inventoryValue)]);
            foreach ([$this->product->inventory_subject_id => $inventoryValue, $customer->subject_id => -$inventoryValue] as $subject => $value) {
                Transaction::create(['document_id' => $document->id, 'subject_id' => $subject, 'value' => $value, 'user_id' => $user->id]);
            }
            $this->assertEquals(0, $document->transactions()->sum('value'));
        }
    }

    public function test_interval_opening_includes_prior_regular_purchases_and_sales(): void
    {
        $row = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/10', 'end_date' => '2026/04/20'])['rows']->first();
        $this->assertEquals(6, $row['opening_quantity'], '10 bought - 4 sold before the range');
        $this->assertEquals(600, $row['opening_balance']);
        $this->assertEquals(12, $row['remaining_quantity']);
        $this->assertEquals(1950, $row['remaining_balance']);
    }

    public function test_interval_without_movements_preserves_quantity_and_value_at_its_start(): void
    {
        $row = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/20', 'end_date' => '2026/04/30'])['rows']->first();
        $this->assertEquals(12, $row['opening_quantity']);
        $this->assertEquals(1950, $row['opening_balance']);
        $this->assertEquals(12, $row['remaining_quantity']);
        $this->assertEquals(1950, $row['remaining_balance']);
    }

    public function test_full_year_monetary_movements_reconcile_with_posted_inventory_ledger(): void
    {
        $row = app(InventoryTurnoverService::class)->report()['rows']->first();
        $this->assertEquals(20, $row['imported_quantity']);
        $this->assertEquals(3000, $row['imported_balance'], 'Purchases are 1000 + 2000, not 20 times the final average.');
        $this->assertEquals(1050, $row['exported_balance'], 'Posted costs are 400 + 650.');
        $this->assertEquals(-1950, (float) Transaction::where('subject_id', $this->product->inventory_subject_id)->sum('value'));
        $this->assertEquals(1950, $row['remaining_balance']);
    }

    public function test_warehouse_transfer_changes_only_the_selected_warehouse_report(): void
    {
        [$source, $destination] = $this->transfer();
        $filters = ['start_date' => '2026/04/10', 'end_date' => '2026/04/20'];
        $service = app(InventoryTurnoverService::class);
        $sourceRow = $service->report([...$filters, 'warehouse_id' => $source->id])['rows']->first();
        $destinationRow = $service->report([...$filters, 'warehouse_id' => $destination->id])['rows']->first();
        $globalRow = $service->report($filters)['rows']->first();
        $this->assertEquals(10, $sourceRow['remaining_quantity']);
        $this->assertEquals(1625, $sourceRow['remaining_balance']);
        $this->assertEquals(2, $destinationRow['imported_quantity']);
        $this->assertEquals(325, $destinationRow['imported_balance']);
        $this->assertEquals(12, $globalRow['remaining_quantity']);
        $this->assertEquals(1950, $globalRow['remaining_balance']);
    }

    public function test_prior_warehouse_transfer_is_carried_into_opening_quantity_and_value(): void
    {
        [$source, $destination] = $this->transfer();
        foreach ([[$source, 10, 1625], [$destination, 2, 325]] as [$warehouse, $quantity, $value]) {
            $row = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/20', 'end_date' => '2026/04/30', 'warehouse_id' => $warehouse->id])['rows']->first();
            $this->assertEquals($quantity, $row['opening_quantity']);
            $this->assertEquals($value, $row['opening_balance']);
        }
    }

    public function test_approved_capitalized_ancillary_cost_is_included_in_stock_value(): void
    {
        $invoice = Invoice::where('invoice_type', InvoiceType::BUY)->orderByDesc('date')->firstOrFail();
        $doc = Document::factory()->create(['fiscal_year_id' => getActiveFiscalYear(), 'date' => '2026-04-16', 'number' => 5]);
        $cost = AncillaryCost::create(['fiscal_year_id' => getActiveFiscalYear(), 'invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id, 'document_id' => $doc->id, 'number' => 1, 'date' => '2026-04-16', 'type' => 1, 'status' => InvoiceStatus::APPROVED, 'amount' => 120, 'vat' => 0]);
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $this->product->inventory_subject_id, 'value' => -120, 'user_id' => $invoice->creator_id]);
        Transaction::create(['document_id' => $doc->id, 'subject_id' => $invoice->customer->subject_id, 'value' => 120, 'user_id' => $invoice->creator_id]);
        $row = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/10', 'end_date' => '2026/04/20'])['rows']->first();
        $this->assertEquals(10, $row['imported_quantity']);
        $this->assertEquals(2120, $row['imported_balance']);
        $this->assertEquals(2070, $row['remaining_balance']);
        $later = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/20', 'end_date' => '2026/04/30'])['rows']->first();
        $this->assertEquals(2070, $later['opening_balance']);
        $cost->update(['status' => InvoiceStatus::UNAPPROVED]);
        $draftExcluded = app(InventoryTurnoverService::class)->report(['start_date' => '2026/04/10', 'end_date' => '2026/04/20'])['rows']->first();
        $this->assertEquals(2000, $draftExcluded['imported_balance']);
    }

    private function transfer(): array
    {
        $source = Warehouse::sole();
        $destination = Warehouse::create(['fiscal_year_id' => getActiveFiscalYear(), 'name' => 'انبار شعبه', 'code' => 'BRANCH']);
        $this->product->warehouses()->attach($destination->id, ['quantity' => 2, 'average_cost' => 162.5]);
        WarehouseTransfer::create(['product_id' => $this->product->id, 'from_warehouse_id' => $source->id, 'to_warehouse_id' => $destination->id, 'quantity' => 2, 'unit_cost' => 162.5, 'transferred_at' => '2026-04-19']);

        return [$source, $destination];
    }
}
