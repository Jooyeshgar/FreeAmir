<?php

namespace Tests\Feature;

use App\Http\Controllers\CompanyController;
use App\Http\Middleware\CheckPermission;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyControllerFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_assigned_fiscal_years_by_company_name(): void
    {
        $user = User::factory()->create();
        $assignedCompany = Company::factory()->create(['name' => 'Accessible Source']);
        $otherCompany = Company::factory()->create(['name' => 'Inaccessible Source']);
        $assignedYear = FiscalYear::create(['company_id' => $assignedCompany->id, 'year' => 1402]);
        $otherYear = FiscalYear::create(['company_id' => $otherCompany->id, 'year' => 1403]);
        $assignedYear->users()->attach($user);
        $otherYear->users()->attach(User::factory()->create());

        $this->actingAs($user);
        $request = Request::create(route('companies.index'), 'GET', ['search' => 'Source']);
        $request->setLaravelSession(app('session')->driver());

        $fiscalYears = app(CompanyController::class)->index($request)->getData()['fiscalYears'];

        $this->assertSame([$assignedYear->id], $fiscalYears->pluck('id')->all());
        $this->assertSame('Accessible Source', $fiscalYears->first()->name);
    }

    public function test_deleting_one_fiscal_year_keeps_the_company_and_other_year(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $firstYear = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $secondYear = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $firstYear->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)
            ->delete(route('companies.destroy', $firstYear))
            ->assertRedirect(route('companies.index'));

        $this->assertDatabaseMissing('fiscal_years', ['id' => $firstYear->id]);
        $this->assertDatabaseHas('fiscal_years', ['id' => $secondYear->id]);
        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_show_groups_fiscal_years_by_company_id(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'access-super-admin-panel']));
        $company = Company::factory()->create(['name' => 'Shared Name']);
        $otherCompany = Company::factory()->create(['name' => 'Shared Name']);
        FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        FiscalYear::create(['company_id' => $otherCompany->id, 'year' => 1404]);

        $this->actingAs($user);
        $request = Request::create(route('companies.show', $company));
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $user);

        $data = app(CompanyController::class)->show($request, $company)->getData();

        $this->assertSame(2, $data['metrics']['fiscalYears']);
        $this->assertSame([1403, 1402], $data['fiscalYears']->pluck('year')->all());
    }

    public function test_registered_user_creation_persists_company_and_assigned_fiscal_year(): void
    {
        $user = User::factory()->create();

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)
            ->post(route('registered-user.company.store'), [
                'name' => 'New Business',
                'fiscal_year' => 1405,
                'phone_number' => '09123456789',
            ])
            ->assertRedirect(route('home'));

        $company = Company::where('name', 'New Business')->firstOrFail();
        $fiscalYear = $company->fiscalYears()->where('year', 1405)->firstOrFail();
        $this->assertTrue($fiscalYear->users()->whereKey($user->id)->exists());
    }

    public function test_edit_and_update_company_details_leave_fiscal_years_unchanged(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Original']);
        $fiscalYear = FiscalYear::create(['company_id' => $company->id, 'year' => 1402]);
        $otherFiscalYear = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $fiscalYear->users()->attach($user);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)
            ->get(route('companies.edit', $company))
            ->assertOk()
            ->assertSee('Original')
            ->assertDontSee('name="fiscal_year"', false);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)
            ->put(route('companies.update', $company), [
                'name' => 'Updated',
                'fiscal_year' => 1404,
            ])
            ->assertRedirect(route('companies.index'));

        $this->assertSame('Updated', $company->fresh()->name);
        $this->assertSame(1402, (int) $fiscalYear->fresh()->year);
        $this->assertSame(1403, (int) $otherFiscalYear->fresh()->year);
    }

    public function test_company_edit_requires_access_to_one_of_its_fiscal_years(): void
    {
        $user = User::factory()->create();
        $accessibleCompany = Company::factory()->create();
        $inaccessibleCompany = Company::factory()->create();
        FiscalYear::create(['company_id' => $accessibleCompany->id, 'year' => 1402])->users()->attach($user);
        FiscalYear::create(['company_id' => $inaccessibleCompany->id, 'year' => 1403]);

        $this->withoutMiddleware(CheckPermission::class)->actingAs($user)
            ->get(route('companies.edit', $inaccessibleCompany))->assertForbidden();
        $this->put(route('companies.update', $inaccessibleCompany), ['name' => 'Changed'])->assertForbidden();
        $this->assertNotSame('Changed', $inaccessibleCompany->fresh()->name);
    }
}
