<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mcp\Auth\TokenAuthenticator;
use App\Mcp\Support\ContextResolver;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseProductStock;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class McpMutationTest extends TestCase
{
    use RefreshDatabase, SeederHelper;

    private User $user;

    private FiscalYear $year;

    private CustomerGroup $customerGroup;

    private ProductGroup $productGroup;

    private Customer $customer;

    private Product $product;

    private Warehouse $warehouse;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app()->setLocale('en');
        $this->user = User::factory()->create();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        $this->user->fiscalYears()->sync([$this->year->id]);
        config(['active-fiscal-year-id' => $this->year->id]);
        $this->importSubjects($this->year->id);
        $this->importConfigs($this->year->id);
        $this->customerGroup = CustomerGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->year->id]);
        $this->productGroup = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->year->id]);
        $this->customer = Customer::factory()->withGroup($this->customerGroup)->withSubject()->create();
        $this->product = Product::factory()->withGroup($this->productGroup)->withSubjects()->create(['quantity' => 0]);
        $this->warehouse = Warehouse::create(['name' => 'Main', 'code' => 'MAIN']);
        foreach (['api.access', 'customers.store', 'products.store', 'invoices.store'] as $permission) {
            $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->token = $this->user->createToken('MCP writer', ['customers.store', 'products.store', 'invoices.store'])->plainTextToken;
        app('auth')->forgetGuards();
    }

    private function arguments(string $tool): array
    {
        return ['fiscal_year_id' => $this->year->id] + match ($tool) {
            'create_customer' => ['name' => 'MCP Customer', 'group_id' => $this->customerGroup->id, 'type' => 'individual'],
            'create_product' => ['name' => 'MCP Product', 'group_id' => $this->productGroup->id, 'oversell' => false, 'selling_price' => 125.5],
            'create_invoice' => [
                'invoice_type' => 'sell', 'customer_id' => $this->customer->id, 'warehouse_id' => $this->warehouse->id,
                'number' => 901, 'date' => '2026-11-16', 'subtraction' => 2,
                'items' => [['item_type' => 'product', 'item_id' => $this->product->id, 'quantity' => 2, 'unit' => 125.55, 'discount' => 1, 'vat' => 10]],
            ],
        };
    }

    private function callTool(string $name, array $arguments): TestResponse
    {
        return $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments],
        ]);
    }

    private function created(string $tool, array $arguments): array
    {
        $response = $this->callTool($tool, $arguments)->assertOk();
        self::assertFalse($response->json('result.isError'), $response->getContent());

        return json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_creates_customer_with_contact_details_and_subject(): void
    {
        $result = $this->created('create_customer', $this->arguments('create_customer') + ['phone' => '02112345678', 'mobile' => '09123456789', 'email' => 'mcp@example.com']);
        $customer = Customer::findOrFail($result['id']);
        self::assertSame('MCP Customer', $result['name']);
        self::assertSame($this->year->id, $customer->fiscal_year_id);
        self::assertSame('mcp@example.com', $result['email']);
        self::assertSame($this->customerGroup->subject_id, $customer->subject->parent_id);
        self::assertSame($customer->subject_id, $result['subject_id']);
    }

    public function test_creates_customer_with_default_group_and_default_type(): void
    {
        $arguments = ['fiscal_year_id' => $this->year->id, 'name' => 'Default Group Customer'];
        $result = $this->created('create_customer', $arguments);
        self::assertSame('Default Group Customer', $result['name']);
        self::assertSame($this->customerGroup->id, $result['group_id']);
        self::assertSame('individual', $result['type']);
    }

    #[TestWith(['en'])]
    #[TestWith(['fa'])]
    public function test_context_includes_customer_groups_and_warehouses(string $locale): void
    {
        Config::updateOrCreate(['key' => 'app_locale', 'fiscal_year_id' => $this->year->id], ['value' => $locale, 'type' => 1, 'category' => 1]);
        $result = $this->created('get_current_context', ['fiscal_year_id' => $this->year->id]);
        self::assertNotEmpty($result['customer_groups']);
        self::assertSame($this->customerGroup->id, $result['customer_groups'][0]['id']);
        self::assertNotEmpty($result['warehouses']);
        self::assertSame($this->warehouse->id, $result['warehouses'][0]['id']);
        self::assertStringContainsString($locale === 'fa' ? 'گروه‌های طرف‌حساب' : 'Customer groups', $result['human_summary']);
        self::assertStringContainsString($locale === 'fa' ? 'انبارها' : 'Warehouses', $result['human_summary']);
        self::assertStringContainsString($locale === 'fa' ? 'شناسه '.$this->customerGroup->id : 'ID '.$this->customerGroup->id, $result['human_summary']);
    }

    #[TestWith(['create_product', 'USD', 'en'])]
    #[TestWith(['create_invoice', 'USD', 'en'])]
    #[TestWith(['create_product', null, 'en'])]
    #[TestWith(['create_invoice', null, 'en'])]
    #[TestWith(['create_product', null, 'fa'])]
    #[TestWith(['create_invoice', null, 'fa'])]
    public function test_creation_uses_selected_company_currency_and_translated_messages(string $tool, ?string $currency, string $locale): void
    {
        $expectedCurrency = $currency ?? 'Rial';
        $this->year->company->update(['currency' => $expectedCurrency]);
        $other = FiscalYear::factory()->create();
        $other->company->update(['currency' => 'EUR']);
        $this->user->fiscalYears()->attach($other);
        config(['active-fiscal-year-id' => $other->id, 'amir.currency' => 'EUR']);
        Config::updateOrCreate(['key' => 'currency', 'fiscal_year_id' => $this->year->id], ['value' => 'EUR', 'type' => 1, 'category' => 1]);
        Config::updateOrCreate(['key' => 'app_locale', 'fiscal_year_id' => $this->year->id], ['value' => $locale, 'type' => 1, 'category' => 1]);

        $result = $this->created($tool, $this->arguments($tool));

        self::assertSame($this->year->id, $result['fiscal_year_id']);
        self::assertSame($locale === 'fa' && $expectedCurrency === 'Rial' ? 'ریال' : $expectedCurrency, $result['currency']);
        self::assertSame(__($tool === 'create_product' ? 'Product created successfully.' : 'Invoice created successfully.', [], $locale), $result['message']);
        self::assertEquals($tool === 'create_product' ? 125.5 : 273.11, $result[$tool === 'create_product' ? 'selling_price' : 'amount']);
    }

    public function test_creates_product_with_automatic_code_price_and_four_subjects(): void
    {
        $result = $this->created('create_product', $this->arguments('create_product'));
        $product = Product::findOrFail($result['id']);
        self::assertNotEmpty($result['code']);
        self::assertEquals(125.5, $result['selling_price']);
        self::assertEquals(0, $product->quantity);
        foreach (['income_subject_id', 'sales_returns_subject_id', 'cogs_subject_id', 'inventory_subject_id'] as $column) {
            self::assertNotNull($product->$column);
            self::assertEquals($this->year->id, Subject::findOrFail($product->$column)->fiscal_year_id);
        }
    }

    #[TestWith(['sell', 'en'])]
    #[TestWith(['sell', 'fa'])]
    #[TestWith(['buy', 'en'])]
    #[TestWith(['buy', 'fa'])]
    public function test_creates_only_draft_invoices_without_posting_or_inventory_changes(string $type, string $locale): void
    {
        app()->setLocale($locale);
        $documents = Document::count();
        $arguments = $this->arguments('create_invoice');
        $arguments['invoice_type'] = $type;
        $result = $this->created('create_invoice', $arguments);
        $invoice = Invoice::findOrFail($result['id']);
        self::assertSame($type === 'sell' ? InvoiceStatus::PRE_INVOICE : InvoiceStatus::PENDING, $invoice->status);
        self::assertSame($this->user->id, $invoice->creator_id);
        self::assertSame('2026-11-16', $invoice->date->format('Y-m-d'));
        self::assertFalse($result['approved']);
        self::assertEquals(273.11, $result['amount']);
        self::assertEquals(25.01, $result['vat']);
        self::assertCount(1, $result['items']);
        self::assertNull($invoice->document_id);
        self::assertSame($documents, Document::count());
        self::assertEquals(0, $this->product->fresh()->quantity);
        self::assertSame(0, WarehouseProductStock::count());
    }

    public function test_write_tools_and_context_require_both_user_and_token_permissions(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertOk();
        $tools = collect($response->json('result.tools'))->keyBy('name');
        foreach (['create_customer', 'create_product', 'create_invoice'] as $tool) {
            self::assertFalse($tools[$tool]['annotations']['readOnlyHint']);
            self::assertFalse($tools[$tool]['inputSchema']['additionalProperties']);
        }
        self::assertTrue($tools['get_current_context']['annotations']['readOnlyHint']);
        self::assertFalse($tools['create_invoice']['inputSchema']['properties']['items']['items']['additionalProperties']);
        $this->created('get_current_context', ['fiscal_year_id' => $this->year->id]);
    }

    #[TestWith(['create_customer', 'customers.store'])]
    #[TestWith(['create_product', 'products.store'])]
    #[TestWith(['create_invoice', 'invoices.store'])]
    public function test_missing_user_or_token_permission_prevents_mutation(string $tool, string $permission): void
    {
        $this->user->revokePermissionTo($permission);
        $this->callTool($tool, $this->arguments($tool))->assertStatus(400);
        $this->user->givePermissionTo($permission);
        $this->token = $this->user->createToken('reader', ['customers.show'])->plainTextToken;
        app('auth')->forgetGuards();
        $this->callTool($tool, $this->arguments($tool))->assertStatus(400);
        self::assertSame(1, Customer::count());
        self::assertSame(1, Product::count());
        self::assertSame(0, Invoice::count());
    }

    #[TestWith(['create_customer'])]
    #[TestWith(['create_product'])]
    #[TestWith(['create_invoice'])]
    public function test_closed_and_unassigned_fiscal_years_prevent_mutations(string $tool): void
    {
        $this->year->forceFill(['closed_at' => now()])->save();
        $this->callTool($tool, $this->arguments($tool))->assertOk()->assertJsonPath('result.isError', true);
        $other = FiscalYear::factory()->create();
        $this->user->fiscalYears()->detach($other);
        $arguments = $this->arguments($tool);
        $arguments['fiscal_year_id'] = $other->id;
        $this->callTool($tool, $arguments)->assertOk()->assertJsonPath('result.isError', true);
        self::assertEquals($this->year->id, config('active-fiscal-year-id'));
        self::assertSame(1, Customer::count());
        self::assertSame(1, Product::count());
        self::assertSame(0, Invoice::count());
    }

    #[TestWith(['create_customer'])]
    #[TestWith(['create_product'])]
    #[TestWith(['create_invoice'])]
    public function test_explicit_year_is_required_and_approval_fields_are_rejected(string $tool): void
    {
        $arguments = $this->arguments($tool);
        unset($arguments['fiscal_year_id']);
        $this->callTool($tool, $arguments)->assertOk()->assertJsonPath('result.isError', true);
        $this->callTool($tool, $this->arguments($tool) + ['approve' => true])->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame(0, Invoice::count());
    }

    public function test_rejects_foreign_groups_customer_warehouse_and_items(): void
    {
        $other = FiscalYear::factory()->create();
        $customerGroup = CustomerGroup::factory()->create(['fiscal_year_id' => $other->id]);
        $productGroup = ProductGroup::factory()->create(['fiscal_year_id' => $other->id]);
        $customer = Customer::create(['name' => 'Foreign', 'fiscal_year_id' => $other->id]);
        $warehouse = Warehouse::create(['name' => 'Foreign', 'code' => 'OTHER', 'fiscal_year_id' => $other->id]);
        $product = Product::factory()->create(['name' => 'Foreign', 'code' => 'OTHER', 'fiscal_year_id' => $other->id]);
        foreach ([['create_customer', 'group_id', $customerGroup->id], ['create_product', 'group_id', $productGroup->id], ['create_invoice', 'customer_id', $customer->id], ['create_invoice', 'warehouse_id', $warehouse->id]] as [$tool, $key, $id]) {
            $arguments = $this->arguments($tool);
            $arguments[$key] = $id;
            $this->callTool($tool, $arguments)->assertOk()->assertJsonPath('result.isError', true);
        }
        $arguments = $this->arguments('create_invoice');
        $arguments['items'][0]['item_id'] = $product->id;
        $this->callTool('create_invoice', $arguments)->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame(0, Invoice::count());
    }

    #[TestWith(['create_customer'])]
    #[TestWith(['create_product'])]
    #[TestWith(['create_invoice'])]
    public function test_reuses_web_validation_to_reject_duplicates(string $tool): void
    {
        $arguments = $this->arguments($tool);
        if ($tool === 'create_product') {
            $arguments['code'] = 'MCP-1';
        }
        $this->created($tool, $arguments);
        $this->callTool($tool, $arguments)->assertOk()->assertJsonPath('result.isError', true);
    }

    #[TestWith(['discount', 999])]
    #[TestWith(['vat', 101])]
    #[TestWith(['quantity', 0])]
    #[TestWith(['quantity', 0.5])]
    #[TestWith(['unit', -1])]
    #[TestWith(['unit', 1e20])]
    #[TestWith(['creator_id', 123])]
    public function test_invalid_line_items_are_rejected_without_partial_invoice(string $key, int|float $value): void
    {
        $arguments = $this->arguments('create_invoice');
        $arguments['items'][0][$key] = $value;
        $this->callTool('create_invoice', $arguments)->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame(0, Invoice::count());
    }

    public function test_duplicate_line_items_use_existing_invoice_validation(): void
    {
        $arguments = $this->arguments('create_invoice');
        $arguments['items'][] = $arguments['items'][0];
        $this->callTool('create_invoice', $arguments)->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame(0, Invoice::count());
    }

    public function test_item_validation_batches_lookups_and_rejects_a_missing_item(): void
    {
        $arguments = $this->arguments('create_invoice');
        $arguments['items'] = array_fill(0, 100, $arguments['items'][0]);
        $arguments['items'][] = ['item_type' => 'service', 'item_id' => 999999, 'quantity' => 1, 'unit' => 10];
        DB::enableQueryLog();
        try {
            $response = $this->callTool('create_invoice', $arguments)->assertOk()->assertJsonPath('result.isError', true);
            $queries = collect(DB::getQueryLog())->filter(fn (array $query) => preg_match('/from ["`](products|services)["`]/i', $query['query']));
            self::assertCount(2, $queries);
            self::assertStringContainsString('items.100.item_id', $response->json('result.content.0.text'));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        self::assertSame(0, Invoice::count());
    }

    public function test_missing_warehouse_error_is_translated_in_persian(): void
    {
        Config::updateOrCreate(['key' => 'app_locale', 'fiscal_year_id' => $this->year->id], ['value' => 'fa', 'type' => 1, 'category' => 1]);
        $arguments = $this->arguments('create_invoice');
        $arguments['warehouse_id'] = 999999;
        $response = $this->callTool('create_invoice', $arguments)->assertOk()->assertJsonPath('result.isError', true);
        $error = json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('انبار انتخاب‌شده معتبر نیست.', $error['errors']['warehouse_id'][0]);
    }

    public function test_product_creation_rolls_back_when_subject_creation_fails(): void
    {
        $count = Subject::count();
        $this->mock(SubjectService::class)->shouldReceive('createSubject')->once()->andThrow(new RuntimeException('internal SQL secret'));
        $response = $this->callTool('create_product', $this->arguments('create_product'))->assertOk()->assertJsonPath('result.isError', true);
        self::assertStringNotContainsString('internal SQL secret', $response->getContent());
        self::assertSame(1, Product::count());
        self::assertSame($count, Subject::count());
    }

    public function test_authentication_context_sets_stdio_actor_and_restores_it_after_failure(): void
    {
        $previous = User::factory()->create();
        auth()->guard()->setUser($previous);
        $context = app(TokenAuthenticator::class)->authenticate($this->token);
        try {
            ContextResolver::withAuthContext($context, function (): void {
                self::assertSame($this->user->id, auth()->id());
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
            self::assertSame($previous->id, auth()->id());
            self::assertNull(ContextResolver::authContext());
        }
    }

    public function test_stdio_transport_creates_customer_product_and_draft_invoice(): void
    {
        $call = fn (int $id, string $tool, array $arguments): string => json_encode([
            'jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments],
        ], JSON_THROW_ON_ERROR)."\n";
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/mcp-stdio.php'), 'writer'], base_path());
        $process->setInput(
            $call(1, 'create_customer', ['fiscal_year_id' => 1, 'name' => 'Stdio Customer', 'group_id' => 1, 'type' => 'individual'])
            .$call(2, 'create_product', ['fiscal_year_id' => 1, 'name' => 'Stdio Product', 'group_id' => 1, 'oversell' => false])
            .$call(3, 'create_invoice', [
                'fiscal_year_id' => 1, 'invoice_type' => 'sell', 'customer_id' => 1, 'warehouse_id' => 1,
                'number' => 1, 'date' => '2026-11-16', 'items' => [
                    ['item_type' => 'product', 'item_id' => 1, 'quantity' => 1, 'unit' => 100],
                ],
            ])
        );
        $process->mustRun();
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
        self::assertCount(3, $lines, $process->getOutput().$process->getErrorOutput());
        foreach ($lines as $line) {
            $result = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertFalse($result['result']['isError'], $line);
            $data = json_decode($result['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(1, $data['id']);
            self::assertSame(1, $data['fiscal_year_id']);
        }
        self::assertSame('pre_invoice', $data['status']);
        self::assertFalse($data['approved']);
    }

    public function test_fiscal_context_loads_only_its_own_configuration_and_restores_host_state(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1404]);
        Config::create(['key' => 'mcp_probe', 'value' => 'target', 'type' => 1, 'category' => 1, 'fiscal_year_id' => $this->year->id]);
        Config::create(['key' => 'mcp_probe', 'value' => 'global', 'type' => 1, 'category' => 1, 'fiscal_year_id' => null]);
        Config::create(['key' => 'mcp_probe', 'value' => 'other', 'type' => 1, 'category' => 1, 'fiscal_year_id' => $other->id]);
        Config::create(['key' => 'app_activity_logger_enabled', 'value' => '0', 'type' => 1, 'category' => 1, 'fiscal_year_id' => $this->year->id]);
        Config::create(['key' => 'app_locale', 'value' => 'en', 'type' => 1, 'category' => 1, 'fiscal_year_id' => $this->year->id]);
        $previous = ['mcp_probe' => 'host', 'foreign_only' => 'host-only'];
        $previousApp = config('app');
        $previousLocale = app()->getLocale();
        $previousActivityLog = config('activitylog.enabled');
        config(['amir' => $previous, 'active-fiscal-year-id' => $other->id]);
        $context = app(TokenAuthenticator::class)->authenticate($this->token);
        ContextResolver::withAuthContext($context, function (): void {
            try {
                ContextResolver::withContext(['fiscal_year_id' => $this->year->id], function (): void {
                    self::assertSame('target', config('amir.mcp_probe'));
                    self::assertNull(config('amir.foreign_only'));
                    self::assertSame($this->year->id, config('active-fiscal-year-id'));
                    self::assertFalse(config('activitylog.enabled'));
                    self::assertSame('en', app()->getLocale());
                    throw new RuntimeException('restore context');
                });
            } catch (RuntimeException) {
                // Assert the restoration below, including the exception path.
            }
        });
        self::assertSame($previous, config('amir'));
        self::assertSame($previousApp, config('app'));
        self::assertSame($previousLocale, app()->getLocale());
        self::assertSame($previousActivityLog, config('activitylog.enabled'));
        self::assertSame($other->id, config('active-fiscal-year-id'));
    }
}
