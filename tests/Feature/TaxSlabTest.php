<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxSlab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaxSlabTest extends TestCase
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

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'salary.tax-slabs.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makeTaxSlab(array $overrides = []): TaxSlab
    {
        return TaxSlab::factory()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'income_to' => 500_000_000,
            'tax_rate' => 10,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_tax_slabs_for_active_company(): void
    {
        $this->makeTaxSlab();

        $response = $this->get(route('salary.tax-slabs.index'));

        $response->assertStatus(200);
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('salary.tax-slabs.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_tax_slab_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('salary.tax-slabs.store'), $payload);

        $response->assertRedirect(route('salary.tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_slabs', [
            'company_id' => $this->companyId,
            'tax_rate' => 10,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('salary.tax-slabs.store'), []);

        $response->assertSessionHasErrors(['tax_rate']);
    }

    public function test_store_allows_null_income_to_for_unlimited_slab(): void
    {
        $payload = $this->validPayload(['income_to' => null]);

        $response = $this->post(route('salary.tax-slabs.store'), $payload);

        $response->assertRedirect(route('salary.tax-slabs.index'));
        $this->assertDatabaseHas('tax_slabs', [
            'company_id' => $this->companyId,
            'income_to' => null,
        ]);
    }

    public function test_store_rejects_tax_rate_out_of_range(): void
    {
        $response = $this->post(route('salary.tax-slabs.store'), $this->validPayload(['tax_rate' => 150]));

        $response->assertSessionHasErrors(['tax_rate']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $taxSlab = $this->makeTaxSlab();

        $response = $this->get(route('salary.tax-slabs.edit', $taxSlab));

        $response->assertStatus(200);
    }

    public function test_update_modifies_tax_slab_and_redirects(): void
    {
        $taxSlab = $this->makeTaxSlab(['tax_rate' => 10]);

        $response = $this->put(route('salary.tax-slabs.update', $taxSlab), $this->validPayload([
            'tax_rate' => 20,
        ]));

        $response->assertRedirect(route('salary.tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_slabs', [
            'id' => $taxSlab->id,
            'tax_rate' => 20,
        ]);
    }
    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_tax_slab_and_redirects(): void
    {
        $taxSlab = $this->makeTaxSlab();

        $response = $this->delete(route('salary.tax-slabs.destroy', $taxSlab));

        $response->assertRedirect(route('salary.tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tax_slabs', ['id' => $taxSlab->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('salary.tax-slabs.index'));

        $response->assertRedirect(route('login'));
    }
}
