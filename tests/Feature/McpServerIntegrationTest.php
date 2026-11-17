<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Mcp\Auth\TokenAuthenticator;
use App\Mcp\Support\ContextResolver;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CostIncomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class McpServerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FiscalYear $year;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user = User::factory()->create();
        $this->year = FiscalYear::factory()->create(['year' => 1405]);
        $this->user->fiscalYears()->sync([$this->year->id]);
        foreach (['api.access', 'invoices.index', 'customers.show', 'reports.cost-income', 'reports.trial-balance'] as $p) {
            $this->user->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
        $this->token = $this->user->createToken('Review', ['invoices.index', 'customers.show', 'reports.cost-income', 'reports.trial-balance'])->plainTextToken;
        config(['active-fiscal-year-id' => null]);
    }

    private function callTool(string $name, array $arguments = []): array
    {
        $response = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments],
        ])->assertOk();
        $result = $response->json('result');
        self::assertFalse($result['isError'] ?? true, json_encode($result, JSON_UNESCAPED_UNICODE));

        return json_decode($result['content'][0]['text'], true);
    }

    public function test_real_http_rejects_missing_bearer_and_missing_api_access(): void
    {
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $this->user->revokePermissionTo('api.access');
        $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
    }

    public function test_real_http_isolates_context_and_restores_host_configuration(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->detach($other->id);
        $context = $this->callTool('get_current_context', ['fiscal_year_id' => $this->year->id]);
        self::assertSame([$this->year->id], array_column($context['available_fiscal_years'], 'id'));
        self::assertNull(config('active-fiscal-year-id'));
        $response = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'get_current_context', 'arguments' => ['fiscal_year_id' => $other->id]],
        ])->assertOk();
        self::assertTrue($response->json('result.isError'));
    }

    public function test_real_financial_services_can_run(): void
    {
        $summary = $this->callTool('get_financial_summary', ['fiscal_year_id' => $this->year->id]);
        self::assertSame(0, $summary['net_profit']);
        $profit = $this->callTool('get_profit_loss', ['fiscal_year_id' => $this->year->id]);
        self::assertSame(0, $profit['net_profit']);
    }

    public function test_mcp_reports_use_company_currency_and_stable_status_codes(): void
    {
        $this->year->company->update(['currency' => 'USD']);
        app()->setLocale('en');
        config(['amir.currency' => 'EUR']);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(12, 0));

        $customer = Customer::create([
            'name' => 'Currency customer',
            'credit' => 1250,
            'fiscal_year_id' => $this->year->id,
        ]);

        config(['active-fiscal-year-id' => $this->year->id]);
        Invoice::create([
            'number' => 9301,
            'date' => '2026-10-26',
            'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED,
            'customer_id' => $customer->id,
            'creator_id' => $this->user->id,
            'fiscal_year_id' => $this->year->id,
            'subtraction' => 0,
            'vat' => 0,
            'amount' => 1250,
        ]);
        config(['active-fiscal-year-id' => null]);

        $context = $this->callTool('get_current_context', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('USD', $context['currency']);
        self::assertSame('USD', $context['active_fiscal_year']['currency']);
        self::assertSame('USD', $context['available_fiscal_years'][0]['currency']);
        self::assertSame('Aban', $context['current_month_name']);

        $balance = $this->callTool('get_customer_balance', [
            'query' => $customer->name,
            'fiscal_year_id' => $this->year->id,
        ]);
        self::assertSame('USD', $balance['currency']);
        self::assertSame('settled', $balance['customers'][0]['balance_status_code']);
        self::assertSame('Settled (zero)', $balance['customers'][0]['balance_status']);
        self::assertSame('0 USD (Settled (zero))', $balance['customers'][0]['formatted_balance']);
        self::assertSame('1,250 USD', $balance['customers'][0]['formatted_credit_limit']);

        $invoices = $this->callTool('get_invoices_summary', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('USD', $invoices['currency']);
        self::assertSame('sell', $invoices['recent_invoices'][0]['type_code']);
        self::assertSame('1,250 USD', $invoices['recent_invoices'][0]['formatted_amount']);
        self::assertSame('Aban', $invoices['recent_invoices'][0]['jalali_month_name']);
        self::assertSame('1,250 USD', $invoices['summary']['formatted_sales_amount']);

        $summary = $this->callTool('get_financial_summary', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('USD', $summary['currency']);
        self::assertStringContainsString('0 USD', $summary['human_summary']);

        $profit = $this->callTool('get_profit_loss', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('USD', $profit['currency']);
        self::assertSame('profitable', $profit['status_code']);
        self::assertSame('Profitable', $profit['status']);

        app()->setLocale('fa');
        $translatedBalance = $this->callTool('get_customer_balance', [
            'query' => $customer->name,
            'fiscal_year_id' => $this->year->id,
        ]);
        self::assertSame('تسویه (صفر)', $translatedBalance['customers'][0]['balance_status']);
        self::assertSame($balance['customers'][0]['balance_status_code'], $translatedBalance['customers'][0]['balance_status_code']);

        $translatedProfit = $this->callTool('get_profit_loss', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('سودده', $translatedProfit['status']);
        self::assertSame($profit['status_code'], $translatedProfit['status_code']);
    }

    #[TestWith(['en'])]
    #[TestWith(['fa'])]
    public function test_currency_name_is_not_translated(string $locale): void
    {
        $this->year->company->update(['currency' => 'Rial']);
        app()->setLocale($locale);

        $context = $this->callTool('get_current_context', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('Rial', $context['currency']);
        self::assertSame('Rial', $context['active_fiscal_year']['currency']);
        self::assertSame('Rial', $context['available_fiscal_years'][0]['currency']);

        $summary = $this->callTool('get_financial_summary', ['fiscal_year_id' => $this->year->id]);
        self::assertSame('Rial', $summary['currency']);
    }

    public function test_approved_status_filter_returns_approved_invoice(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $customer = Customer::create(['name' => 'Review customer', 'fiscal_year_id' => $this->year->id]);
        Invoice::create(['number' => 9101, 'date' => '2026-10-26', 'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED, 'customer_id' => $customer->id, 'creator_id' => $this->user->id,
            'fiscal_year_id' => $this->year->id, 'subtraction' => 0, 'vat' => 0, 'amount' => 1200]);
        $raw = $this->callTool('get_invoices_summary', ['status' => '3', 'fiscal_year_id' => $this->year->id]);
        self::assertSame(1, $raw['summary']['total_sales_count']);
        $named = $this->callTool('get_invoices_summary', ['status' => 'approved', 'fiscal_year_id' => $this->year->id]);
        self::assertSame(1, $named['summary']['total_sales_count']);
        // 2026-10-26 corresponds to Aban (month 8) in 1405
        $month8 = collect($named['monthly_breakdown'])->firstWhere('month_number', 8);
        self::assertNotNull($month8);
        self::assertSame(1, $month8['sales_count']);
        self::assertSame(1200, $month8['sales_amount']);
        $month1 = collect($named['monthly_breakdown'])->firstWhere('month_number', 1);
        self::assertSame(0, $month1['sales_count']);
    }

    public function test_notifications_and_removed_transport_routes(): void
    {
        $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202)->assertContent('');
        $this->withToken($this->token)->getJson('/api/mcp')->assertStatus(405);
        $this->withToken($this->token)->getJson('/api/mcp/sse')->assertNotFound();
        $this->withToken($this->token)->postJson('/api/mcp/message', [])->assertNotFound();
    }

    public function test_schema_errors_and_unauthorized_tools_do_not_execute(): void
    {
        $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_invoices_summary', 'arguments' => ['limit' => 51]],
        ])->assertOk()->assertJsonPath('result.isError', true);
        $this->user->tokens()->delete();
        $limited = $this->user->createToken('Limited', ['invoices.index'])->plainTextToken;
        app('auth')->forgetGuards();
        $this->withToken($limited)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'get_customer_balance', 'arguments' => ['query' => 'Customer']],
        ])->assertStatus(400)->assertJsonPath('error.code', -32602);
    }

    public function test_stdio_authenticator_checks_expiration_and_revocation_without_setting_global_auth(): void
    {
        app('auth')->forgetGuards();
        $authenticator = app(TokenAuthenticator::class);
        self::assertNotNull($authenticator->authenticate($this->token));
        self::assertNull(auth()->user());
        $this->user->tokens()->update(['expires_at' => now()->subMinute()]);
        self::assertNull($authenticator->authenticate($this->token));
        $this->user->tokens()->delete();
        self::assertNull($authenticator->authenticate($this->token));
    }

    public function test_customer_balance_is_scoped_to_authorized_year(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1404]);
        $visible = Customer::create(['name' => 'Scoped customer', 'fiscal_year_id' => $this->year->id]);
        Customer::create(['name' => 'Scoped customer', 'fiscal_year_id' => $other->id]);
        $result = $this->callTool('get_customer_balance', ['query' => 'Scoped customer', 'fiscal_year_id' => $this->year->id]);
        self::assertSame([$visible->id], array_column($result['customers'], 'id'));
        self::assertNull(config('active-fiscal-year-id'));
    }

    public function test_customer_balances_and_statuses_match_the_host_ledger(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $document = Document::create([
            'number' => 1, 'date' => '2026-10-26', 'creator_id' => $this->user->id,
            'approved_at' => now(), 'approver_id' => $this->user->id, 'title' => 'Customer ledger fixture', 'fiscal_year_id' => $this->year->id,
        ]);
        $customers = [];
        foreach ([[-700, 'بدهکار'], [400, 'بستانکار'], [0, 'تسویه (صفر)']] as [$balance, $status]) {
            $customer = Customer::create([
                'name' => 'Ledger customer '.count($customers), 'balance' => 999,
                'fiscal_year_id' => $this->year->id,
            ]);
            if ($balance !== 0) {
                $subject = Subject::factory()->create(['is_permanent' => true, 'fiscal_year_id' => $this->year->id]);
                $subject->subjectable()->associate($customer);
                $subject->save();
                $customer->update(['subject_id' => $subject->id]);
                Transaction::create([
                    'subject_id' => $subject->id, 'value' => $balance, 'document_id' => $document->id,
                    'user_id' => $this->user->id, 'desc' => 'Customer ledger fixture',
                ]);
            }
            $customers[] = [$customer, $balance, $status];
        }
        $counter = Subject::factory()->create(['is_permanent' => true, 'fiscal_year_id' => $this->year->id]);
        Transaction::create([
            'subject_id' => $counter->id, 'value' => 300, 'document_id' => $document->id,
            'user_id' => $this->user->id, 'desc' => 'Customer ledger fixture',
        ]);
        config(['active-fiscal-year-id' => null]);
        foreach ($customers as [$customer, $balance, $status]) {
            $report = $this->callTool('get_customer_balance', ['query' => $customer->name, 'fiscal_year_id' => $this->year->id]);
            self::assertSame($customer->id, $report['customers'][0]['id']);
            self::assertSame($balance, $report['customers'][0]['balance']);
            self::assertSame($status, $report['customers'][0]['balance_status']);
        }
        self::assertNull(config('active-fiscal-year-id'));
    }

    #[TestWith([1404, '2025-10-26'])]
    #[TestWith([1405, '2026-10-26'])]
    public function test_nonzero_financial_reports_match_approved_host_ledger(int $year, string $date): void
    {
        $this->year->update(['year' => $year]);
        config(['active-fiscal-year-id' => $this->year->id, 'active-company-fiscal-year' => 1403]);
        $income = Subject::factory()->create(['name' => 'Revenue', 'is_permanent' => false, 'fiscal_year_id' => $this->year->id]);
        $cost = Subject::factory()->create(['name' => 'Expense', 'is_permanent' => false, 'fiscal_year_id' => $this->year->id]);
        $counter = Subject::factory()->create(['name' => 'Counter', 'is_permanent' => true, 'fiscal_year_id' => $this->year->id]);
        $document = Document::create([
            'number' => 1, 'date' => $date, 'creator_id' => $this->user->id,
            'approved_at' => now(), 'approver_id' => $this->user->id, 'title' => 'Integration fixture', 'fiscal_year_id' => $this->year->id,
        ]);
        foreach ([[$income, 1000], [$cost, -400], [$counter, -600]] as [$subject,$value]) {
            Transaction::create(['subject_id' => $subject->id, 'value' => $value, 'document_id' => $document->id, 'user_id' => $this->user->id, 'desc' => 'Integration fixture']);
        }
        config(['active-fiscal-year-id' => null]);
        foreach (['get_financial_summary', 'get_profit_loss'] as $tool) {
            $report = $this->callTool($tool, ['fiscal_year_id' => $this->year->id]);
            self::assertSame(1000, $report['total_income']);
            self::assertSame(400, $report['total_cost']);
            self::assertSame(600, $report['net_profit']);
        }
        $monthly = $this->callTool('get_profit_loss', ['fiscal_year_id' => $this->year->id, 'month' => 8]);
        self::assertSame(600, $monthly['net_profit']);
        self::assertNull(config('active-fiscal-year-id'));
        self::assertSame(1403, config('active-company-fiscal-year'));
    }

    public function test_internal_service_exception_does_not_disclose_sql_and_restores_context(): void
    {
        config(['active-company-fiscal-year' => 1403]);
        $service = Mockery::mock(CostIncomeService::class);
        $service->shouldReceive('summary')->once()->andThrow(new RuntimeException('SQL secret_schema internal-path'));
        app()->instance(CostIncomeService::class, $service);
        $response = $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'get_financial_summary', 'arguments' => ['fiscal_year_id' => $this->year->id]],
        ])->assertOk()->assertJsonPath('result.isError', true);
        self::assertStringNotContainsString('secret_schema', $response->getContent());
        self::assertNull(config('active-fiscal-year-id'));
        self::assertSame(1403, config('active-company-fiscal-year'));
        self::assertNull(ContextResolver::authContext());
    }

    public function test_tool_visibility_requires_both_user_permission_and_token_ability(): void
    {
        $limited = $this->user->createToken('Invoices only', ['invoices.index'])->plainTextToken;
        $response = $this->withToken($limited)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
        ])->assertOk();
        self::assertSame(['get_current_context', 'get_invoices_summary'], array_column($response->json('result.tools'), 'name'));
        $this->user->revokePermissionTo('invoices.index');
        app('auth')->forgetGuards();
        $response = $this->withToken($limited)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list',
        ])->assertOk();
        self::assertSame([], $response->json('result.tools'));
    }

    public function test_company_overview_requires_documents_permission_in_both_layers(): void
    {
        $this->user->givePermissionTo(Permission::findOrCreate('reports.company-overview', 'web'));
        $this->user->givePermissionTo(Permission::findOrCreate('documents.show', 'web'));
        foreach ([['reports.company-overview'], ['reports.company-overview', 'documents.show']] as $abilities) {
            $token = $this->user->createToken('Overview', $abilities)->plainTextToken;
            app('auth')->forgetGuards();
            $response = $this->withToken($token)->postJson('/api/mcp', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list',
            ])->assertOk();
            $names = array_column($response->json('result.tools'), 'name');
            self::assertSame(count($abilities) === 2, in_array('get_profit_loss', $names, true));
            self::assertSame(count($abilities) === 2, in_array('get_financial_summary', $names, true));
        }
    }

    public function test_customer_names_require_customer_permission_and_token_ability(): void
    {
        config(['active-fiscal-year-id' => $this->year->id]);
        $customer = Customer::create(['name' => 'Private customer', 'fiscal_year_id' => $this->year->id]);
        Invoice::create(['number' => 9201, 'date' => '2026-10-26', 'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::APPROVED, 'customer_id' => $customer->id, 'creator_id' => $this->user->id,
            'fiscal_year_id' => $this->year->id, 'subtraction' => 0, 'vat' => 0, 'amount' => 100]);
        self::assertNull($this->callTool('get_invoices_summary')['recent_invoices'][0]['customer']);
        $this->user->givePermissionTo(Permission::findOrCreate('customers.index', 'web'));
        $this->token = $this->user->createToken('Names', ['invoices.index', 'customers.index'])->plainTextToken;
        app('auth')->forgetGuards();
        self::assertSame('Private customer', $this->callTool('get_invoices_summary')['recent_invoices'][0]['customer']);
        $this->user->revokePermissionTo('customers.index');
        app('auth')->forgetGuards();
        self::assertNull($this->callTool('get_invoices_summary')['recent_invoices'][0]['customer']);
    }

    public function test_schema_rejects_bad_arguments_before_financial_services_execute(): void
    {
        $service = Mockery::mock(CostIncomeService::class);
        $service->shouldNotReceive('summary');
        app()->instance(CostIncomeService::class, $service);
        foreach ([['limit' => 0], ['limit' => 21], ['limit' => []], ['fiscal_year_id' => null], ['company_id' => 1], ['unknown' => true]] as $arguments) {
            $this->withToken($this->token)->postJson('/api/mcp', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'get_financial_summary', 'arguments' => $arguments],
            ])->assertOk()->assertJsonPath('result.isError', true);
        }
    }

    public function test_unknown_and_foreign_years_return_the_same_error(): void
    {
        $foreign = FiscalYear::factory()->create(['year' => 1404]);
        $this->user->fiscalYears()->detach($foreign->id);
        $errors = [];
        foreach ([$foreign->id, 999999] as $yearId) {
            $response = $this->withToken($this->token)->postJson('/api/mcp', [
                'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
                'params' => ['name' => 'get_current_context', 'arguments' => ['fiscal_year_id' => $yearId]],
            ])->assertOk()->assertJsonPath('result.isError', true);
            $errors[] = $response->json('result.content.0.text');
        }
        self::assertSame($errors[0], $errors[1]);
        self::assertNull(ContextResolver::authContext());
    }

    public function test_same_year_in_multiple_companies_requires_an_explicit_fiscal_year(): void
    {
        $second = FiscalYear::factory()->create(['year' => 1405]);
        $this->user->fiscalYears()->syncWithoutDetaching([$second->id]);
        $this->withToken($this->token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'get_current_context', 'arguments' => ['year' => 1405]],
        ])->assertOk()->assertJsonPath('result.isError', true);
        self::assertSame($second->id, $this->callTool('get_current_context', ['fiscal_year_id' => $second->id])['active_fiscal_year']['id']);
    }

    public function test_http_rejects_expired_and_revoked_tokens(): void
    {
        $this->user->tokens()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])->assertUnauthorized();
        $this->user->tokens()->delete();
        app('auth')->forgetGuards();
        $this->withToken($this->token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'])->assertUnauthorized();
    }

    public function test_stdio_uses_the_official_transport_and_requires_a_real_token(): void
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/mcp-stdio.php')], base_path());
        $process->setInput(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])."\n".
            json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])."\n".
            json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])."\n");
        $process->mustRun();
        self::assertSame('', $process->getErrorOutput());
        $lines = preg_split('/\r?\n/', trim($process->getOutput()));
        self::assertCount(2, $lines);
        self::assertSame(1, json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR)['id']);
        $tools = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['get_current_context', 'get_invoices_summary'], array_column($tools['result']['tools'], 'name'));

        $invalid = new Process([PHP_BINARY, base_path('tests/Fixtures/mcp-stdio.php'), 'invalid'], base_path());
        $invalid->setInput('');
        $invalid->run();
        self::assertSame(1, $invalid->getExitCode());
        self::assertSame('', $invalid->getOutput());
        self::assertStringContainsString('valid token', $invalid->getErrorOutput());
    }
}
