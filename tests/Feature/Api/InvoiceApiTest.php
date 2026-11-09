<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private Company $company;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        config(['active-company-id' => $this->company->id]);

        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->importSubjects($this->company->id);
        $this->importConfigs($this->company->id);

        $productGroup = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->company->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->company->id]);

        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->company->id]);

        $this->warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'name' => 'انبار اصلی',
            'code' => 'MAIN',
        ]);

        $this->product = Product::factory()
            ->withGroup($productGroup)
            ->withSubjects()
            ->create([
                'company_id' => $this->company->id,
                'quantity' => 0,
                'average_cost' => 100,
            ]);

        WarehouseProductStock::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id],
            ['quantity' => 0, 'average_cost' => 100]
        );
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }
    }

    /**
     * Build the Authorization header for a Sanctum token that carries the given abilities.
     */
    private function apiHeaders(User $user, array $abilities): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('test', $abilities)->plainTextToken,
        ];
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'invoice_type' => 'buy',
            'date' => now()->toDateString(),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'invoice_number' => 1001,
            'document_number' => 1001,
            'title' => 'API buy invoice',
            'items' => [
                [
                    'item_type' => 'product',
                    'item_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'unit_discount' => 0,
                    'vat' => 0,
                ],
            ],
        ], $overrides);
    }

    public function test_store_invoice_requires_invoices_store_permission(): void
    {
        $limitedUser = User::factory()->create();
        $this->company->users()->attach($limitedUser);
        $this->grant($limitedUser, ['api.access', 'invoices.index']);

        $this->postJson(
            "/api/companies/{$this->company->id}/invoices",
            $this->invoicePayload(),
            $this->apiHeaders($limitedUser, ['api.access', 'invoices.index'])
        )->assertForbidden();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_store_invoice_creates_a_buy_invoice_with_permission(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.store']);

        $response = $this->postJson(
            "/api/companies/{$this->company->id}/invoices",
            $this->invoicePayload(),
            $this->apiHeaders($this->user, ['api.access', 'invoices.store'])
        );

        $response->assertCreated()
            ->assertJsonPath('data.invoice_type', 'buy')
            ->assertJsonPath('data.customer_id', $this->customer->id)
            ->assertJsonPath('data.warehouse_id', $this->warehouse->id)
            ->assertJsonPath('data.items_count', 1);

        $this->assertDatabaseHas('invoices', [
            'id' => $response->json('data.id'),
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_show_invoice_returns_404_for_non_numeric_id(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.show']);

        $this->getJson(
            "/api/companies/{$this->company->id}/invoices/abc",
            $this->apiHeaders($this->user, ['api.access', 'invoices.show'])
        )->assertNotFound();
    }

    public function test_change_status_requires_invoices_change_status_permission(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.store']);

        $created = $this->postJson(
            "/api/companies/{$this->company->id}/invoices",
            $this->invoicePayload(),
            $this->apiHeaders($this->user, ['api.access', 'invoices.store'])
        )->assertCreated();

        $invoiceId = $created->json('data.id');

        $approver = User::factory()->create();
        $this->company->users()->attach($approver);
        $this->grant($approver, ['api.access', 'invoices.index', 'invoices.approve']);

        $this->postJson(
            "/api/companies/{$this->company->id}/invoices/{$invoiceId}/change-status/approved",
            [],
            $this->apiHeaders($approver, ['api.access', 'invoices.index', 'invoices.approve'])
        )->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => 1,
        ]);
    }
}
