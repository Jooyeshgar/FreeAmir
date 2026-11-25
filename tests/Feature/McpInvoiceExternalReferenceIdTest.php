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

class McpInvoiceExternalReferenceIdTest extends TestCase
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

    private function invoiceArguments(string $externalReferenceId): array
    {
        return [
            'fiscal_year_id' => $this->year->id,
            'invoice_type' => 'sell',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'date' => '2026-05-01',
            'external_reference_id' => $externalReferenceId,
            'items' => [[
                'item_type' => 'product',
                'item_id' => $this->product->id,
                'quantity' => 1,
                'unit' => 1000,
            ]],
        ];
    }

    public function test_create_invoice_stores_external_reference_id_and_blocks_duplicates_until_rejected_or_voided(): void
    {
        $firstResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');

        $this->assertFalse($firstResponse['isError'] ?? true);
        $created = json_decode($firstResponse['content'][0]['text'], true);
        $this->assertSame('fnirsi:FN-180625', $created['external_reference_id']);
        $this->assertSame('fnirsi:FN-180625', Invoice::withoutGlobalScopes()->findOrFail($created['id'])->external_reference_id);

        // Duplicate external_reference_id in the same fiscal year is rejected with existing invoice details
        $duplicateResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-180625')],
        ])->assertOk()->json('result');

        $this->assertTrue($duplicateResponse['isError']);
        $duplicatePayload = json_decode($duplicateResponse['content'][0]['text'], true);
        $this->assertSame($created['id'], $duplicatePayload['existing_invoice']['id']);
        $this->assertSame('fnirsi:FN-180625', $duplicatePayload['existing_invoice']['external_reference_id']);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        // Rejecting the existing invoice allows creating a replacement invoice with the same external_reference_id
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
        $this->assertSame('fnirsi:FN-180625', $replacement['external_reference_id']);

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

    public function test_web_invoice_edit_preserves_external_reference_id_and_blocks_subsequent_duplicates(): void
    {
        $createResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-999001')],
        ])->assertOk()->json('result');

        $this->assertFalse($createResponse['isError'] ?? true);
        $created = json_decode($createResponse['content'][0]['text'], true);
        $invoice = Invoice::withoutGlobalScopes()->findOrFail($created['id']);

        $this->user->givePermissionTo(Permission::findOrCreate('invoices.edit', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('invoices.update', 'web'));

        $this->actingAs($this->user)
            ->withSession(['active-fiscal-year-id' => $this->year->id])
            ->put(route('invoices.update', $invoice), [
                'invoice_id' => $invoice->id,
                'title' => 'Edited From Web',
                'description' => 'Updated description',
                'date' => convertToJalali('2026-05-01', true),
                'invoice_type' => 'sell',
                'customer_id' => $this->customer->id,
                'warehouse_id' => $this->warehouse->id,
                'invoice_number' => (int) $invoice->number,
                'document_number' => 1,
                'subtraction' => '0',
                'transactions' => [[
                    'item_id' => 'product-'.$this->product->id,
                    'quantity' => '1',
                    'unit' => '1000',
                    'discount' => '0',
                    'vat' => '0',
                    'desc' => null,
                    'total' => '1000',
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('Edited From Web', $invoice->title);
        $this->assertSame('fnirsi:FN-999001', $invoice->external_reference_id);

        $summaryResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_invoices_summary',
                'arguments' => [
                    'fiscal_year_id' => $this->year->id,
                    'status' => 'pre_invoice',
                ],
            ],
        ])->assertOk()->json('result');

        $this->assertFalse($summaryResponse['isError'] ?? true);
        $summaryPayload = json_decode($summaryResponse['content'][0]['text'], true);
        $this->assertSame('fnirsi:FN-999001', $summaryPayload['recent_invoices'][0]['external_reference_id'] ?? null);

        $duplicateResponse = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'create_invoice', 'arguments' => $this->invoiceArguments('fnirsi:FN-999001')],
        ])->assertOk()->json('result');

        $this->assertTrue($duplicateResponse['isError']);
        $duplicatePayload = json_decode($duplicateResponse['content'][0]['text'], true);
        $this->assertSame($invoice->id, $duplicatePayload['existing_invoice']['id']);
        $this->assertSame('fnirsi:FN-999001', $duplicatePayload['existing_invoice']['external_reference_id']);
    }
}
