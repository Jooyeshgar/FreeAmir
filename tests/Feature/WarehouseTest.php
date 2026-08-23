<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Models\WarehouseTransfer;
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
}
