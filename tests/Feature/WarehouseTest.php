<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\WarehouseTransfer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
