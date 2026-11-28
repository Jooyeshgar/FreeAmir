<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\MoadianHistory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class McpReconciliationToolsTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private FiscalYear $year;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $company = Company::factory()->create(['currency' => 'Toman']);
        $this->year = FiscalYear::factory()->for($company)->create(['year' => 1405]);
        $this->user = User::factory()->create();
        $this->user->fiscalYears()->sync([$this->year->id]);

        config(['active-fiscal-year-id' => $this->year->id]);
        $this->importSubjects($this->year->id);
        $this->importConfigs($this->year->id);

        $customerGroup = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->year->id]);
        $this->customer = Customer::factory()->withGroup($customerGroup)->withSubject()->create(['fiscal_year_id' => $this->year->id]);

        $productGroup = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->year->id]);
        $this->product = Product::factory()->withGroup($productGroup)->withSubjects()->create([
            'fiscal_year_id' => $this->year->id,
            'code' => '100',
            'quantity' => 5,
            'average_cost' => 0,
        ]);

        $this->warehouse = Warehouse::create([
            'fiscal_year_id' => $this->year->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
        ]);

        WarehouseProductStock::create([
            'fiscal_year_id' => $this->year->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'average_cost' => 500,
        ]);

        foreach (['api.access', 'invoices.store', 'invoices.index', 'invoices.show', 'invoices.edit', 'products.index', 'products.show', 'products.edit', 'products.store'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->token = $this->user->createToken('Invoice Ref Writer', ['invoices.store', 'invoices.index', 'invoices.show', 'invoices.edit', 'products.index', 'products.show', 'products.edit', 'products.store'])->plainTextToken;
        config(['active-fiscal-year-id' => null]);
    }

    private function mcp(string $tool, array $arguments): array
    {
        $result = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => array_merge(['fiscal_year_id' => $this->year->id], $arguments)],
        ])->assertOk()->json('result');

        return ['error' => (bool) ($result['isError'] ?? false), 'data' => json_decode($result['content'][0]['text'], true)];
    }

    private function makeInvoice(?string $reference): Invoice
    {
        $created = $this->mcp('create_invoice', [
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'date' => '2026-05-01',
            'external_reference_id' => $reference,
            'items' => [['item_type' => 'product', 'item_id' => $this->product->id, 'quantity' => 1, 'unit' => 1000]],
        ]);
        $this->assertFalse($created['error']);

        return Invoice::withoutGlobalScopes()->findOrFail($created['data']['id']);
    }

    public function test_invoice_reads_expose_filter_and_include_items_by_external_reference(): void
    {
        $a = $this->makeInvoice('fnirsi:FN-1');
        $this->makeInvoice('fnirsi:FN-2');

        $list = $this->mcp('list_invoices', ['external_reference_id' => 'fnirsi:FN-1', 'include_items' => true]);
        $this->assertFalse($list['error']);
        $this->assertCount(1, $list['data']['invoices']);
        $this->assertSame($a->id, $list['data']['invoices'][0]['id']);
        $this->assertSame('fnirsi:FN-1', $list['data']['invoices'][0]['external_reference_id']);
        $this->assertCount(1, $list['data']['invoices'][0]['items']);

        $plain = $this->mcp('list_invoices', []);
        $this->assertArrayNotHasKey('items', $plain['data']['invoices'][0]);

        $get = $this->mcp('get_invoice', ['external_reference_id' => 'fnirsi:FN-1']);
        $this->assertFalse($get['error']);
        $this->assertSame($a->id, $get['data']['invoice']['id']);
        $this->assertSame('fnirsi:FN-1', $get['data']['invoice']['external_reference_id']);
    }

    public function test_update_invoice_sets_clears_and_rejects_duplicate_references(): void
    {
        $a = $this->makeInvoice(null);
        $b = $this->makeInvoice('fnirsi:FN-2');

        $set = $this->mcp('update_invoice', ['invoice_id' => $a->id, 'external_reference_id' => ' fnirsi:FN-9 ']);
        $this->assertFalse($set['error']);
        $this->assertSame('fnirsi:FN-9', $a->fresh()->external_reference_id);

        $dup = $this->mcp('update_invoice', ['invoice_id' => $a->id, 'external_reference_id' => 'fnirsi:FN-2']);
        $this->assertTrue($dup['error']);
        $this->assertSame('fnirsi:FN-9', $a->fresh()->external_reference_id);

        $clear = $this->mcp('update_invoice', ['invoice_id' => $b->id, 'external_reference_id' => '']);
        $this->assertFalse($clear['error']);
        $this->assertNull($b->fresh()->external_reference_id);
    }

    public function test_context_identifies_company_by_name(): void
    {
        $context = $this->mcp('get_current_context', []);
        $this->assertFalse($context['error']);
        $companyName = $this->year->company->name;
        $this->assertSame($companyName, $context['data']['available_fiscal_years'][0]['company_name']);
        $this->assertSame($companyName, $context['data']['active_fiscal_year']['company_name']);
    }

    public function test_product_external_sku_can_be_created_searched_and_updated(): void
    {
        $created = $this->mcp('create_product', ['name' => 'LC1020E', 'code' => '200', 'external_sku' => 'FNIRSI-LC1020E']);
        $this->assertFalse($created['error'], json_encode($created['data']));
        $this->assertSame('FNIRSI-LC1020E', $created['data']['external_sku']);

        $list = $this->mcp('list_products', ['external_sku' => 'FNIRSI-LC1020E']);
        $this->assertCount(1, $list['data']['products']);
        $this->assertSame($created['data']['id'], $list['data']['products'][0]['id']);

        $get = $this->mcp('get_product', ['external_sku' => 'FNIRSI-LC1020E']);
        $this->assertSame($created['data']['id'], $get['data']['product']['id']);

        $update = $this->mcp('update_product', ['product_id' => $this->product->id, 'external_sku' => 'FNIRSI-X']);
        $this->assertFalse($update['error']);
        $this->assertSame('FNIRSI-X', $this->product->fresh()->external_sku);

        $this->assertSame($this->product->id, $this->mcp('list_products', ['query' => 'FNIRSI-X'])['data']['products'][0]['id']);
    }

    public function test_update_tools_reject_missing_records_and_allow_reference_on_rejected_invoice(): void
    {
        $this->assertTrue($this->mcp('update_invoice', ['invoice_id' => 999999, 'external_reference_id' => 'x'])['error']);
        $this->assertTrue($this->mcp('update_product', ['product_id' => 999999, 'external_sku' => 'x'])['error']);

        $a = $this->makeInvoice('fnirsi:FN-5');
        $b = $this->makeInvoice(null);
        $b->update(['status' => InvoiceStatus::REJECTED->value]);

        $this->assertFalse($this->mcp('update_invoice', ['invoice_id' => $b->id, 'external_reference_id' => 'fnirsi:FN-5'])['error']);
        $this->assertSame($a->external_reference_id, $b->fresh()->external_reference_id);
    }

    public function test_update_tools_require_edit_permissions(): void
    {
        $this->user->revokePermissionTo(['invoices.edit', 'products.edit']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $invoice = $this->makeInvoice(null);
        foreach ([
            ['update_invoice', ['invoice_id' => $invoice->id, 'external_reference_id' => 'x']],
            ['update_product', ['product_id' => $this->product->id, 'external_sku' => 'x']],
        ] as [$tool, $arguments]) {
            $response = $this->withToken($this->token)->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $tool, 'arguments' => array_merge(['fiscal_year_id' => $this->year->id], $arguments)],
            ]);
            $this->assertTrue($response->status() >= 400 || ($response->json('result.isError') ?? false) || $response->json('error') !== null);
        }

        $this->assertNull($invoice->fresh()->external_reference_id);
        $this->assertNull($this->product->fresh()->external_sku);
    }

    public function test_external_sku_must_be_unique_per_fiscal_year(): void
    {
        $this->product->update(['external_sku' => 'FNIRSI-A']);

        $duplicateCreate = $this->mcp('create_product', ['name' => 'Other', 'code' => '201', 'external_sku' => 'FNIRSI-A']);
        $this->assertTrue($duplicateCreate['error']);

        $other = $this->mcp('create_product', ['name' => 'Other', 'code' => '202', 'external_sku' => 'FNIRSI-B']);
        $this->assertFalse($other['error'], json_encode($other['data']));

        $duplicateUpdate = $this->mcp('update_product', ['product_id' => $other['data']['id'], 'external_sku' => 'FNIRSI-A']);
        $this->assertTrue($duplicateUpdate['error']);
        $this->assertSame('FNIRSI-B', Product::find($other['data']['id'])->external_sku);

        // Re-saving the same value on the owning product and clearing are allowed.
        $this->assertFalse($this->mcp('update_product', ['product_id' => $this->product->id, 'external_sku' => 'FNIRSI-A'])['error']);
        $this->assertFalse($this->mcp('update_product', ['product_id' => $this->product->id, 'external_sku' => ''])['error']);
        $this->assertNull($this->product->fresh()->external_sku);
    }

    public function test_update_invoice_reference_is_allowed_for_invoice_sent_to_moadian(): void
    {
        $invoice = $this->makeInvoice(null);
        MoadianHistory::create(['invoice_id' => $invoice->id, 'data' => ['status' => 'success', 'referenceNumber' => 'REF-1']]);

        $response = $this->mcp('update_invoice', ['invoice_id' => $invoice->id, 'external_reference_id' => 'fnirsi:FN-9']);

        $this->assertFalse($response['error'], json_encode($response['data']));
        $this->assertSame('fnirsi:FN-9', $invoice->fresh()->external_reference_id);
    }

    public function test_full_access_token_lists_update_tools_but_never_exceeds_user_permissions(): void
    {
        $full = $this->user->createToken('Full access', ['*'])->plainTextToken;
        $names = fn (): array => array_column($this->withToken($full)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ])->assertOk()->json('result.tools'), 'name');

        self::assertContains('update_invoice', $names());
        self::assertContains('update_product', $names());
        self::assertNotContains('approve_invoice', $names());

        $this->user->revokePermissionTo('invoices.edit');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app('auth')->forgetGuards();
        self::assertNotContains('update_invoice', $names());
        self::assertContains('update_product', $names());
    }
}
