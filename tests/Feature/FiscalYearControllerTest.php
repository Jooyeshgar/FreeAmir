<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FiscalYearControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_view_edit_and_delete_an_assigned_company_fiscal_year(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $existing = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $existing->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user);

        $this->get(route('fiscal-years.create'))->assertOk()->assertSee($company->name);
        $this->post(route('fiscal-years.store'), ['company_id' => $company->id, 'year' => 1403])
            ->assertSessionHasNoErrors();

        $created = $company->fiscalYears()->where('year', 1403)->firstOrFail();
        $this->assertTrue($created->users()->whereKey($user->id)->exists());
        $this->get(route('fiscal-years.index'))->assertOk();
        $this->get(route('fiscal-years.show', $created))->assertOk();
        $this->get(route('fiscal-years.edit', $created))->assertOk();
        $this->patch(route('fiscal-years.update', $created), ['year' => 1404])
            ->assertRedirect(route('fiscal-years.show', $created));
        $this->assertSame(1404, (int) $created->fresh()->year);
        $this->delete(route('fiscal-years.destroy', $created))
            ->assertRedirect(route('fiscal-years.index'));
        $this->assertDatabaseMissing('fiscal_years', ['id' => $created->id]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_duplicate_year_and_unassigned_company_are_rejected(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $year->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user);
        $this->post(route('fiscal-years.store'), ['company_id' => $company->id, 'year' => 1402])
            ->assertSessionHasErrors('year');
        $this->post(route('fiscal-years.store'), ['company_id' => $other->id, 'year' => 1403])
            ->assertNotFound();
        $this->assertDatabaseMissing('fiscal_years', ['company_id' => $other->id, 'year' => 1403]);
    }

    public function test_user_cannot_access_an_unassigned_fiscal_year(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $assignedCompany = Company::factory()->create(['name' => 'Assigned Business']);
        $assignedYear = FiscalYear::create(['company_id' => $assignedCompany->id, 'year' => 1403]);
        $assignedYear->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user);
        $this->get(route('fiscal-years.index'))->assertOk()
            ->assertSee('Assigned Business')
            ->assertDontSee($company->name);
        $this->get(route('fiscal-years.show', $year))->assertForbidden();
        $this->patch(route('fiscal-years.update', $year), ['year' => 1403])->assertForbidden();
        $this->delete(route('fiscal-years.destroy', $year))->assertForbidden();
        $this->assertDatabaseHas('fiscal_years', ['id' => $year->id, 'year' => 1402]);
    }

    public function test_closed_fiscal_year_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1402, 'closed_at' => now()]);
        $year->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user);

        $this->get(route('fiscal-years.edit', $year))->assertSessionHasErrors('fiscal_year', __('This fiscal year is already closed.'));
        $this->patch(route('fiscal-years.update', $year), ['year' => 1403])->assertSessionHasErrors('fiscal_year', __('This fiscal year is already closed.'));
        $this->delete(route('fiscal-years.destroy', $year))->assertSessionHasErrors('fiscal_year', __('This fiscal year is already closed.'));

        $this->assertDatabaseHas('fiscal_years', ['id' => $year->id, 'year' => 1402]);
    }

    public function test_super_admin_cannot_mutate_a_closed_fiscal_year(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1402, 'closed_at' => now()]);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user);

        $this->patch(route('fiscal-years.update', $year), ['year' => 1403])->assertSessionHasErrors('fiscal_year', __('This fiscal year is already closed.'));
        $this->delete(route('fiscal-years.destroy', $year))->assertSessionHasErrors('fiscal_year', __('This fiscal year is already closed.'));
        $this->assertDatabaseHas('fiscal_years', ['id' => $year->id, 'year' => 1402]);
    }

    public function test_new_fiscal_year_can_be_created_when_an_existing_year_is_closed(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $closed = FiscalYear::create(['company_id' => $company->id, 'year' => 1402, 'closed_at' => now()]);
        $closed->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)->post(route('fiscal-years.store'), ['company_id' => $company->id, 'year' => 1403])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fiscal_years', ['company_id' => $company->id, 'year' => 1403]);
    }
}
