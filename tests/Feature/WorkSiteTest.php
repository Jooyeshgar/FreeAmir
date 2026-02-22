<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkSiteTest extends TestCase
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
            Permission::firstOrCreate(['name' => 'work-sites.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makeWorkSite(array $overrides = []): WorkSite
    {
        return WorkSite::factory()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Main Construction Site',
            'code' => 'WS-001',
            'address' => 'Tehran, Iran',
            'phone' => '02112345678',
            'is_active' => '1',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_work_sites_for_active_company(): void
    {
        $this->makeWorkSite(['name' => 'Site Alpha']);
        $this->makeWorkSite(['name' => 'Site Beta', 'code' => 'WS-002']);

        $response = $this->get(route('work-sites.index'));

        $response->assertStatus(200);
        $response->assertSee('Site Alpha');
        $response->assertSee('Site Beta');
    }

    public function test_index_does_not_show_other_company_work_sites(): void
    {
        $otherCompany = Company::factory()->create();
        WorkSite::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Other Site']);

        $response = $this->get(route('work-sites.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Other Site');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('work-sites.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_work_site_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('work-sites.store'), $payload);

        $response->assertRedirect(route('work-sites.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_sites', [
            'company_id' => $this->companyId,
            'name' => 'Main Construction Site',
            'code' => 'WS-001',
            'is_active' => true,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('work-sites.store'), []);

        $response->assertSessionHasErrors(['name', 'code']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->makeWorkSite(['code' => 'WS-001']);

        $response = $this->post(route('work-sites.store'), $this->validPayload(['code' => 'WS-001']));

        $response->assertSessionHasErrors(['code']);
    }

    public function test_store_allows_optional_fields_to_be_null(): void
    {
        $payload = $this->validPayload(['address' => null, 'phone' => null]);

        $response = $this->post(route('work-sites.store'), $payload);

        $response->assertRedirect(route('work-sites.index'));
        $this->assertDatabaseHas('work_sites', [
            'company_id' => $this->companyId,
            'code' => 'WS-001',
            'address' => null,
            'phone' => null,
        ]);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $workSite = $this->makeWorkSite();

        $response = $this->get(route('work-sites.edit', $workSite));

        $response->assertStatus(200);
        $response->assertSee($workSite->name);
    }

    public function test_update_modifies_work_site_and_redirects(): void
    {
        $workSite = $this->makeWorkSite(['name' => 'Old Name']);

        $response = $this->put(route('work-sites.update', $workSite), $this->validPayload([
            'name' => 'New Name',
        ]));

        $response->assertRedirect(route('work-sites.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_sites', [
            'id' => $workSite->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_rejects_duplicate_code_for_another_record(): void
    {
        $first = $this->makeWorkSite(['code' => 'WS-001']);
        $second = $this->makeWorkSite(['code' => 'WS-002']);

        $response = $this->put(route('work-sites.update', $second), $this->validPayload([
            'code' => 'WS-001', // conflicts with $first
        ]));

        $response->assertSessionHasErrors(['code']);
    }

    public function test_update_allows_same_code_for_same_record(): void
    {
        $workSite = $this->makeWorkSite(['code' => 'WS-001', 'name' => 'Old Name']);

        $response = $this->put(route('work-sites.update', $workSite), $this->validPayload([
            'code' => 'WS-001',
            'name' => 'Updated Name',
        ]));

        $response->assertRedirect(route('work-sites.index'));
        $this->assertDatabaseHas('work_sites', ['id' => $workSite->id, 'name' => 'Updated Name']);
    }

    public function test_update_can_deactivate_work_site(): void
    {
        $workSite = $this->makeWorkSite(['is_active' => true]);

        $this->put(route('work-sites.update', $workSite), $this->validPayload(['is_active' => '0']));

        $this->assertDatabaseHas('work_sites', [
            'id' => $workSite->id,
            'is_active' => false,
        ]);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_work_site_and_redirects(): void
    {
        $workSite = $this->makeWorkSite();

        $response = $this->delete(route('work-sites.destroy', $workSite));

        $response->assertRedirect(route('work-sites.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('work_sites', ['id' => $workSite->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('work-sites.index'));

        $response->assertRedirect(route('login'));
    }
}
