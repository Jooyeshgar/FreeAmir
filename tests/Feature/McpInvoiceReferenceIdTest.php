<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class McpInvoiceReferenceIdTest extends TestCase
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

        foreach (['api.access', 'invoices.store', 'invoices.index'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->token = $this->user->createToken('Invoice Ref Writer', ['invoices.store', 'invoices.index'])->plainTextToken;
        config(['active-fiscal-year-id' => null]);
    }

    private function invoiceArguments(string $referenceId): array
    {
        return [
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'date' => '2026-05-01',
            'reference_id' => $referenceId,
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'quantity' => 1,
                'unit' => 1000,
            ]],
        ];
    }

    public function test_create_invoice_stores_reference_id_and_blocks_duplicates_until_rejected_or_voided(): void
    {
        $firstResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');

        $this->assertFalse($firstResponse['isError'] ?? true);
        $created = json_decode($firstResponse['content'][0]['text'], true);
        $this->assertSame('fnirsi:FN-180625', $created['reference_id']);
        $this->assertSame('fnirsi:FN-180625', Invoice::withoutGlobalScopes()->findOrFail($created['id'])->reference_id);

        // Duplicate reference_id in the same fiscal year is rejected with existing invoice details
        $duplicateResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');

        $this->assertTrue($duplicateResponse['isError']);
        $duplicatePayload = json_decode($duplicateResponse['content'][0]['text'], true);
        $this->assertSame($created['id'], $duplicatePayload['existing_invoice']['id']);
        $this->assertSame('fnirsi:FN-180625', $duplicatePayload['existing_invoice']['reference_id']);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        // Rejecting the existing invoice allows creating a replacement invoice with the same reference_id
        Invoice::withoutGlobalScopes()->whereKey($created['id'])->update(['status' => InvoiceStatus::REJECTED->value]);

        $replacementResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');

        $this->assertFalse($replacementResponse['isError'] ?? true);
        $replacement = json_decode($replacementResponse['content'][0]['text'], true);
        $this->assertNotSame($created['id'], $replacement['id']);
        $this->assertSame('fnirsi:FN-180625', $replacement['reference_id']);

        // Voiding also allows reuse
        Invoice::withoutGlobalScopes()->whereKey($replacement['id'])->update(['invoice_type' => InvoiceType::VOID->value]);

        $afterVoid = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');
        $this->assertFalse($afterVoid['isError'] ?? true);
    }
}
