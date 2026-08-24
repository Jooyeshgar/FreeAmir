<?php

namespace Tests\Feature;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\WarehouseTransfer;
use App\Services\FiscalYearService;
use App\Services\ProductService;
use App\Services\WarehouseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'warehouses.index']),
            Permission::firstOrCreate(['name' => 'warehouses.destroy']),
            Permission::firstOrCreate(['name' => 'warehouses.transfer']),
            Permission::firstOrCreate(['name' => 'warehouses.transfer.store']),
            Permission::firstOrCreate(['name' => 'warehouses.transfer-history']),
        ]);

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
        config(['active-company-id' => $this->companyId]);
    }

    private function makeWarehouse(array $overrides = []): Warehouse
    {
        return Warehouse::create(array_merge([
            'company_id' => $this->companyId,
            'name' => 'Warehouse '.fake()->unique()->numberBetween(1, 99999),
            'code' => 'WH-'.fake()->unique()->numberBetween(1, 99999),
        ], $overrides));
    }

    public function test_index_filters_warehouses_by_name_code_and_inventory(): void
    {
        $warehouse = $this->makeWarehouse(['name' => 'Central Warehouse', 'code' => 'CENTRAL']);
        $this->makeWarehouse(['name' => 'Branch Warehouse', 'code' => 'BRANCH']);

        $product = Product::factory()->create(['company_id' => $this->companyId]);
        WarehouseProductStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 12,
            'average_cost' => 100,
        ]);

        $this->get(route('warehouses.index', ['name' => 'Central']))->assertOk()->assertSee('Central Warehouse')->assertDontSee('Branch Warehouse');
        $this->get(route('warehouses.index', ['code' => 'CENTRAL']))->assertOk()->assertSee('Central Warehouse')->assertDontSee('Branch Warehouse');
        $this->get(route('warehouses.index', ['warehouse_inventory' => '12']))->assertOk()->assertSee('Central Warehouse')->assertDontSee('Branch Warehouse');
    }

    public function test_index_disables_delete_when_warehouse_has_stock(): void
    {
        $warehouse = $this->makeWarehouse(['name' => 'Stock Warehouse']);
        $this->makeWarehouse(['name' => 'Empty Warehouse']);

        $product = Product::factory()->create(['company_id' => $this->companyId]);
        WarehouseProductStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'average_cost' => 100,
        ]);

        $this->get(route('warehouses.index'))->assertOk()->assertSee(__('A warehouse with stock cannot be deleted.'));
    }

    public function test_index_disables_delete_when_warehouse_has_transfer_history(): void
    {
        $source = $this->makeWarehouse(['name' => 'Source Warehouse']);
        $destination = $this->makeWarehouse(['name' => 'Destination Warehouse']);
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        WarehouseTransfer::create([
            'company_id' => $this->companyId,
            'product_id' => $product->id,
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $destination->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'transferred_by' => $this->user->id,
            'transferred_at' => now()->toDateString(),
        ]);

        $this->get(route('warehouses.index'))->assertOk()->assertSee(__('A warehouse with transfer history cannot be deleted.'));
    }

    public function test_destroy_rejects_warehouse_with_transfer_history(): void
    {
        $source = $this->makeWarehouse();
        $destination = $this->makeWarehouse();
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        WarehouseTransfer::create([
            'company_id' => $this->companyId,
            'product_id' => $product->id,
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $destination->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'transferred_by' => $this->user->id,
            'transferred_at' => now()->toDateString(),
        ]);

        $this->delete(route('warehouses.destroy', $source))->assertSessionHas('error', __('A warehouse with transfer history cannot be deleted.'));
        $this->assertDatabaseHas('warehouses', ['id' => $source->id]);
    }

    public function test_transfer_history_lists_transfers_and_filters_by_product_and_date(): void
    {
        $source = $this->makeWarehouse(['name' => 'History Source']);
        $destination = $this->makeWarehouse(['name' => 'History Destination']);
        $otherSource = $this->makeWarehouse(['name' => 'Other Source']);
        $otherDestination = $this->makeWarehouse(['name' => 'Other Destination']);
        $product = Product::factory()->create(['company_id' => $this->companyId, 'name' => 'History Product']);
        $otherProduct = Product::factory()->create(['company_id' => $this->companyId, 'name' => 'Other Product']);

        WarehouseTransfer::create([
            'company_id' => $this->companyId,
            'product_id' => $product->id,
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $destination->id,
            'quantity' => 2,
            'unit_cost' => 100,
            'transferred_by' => $this->user->id,
            'transferred_at' => Carbon::yesterday()->toDateString(),
        ]);
        WarehouseTransfer::create([
            'company_id' => $this->companyId,
            'product_id' => $otherProduct->id,
            'from_warehouse_id' => $otherSource->id,
            'to_warehouse_id' => $otherDestination->id,
            'quantity' => 3,
            'unit_cost' => 200,
            'transferred_by' => $this->user->id,
            'transferred_at' => now()->toDateString(),
        ]);

        $this->get(route('warehouses.transfer-history', [
            'product_id' => $product->id,
            'date_from' => formatDate(Carbon::yesterday()),
            'date_to' => formatDate(Carbon::yesterday()),
        ]))->assertOk()->assertViewHas('transfers', fn ($transfers) => $transfers->total() === 1)
            ->assertSee('History Product')->assertSee('History Source');
    }

    public function test_transfer_records_the_transferring_user_and_appears_in_history(): void
    {
        $source = $this->makeWarehouse();
        $destination = $this->makeWarehouse();
        $product = Product::factory()->create([
            'company_id' => $this->companyId,
            'name' => 'Transferred Product',
            'average_cost' => 125,
        ]);

        WarehouseProductStock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'average_cost' => 125,
        ]);

        $this->post(route('warehouses.transfer.store'), [
            'product_id' => $product->id,
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $destination->id,
            'quantity' => 4,
        ])->assertRedirect(route('warehouses.transfer'));

        $this->assertDatabaseHas('warehouse_transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $source->id,
            'to_warehouse_id' => $destination->id,
            'transferred_by' => $this->user->id,
        ]);

        $this->get(route('warehouses.transfer-history'))->assertOk()->assertSee('Transferred Product')->assertSee($this->user->name);
    }

    public function test_product_create_and_update_reject_another_company_warehouse(): void
    {
        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'products.store']),
            Permission::firstOrCreate(['name' => 'products.update']),
        ]);

        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $ownWarehouse = $this->makeWarehouse();
        $otherCompany = Company::factory()->create();
        $foreignWarehouse = Warehouse::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Warehouse',
        ]);

        $payload = [
            'code' => 'WH-SCOPE',
            'name' => 'Scoped Product',
            'group' => $group->id,
            'warehouse_id' => $foreignWarehouse->id,
            'quantity' => '0',
        ];

        $this->post(route('products.store'), $payload)->assertSessionHasErrors('warehouse_id');

        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'warehouse_id' => $ownWarehouse->id,
        ]);

        $this->put(route('products.update', $product), $payload)->assertSessionHasErrors('warehouse_id');
        $this->assertSame($ownWarehouse->id, $product->fresh()->warehouse_id);
    }

    public function test_invoice_requires_company_warehouse_for_product_rows(): void
    {
        $otherCompany = Company::factory()->create();
        $foreignWarehouse = Warehouse::withoutGlobalScopes()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Foreign Invoice Warehouse',
        ]);

        $request = new StoreInvoiceRequest;
        $rules = $request->rules();

        $validator = validator([
            'transactions' => [[
                'item_type' => 'product',
                'warehouse_id' => $foreignWarehouse->id,
            ]],
        ], [
            'transactions.*.warehouse_id' => $rules['transactions.*.warehouse_id'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('transactions.0.warehouse_id', $validator->errors()->toArray());

        $missingValidator = validator([
            'transactions' => [[
                'item_type' => 'product',
                'warehouse_id' => null,
            ]],
        ], [
            'transactions.*.warehouse_id' => $rules['transactions.*.warehouse_id'],
        ]);

        $this->assertTrue($missingValidator->fails());
    }

    public function test_product_edit_keeps_each_warehouse_balance_unchanged(): void
    {
        $source = $this->makeWarehouse();
        $newDefault = $this->makeWarehouse();
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId]);
        $product = Product::factory()->withGroup($group)->withSubjects()->create([
            'company_id' => $this->companyId,
            'warehouse_id' => $source->id,
            'quantity' => 12,
            'average_cost' => 100,
        ]);

        WarehouseProductStock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'average_cost' => 90,
        ]);
        WarehouseProductStock::create([
            'warehouse_id' => $newDefault->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'average_cost' => 110,
        ]);

        app(ProductService::class)->update($product, [
            'name' => 'Edited Product',
            'warehouse_id' => $newDefault->id,
        ]);

        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $newDefault->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_transfer_locks_source_and_destination_rows_and_never_persists_negative_stock(): void
    {
        $source = $this->makeWarehouse();
        $destination = $this->makeWarehouse();
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        WarehouseProductStock::create([
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'average_cost' => 100,
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        app(WarehouseService::class)->transfer($product, $source, $destination, 4);

        $lockingQuery = collect($queries)->first(fn ($sql) => str_contains($sql, 'warehouse_product_stocks') && str_contains($sql, 'for update'));
        $this->assertNotNull($lockingQuery);
        $this->assertStringContainsString('warehouse_id', $lockingQuery);
        $this->assertStringContainsString('order by', $lockingQuery);

        try {
            app(WarehouseService::class)->transfer($product, $source, $destination, 2);
            $this->fail('A transfer larger than the locked source balance should fail.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('warehouse_product_stocks', [
                'warehouse_id' => $source->id,
                'product_id' => $product->id,
                'quantity' => 1,
            ]);
        }

        $this->assertDatabaseMissing('warehouse_product_stocks', [
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => -1,
        ]);
    }

    public function test_warehouse_only_export_omits_product_dependent_stock_and_transfers(): void
    {
        $warehouse = $this->makeWarehouse();
        $destination = $this->makeWarehouse();
        $product = Product::factory()->create(['company_id' => $this->companyId]);

        WarehouseProductStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'average_cost' => 100,
        ]);
        WarehouseTransfer::create([
            'company_id' => $this->companyId,
            'product_id' => $product->id,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $destination->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'transferred_at' => now()->toDateString(),
        ]);

        $export = FiscalYearService::exportData($this->companyId, ['warehouses']);

        $this->assertArrayHasKey('warehouses', $export);
        $this->assertArrayNotHasKey('warehouse_product_stocks', $export);
        $this->assertArrayNotHasKey('warehouse_transfers', $export);
    }
}
