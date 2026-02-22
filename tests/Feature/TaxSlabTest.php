<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxSlab;
use App\Models\User;
use Cookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Cookie::queue('active-company-id', $company->id);
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function makeTaxSlab(array $overrides = []): TaxSlab
    {
        return TaxSlab::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'year' => 1403,
            'slab_order' => 1,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'year' => 1403,
            'slab_order' => 1,
            'income_from' => 0,
            'income_to' => 500_000_000,
            'tax_rate' => 10,
            'annual_exemption' => 36_000_000,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_tax_slabs_for_active_company(): void
    {
        $this->makeTaxSlab(['year' => 1403, 'slab_order' => 1]);
        $this->makeTaxSlab(['year' => 1403, 'slab_order' => 2]);

        $response = $this->get(route('tax-slabs.index'));

        $response->assertStatus(200);
        $response->assertSee('1403');
    }

    public function test_index_filters_by_year(): void
    {
        $this->makeTaxSlab(['year' => 1403, 'slab_order' => 1]);
        $this->makeTaxSlab(['year' => 1402, 'slab_order' => 1]);

        $response = $this->get(route('tax-slabs.index', ['year' => 1402]));

        $response->assertStatus(200);
        $response->assertSee('1402');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('tax-slabs.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_tax_slab_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('tax-slabs.store'), $payload);

        $response->assertRedirect(route('tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_slabs', [
            'company_id' => $this->companyId,
            'year' => 1403,
            'slab_order' => 1,
            'tax_rate' => 10,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('tax-slabs.store'), []);

        $response->assertSessionHasErrors(['year', 'slab_order', 'income_from', 'tax_rate']);
    }

    public function test_store_rejects_duplicate_year_slab_order(): void
    {
        $this->makeTaxSlab(['year' => 1403, 'slab_order' => 1]);

        $response = $this->post(route('tax-slabs.store'), $this->validPayload([
            'year' => 1403,
            'slab_order' => 1,
        ]));

        $response->assertSessionHasErrors(['slab_order']);
    }

    public function test_store_rejects_income_to_less_than_income_from(): void
    {
        $response = $this->post(route('tax-slabs.store'), $this->validPayload([
            'income_from' => 500_000_000,
            'income_to' => 100_000_000,
        ]));

        $response->assertSessionHasErrors(['income_to']);
    }

    public function test_store_allows_null_income_to_for_unlimited_slab(): void
    {
        $payload = $this->validPayload(['income_to' => null]);

        $response = $this->post(route('tax-slabs.store'), $payload);

        $response->assertRedirect(route('tax-slabs.index'));
        $this->assertDatabaseHas('tax_slabs', [
            'company_id' => $this->companyId,
            'income_to' => null,
        ]);
    }

    public function test_store_rejects_tax_rate_out_of_range(): void
    {
        $response = $this->post(route('tax-slabs.store'), $this->validPayload(['tax_rate' => 150]));

        $response->assertSessionHasErrors(['tax_rate']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $taxSlab = $this->makeTaxSlab();

        $response = $this->get(route('tax-slabs.edit', $taxSlab));

        $response->assertStatus(200);
        $response->assertSee($taxSlab->year);
    }

    public function test_update_modifies_tax_slab_and_redirects(): void
    {
        $taxSlab = $this->makeTaxSlab(['tax_rate' => 10]);

        $response = $this->put(route('tax-slabs.update', $taxSlab), $this->validPayload([
            'tax_rate' => 20,
        ]));

        $response->assertRedirect(route('tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tax_slabs', [
            'id' => $taxSlab->id,
            'tax_rate' => 20,
        ]);
    }

    public function test_update_rejects_duplicate_year_slab_order_for_another_record(): void
    {
        $first = $this->makeTaxSlab(['year' => 1403, 'slab_order' => 1]);
        $second = $this->makeTaxSlab(['year' => 1403, 'slab_order' => 2]);

        $response = $this->put(route('tax-slabs.update', $second), $this->validPayload([
            'year' => 1403,
            'slab_order' => 1, // conflicts with $first
        ]));

        $response->assertSessionHasErrors(['slab_order']);
    }

    public function test_update_allows_same_year_slab_order_for_same_record(): void
    {
        $taxSlab = $this->makeTaxSlab(['year' => 1403, 'slab_order' => 1, 'tax_rate' => 10]);

        $response = $this->put(route('tax-slabs.update', $taxSlab), $this->validPayload([
            'year' => 1403,
            'slab_order' => 1,
            'tax_rate' => 15,
        ]));

        $response->assertRedirect(route('tax-slabs.index'));
        $this->assertDatabaseHas('tax_slabs', ['id' => $taxSlab->id, 'tax_rate' => 15]);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_tax_slab_and_redirects(): void
    {
        $taxSlab = $this->makeTaxSlab();

        $response = $this->delete(route('tax-slabs.destroy', $taxSlab));

        $response->assertRedirect(route('tax-slabs.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tax_slabs', ['id' => $taxSlab->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->post('/logout');

        $response = $this->get(route('tax-slabs.index'));

        $response->assertRedirect(route('login'));
    }
}
