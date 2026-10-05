<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $first = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1403]);
        $second = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1404]);
        $user = User::factory()->create();
        $first->users()->attach($user);
        DB::table('company_user')->insert(['company_id' => $second->id, 'user_id' => $user->id]);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'api.access']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'hr.employees.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.create']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'change-company']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'users.create']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'users.store']));
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $first->fiscalYear->id]);

        $this->assertSame($first->fiscalYear->company_identity_id, $second->fiscalYear->company_identity_id);
        $this->get(route('companies.create'))->assertOk()->assertSee('Shared Business - 1403')->assertDontSee('Shared Business - 1404');
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
        $this->assertSame($first->fiscalYear->company_identity_id, getActiveCompany());
        $this->assertSame($first->fiscalYear->id, getActiveFiscalYear());
        $this->assertSame($first->id, getActiveLegacyCompany());

        auth('web')->logout();
        $token = $user->createToken('test', ['api.access', 'companies.index', 'hr.employees.index'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $this->getJson(route('api.companies.index'), $headers)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_id', $first->fiscalYear->company_identity_id)
            ->assertJsonPath('data.0.fiscal_year_id', $first->fiscalYear->id);
        $this->getJson(route('api.employees.index', $first), $headers)->assertOk();
        $this->getJson(route('api.employees.index', $second), $headers)->assertForbidden();

        $second->fiscalYear->users()->syncWithoutDetaching($user);
        $this->actingAs($user)->get(route('change-company', $second))->assertRedirect(route('home'));
        $this->assertSame($second->fiscalYear->company_identity_id, getActiveCompany());
        $this->assertSame($second->fiscalYear->id, getActiveFiscalYear());
        $this->assertSame($second->id, getActiveLegacyCompany());
    }

    public function test_revoked_active_year_is_rejected_and_does_not_expose_its_documents(): void
    {
        $allowed = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1403]);
        $revoked = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1404]);
        $user = User::factory()->create();
        $allowed->fiscalYear->users()->syncWithoutDetaching($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'documents.index']));
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.index']));
        Document::factory()->create(['company_id' => $revoked->id]);
        $this->actingAs($user)->withCookies(['active-fiscal-year-id' => (string) $revoked->fiscalYear->id]);

        $this->get(route('documents.index'))->assertForbidden()
            ->assertCookieExpired('active-fiscal-year-id');
        $this->get(route('companies.index'))->assertForbidden();

        $this->withCookies(['active-fiscal-year-id' => (string) $allowed->fiscalYear->id]);
        $this->get(route('documents.index'))->assertOk();
        $this->get(route('companies.index'))->assertOk();
    }

    public function test_legacy_assignment_changes_keep_year_grants_in_sync(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $user->companies()->attach($company);
        $this->assertTrue($user->canAccessFiscalYear($company));

        $user->companies()->detach($company);
        $this->assertFalse($user->canAccessFiscalYear($company));
    }

    public function test_company_cookie_selects_only_an_accessible_fiscal_year(): void
    {
        $allowed = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1403]);
        $denied = Company::factory()->create(['name' => 'Shared Business', 'fiscal_year' => 1404]);
        $user = User::factory()->create();
        $allowed->fiscalYear->users()->syncWithoutDetaching($user);
        $user->givePermissionTo(Permission::firstOrCreate(['name' => 'companies.index']));

        $this->actingAs($user)->withCookies([
            'active-company-id' => (string) $allowed->fiscalYear->company_identity_id,
        ])->get(route('companies.index'))->assertOk();

        $this->assertSame($allowed->fiscalYear->id, getActiveFiscalYear());
        $this->assertNotSame($denied->fiscalYear->id, getActiveFiscalYear());
    }
}
