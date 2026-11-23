<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\ApiTokenAbilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpProductReadToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $year;

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

        foreach (['api.access', 'products.index', 'products.show'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->token = $this->user->createToken('Products Read', ['products.index', 'products.show'])->plainTextToken;
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

    public function test_api_token_ability_service_includes_product_read_permissions(): void
    {
        $abilities = app(ApiTokenAbilityService::class)->abilities();

        $this->assertTrue($abilities->contains('products.index'));
        $this->assertTrue($abilities->contains('products.show'));
    }

    public function test_list_products_searches_filters_and_paginates_within_fiscal_year(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $groupA = ProductGroup::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Oscilloscopes']);
        $groupB = ProductGroup::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Multimeters']);

        $p1 = Product::factory()->create([
            'fiscal_year_id' => $this->year->id,
            'group' => $groupA->id,
            'code' => 'FN-101',
            'name' => 'FNIRSI 1014D',
            'selling_price' => 12500000,
            'vat' => 10,
            'quantity' => 4,
            'oversell' => false,
        ]);
        $p2 = Product::factory()->create([
            'fiscal_year_id' => $this->year->id,
            'group' => $groupB->id,
            'code' => 'FN-202',
            'name' => 'FNIRSI DMT-99',
            'selling_price' => 3500000,
        ]);

        $otherYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->attach($otherYear->id);
        config(['active-fiscal-year-id' => $otherYear->id]);
        Product::factory()->create([
            'fiscal_year_id' => $otherYear->id,
            'code' => 'FN-999',
            'name' => 'Foreign Year Product',
        ]);
        config(['active-fiscal-year-id' => null]);

        // Search by name/code
        $byName = $this->callTool('list_products', [
            'fiscal_year_id' => $this->year->id,
            'query' => '1014D',
        ]);
        $this->assertSame('Toman', $byName['currency']);
        $this->assertSame(1, $byName['pagination']['total']);
        $this->assertSame($p1->id, $byName['products'][0]['id']);
        $this->assertSame('FN-101', $byName['products'][0]['code']);
        $this->assertSame('Oscilloscopes', $byName['products'][0]['group_name']);
        $this->assertSame(12500000.0, (float) $byName['products'][0]['selling_price']);
        $this->assertSame(10.0, (float) $byName['products'][0]['vat']);
        $this->assertSame(4.0, (float) $byName['products'][0]['quantity']);
        $this->assertFalse($byName['products'][0]['oversell']);

        // Search by exact numeric ID
        $byId = $this->callTool('list_products', [
            'fiscal_year_id' => $this->year->id,
            'query' => (string) $p2->id,
        ]);
        $this->assertSame(1, $byId['pagination']['total']);
        $this->assertSame($p2->id, $byId['products'][0]['id']);

        // Filter by group_id
        $byGroup = $this->callTool('list_products', [
            'fiscal_year_id' => $this->year->id,
            'group_id' => $groupB->id,
        ]);
        $this->assertSame(1, $byGroup['pagination']['total']);
        $this->assertSame('FN-202', $byGroup['products'][0]['code']);

        // Pagination
        $page1 = $this->callTool('list_products', [
            'fiscal_year_id' => $this->year->id,
            'limit' => 1,
            'page' => 1,
        ]);
        $this->assertSame(2, $page1['pagination']['total']);
        $this->assertSame(2, $page1['pagination']['last_page']);
        $this->assertCount(1, $page1['products']);

        // Literal % in query does not act as SQL wildcard
        $wildcard = $this->callTool('list_products', [
            'fiscal_year_id' => $this->year->id,
            'query' => '%',
        ]);
        $this->assertSame(0, $wildcard['pagination']['total']);
    }

    public function test_get_product_returns_details_by_id_or_code_and_rejects_foreign_year(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $group = ProductGroup::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Power Supplies']);
        $warehouse = Warehouse::create(['fiscal_year_id' => $this->year->id, 'name' => 'Main Warehouse', 'code' => 'WH-1']);

        $product = Product::factory()->create([
            'fiscal_year_id' => $this->year->id,
            'group' => $group->id,
            'code' => 'DPS-150',
            'name' => 'FNIRSI DPS-150',
            'selling_price' => 4200000,
            'vat' => 10,
            'quantity' => 7,
            'oversell' => true,
            'location' => 'Shelf A2',
        ]);

        WarehouseProductStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'average_cost' => 3000000,
        ]);

        $otherYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->attach($otherYear->id);
        config(['active-fiscal-year-id' => $otherYear->id]);
        $foreignProduct = Product::factory()->create([
            'fiscal_year_id' => $otherYear->id,
            'code' => 'FOREIGN-1',
            'name' => 'Other Year Item',
        ]);
        config(['active-fiscal-year-id' => null]);

        $byId = $this->callTool('get_product', [
            'fiscal_year_id' => $this->year->id,
            'product_id' => $product->id,
        ]);
        $this->assertSame($product->id, $byId['product']['id']);
        $this->assertSame('DPS-150', $byId['product']['code']);
        $this->assertTrue($byId['product']['oversell']);
        $this->assertCount(1, $byId['product']['warehouse_stocks']);
        $this->assertSame('Main Warehouse', $byId['product']['warehouse_stocks'][0]['warehouse_name']);

        $byCode = $this->callTool('get_product', [
            'fiscal_year_id' => $this->year->id,
            'code' => 'DPS-150',
        ]);
        $this->assertSame($product->id, $byCode['product']['id']);

        // Foreign product is not returned in $this->year
        $notFound = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_product',
                'arguments' => [
                    'fiscal_year_id' => $this->year->id,
                    'product_id' => $foreignProduct->id,
                ],
            ],
        ])->assertOk()->json('result');
        $this->assertTrue($notFound['isError']);

        // Missing both product_id and code fails validation
        $missingIdentifier = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_product',
                'arguments' => [
                    'fiscal_year_id' => $this->year->id,
                ],
            ],
        ])->assertOk()->json('result');
        $this->assertTrue($missingIdentifier['isError']);
    }

    public function test_product_tools_enforce_two_layer_permissions_and_context_access(): void
    {
        // Token with only products.index can see get_current_context, list_products, and get_product
        $list = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');

        $names = array_column($list, 'name');
        $this->assertContains('get_current_context', $names);
        $this->assertContains('list_products', $names);
        $this->assertContains('get_product', $names);

        // Token without product abilities cannot list or call product tools
        $noProductToken = $this->user->createToken('No Products', ['customers.show'])->plainTextToken;
        app('auth')->forgetGuards();

        $restrictedList = $this->withToken($noProductToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');
        $restrictedNames = array_column($restrictedList, 'name');
        $this->assertNotContains('list_products', $restrictedNames);
        $this->assertNotContains('get_product', $restrictedNames);

        $deniedError = $this->withToken($noProductToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'list_products', 'arguments' => ['fiscal_year_id' => $this->year->id]],
        ])->assertStatus(400)->json('error');
        $this->assertNotNull($deniedError);

        // Token with only products.index can see list_products, but NOT get_product
        $indexOnlyToken = $this->user->createToken('Products Index Only', ['products.index'])->plainTextToken;
        app('auth')->forgetGuards();

        $indexOnlyList = $this->withToken($indexOnlyToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/list',
        ])->assertOk()->json('result.tools');
        $indexOnlyNames = array_column($indexOnlyList, 'name');
        $this->assertContains('list_products', $indexOnlyNames);
        $this->assertNotContains('get_product', $indexOnlyNames);

        $indexOnlyError = $this->withToken($indexOnlyToken)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'get_product', 'arguments' => ['fiscal_year_id' => $this->year->id, 'product_id' => 1]],
        ])->assertStatus(400)->json('error');
        $this->assertNotNull($indexOnlyError);

        // Revoking user permission also blocks existing token
        $this->user->revokePermissionTo(['products.index', 'products.show']);
        app('auth')->forgetGuards();

        $revokedError = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'list_products', 'arguments' => ['fiscal_year_id' => $this->year->id]],
        ])->assertStatus(400)->json('error');
        $this->assertNotNull($revokedError);
    }
}
