<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Helpers\SeederHelper;
use Tests\TestCase;

class ProductGroupOrServiceGroupDeletionTest extends TestCase
{
    use RefreshDatabase;
    use SeederHelper;

    private User $user;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create(['fiscal_year' => 1405]);
        $this->companyId = $company->id;

        config([
            'active-company-id' => $this->companyId,
            'active-company-fiscal-year' => $company->fiscal_year,
        ]);

        $this->withCookies(['active-company-id' => (string) $this->companyId]);

        $this->importSubjects($this->companyId);
        $this->importConfigs($this->companyId);

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'product-groups.index']),
            Permission::firstOrCreate(['name' => 'product-groups.show']),
            Permission::firstOrCreate(['name' => 'product-groups.destroy']),
            Permission::firstOrCreate(['name' => 'service-groups.index']),
            Permission::firstOrCreate(['name' => 'service-groups.show']),
            Permission::firstOrCreate(['name' => 'service-groups.destroy']),
        ]);
    }

    public function test_product_group_with_products_cannot_be_deleted_and_shows_disabled_button(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Widgets']);
        Product::factory()->withGroup($group)->withSubjects()->create(['company_id' => $this->companyId]);
        $message = __('Cannot delete product group because it has products.');

        $this->actingAs($this->user)->delete(route('product-groups.destroy', $group))->assertSessionHasErrors(['product_group' => $message]);

        $this->assertDatabaseHas('product_groups', ['id' => $group->id]);

        $response = $this->actingAs($this->user)->get(route('product-groups.index'));

        $response->assertOk();
        $response->assertSee($message);
        $response->assertSee('disabled', false);
        $response->assertDontSee('action="'.route('product-groups.destroy', $group).'"', false);
    }

    public function test_product_group_with_subject_children_cannot_be_deleted(): void
    {
        $group = ProductGroup::factory()->withSubjects()->create(['company_id' => $this->companyId, 'name' => 'Nested']);
        Subject::factory()->withParent($group->incomeSubject)->create(['company_id' => $this->companyId, 'name' => 'Manual child']);
        $message = __('Cannot delete product group because one of its subjects has children.');

        $this->actingAs($this->user)->delete(route('product-groups.destroy', $group))->assertSessionHasErrors(['product_group' => $message]);

        $this->assertDatabaseHas('product_groups', ['id' => $group->id]);
    }

    public function test_service_group_with_services_cannot_be_deleted_and_show_page_disables_button(): void
    {
        $group = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Consulting']);
        Service::factory()->withGroup($group)->withSubject()->create(['company_id' => $this->companyId]);
        $message = __('Cannot delete service group because it has services.');

        $this->actingAs($this->user)->delete(route('service-groups.destroy', $group))->assertSessionHasErrors(['service_group' => $message]);

        $response = $this->actingAs($this->user)->get(route('service-groups.show', $group));

        $response->assertOk();
        $response->assertSee($message);
        $response->assertSee('disabled', false);
        $response->assertDontSee('action="'.route('service-groups.destroy', $group).'"', false);
    }

    public function test_empty_service_group_can_be_deleted_with_its_subjects(): void
    {
        $group = ServiceGroup::factory()->withSubject()->create(['company_id' => $this->companyId, 'name' => 'Empty']);
        $subjectIds = collect([
            $group->subject_id,
            $group->cogs_subject_id,
            $group->sales_returns_subject_id,
        ])->filter();

        $this->actingAs($this->user)->delete(route('service-groups.destroy', $group))->assertRedirect(route('service-groups.index'))->assertSessionHas('success', __('Service group deleted successfully.'));
        $this->assertDatabaseMissing('service_groups', ['id' => $group->id]);

        foreach ($subjectIds as $subjectId) {
            $this->assertDatabaseMissing('subjects', ['id' => $subjectId]);
        }
    }
}
