<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkSiteContractTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected WorkSite $workSite;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'salary.work-site-contracts.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $this->workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);
    }

    private function makeContract(array $overrides = []): WorkSiteContract
    {
        return WorkSiteContract::factory()->create(array_merge([
            'work_site_id' => $this->workSite->id,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'work_site_id' => $this->workSite->id,
            'name' => 'Construction Contract A',
            'code' => 'C-001',
            'description' => 'Main construction contract.',
            'is_active' => true,
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_contracts(): void
    {
        $this->makeContract(['name' => 'Contract Alpha', 'code' => 'CA-001']);
        $this->makeContract(['name' => 'Contract Beta', 'code' => 'CB-002']);

        $response = $this->get(route('work-site-contracts.index'));

        $response->assertStatus(200);
        $response->assertSee('Contract Alpha');
        $response->assertSee('Contract Beta');
    }

    public function test_index_filters_by_search(): void
    {
        $this->makeContract(['name' => 'Alpha Contract', 'code' => 'CA-001']);
        $this->makeContract(['name' => 'Beta Contract', 'code' => 'CB-002']);

        $response = $this->get(route('work-site-contracts.index', ['search' => 'Alpha']));

        $response->assertStatus(200);
        $response->assertSee('Alpha Contract');
        $response->assertDontSee('Beta Contract');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('work-site-contracts.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_contract_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('work-site-contracts.store'), $payload);

        $response->assertRedirect(route('work-site-contracts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_site_contracts', [
            'work_site_id' => $this->workSite->id,
            'name' => 'Construction Contract A',
            'code' => 'C-001',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('work-site-contracts.store'), []);

        $response->assertSessionHasErrors(['work_site_id', 'name', 'code']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $this->makeContract(['code' => 'DUP-001']);

        $response = $this->post(route('work-site-contracts.store'), $this->validPayload([
            'code' => 'DUP-001',
        ]));

        $response->assertSessionHasErrors(['code']);
    }

    public function test_store_rejects_non_existent_work_site(): void
    {
        $response = $this->post(route('work-site-contracts.store'), $this->validPayload([
            'work_site_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['work_site_id']);
    }

    public function test_store_rejects_name_exceeding_max_length(): void
    {
        $response = $this->post(route('work-site-contracts.store'), $this->validPayload([
            'name' => str_repeat('A', 201),
        ]));

        $response->assertSessionHasErrors(['name']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200_with_existing_data(): void
    {
        $contract = $this->makeContract(['name' => 'Edit Contract', 'code' => 'EC-001']);

        $response = $this->get(route('work-site-contracts.edit', $contract));

        $response->assertStatus(200);
        $response->assertSee('Edit Contract');
    }

    public function test_update_modifies_contract_and_redirects(): void
    {
        $contract = $this->makeContract(['name' => 'Old Name', 'code' => 'OLD-001']);

        $response = $this->put(route('work-site-contracts.update', $contract), $this->validPayload([
            'name' => 'New Name',
            'code' => 'NEW-001',
        ]));

        $response->assertRedirect(route('work-site-contracts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_site_contracts', [
            'id' => $contract->id,
            'name' => 'New Name',
            'code' => 'NEW-001',
        ]);
    }

    public function test_update_allows_same_code_on_same_record(): void
    {
        $contract = $this->makeContract(['code' => 'SAME-001']);

        $response = $this->put(route('work-site-contracts.update', $contract), $this->validPayload([
            'code' => 'SAME-001',
        ]));

        $response->assertRedirect(route('work-site-contracts.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_update_validates_required_fields(): void
    {
        $contract = $this->makeContract();

        $response = $this->put(route('work-site-contracts.update', $contract), [
            'name' => '',
            'code' => '',
        ]);

        $response->assertSessionHasErrors(['work_site_id', 'name', 'code']);
    }

    public function test_update_rejects_duplicate_code_from_another_record(): void
    {
        $this->makeContract(['code' => 'TAKEN-001']);
        $contract = $this->makeContract(['code' => 'MINE-002']);

        $response = $this->put(route('work-site-contracts.update', $contract), $this->validPayload([
            'code' => 'TAKEN-001',
        ]));

        $response->assertSessionHasErrors(['code']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_contract_and_redirects(): void
    {
        $contract = $this->makeContract();

        $response = $this->delete(route('work-site-contracts.destroy', $contract));

        $response->assertRedirect(route('work-site-contracts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('work_site_contracts', ['id' => $contract->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('work-site-contracts.index'));

        $response->assertRedirect(route('login'));
    }
}
