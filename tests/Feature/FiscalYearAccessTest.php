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

class FiscalYearAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_one_year_grant_does_not_allow_another_year_of_the_same_company(): void
    {
        $company = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared Business']);
        $first = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $second = FiscalYear::create(['company_id' => $company->id, 'year' => 1404]);
        $user = User::factory()->create();
        $company->users()->attach($user);
        $first->users()->attach($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'hr.employees.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.create']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'change-company']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'users.create']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'users.store']));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $first->id]);

        $this->assertSame($first->company_id, $second->company_id);
        $this->get(route('companies.create'))->assertOk()
            ->assertViewHas('previousYears', fn ($years) => $years->pluck('id')->all() === [$first->id]);
        $this->get(route('companies.index'))->assertOk()
            ->assertViewHas('companies', fn ($companies) => $companies->pluck('id')->all() === [$first->id]);
        $this->get(route('users.create'))->assertOk()
            ->assertViewHas('companies', fn ($companies) => $companies->pluck('id')->all() === [$first->id]);
        $role = Role::firstOrCreate(['name' => 'Year Operator']);
        $this->post(route('users.store'), [
            'name' => 'Unauthorized Operator',
            'email' => 'unauthorized@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => [$role->name],
            'company' => [$second->id],
        ])->assertSessionHasErrors('company');
        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
        $this->get(route('change-company', $second))->assertForbidden();
        $this->assertSame($company->id, getActiveCompany());
        $this->assertSame($first->id, getActiveFiscalYear());

        auth('web')->logout();
        $token = $user->createToken('test', ['api.access', 'companies.index', 'hr.employees.index'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson(route('api.companies.index'), $headers)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_id', $company->id)
            ->assertJsonPath('data.0.fiscal_year_id', $first->id);
        $this->getJson(route('api.employees.index', $first), $headers)->assertOk();
        $this->getJson(route('api.employees.index', $second), $headers)->assertForbidden();

        $second->users()->syncWithoutDetaching($user);
        $this->actingAs($user)->get(route('change-company', $second))->assertRedirect(route('home'));
        $this->assertSame($company->id, getActiveCompany());
        $this->assertSame($second->id, getActiveFiscalYear());
    }

    public function test_revoked_active_year_is_rejected_and_does_not_expose_its_documents(): void
    {
        $company = Company::factory()->withoutFiscalYear()->create(['name' => 'Shared Business']);
        $allowed = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $revoked = FiscalYear::create(['company_id' => $company->id, 'year' => 1404]);
        $user = User::factory()->create();
        $company->users()->syncWithoutDetaching($user);
        $allowed->users()->syncWithoutDetaching($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'documents.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.index']));
        config(['active-company-id' => $company->id, 'active-fiscal-year-id' => $revoked->id]);
        Document::factory()->create(['company_id' => $company->id, 'fiscal_year_id' => $revoked->id]);
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $revoked->id]);

        $this->get(route('documents.index'))->assertForbidden()
            ->assertCookieExpired('active-fiscal-year-id');
        $this->get(route('companies.index'))->assertForbidden();

        $this->withCookies(['active-fiscal-year-id' => (string) $allowed->id]);
        $this->get(route('documents.index'))->assertOk();
        $this->get(route('companies.index'))->assertOk();
    }

    public function test_fiscal_year_grants_are_independent_of_company_access(): void
    {
        $company = Company::factory()->withoutFiscalYear()->create();
        $year = FiscalYear::create(['company_id' => $company->id, 'year' => 1403]);
        $user = User::factory()->create();

        $user->companies()->attach($company);
        $this->assertFalse($user->canAccessFiscalYear($year));

        $user->fiscalYears()->attach($year);
        $this->assertTrue($user->canAccessFiscalYear($year));

        $user->fiscalYears()->detach($year);
        $this->assertFalse($user->canAccessFiscalYear($year));
    }
}
