<?php

namespace Tests\Feature\Api;

use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private FiscalYear $fiscalYear;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fiscalYear = FiscalYear::factory()->create();
        $this->user = User::factory()->create();
        $this->fiscalYear->users()->attach($this->user);
        config(['active-fiscal-year-id' => $this->fiscalYear->id]);
    }

    private function headers(array $abilities): array
    {
        return [
            'Authorization' => 'Bearer '.$this->user->createToken('api-test', $abilities)->plainTextToken,
        ];
    }

    private function grant(string $permission): void
    {
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]));
    }

    public function test_invoice_index_requires_authentication(): void
    {
        $this->getJson('/api/companies/'.$this->fiscalYear->id.'/invoices')->assertUnauthorized();
    }

    public function test_invoice_index_requires_permission(): void
    {
        $this->grant('api.access');

        $this->getJson(
            '/api/companies/'.$this->fiscalYear->id.'/invoices',
            $this->headers(['api.access'])
        )->assertForbidden();
    }

    public function test_invoice_index_is_available_to_a_permitted_user(): void
    {
        $this->grant('api.access');
        $this->grant('invoices.index');

        $this->getJson(
            '/api/companies/'.$this->fiscalYear->id.'/invoices',
            $this->headers(['api.access', 'invoices.index'])
        )->assertOk()->assertJsonStructure(['data', 'current_page', 'total', 'per_page', 'last_page']);
    }

    public function test_invoice_show_returns_not_found_for_a_missing_invoice(): void
    {
        $this->grant('api.access');
        $this->grant('invoices.show');

        $this->getJson(
            '/api/companies/'.$this->fiscalYear->id.'/invoices/999999',
            $this->headers(['api.access', 'invoices.show'])
        )->assertNotFound();
    }
}
