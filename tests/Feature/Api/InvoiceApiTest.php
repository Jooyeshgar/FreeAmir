<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private FiscalYear $fiscalYear;

    private User $user;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);

        $this->importSubjects($this->fiscalYear->id);
        $this->importConfigs($this->fiscalYear->id);

        $productGroup = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYear->id]);
        $customerGroup = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYear->id]);

        $this->customer = Customer::factory()
            ->withGroup($customerGroup)
            ->withSubject()
            ->create();

        $this->warehouse = Warehouse::create([
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'انبار اصلی',
            'code' => 'MAIN',
        ]);

        $this->product = Product::factory()
            ->withGroup($productGroup)
            ->withSubjects()
            ->create([
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
            $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission]));
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

    private function apiUrl(string $path): string
    {
        return '/api/companies/'.$this->fiscalYear->id.$path;
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

    private function createInvoice(User $user, array $abilities = ['api.access', 'invoices.store']): int
    {
        return $this->postJson(
            $this->apiUrl('/invoices'),
            $this->invoicePayload(),
            $this->apiHeaders($user, $abilities)
        )->assertCreated()->json('data.id');
    }

    public function test_store_invoice_requires_invoices_store_permission(): void
    {
        $limitedUser = User::factory()->create();
        $this->fiscalYear->users()->attach($limitedUser);
        $this->grant($limitedUser, ['api.access', 'invoices.index']);

        $this->postJson(
            $this->apiUrl('/invoices'),
            $this->invoicePayload(),
            $this->apiHeaders($limitedUser, ['api.access', 'invoices.index'])
        )->assertForbidden();

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_store_invoice_creates_a_buy_invoice_with_permission(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.store']);

        $response = $this->postJson(
            $this->apiUrl('/invoices'),
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
            'fiscal_year_id' => $this->fiscalYear->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_show_invoice_returns_404_for_non_numeric_id(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.show']);

        $this->getJson(
            $this->apiUrl('/invoices/abc'),
            $this->apiHeaders($this->user, ['api.access', 'invoices.show'])
        )->assertNotFound();
    }

    public function test_invoices_from_other_fiscal_years_are_not_accessible(): void
    {
        $permissions = ['api.access', 'invoices.store', 'invoices.show', 'invoices.index'];
        $this->grant($this->user, $permissions);
        $invoiceId = $this->createInvoice($this->user, $permissions);

        $otherYear = FiscalYear::create(['company_id' => $this->fiscalYear->company_id, 'year' => 1405]);
        $this->user->fiscalYears()->syncWithoutDetaching([$otherYear->id]);

        DB::table('invoices')->where('id', $invoiceId)->update([
            'fiscal_year_id' => $otherYear->id,
        ]);

        $this->getJson(
            $this->apiUrl("/invoices/{$invoiceId}"),
            $this->apiHeaders($this->user, $permissions)
        )->assertNotFound();

        $this->getJson(
            $this->apiUrl('/invoices'),
            $this->apiHeaders($this->user, $permissions)
        )->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_index_rejects_invalid_type_status_and_date_filters(): void
    {
        $permissions = ['api.access', 'invoices.index'];
        $this->grant($this->user, $permissions);
        $headers = $this->apiHeaders($this->user, $permissions);

        $this->getJson($this->apiUrl('/invoices').'?invoice_type=not_a_type', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice_type']);

        $this->getJson($this->apiUrl('/invoices').'?status=not_a_status', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->getJson($this->apiUrl('/invoices').'?start_date=garbage', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);

        $this->getJson($this->apiUrl('/invoices').'?end_date=1405/13/40', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_invoice_accepts_external_reference_id_and_blocks_duplicates(): void
    {
        $permissions = ['api.access', 'invoices.store', 'invoices.show', 'invoices.index'];
        $this->grant($this->user, $permissions);
        $headers = $this->apiHeaders($this->user, $permissions);

        $payload = $this->invoicePayload(['external_reference_id' => 'shop:ORDER-1', 'invoice_number' => 2001, 'document_number' => 2001]);

        $response = $this->postJson($this->apiUrl('/invoices'), $payload, $headers)
            ->assertCreated()
            ->assertJsonPath('data.external_reference_id', 'shop:ORDER-1');

        $invoiceId = $response->json('data.id');

        $this->getJson($this->apiUrl("/invoices/{$invoiceId}"), $headers)
            ->assertOk()
            ->assertJsonPath('data.external_reference_id', 'shop:ORDER-1');

        $this->getJson($this->apiUrl('/invoices'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.external_reference_id', 'shop:ORDER-1');

        $this->postJson($this->apiUrl('/invoices'), array_merge($payload, ['invoice_number' => 2002, 'document_number' => 2002]), $headers)
            ->assertStatus(422);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_approve_requires_approve_permission(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.store']);
        $invoiceId = $this->createInvoice($this->user);

        $this->postJson(
            $this->apiUrl("/invoices/{$invoiceId}/approve"),
            [],
            $this->apiHeaders($this->user, ['api.access', 'invoices.store'])
        )->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => InvoiceStatus::PENDING->value,
        ]);
    }

    public function test_approve_requires_token_ability(): void
    {
        $this->grant($this->user, ['api.access', 'invoices.store', 'invoices.approve']);
        $invoiceId = $this->createInvoice($this->user);

        $this->postJson(
            $this->apiUrl("/invoices/{$invoiceId}/approve"),
            [],
            $this->apiHeaders($this->user, ['api.access', 'invoices.store'])
        )->assertForbidden();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'status' => InvoiceStatus::PENDING->value,
        ]);
    }

    public function test_approve_approves_invoice_and_records_approver(): void
    {
        $permissions = ['api.access', 'invoices.store', 'invoices.approve'];
        $this->grant($this->user, $permissions);
        $invoiceId = $this->createInvoice($this->user, $permissions);

        $this->postJson(
            $this->apiUrl("/invoices/{$invoiceId}/approve"),
            [],
            $this->apiHeaders($this->user, $permissions)
        )->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approver_id', $this->user->id);

        $invoice = DB::table('invoices')->find($invoiceId);

        $this->assertSame(InvoiceStatus::APPROVED->value, (int) $invoice->status);
        $this->assertSame($this->user->id, (int) $invoice->approver_id);
        $this->assertNotNull($invoice->document_id);
    }

    public function test_unapprove_reverts_invoice_and_clears_approver(): void
    {
        $permissions = ['api.access', 'invoices.store', 'invoices.approve'];
        $this->grant($this->user, $permissions);
        $invoiceId = $this->createInvoice($this->user, $permissions);

        $this->postJson(
            $this->apiUrl("/invoices/{$invoiceId}/approve"),
            [],
            $this->apiHeaders($this->user, $permissions)
        )->assertOk();

        $this->postJson(
            $this->apiUrl("/invoices/{$invoiceId}/unapprove"),
            [],
            $this->apiHeaders($this->user, $permissions)
        )->assertOk()
            ->assertJsonPath('data.status', 'unapproved')
            ->assertJsonPath('data.approver_id', null);

        $invoice = DB::table('invoices')->find($invoiceId);

        $this->assertSame(InvoiceStatus::UNAPPROVED->value, (int) $invoice->status);
        $this->assertNull($invoice->approver_id);
    }
}
