<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Extensions\Contracts\ModelExtension;
use App\Extensions\InvoiceExtensionRegistry;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ApiTokenAbilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class McpExtensionsAndApproveInvoiceTest extends TestCase
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
            'quantity' => 0,
            'oversell' => true,
            'average_cost' => 0,
        ]);

        $this->warehouse = Warehouse::create([
            'fiscal_year_id' => $this->year->id,
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
        ]);

        foreach (['api.access', 'invoices.store', 'invoices.approve', 'invoices.index', 'customers.store', 'products.store'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->token = $this->user->createToken('Writer & Approver', [
            'invoices.store',
            'invoices.approve',
            'invoices.index',
        ])->plainTextToken;

        config(['active-fiscal-year-id' => null]);
    }

    private function callTool(string $name, array $arguments = [], ?string $token = null): array
    {
        $response = $this->withToken($token ?? $this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ])->assertOk();

        $result = $response->json('result');
        self::assertFalse($result['isError'] ?? true, json_encode($result, JSON_UNESCAPED_UNICODE));

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_api_token_ability_service_includes_invoices_approve(): void
    {
        $abilities = app(ApiTokenAbilityService::class)->abilities();

        $this->assertTrue($abilities->contains('invoices.approve'));
    }

    public function test_invoice_extensions_are_hidden_when_unregistered_and_persisted_when_registered(): void
    {
        $registry = new InvoiceExtensionRegistry(app());
        app()->instance(InvoiceExtensionRegistry::class, $registry);

        $toolsBefore = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');

        $createInvoiceBefore = collect($toolsBefore)->firstWhere('name', 'create_invoice');
        $this->assertArrayNotHasKey('extensions', $createInvoiceBefore['inputSchema']['properties']);

        $contextBefore = $this->callTool('get_current_context', ['fiscal_year_id' => $this->year->id]);
        $this->assertArrayNotHasKey('invoice_extensions', $contextBefore);

        // Register a fake extension (simulating an optional package such as amir/invoice-categories)
        FakeInvoiceCategoryExtension::$persisted = [];
        $registry->register('categories', FakeInvoiceCategoryExtension::class);

        $toolsAfter = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');

        $createInvoiceAfter = collect($toolsAfter)->firstWhere('name', 'create_invoice');
        $this->assertArrayHasKey('extensions', $createInvoiceAfter['inputSchema']['properties']);
        $this->assertArrayHasKey('categories', $createInvoiceAfter['inputSchema']['properties']['extensions']['properties']);

        $contextAfter = $this->callTool('get_current_context', ['fiscal_year_id' => $this->year->id]);
        $this->assertSame([['id' => 7, 'name' => 'Online Sales']], $contextAfter['invoice_extensions']['categories']);

        $created = $this->callTool('create_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'quantity' => 1,
                'unit' => 1000,
            ]],
            'extensions' => [
                'categories' => ['category_ids' => [7]],
            ],
        ]);

        $this->assertSame('pre_invoice', $created['status']);
        $this->assertCount(1, FakeInvoiceCategoryExtension::$persisted);
        $this->assertSame($created['id'], FakeInvoiceCategoryExtension::$persisted[0]['invoice_id']);
        $this->assertSame(['category_ids' => [7]], FakeInvoiceCategoryExtension::$persisted[0]['data']);
    }

    public function test_approve_invoice_requires_two_layer_permission_and_open_year_and_posts_document(): void
    {
        $created = $this->callTool('create_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'quantity' => 1,
                'unit' => 5000,
            ]],
        ]);
        $this->assertFalse($created['approved']);
        $this->assertSame('pre_invoice', $created['status']);

        // Token without invoices.approve cannot list or call approve_invoice
        $storeOnlyToken = $this->user->createToken('Store Only', ['invoices.store'])->plainTextToken;
        app('auth')->forgetGuards();

        $storeOnlyTools = $this->withToken($storeOnlyToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');
        $this->assertNotContains('approve_invoice', array_column($storeOnlyTools, 'name'));

        $this->withToken($storeOnlyToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $created['id']]],
        ])->assertStatus(400);

        // Overselling product without confirm returns a confirmation prompt
        app('auth')->forgetGuards();
        $needsConfirm = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $created['id']]],
        ])->assertOk()->json('result');
        $this->assertTrue($needsConfirm['isError']);
        $confirmPayload = json_decode($needsConfirm['content'][0]['text'], true);
        $this->assertTrue($confirmPayload['needs_confirmation']);
        $this->assertSame('product', $confirmPayload['conflicts'][0]['type']);
        $this->assertSame($this->product->id, $confirmPayload['conflicts'][0]['id']);
        $this->assertSame('100', $confirmPayload['conflicts'][0]['code']);
        $this->assertSame($this->product->name, $confirmPayload['conflicts'][0]['name']);

        // Authorized token with confirm: true approves the draft invoice
        $approved = $this->callTool('approve_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_id' => $created['id'],
            'confirm' => true,
        ]);

        $this->assertTrue($approved['approved']);
        $this->assertSame('approved', $approved['status']);
        $this->assertNotNull($approved['document_id']);

        $invoice = Invoice::withoutGlobalScopes()->findOrFail($created['id']);
        $this->assertSame(InvoiceStatus::APPROVED, $invoice->status);
        $this->assertSame(InvoiceType::SELL, $invoice->invoice_type);

        // Existing invoice with out-of-range date cannot be approved
        $outOfRangeDraft = Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'number' => 777,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PRE_INVOICE,
            'date' => '2025-01-01',
            'amount' => 1000,
            'vat' => 0,
            'subtraction' => 0,
        ]);
        $outOfRangeResult = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $outOfRangeDraft->id, 'confirm' => true]],
        ])->assertOk()->json('result');
        $this->assertTrue($outOfRangeResult['isError']);

        // APPROVED_INACTIVE invoice cannot be reactivated via approve_invoice
        $inactiveInvoice = Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $this->customer->id,
            'creator_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
            'number' => 778,
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED_INACTIVE,
            'date' => '2026-05-01',
            'amount' => 1000,
            'vat' => 0,
            'subtraction' => 0,
        ]);
        $inactiveResult = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $inactiveInvoice->id, 'confirm' => true]],
        ])->assertOk()->json('result');
        $this->assertTrue($inactiveResult['isError']);

        // Closed fiscal year rejects approving a real draft invoice
        $closedYearDraft = $this->callTool('create_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'quantity' => 1,
                'unit' => 2000,
            ]],
        ]);
        $this->year->update(['closed_at' => now()]);
        $closedResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $closedYearDraft['id'], 'confirm' => true]],
        ])->assertOk()->json('result');
        $this->assertTrue($closedResponse['isError']);

        // Revoking user permission blocks a token that still carries the invoices.approve ability
        $this->year->update(['closed_at' => null]);
        $this->user->revokePermissionTo('invoices.approve');
        app('auth')->forgetGuards();

        $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => ['name' => 'approve_invoice', 'arguments' => ['fiscal_year_id' => $this->year->id, 'invoice_id' => $closedYearDraft['id'], 'confirm' => true]],
        ])->assertStatus(400);
    }
}

class FakeInvoiceCategoryExtension implements ModelExtension
{
    public static array $persisted = [];

    public function validate(array $input, ?Model $model = null): array
    {
        return ['category_ids' => array_map('intval', $input['category_ids'] ?? [])];
    }

    public function persist(Model $model, array $data): void
    {
        self::$persisted[] = [
            'invoice_id' => $model->id,
            'data' => $data,
        ];
    }

    public function extendQuery(Builder $query, Request $request): void {}

    public function mcpOptions(int $fiscalYearId): array
    {
        return [['id' => 7, 'name' => 'Online Sales']];
    }

    public function mcpDescription(): string
    {
        return 'Optional invoice category IDs (e.g. {"category_ids": [7]}).';
    }
}
