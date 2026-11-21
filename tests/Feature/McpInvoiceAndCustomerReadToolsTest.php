<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpInvoiceAndCustomerReadToolsTest extends TestCase
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

        foreach (['api.access', 'invoices.index', 'invoices.show', 'customers.index', 'customers.show'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $this->token = $this->user->createToken('Invoices & Customers Read', [
            'invoices.index',
            'invoices.show',
            'customers.index',
            'customers.show',
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

    private function callToolExpectError(string $name, array $arguments = [], ?string $token = null): string
    {
        $response = $this->withToken($token ?? $this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ])->assertOk();

        $result = $response->json('result');
        self::assertTrue($result['isError'] ?? false);

        return $result['content'][0]['text'];
    }

    public function test_list_invoices_filters_paginates_and_isolates_fiscal_year(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $customerA = Customer::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Alpha Co']);
        $customerB = Customer::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Beta Co']);

        $inv1 = Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $customerA->id,
            'creator_id' => $this->user->id,
            'number' => 'INV-1001',
            'title' => 'Server Rack',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'date' => '2026-04-10',
            'amount' => 5000000,
            'vat' => 0,
            'subtraction' => 0,
        ]);
        Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $customerB->id,
            'creator_id' => $this->user->id,
            'number' => 'INV-1002',
            'title' => 'Cables',
            'invoice_type' => InvoiceType::BUY,
            'status' => InvoiceStatus::PENDING,
            'date' => '2026-04-15',
            'amount' => 1200000,
            'vat' => 0,
            'subtraction' => 0,
        ]);

        $otherYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->attach($otherYear->id);
        config(['active-fiscal-year-id' => $otherYear->id]);
        Invoice::create([
            'fiscal_year_id' => $otherYear->id,
            'customer_id' => $customerA->id,
            'creator_id' => $this->user->id,
            'number' => 'INV-9999',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'date' => '2025-04-10',
            'amount' => 900000,
            'vat' => 0,
            'subtraction' => 0,
        ]);

        config(['active-fiscal-year-id' => null]);

        $data = $this->callTool('list_invoices', [
            'fiscal_year_id' => $this->year->id,
            'type' => 'sell',
            'status' => 'approved',
            'customer_id' => $customerA->id,
            'query' => '1001',
            'page' => 1,
            'limit' => 10,
        ]);

        $this->assertSame($this->year->id, $data['fiscal_year_id']);
        $this->assertSame('Toman', $data['currency']);
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame($inv1->id, $data['invoices'][0]['id']);
        $this->assertSame('INV-1001', $data['invoices'][0]['number']);
        $this->assertSame('Alpha Co', $data['invoices'][0]['customer_name']);
        $this->assertSame('sell', $data['invoices'][0]['type']);
        $this->assertSame('approved', $data['invoices'][0]['status']);

        $invoiceOnlyToken = $this->user->createToken('Invoice Only', ['invoices.index'])->plainTextToken;
        app('auth')->forgetGuards();
        $masked = $this->callTool('list_invoices', ['fiscal_year_id' => $this->year->id], $invoiceOnlyToken);
        $this->assertNull($masked['invoices'][0]['customer_name']);
    }

    public function test_get_invoice_returns_line_items_and_rejects_other_fiscal_year(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $customer = Customer::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'Parsian Tech']);
        $warehouse = Warehouse::create([
            'fiscal_year_id' => $this->year->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-1',
        ]);
        $document = Document::factory()->create(['fiscal_year_id' => $this->year->id, 'number' => 42]);
        $product = Product::factory()->create(['fiscal_year_id' => $this->year->id, 'code' => 'PRD-10', 'name' => 'Router']);

        $invoice = Invoice::create([
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'warehouse_id' => $warehouse->id,
            'document_id' => $document->id,
            'number' => 'S-500',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::PAID,
            'date' => '2026-04-12',
            'amount' => 2200000,
            'vat' => 200000,
            'description' => 'Delivered on time',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Product::class,
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1000000,
            'discount' => 0,
            'vat' => 200000,
            'amount' => 2200000,
            'description' => 'Line 1',
        ]);

        $otherYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->attach($otherYear->id);
        config(['active-fiscal-year-id' => $otherYear->id]);
        $foreignInvoice = Invoice::create([
            'fiscal_year_id' => $otherYear->id,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'number' => 'S-999',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'date' => '2025-04-12',
            'amount' => 100000,
            'vat' => 0,
            'subtraction' => 0,
        ]);

        config(['active-fiscal-year-id' => null]);

        $byId = $this->callTool('get_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_id' => $invoice->id,
        ]);

        $this->assertSame('S-500', $byId['invoice']['number']);
        $this->assertSame('Main Warehouse', $byId['invoice']['warehouse_name']);
        $this->assertSame(42, $byId['invoice']['document_number']);
        $this->assertCount(1, $byId['invoice']['items']);
        $this->assertSame('product', $byId['invoice']['items'][0]['item_type']);
        $this->assertSame('PRD-10', $byId['invoice']['items'][0]['item_code']);
        $this->assertSame('Router', $byId['invoice']['items'][0]['item_name']);
        $this->assertEquals(2.0, $byId['invoice']['items'][0]['quantity']);

        $byNumber = $this->callTool('get_invoice', [
            'fiscal_year_id' => $this->year->id,
            'number' => 'S-500',
        ]);
        $this->assertSame($invoice->id, $byNumber['invoice']['id']);

        $error = $this->callToolExpectError('get_invoice', [
            'fiscal_year_id' => $this->year->id,
            'invoice_id' => $foreignInvoice->id,
        ]);
        $this->assertStringContainsString('not found', strtolower($error));
    }

    public function test_list_customers_and_get_customer_return_profile_and_balance(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $group = CustomerGroup::factory()->create(['fiscal_year_id' => $this->year->id, 'name' => 'VIP']);
        $subject = Subject::factory()->create(['fiscal_year_id' => $this->year->id]);
        $customer = Customer::factory()->create([
            'fiscal_year_id' => $this->year->id,
            'group_id' => $group->id,
            'name' => 'Aria Industrial',
            'phone' => '02188887777',
            'mobile' => '09121112233',
            'personal_code' => '0012345678',
            'address' => 'Tehran, Valiasr St',
            'credit' => 10000000,
        ]);
        $subject->forceFill([
            'subjectable_type' => Customer::class,
            'subjectable_id' => $customer->id,
        ])->save();

        $doc = Document::factory()->create(['fiscal_year_id' => $this->year->id, 'approved_at' => now()]);
        Transaction::factory()->create([
            'document_id' => $doc->id,
            'subject_id' => $subject->id,
            'value' => -350000,
        ]);

        $otherYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->attach($otherYear->id);
        config(['active-fiscal-year-id' => $otherYear->id]);
        $foreignCustomer = Customer::factory()->create(['fiscal_year_id' => $otherYear->id, 'name' => 'Foreign Customer']);

        config(['active-fiscal-year-id' => null]);

        $list = $this->callTool('list_customers', [
            'fiscal_year_id' => $this->year->id,
            'query' => '09121112233',
            'group_id' => $group->id,
        ]);

        $this->assertSame(1, $list['pagination']['total']);
        $this->assertSame($customer->id, $list['customers'][0]['id']);
        $this->assertSame('VIP', $list['customers'][0]['group_name']);

        $detail = $this->callTool('get_customer', [
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertSame('Aria Industrial', $detail['customer']['name']);
        $this->assertSame('Tehran, Valiasr St', $detail['customer']['address']);
        $this->assertSame('0012345678', $detail['customer']['personal_code']);
        $this->assertSame(-350000, $detail['customer']['balance']);
        $this->assertSame('debtor', $detail['customer']['balance_status_code']);
        $this->assertSame(10000000, $detail['customer']['credit_limit']);

        $error = $this->callToolExpectError('get_customer', [
            'fiscal_year_id' => $this->year->id,
            'customer_id' => $foreignCustomer->id,
        ]);
        $this->assertStringContainsString('not found', strtolower($error));
    }

    public function test_unauthorized_token_cannot_call_invoice_or_customer_read_tools(): void
    {
        $limitedToken = $this->user->createToken('Products Only', ['products.index'])->plainTextToken;
        app('auth')->forgetGuards();

        foreach ([
            ['list_invoices', ['fiscal_year_id' => $this->year->id]],
            ['get_invoice', ['fiscal_year_id' => $this->year->id, 'invoice_id' => 1]],
            ['list_customers', ['fiscal_year_id' => $this->year->id]],
            ['get_customer', ['fiscal_year_id' => $this->year->id, 'customer_id' => 1]],
        ] as [$tool, $args]) {
            $error = $this->withToken($limitedToken)->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => $tool, 'arguments' => $args],
            ])->assertStatus(400)->json('error');

            $this->assertNotNull($error);
        }
    }
}
