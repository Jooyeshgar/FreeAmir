<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
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

    private int $fiscalYearId;

    protected function setUp(): void
    {
        parent::setUp();

        $fiscalYear = FiscalYear::factory()->create(['year' => 1405]);
        $this->fiscalYearId = $fiscalYear->id;

        config([
            'active-fiscal-year-id' => $this->fiscalYearId,
        ]);

        $this->withCookies(['active-fiscal-year-id' => (string) $this->fiscalYearId]);

        $this->importSubjects($this->fiscalYearId);
        $this->importConfigs($this->fiscalYearId);

        $this->user = User::factory()->create();
        $fiscalYear->users()->attach($this->user);

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
        $group = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Widgets']);
        Product::factory()->withGroup($group)->withSubjects()->create(['fiscal_year_id' => $this->fiscalYearId]);
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
        $group = ProductGroup::factory()->withSubjects()->create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Nested']);
        Subject::factory()->withParent($group->incomeSubject)->create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Manual child']);
        $message = __('Cannot delete product group because one of its subjects has children.');

        $this->actingAs($this->user)->delete(route('product-groups.destroy', $group))->assertSessionHasErrors(['product_group' => $message]);

        $this->assertDatabaseHas('product_groups', ['id' => $group->id]);
    }

    public function test_service_group_with_services_cannot_be_deleted_and_show_page_disables_button(): void
    {
        $group = ServiceGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Consulting']);
        Service::factory()->withGroup($group)->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId]);
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
        $group = ServiceGroup::factory()->withSubject()->create(['fiscal_year_id' => $this->fiscalYearId, 'name' => 'Empty']);
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
