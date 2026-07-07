<?php

namespace Tests\Feature;

use App\Enums\FiscalYearSection;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
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
}
