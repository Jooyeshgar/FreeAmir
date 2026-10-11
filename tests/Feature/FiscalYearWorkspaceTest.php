<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FiscalYearWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_gets_the_matching_fiscal_year_list_in_each_interface(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Super-Admin']));
        $year = FiscalYear::factory()->create();

        $this->actingAs($admin)->withSession(['interface_mode' => 'management'])
            ->get(route('fiscal-years.index'))->assertOk()
            ->assertViewIs('fiscal-years.index')
            ->assertSee('id="management-drawer"', false)
            ->assertSee('rounded-2xl border border-slate-200/80', false)
            ->assertSee($year->company->name);

        $this->withSession(['interface_mode' => 'workspace'])
            ->get(route('fiscal-years.index'))->assertOk()
            ->assertViewIs('fiscal-years.workspace-index')
            ->assertSee('id="workspace-drawer"', false)
            ->assertSee('card bg-base-100 shadow-xl', false)
            ->assertDontSee('id="management-drawer"', false)
            ->assertSee(route('fiscal-years.create'), false);
    }

    public function test_fiscal_year_menu_and_list_work_with_only_the_index_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'fiscal-years.index']));
        $company = Company::factory()->create(['name' => 'Assigned Business']);
        $assigned = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $assigned->users()->attach($user);
        $other = FiscalYear::create(['company_id' => $company->id, 'year' => 1404]);

        $this->actingAs($user)->withSession(['interface_mode' => 'management'])
            ->get(route('fiscal-years.index'))->assertOk()
            ->assertViewIs('fiscal-years.workspace-index')
            ->assertSee('href="'.route('fiscal-years.index').'"', false)
            ->assertSee('Assigned Business')
            ->assertSee(localizeNumber($assigned->year))
            ->assertDontSee(localizeNumber($other->year))
            ->assertDontSee('href="'.route('companies.index').'"', false)
            ->assertDontSee(route('fiscal-years.create'), false)
            ->assertDontSee(route('fiscal-years.edit', $assigned), false)
            ->assertDontSee(route('fiscal-years.destroy', $assigned), false);
    }

    public function test_workspace_menu_hides_fiscal_years_without_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'companies.index']));
        FiscalYear::factory()->create();

        $this->actingAs($user)->get(route('companies.index'))->assertOk()
            ->assertDontSee('href="'.route('fiscal-years.index').'"', false);
        $this->get(route('fiscal-years.index'))->assertForbidden();
    }

    public function test_each_fiscal_year_displays_its_document_count_regardless_of_the_active_year(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::create(['name' => 'Super-Admin']));
        $first = FiscalYear::factory()->create(['year' => 1403]);
        $second = FiscalYear::create(['company_id' => $first->company_id, 'year' => 1404]);
        $second->users()->attach($admin);
        Document::factory()->count(2)->create(['fiscal_year_id' => $first->id]);
        Document::factory()->count(3)->create(['fiscal_year_id' => $second->id]);

        $this->actingAs($admin)->withCookie('active-fiscal-year-id', (string) $first->id);
        foreach (['management', 'workspace'] as $mode) {
            $this->withSession(['interface_mode' => $mode])->get(route('fiscal-years.index'))
                ->assertOk()->assertViewHas('years', function ($years) use ($first, $second) {
                    return $years->firstWhere('id', $first->id)->documents_count === 2
                        && $years->firstWhere('id', $second->id)->documents_count === 3;
                });
        }
    }

    public function test_workspace_filters_and_pagination_preserve_the_selected_filters(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::create(['name' => 'fiscal-years.index']));
        $company = Company::factory()->create(['name' => 'Filtered Business']);
        foreach (range(1380, 1392) as $number) {
            $year = FiscalYear::create(['company_id' => $company->id, 'year' => $number]);
            $year->users()->attach($user);
        }
        $closed = FiscalYear::create(['company_id' => $company->id, 'year' => 1393, 'closed_at' => now()]);
        $closed->users()->attach($user);
        $other = FiscalYear::factory()->create();
        $other->users()->attach($user);

        $this->actingAs($user)->get(route('fiscal-years.index', ['search' => 'Filtered', 'status' => 'open']))
            ->assertOk()->assertViewHas('years', fn ($years) => $years->total() === 13 && $years->count() === 12
                && ! $years->contains('id', $closed->id) && ! $years->contains('id', $other->id))
            ->assertSee('search=Filtered', false)->assertSee('status=open', false)
            ->assertDontSee(localizeNumber($closed->year));
        $this->get(route('fiscal-years.index', ['search' => 'Filtered', 'status' => 'open', 'page' => 2]))
            ->assertOk()->assertViewHas('years', fn ($years) => $years->count() === 1);
        $this->get(route('fiscal-years.index', ['status' => 'closed']))->assertOk()
            ->assertViewHas('years', fn ($years) => $years->total() === 1 && $years->first()->id === $closed->id);
    }
}
