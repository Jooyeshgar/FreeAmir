<?php

namespace Tests\Feature;

use App\Enums\AncillaryCostType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AncillaryCost;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\User;
use App\Services\WarehouseDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class WarehouseDashboardTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private Customer $customer;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'fiscal_year' => 1405,
        ]);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_user_with_products_index_can_view_warehouse_dashboard(): void
    {
        $this->givePermission('products.index');

        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertOk();
        $response->assertViewIs('warehouse.dashboard');
        $response->assertViewHas('inventory');
    }

    public function test_user_without_products_index_cannot_view_warehouse_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertForbidden();
    }

    public function test_accounting_section_is_hidden_without_report_or_document_access(): void
    {
        $this->givePermission('products.index');

        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertOk();
        $response->assertViewHas('accounting', null);
        $response->assertDontSee(__('Accounting KPIs'));
    }

    public function test_accounting_section_is_visible_with_report_access(): void
    {
        $this->givePermission('products.index', 'reports.documents');

        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertOk();
        $response->assertViewHas('canViewAccounting', true);
        $response->assertSee(__('Accounting KPIs'));
        $this->assertNotNull($response->viewData('accounting'));
    }

    public function test_dashboard_service_calculates_operational_and_accounting_kpis(): void
    {
        $productGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $serviceGroup = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        $product = Product::factory()->withGroup($productGroup)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-001',
            'name' => 'Test Product',
            'quantity' => 8,
            'quantity_warning' => 10,
            'average_cost' => 50,
            'selling_price' => 120,
        ]);

        Product::factory()->withGroup($productGroup)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-002',
            'quantity' => -2,
            'quantity_warning' => 5,
            'average_cost' => 30,
        ]);

        $service = Service::factory()->withGroup($serviceGroup)->withSubject()->create([
            'company_id' => $this->companyId,
            'code' => 'S-001',
            'name' => 'Test Service',
            'selling_price' => 200,
        ]);

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, '2026-03-21', 500);
        InvoiceItem::factory()->create([
            'invoice_id' => $sell->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 300,
            'cog_after' => 50,
            'quantity_at' => 11,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $sell->id,
            'itemable_type' => Service::class,
            'itemable_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 200,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 400,
        ]);

        $returnSell = $this->invoice(InvoiceType::RETURN_SELL, InvoiceStatus::APPROVED, 2, '2026-03-22', 100, $sell->id);
        InvoiceItem::factory()->create([
            'invoice_id' => $returnSell->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 100,
            'cog_after' => 50,
            'quantity_at' => 10,
        ]);

        AncillaryCost::create([
            'number' => 1,
            'type' => AncillaryCostType::Shipping,
            'amount' => 25,
            'vat' => 0,
            'date' => '2026-03-22',
            'invoice_id' => $sell->id,
            'status' => InvoiceStatus::APPROVED,
            'company_id' => $this->companyId,
            'customer_id' => $this->customer->id,
        ]);

        $data = app(WarehouseDashboardService::class)->dashboard(includeAccounting: true);

        $this->assertSame(2, $data['inventory']['productsCount']);
        $this->assertSame(1, $data['inventory']['servicesCount']);
        $this->assertSame(2, $data['inventory']['lowStockCount']);
        $this->assertSame(1, $data['inventory']['negativeStockCount']);
        $this->assertEquals(2.0, $data['sales']['netProductUnits']);
        $this->assertEquals(2.0, $data['sales']['netServiceUnits']);
        $this->assertEquals(340.0, $data['accounting']['inventoryValue']);
        $this->assertEquals(600.0, $data['accounting']['netSales']);
        $this->assertEquals(100.0, $data['accounting']['productGrossProfit']);
        $this->assertEquals(25.0, $data['accounting']['approvedAncillaryCosts']);
    }

    private function givePermission(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)
                ->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
                ->all()
        );
    }

    private function invoice(
        InvoiceType $type,
        InvoiceStatus $status,
        int $number,
        string $date,
        float $amount,
        ?int $returnedInvoiceId = null
    ): Invoice {
        return Invoice::create([
            'number' => $number,
            'date' => $date,
            'invoice_type' => $type,
            'status' => $status,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => $amount,
            'title' => $type->label(),
            'returned_invoice_id' => $returnedInvoiceId,
        ]);
    }
}
