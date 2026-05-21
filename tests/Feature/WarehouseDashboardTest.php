<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Services\WarehouseDashboardService;
use Carbon\Carbon;
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

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);
        $_COOKIE['active-company-id'] = (string) $this->companyId;
        config(['active-company-id' => $this->companyId]);
        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_user_with_products_index_can_view_warehouse_dashboard(): void
    {
        $this->grant('products.index');

        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertOk();
        $response->assertViewIs('warehouse.dashboard');
        $response->assertViewHas('summary');
        $response->assertViewHas('categoryBreakdown');
        $response->assertViewHas('topSellers');
        $response->assertViewHas('belowReorderItems');
        $response->assertViewHas('stagnantItems');
    }

    public function test_user_without_products_index_cannot_view_warehouse_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('warehouse.dashboard'));

        $response->assertForbidden();
    }

    public function test_service_computes_inventory_kpis_and_top_sellers(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);

        $bestSeller = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-001',
            'name' => 'Best Seller',
            'quantity' => 5,
            'quantity_warning' => 10,
            'average_cost' => 100,
            'selling_price' => 250,
        ]);

        $stagnant = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-002',
            'name' => 'Stagnant Item',
            'quantity' => 20,
            'quantity_warning' => 5,
            'average_cost' => 50,
            'selling_price' => 90,
        ]);

        $sell = $this->invoice(InvoiceType::SELL, InvoiceStatus::APPROVED, 1, Carbon::now()->subDays(5)->toDateString(), 750);
        InvoiceItem::factory()->create([
            'invoice_id' => $sell->id,
            'itemable_type' => Product::class,
            'itemable_id' => $bestSeller->id,
            'quantity' => 3,
            'unit_price' => 250,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => 750,
            'cog_after' => 100,
            'quantity_at' => 8,
        ]);

        $data = app(WarehouseDashboardService::class)->dashboard();

        $this->assertEquals(2, $data['summary']['total_item_count']);
        $this->assertEquals(1500.0, $data['summary']['total_inventory_value']);
        $this->assertEquals(1, $data['summary']['below_reorder_count']);
        $this->assertCount(1, $data['topSellers']);
        $this->assertEquals('Best Seller', $data['topSellers']->first()['name']);
        $this->assertEquals(3.0, $data['topSellers']->first()['units']);
    }

    public function test_category_filter_restricts_dashboard_scope(): void
    {
        $widgets = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        $gadgets = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Gadgets']);

        Product::factory()->withGroup($widgets)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'W-1',
            'quantity' => 5,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);
        Product::factory()->withGroup($gadgets)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'G-1',
            'quantity' => 20,
            'quantity_warning' => 5,
            'average_cost' => 50,
        ]);

        $data = app(WarehouseDashboardService::class)->dashboard(['category_ids' => [$widgets->id]]);

        $this->assertEquals(1, $data['summary']['total_item_count']);
        $this->assertEquals(500.0, $data['summary']['total_inventory_value']);
        $this->assertEquals(1, $data['categoryBreakdown']->count());
        $this->assertEquals('Widgets', $data['categoryBreakdown']->first()['name']);
    }

    public function test_status_filter_returns_below_reorder_items(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        $lowStock = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-LOW',
            'quantity' => 3,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);
        Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'code' => 'P-OK',
            'quantity' => 100,
            'quantity_warning' => 10,
            'average_cost' => 100,
        ]);

        $data = app(WarehouseDashboardService::class)->dashboard(['status' => 'below_reorder']);

        $this->assertCount(1, $data['statusFilteredItems']);
        $this->assertEquals($lowStock->id, $data['statusFilteredItems']->first()['id']);
    }

    private function grant(string ...$permissions): void
    {
        $this->user->givePermissionTo(
            collect($permissions)
                ->map(fn (string $permission) => Permission::firstOrCreate(['name' => $permission]))
                ->all()
        );
    }

    private function invoice(InvoiceType $type, InvoiceStatus $status, int $number, string $date, float $amount): Invoice
    {
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
        ]);
    }
}
