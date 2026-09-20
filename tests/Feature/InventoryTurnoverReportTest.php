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
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\ReportExportNotification;
use App\Services\InventoryTurnoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InventoryTurnoverReportTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Customer $customer;

    private ProductGroup $group;

    private Product $product;

    private Warehouse $mainWarehouse;

    private Warehouse $otherWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        $this->company = Company::factory()->create(['name' => 'Test Company', 'fiscal_year' => 1404]);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);
        $this->withCookies(['active-company-id' => (string) $this->company->id]);
        config(['active-company-id' => $this->company->id]);
        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $this->mainWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Main warehouse',
            'code' => 'MAIN',
        ]);
        $this->otherWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'Other warehouse',
            'code' => 'OTHER',
        ]);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->company->id]);
        $this->group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id, 'name' => 'Widgets']);
        $this->product = Product::factory()->withGroup($this->group)->withSubjects()->create([
            'company_id' => $this->company->id,
            'code' => 'P-001',
            'name' => 'Widget',
        ]);
        $this->product->warehouses()->attach([
            $this->mainWarehouse->id => ['quantity' => 0, 'average_cost' => 0],
            $this->otherWarehouse->id => ['quantity' => 0, 'average_cost' => 0],
        ]);
    }

    public function test_report_calculates_opening_import_export_and_remaining_for_the_selected_period(): void
    {
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-01', 10, -1000, 1);
        $this->movement(InvoiceType::BUY, '2026-01-15', 2, -200, 2);
        $this->movement(InvoiceType::BUY, '2026-02-05', 5, -600, 3);
        $this->movement(InvoiceType::RETURN_SELL, '2026-02-07', 1, -100, 4);
        $this->movement(InvoiceType::SELL, '2026-02-10', 4, 400, 5);
        $this->movement(InvoiceType::RETURN_BUY, '2026-02-12', 2, 250, 6);
        $this->movement(InvoiceType::BUY, '2026-03-01', 8, -800, 7);
        $this->movement(InvoiceType::BUY, '2026-02-20', 99, -9900, 8, InvoiceStatus::PENDING);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-02', 99, -9900, 9, InvoiceStatus::PENDING);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-02-15', 77, -7700, 10);

        $report = app(InventoryTurnoverService::class)->report([
            'start_date' => '2026/02/01',
            'end_date' => '2026/02/28',
            'product_group' => $this->group->id,
        ]);
        $row = $report['rows']->first();

        $this->assertSame($this->product->code, $row['product_code']);
        $this->assertEquals(10, $row['opening_quantity']);
        $this->assertEquals(1000, $row['opening_balance']);
        $this->assertEquals(6, $row['imported_quantity']);
        $this->assertEquals(700, $row['imported_balance']);
        $this->assertEquals(6, $row['exported_quantity']);
        $this->assertEquals(650, $row['exported_balance']);
        $this->assertEquals(10, $row['remaining_quantity']);
        $this->assertEquals(1050, $row['remaining_balance']);
        $this->assertEquals(10, $report['totals']['remaining_quantity']);
    }

    public function test_report_includes_only_approved_or_settled_invoice_statuses(): void
    {
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-01', 3, -300, 1, InvoiceStatus::PARTIALLY_PAID);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-02', 4, -400, 2, InvoiceStatus::PAID);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-03', 50, -5000, 3, InvoiceStatus::UNAPPROVED);
        $this->movement(InvoiceType::BUY, '2026-02-05', 2, -220, 4, InvoiceStatus::PAID);
        $this->movement(InvoiceType::SELL, '2026-02-10', 1, 110, 5, InvoiceStatus::PARTIALLY_PAID);
        $this->movement(InvoiceType::BUY, '2026-02-15', 60, -6000, 6, InvoiceStatus::READY_TO_APPROVE);

        $row = app(InventoryTurnoverService::class)->report([
            'start_date' => '2026/02/01',
            'end_date' => '2026/02/28',
        ])['rows']->first();

        $this->assertEquals(7, $row['opening_quantity']);
        $this->assertEquals(700, $row['opening_balance']);
        $this->assertEquals(2, $row['imported_quantity']);
        $this->assertEquals(220, $row['imported_balance']);
        $this->assertEquals(1, $row['exported_quantity']);
        $this->assertEquals(110, $row['exported_balance']);
        $this->assertEquals(8, $row['remaining_quantity']);
        $this->assertEquals(810, $row['remaining_balance']);
    }

    public function test_beginning_inventory_on_start_date_is_opening_but_later_records_are_ignored(): void
    {
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-02-01', 10, -1000, 1);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-02-02', 50, -5000, 2);

        $row = app(InventoryTurnoverService::class)->report([
            'start_date' => '2026/02/01',
            'end_date' => '2026/02/28',
        ])['rows']->first();

        $this->assertEquals(10, $row['opening_quantity']);
        $this->assertEquals(1000, $row['opening_balance']);
        $this->assertEquals(0, $row['imported_quantity']);
        $this->assertEquals(0, $row['imported_balance']);
        $this->assertEquals(10, $row['remaining_quantity']);
        $this->assertEquals(1000, $row['remaining_balance']);
    }

    public function test_product_group_filter_excludes_products_from_other_groups(): void
    {
        $otherGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id, 'name' => 'Other']);
        Product::factory()->withGroup($otherGroup)->withSubjects()->create([
            'company_id' => $this->company->id,
            'code' => 'P-002',
            'name' => 'Other product',
        ]);

        $rows = app(InventoryTurnoverService::class)->report(['product_group' => $this->group->id])['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($this->product->id, $rows->first()['product_id']);
    }

    public function test_warehouse_filter_limits_quantities_and_balances_to_the_selected_warehouse(): void
    {
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-01', 10, -1000, 1);
        $this->movement(InvoiceType::BUY, '2026-02-05', 5, -500, 2);
        $this->movement(InvoiceType::SELL, '2026-02-10', 2, 200, 3);
        $this->movement(InvoiceType::BUY, '2026-02-12', 7, -700, 4, warehouse: $this->otherWarehouse);
        $this->movement(InvoiceType::BEGINNING_INVENTORY, '2026-01-01', 20, -2000, 5, warehouse: $this->otherWarehouse);

        $report = app(InventoryTurnoverService::class)->report([
            'start_date' => '2026/02/01',
            'end_date' => '2026/02/28',
            'warehouse_id' => $this->mainWarehouse->id,
        ]);
        $row = $report['rows']->first();

        $this->assertSame($this->mainWarehouse->id, $report['filters']['warehouse_id']);
        $this->assertEquals(10, $row['opening_quantity']);
        $this->assertEquals(1000, $row['opening_balance']);
        $this->assertEquals(5, $row['imported_quantity']);
        $this->assertEquals(500, $row['imported_balance']);
        $this->assertEquals(2, $row['exported_quantity']);
        $this->assertEquals(200, $row['exported_balance']);
        $this->assertEquals(13, $row['remaining_quantity']);
        $this->assertEquals(1300, $row['remaining_balance']);
    }

    public function test_authorized_user_can_view_the_report_and_invalid_date_range_is_rejected(): void
    {
        $this->grant('reports.inventory-turnover');
        $this->grant('reports.inventory-turnover.pdf');

        $this->actingAs($this->user)->get(route('reports.inventory-turnover'))
            ->assertOk()
            ->assertViewIs('reports.inventoryTurnover')
            ->assertSee('Product Inventory Turnover')
            ->assertSee('All Warehouses')
            ->assertSee('data-jdp', false)
            ->assertSee('x-data="searchSelect({', false)
            ->assertSee("url: ''", false)
            ->assertSee('name="product_group"', false)
            ->assertSee('name="warehouse_id"', false)
            ->assertDontSee('<select name="product_group"', false)
            ->assertDontSee('<select name="warehouse_id"', false)
            ->assertSee('inventory-turnover-pdf-delivery')
            ->assertSee('name="delivery" value="download"', false)
            ->assertSee('name="delivery" value="email"', false);

        $this->actingAs($this->user)->from(route('reports.inventory-turnover'))->get(route('reports.inventory-turnover', [
            'start_date' => '2026/03/01',
            'end_date' => '2026/02/28',
        ]))->assertRedirect(route('reports.inventory-turnover'))->assertSessionHasErrors('start_date');
    }

    public function test_report_defaults_to_fiscal_year_and_rejects_dates_outside_it(): void
    {
        [$fiscalStart, $fiscalEnd] = $this->company->fiscalYearRange();

        $report = app(InventoryTurnoverService::class)->report();

        $this->assertSame($fiscalStart->toDateString(), $report['filters']['start_date']);
        $this->assertSame($fiscalEnd->toDateString(), $report['filters']['end_date']);

        $this->grant('reports.inventory-turnover');
        $this->actingAs($this->user)->get(route('reports.inventory-turnover', [
            'start_date' => $fiscalStart->copy()->subDay()->toDateString(),
            'end_date' => $fiscalEnd->toDateString(),
        ]))->assertSessionHasErrors('start_date');

        $this->actingAs($this->user)->get(route('reports.inventory-turnover', [
            'start_date' => $fiscalStart->toDateString(),
            'end_date' => $fiscalEnd->copy()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('end_date');
    }

    public function test_pdf_has_company_creation_date_and_page_count_in_its_header(): void
    {
        $data = app(InventoryTurnoverService::class)->report();
        $html = Blade::render(file_get_contents(resource_path('views/reports/inventoryTurnoverPdf.blade.php')), $data);

        $this->assertStringContainsString('Test Company', $html);
        $this->assertStringContainsString('Created at', $html);
        $this->assertStringContainsString('{PAGENO}', $html);
        $this->assertStringContainsString('{nbpg}', $html);
    }

    public function test_pdf_route_returns_an_inline_pdf(): void
    {
        $this->grant('reports.inventory-turnover.pdf');

        $this->actingAs($this->user)->get(route('reports.inventory-turnover.pdf'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition');
    }

    public function test_pdf_can_be_downloaded_or_sent_by_email_through_the_delivery_dialog(): void
    {
        Notification::fake();
        $this->grant('report');
        $this->grant('reports.inventory-turnover.pdf');

        $this->actingAs($this->user)->post(route('report', ['delivery' => 'download']), [
            'export' => 'inventory_turnover_pdf',
            'filters' => ['warehouse_id' => $this->mainWarehouse->id],
        ])->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition');

        $this->actingAs($this->user)->post(route('report', ['delivery' => 'email']), [
            'export' => 'inventory_turnover_pdf',
            'filters' => ['warehouse_id' => $this->mainWarehouse->id],
            'email' => $this->user->email,
        ])->assertRedirect()->assertSessionHas('success');

        Notification::assertSentTo(
            $this->user,
            ReportExportNotification::class,
            fn (ReportExportNotification $notification): bool => $notification->mime === 'application/pdf'
                && str_starts_with($notification->filename, 'inventory-turnover-')
        );
    }

    private function movement(
        InvoiceType $type,
        string $date,
        float $quantity,
        float $balance,
        int $number,
        InvoiceStatus $status = InvoiceStatus::APPROVED,
        ?Warehouse $warehouse = null
    ): void {
        $warehouse ??= $this->mainWarehouse;
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'creator_id' => $this->user->id,
            'number' => $number,
            'date' => $date,
        ]);
        $invoice = Invoice::create([
            'number' => $number,
            'date' => $date,
            'invoice_type' => $type,
            'status' => $status,
            'customer_id' => $type === InvoiceType::BEGINNING_INVENTORY ? null : $this->customer->id,
            'creator_id' => $this->user->id,
            'document_id' => $document->id,
            'warehouse_id' => $warehouse->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => abs($balance),
            'title' => $type->label(),
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => $quantity > 0 ? abs($balance) / $quantity : 0,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => abs($balance),
        ]);
        if ($status->isApprovedOrSettled()) {
            Transaction::create([
                'document_id' => $document->id,
                'subject_id' => $this->product->inventory_subject_id,
                'user_id' => $this->user->id,
                'value' => $balance,
            ]);
        }
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
    }
}
