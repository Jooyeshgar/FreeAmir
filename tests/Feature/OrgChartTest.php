<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OrgChart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OrgChartTest extends TestCase
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

        foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
            Permission::firstOrCreate(['name' => "org-charts.{$action}"]);
        }
        $this->user->givePermissionTo(
            Permission::where('name', 'like', 'org-charts.%')->get()
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makeNode(array $overrides = []): OrgChart
    {
        return OrgChart::factory()->create(array_merge([
            'company_id' => $this->companyId,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Chief Executive Officer',
            'parent_id' => null,
            'description' => 'Top-level position.',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_org_chart_nodes_for_active_company(): void
    {
        $this->makeNode(['title' => 'CEO']);
        $this->makeNode(['title' => 'CTO']);

        $response = $this->get(route('org-charts.index'));

        $response->assertStatus(200);
        $response->assertSee('CEO');
        $response->assertSee('CTO');
    }

    public function test_index_filters_by_search_title(): void
    {
        $this->makeNode(['title' => 'CEO']);
        $this->makeNode(['title' => 'Finance Manager']);

        $response = $this->get(route('org-charts.index', ['search' => 'Finance']));

        $response->assertStatus(200);
        $response->assertSee('Finance Manager');
        $response->assertDontSee('CEO');
    }

    public function test_index_does_not_show_nodes_from_other_companies(): void
    {
        $otherCompany = Company::factory()->create();
        OrgChart::factory()->create([
            'company_id' => $otherCompany->id,
            'title' => 'Foreign Node',
        ]);

        $response = $this->get(route('org-charts.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Foreign Node');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('org-charts.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_root_node_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('org-charts.store'), $payload);

        $response->assertRedirect(route('org-charts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('org_charts', [
            'company_id' => $this->companyId,
            'title' => 'Chief Executive Officer',
            'parent_id' => null,
        ]);
    }

    public function test_store_creates_a_child_node_with_parent(): void
    {
        $parent = $this->makeNode(['title' => 'CEO']);

        $payload = $this->validPayload([
            'title' => 'CTO',
            'parent_id' => $parent->id,
        ]);

        $response = $this->post(route('org-charts.store'), $payload);

        $response->assertRedirect(route('org-charts.index'));

        $this->assertDatabaseHas('org_charts', [
            'company_id' => $this->companyId,
            'title' => 'CTO',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('org-charts.store'), []);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_store_rejects_title_exceeding_max_length(): void
    {
        $response = $this->post(route('org-charts.store'), $this->validPayload([
            'title' => str_repeat('A', 201),
        ]));

        $response->assertSessionHasErrors(['title']);
    }

    public function test_store_rejects_non_existent_parent_id(): void
    {
        $response = $this->post(route('org-charts.store'), $this->validPayload([
            'parent_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['parent_id']);
    }

    // ----------------------------------------------------------------
    // show
    // ----------------------------------------------------------------

    public function test_show_returns_200_with_node_details(): void
    {
        $node = $this->makeNode(['title' => 'CEO']);

        $response = $this->get(route('org-charts.show', $node));

        $response->assertStatus(200);
        $response->assertSee('CEO');
    }

    public function test_show_displays_children(): void
    {
        $parent = $this->makeNode(['title' => 'CEO']);
        $this->makeNode(['title' => 'CTO', 'parent_id' => $parent->id]);

        $response = $this->get(route('org-charts.show', $parent));

        $response->assertStatus(200);
        $response->assertSee('CTO');
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200_with_existing_data(): void
    {
        $node = $this->makeNode(['title' => 'CEO']);

        $response = $this->get(route('org-charts.edit', $node));

        $response->assertStatus(200);
        $response->assertSee('CEO');
    }

    public function test_update_modifies_node_and_redirects(): void
    {
        $node = $this->makeNode(['title' => 'CEO']);

        $response = $this->put(route('org-charts.update', $node), $this->validPayload([
            'title' => 'Managing Director',
        ]));

        $response->assertRedirect(route('org-charts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('org_charts', [
            'id' => $node->id,
            'title' => 'Managing Director',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $node = $this->makeNode();

        $response = $this->put(route('org-charts.update', $node), ['title' => '']);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_update_rejects_non_existent_parent_id(): void
    {
        $node = $this->makeNode();

        $response = $this->put(route('org-charts.update', $node), $this->validPayload([
            'parent_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['parent_id']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_node_and_redirects(): void
    {
        $node = $this->makeNode();

        $response = $this->delete(route('org-charts.destroy', $node));

        $response->assertRedirect(route('org-charts.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('org_charts', ['id' => $node->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('org-charts.index'));

        $response->assertRedirect(route('login'));
    }
}
