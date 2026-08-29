<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $accessibleCompany;

    private Company $inaccessibleCompany;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->accessibleCompany = Company::factory()->create(['name' => 'Accessible Source']);
        $this->inaccessibleCompany = Company::factory()->create(['name' => 'Inaccessible Source']);

        $this->accessibleCompany->users()->syncWithoutDetaching([$this->user->id]);
        $this->inaccessibleCompany->users()->detach($this->user->id);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'companies.create']),
            Permission::firstOrCreate(['name' => 'companies.store']),
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => (string) $this->accessibleCompany->id]);
    }

    public function test_create_form_lists_only_companies_accessible_to_user(): void
    {
        $response = $this->get(route('companies.create'));

        $response->assertOk();
        $response->assertSee('Accessible Source');
        $response->assertDontSee('Inaccessible Source');
    }

    public function test_super_admin_without_a_source_company_can_create_their_first_company(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));

        $form = $this->actingAs($superAdmin)->get(route('companies.create'));

        $form->assertOk()->assertDontSee('id="previousYears"', false)->assertDontSee('name="source_year_id"', false);

        $response = $this->post(route('companies.store'), [
            'name' => 'First Company',
            'fiscal_year' => 1405,
            'currency' => 'Rial',
        ]);

        $response->assertRedirect(route('companies.index'));

        $company = Company::where('name', 'First Company')->firstOrFail();
        $this->assertTrue($company->users()->whereKey($superAdmin->id)->exists());
    }

    public function test_company_index_shows_create_button_to_super_admin_without_companies(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));

        $response = $this->actingAs($superAdmin)->withSession(['interface_mode' => 'management'])->get(route('companies.index'));
        $response->assertOk()->assertSee('data-testid="create-first-company"', false);
    }

    public function test_company_index_hides_first_company_button_after_super_admin_has_a_company(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'Super-Admin']));
        $this->accessibleCompany->users()->attach($superAdmin);

        $response = $this->actingAs($superAdmin)->withSession(['interface_mode' => 'management'])->get(route('companies.index'));
        $response->assertOk()->assertDontSee('data-testid="create-first-company"', false);
    }

    public function test_store_rejects_inaccessible_source_company(): void
    {
        $companyCount = Company::count();

        $response = $this->post(route('companies.store'), [
            'name' => 'Unauthorized Copy',
            'fiscal_year' => 1405,
            'source_year_id' => $this->inaccessibleCompany->id,
            'tables_to_copy' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertSessionHasErrors('source_year_id');
        $this->assertSame($companyCount, Company::count());
        $this->assertDatabaseMissing('companies', ['name' => 'Unauthorized Copy']);
    }

    public function test_store_accepts_accessible_source_company_during_validation(): void
    {
        $response = $this->post(route('companies.store'), [
            'fiscal_year' => 1405,
            'source_year_id' => $this->accessibleCompany->id,
            'tables_to_copy' => [FiscalYearSection::SUBJECTS->value],
        ]);

        $response->assertSessionHasErrors('name');
        $response->assertSessionDoesntHaveErrors('source_year_id');
    }

    public function test_store_copies_bank_accounts_with_same_iban_into_new_company(): void
    {
        config(['active-company-id' => $this->accessibleCompany->id]);

        $bank = Bank::create([
            'name' => 'Source Bank',
            'company_id' => $this->accessibleCompany->id,
        ]);

        $bankRoot = Subject::create([
            'code' => '010',
            'name' => 'Banks',
            'type' => 3,
            'company_id' => $this->accessibleCompany->id,
            'parent_id' => null,
        ]);

        $accountSubject = Subject::create([
            'code' => '010001',
            'name' => 'Main Account',
            'type' => 3,
            'company_id' => $this->accessibleCompany->id,
            'parent_id' => $bankRoot->id,
        ]);

        $sourceAccount = new BankAccount;
        $sourceAccount->forceFill([
            'name' => 'Main Account',
            'number' => '123456789',
            'type' => 1,
            'owner' => 'Source Owner',
            'bank_id' => $bank->id,
            'company_id' => $this->accessibleCompany->id,
            'subject_id' => $accountSubject->id,
            'iban' => 'IR163212724891703088374062',
        ])->saveQuietly();

        $accountSubject->subjectable()->associate($sourceAccount);
        $accountSubject->save();

        $response = $this->post(route('companies.store'), [
            'name' => 'Copied Company',
            'fiscal_year' => 1405,
            'source_year_id' => $this->accessibleCompany->id,
            'tables_to_copy' => [
                FiscalYearSection::SUBJECTS->value,
                FiscalYearSection::BANKS->value,
            ],
        ]);

        $response->assertRedirect(route('companies.index'));

        $newCompany = Company::where('name', 'Copied Company')->firstOrFail();

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $this->accessibleCompany->id,
            'iban' => 'IR163212724891703088374062',
        ]);

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $newCompany->id,
            'iban' => 'IR163212724891703088374062',
        ]);
    }
}
