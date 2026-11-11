<?php

namespace Tests\Feature;

use Amir\McpServer\Auth\AuthContext;
use Amir\McpServer\Protocol\ProtocolHandler;
use Amir\McpServer\Support\ContextResolver;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FiscalYear $allowedYear;

    private FiscalYear $foreignYear;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(ProtocolHandler::class)) {
            $this->markTestSkipped('The optional MCP package is not installed.');
        }
        $this->owner = User::factory()->create();
        $this->allowedYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->foreignYear = FiscalYear::factory()->create(['year' => 1404]);
        $this->owner->fiscalYears()->sync([$this->allowedYear->id]);
        config(['active-fiscal-year-id' => $this->allowedYear->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['invoices.index', 'customers.index', 'customers.show', 'reports.company-overview', 'documents.show', 'reports.cost-income', 'reports.ledger'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->owner->givePermissionTo('invoices.index', 'customers.index', 'customers.show');
    }

    private function handler(array $abilities): ProtocolHandler
    {
        $handler = app(ProtocolHandler::class);
        $handler->setAuthContext(new AuthContext(user: $this->owner, abilities: $abilities, isAuthenticated: true));

        return $handler;
    }

    private function mcpCall(string $name, array $arguments, array $abilities = ['invoices.index']): array
    {
        return $this->handler($abilities)->handle([
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ])['result'];
    }

    public function test_context_lists_only_assigned_fiscal_years(): void
    {
        $result = $this->mcpCall('get_current_context', []);
        $this->assertFalse($result['isError']);
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame([$this->allowedYear->id], array_column($data['available_fiscal_years'], 'id'));
        $this->assertSame($this->allowedYear->id, $data['active_fiscal_year']['id']);
    }

    public function test_foreign_and_nonexistent_fiscal_years_are_rejected_without_disclosing_metadata(): void
    {
        foreach ([['fiscal_year_id' => $this->foreignYear->id], ['year' => 1404], ['company_id' => $this->foreignYear->company_id], ['fiscal_year_id' => 999999]] as $arguments) {
            foreach (['get_current_context', 'get_invoices_summary', 'get_profit_loss', 'get_financial_summary', 'get_customer_balance'] as $name) {
                $this->owner->givePermissionTo('reports.cost-income', 'reports.ledger');
                $result = $this->mcpCall($name, $arguments + ['query' => 'private'], ['invoices.index', 'reports.cost-income', 'reports.ledger', 'customers.show']);
                $this->assertTrue($result['isError'], $name);
                $this->assertStringNotContainsString('1404', $result['content'][0]['text']);
            }
        }
    }

    public function test_invoice_customer_names_require_both_token_and_user_customer_permission(): void
    {
        $customer = Customer::create(['name' => 'PRIVATE CUSTOMER', 'fiscal_year_id' => $this->allowedYear->id]);
        Invoice::create([
            'number' => 1, 'date' => '2026-04-01', 'invoice_type' => InvoiceType::SELL,
            'status' => InvoiceStatus::UNAPPROVED, 'customer_id' => $customer->id,
            'creator_id' => $this->owner->id, 'amount' => 1000, 'vat' => 0, 'subtraction' => 0,
        ]);
        $arguments = ['fiscal_year_id' => $this->allowedYear->id, 'type' => 'sell'];
        $result = $this->mcpCall('get_invoices_summary', $arguments);
        $this->assertFalse($result['isError'], $result['content'][0]['text']);
        $this->assertStringNotContainsString('PRIVATE CUSTOMER', $result['content'][0]['text']);
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertCount(1, $data['recent_invoices']);
        $this->assertNull($data['recent_invoices'][0]['customer']);
        $visible = $this->mcpCall('get_invoices_summary', $arguments, ['invoices.index', 'customers.index']);
        $this->assertStringContainsString('PRIVATE CUSTOMER', $visible['content'][0]['text']);
        $this->owner->revokePermissionTo('customers.index');
        $hidden = $this->mcpCall('get_invoices_summary', $arguments, ['invoices.index', 'customers.index']);
        $this->assertStringNotContainsString('PRIVATE CUSTOMER', $hidden['content'][0]['text']);
    }

    public function test_context_rejects_mismatched_company_and_restores_configuration(): void
    {
        $result = $this->mcpCall('get_current_context', ['fiscal_year_id' => $this->allowedYear->id, 'company_id' => $this->foreignYear->company_id]);
        $this->assertTrue($result['isError']);
        $this->assertSame($this->allowedYear->id, config('active-fiscal-year-id'));
        $this->assertFalse(ContextResolver::can('invoices.index'));
    }

    public function test_default_context_ignores_foreign_configured_fiscal_year(): void
    {
        config(['active-fiscal-year-id' => $this->foreignYear->id]);
        $result = $this->mcpCall('get_current_context', []);
        $this->assertFalse($result['isError']);
        $data = json_decode($result['content'][0]['text'], true);
        $this->assertSame($this->allowedYear->id, $data['active_fiscal_year']['id']);
        $this->assertSame($this->foreignYear->id, config('active-fiscal-year-id'));
    }

    public function test_user_without_assigned_fiscal_years_cannot_fall_back_to_another_company(): void
    {
        $this->owner->fiscalYears()->detach();
        $result = $this->mcpCall('get_current_context', []);
        $this->assertTrue($result['isError']);
        $this->assertArrayNotHasKey('active_fiscal_year', json_decode($result['content'][0]['text'], true) ?? []);
    }

    public function test_same_year_in_multiple_assigned_companies_requires_explicit_fiscal_year(): void
    {
        $other = FiscalYear::factory()->create(['year' => 1405]);
        $this->owner->fiscalYears()->sync([$this->allowedYear->id, $other->id]);
        $result = $this->mcpCall('get_current_context', ['year' => 1405]);
        $this->assertTrue($result['isError']);
        $selected = $this->mcpCall('get_current_context', ['year' => 1405, 'fiscal_year_id' => $other->id]);
        $this->assertFalse($selected['isError']);
        $data = json_decode($selected['content'][0]['text'], true);
        $this->assertSame($other->id, $data['active_fiscal_year']['id']);
    }

    public function test_http_requires_token_and_applies_token_customer_ceiling(): void
    {
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
        $token = $this->owner->createToken('invoice reader', ['invoices.index'])->plainTextToken;
        $response = $this->withToken($token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->assertOk();
        $names = array_column($response->json('result.tools'), 'name');
        $this->assertContains('get_current_context', $names);
        $this->assertContains('get_invoices_summary', $names);
        $this->assertNotContains('get_customer_balance', $names);
        $this->withToken($token)->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'get_current_context', 'arguments' => ['fiscal_year_id' => $this->foreignYear->id]],
        ])->assertOk()->assertJsonPath('result.isError', true);
    }
}
