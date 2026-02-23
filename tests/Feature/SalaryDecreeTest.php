<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DecreeBenefit;
use App\Models\Employee;
use App\Models\OrgChart;
use App\Models\PayrollElement;
use App\Models\SalaryDecree;
use App\Models\User;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SalaryDecreeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected Employee $employee;

    protected OrgChart $orgChart;

    protected PayrollElement $payrollElement;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'salary-decrees.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);

        $workSite = WorkSite::factory()->create(['company_id' => $this->companyId]);

        $this->orgChart = OrgChart::factory()->create(['company_id' => $this->companyId]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->companyId,
            'work_site_id' => $workSite->id,
        ]);

        $this->payrollElement = PayrollElement::factory()->create([
            'company_id' => $this->companyId,
        ]);
    }

    private function makeDecree(array $overrides = []): SalaryDecree
    {
        return SalaryDecree::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'org_chart_id' => $this->orgChart->id,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'org_chart_id' => $this->orgChart->id,
            'name' => 'Decree-1403-001',
            'start_date' => '2026-01-01',
            'end_date' => null,
            'contract_type' => 'full_time',
            'daily_wage' => '500000',
            'description' => 'Test decree',
            'is_active' => '1',
            'benefits' => [],
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_decrees_for_active_company(): void
    {
        $this->makeDecree(['name' => 'Decree Alpha']);
        $this->makeDecree(['name' => 'Decree Beta']);

        $response = $this->get(route('salary-decrees.index'));

        $response->assertStatus(200);
        $response->assertSee('Decree Alpha');
        $response->assertSee('Decree Beta');
    }

    public function test_index_filters_by_search_name(): void
    {
        $this->makeDecree(['name' => 'Decree Alpha']);
        $this->makeDecree(['name' => 'Decree Beta']);

        $response = $this->get(route('salary-decrees.index', ['search' => 'Alpha']));

        $response->assertStatus(200);
        $response->assertSee('Decree Alpha');
        $response->assertDontSee('Decree Beta');
    }

    public function test_index_does_not_show_decrees_from_other_companies(): void
    {
        $otherCompany = Company::factory()->create();
        $otherWorkSite = WorkSite::factory()->create(['company_id' => $otherCompany->id]);
        $otherOrgChart = OrgChart::factory()->create(['company_id' => $otherCompany->id]);
        $otherEmployee = Employee::factory()->create([
            'company_id' => $otherCompany->id,
            'work_site_id' => $otherWorkSite->id,
        ]);

        SalaryDecree::factory()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'org_chart_id' => $otherOrgChart->id,
            'name' => 'Foreign Decree',
        ]);

        $response = $this->get(route('salary-decrees.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Foreign Decree');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('salary-decrees.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_decree_and_redirects(): void
    {
        $payload = $this->validPayload();

        $response = $this->post(route('salary-decrees.store'), $payload);

        $response->assertRedirect(route('salary-decrees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('salary_decrees', [
            'company_id' => $this->companyId,
            'employee_id' => $this->employee->id,
            'name' => 'Decree-1403-001',
            'start_date' => '2026-01-01',
        ]);
    }

    public function test_store_creates_decree_with_benefits(): void
    {
        $payload = $this->validPayload([
            'benefits' => [
                ['element_id' => $this->payrollElement->id, 'value' => '2000000'],
            ],
        ]);

        $response = $this->post(route('salary-decrees.store'), $payload);

        $response->assertRedirect(route('salary-decrees.index'));

        $decree = SalaryDecree::where('name', 'Decree-1403-001')->first();
        $this->assertNotNull($decree);

        $this->assertDatabaseHas('decree_benefits', [
            'decree_id' => $decree->id,
            'element_id' => $this->payrollElement->id,
            'element_value' => '2000000.00',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('salary-decrees.store'), []);

        $response->assertSessionHasErrors(['employee_id', 'org_chart_id', 'start_date']);
    }

    public function test_store_validates_employee_exists(): void
    {
        $response = $this->post(route('salary-decrees.store'), $this->validPayload([
            'employee_id' => 99999,
        ]));

        $response->assertSessionHasErrors(['employee_id']);
    }

    public function test_store_validates_end_date_after_start_date(): void
    {
        $response = $this->post(route('salary-decrees.store'), $this->validPayload([
            'start_date' => '2026-06-01',
            'end_date' => '2026-01-01',
        ]));

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_store_validates_contract_type_enum(): void
    {
        $response = $this->post(route('salary-decrees.store'), $this->validPayload([
            'contract_type' => 'invalid_type',
        ]));

        $response->assertSessionHasErrors(['contract_type']);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200_with_decree_data(): void
    {
        $decree = $this->makeDecree(['name' => 'Decree-Edit']);

        $response = $this->get(route('salary-decrees.edit', $decree));

        $response->assertStatus(200);
        $response->assertSee('Decree-Edit');
    }

    public function test_update_modifies_decree_and_redirects(): void
    {
        $decree = $this->makeDecree(['name' => 'Old Name']);

        $response = $this->put(
            route('salary-decrees.update', $decree),
            $this->validPayload(['name' => 'New Name'])
        );

        $response->assertRedirect(route('salary-decrees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('salary_decrees', [
            'id' => $decree->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_replaces_benefits(): void
    {
        $decree = $this->makeDecree();
        DecreeBenefit::factory()->create([
            'decree_id' => $decree->id,
            'element_id' => $this->payrollElement->id,
            'element_value' => 1_000_000,
        ]);

        $newElement = PayrollElement::factory()->create(['company_id' => $this->companyId]);

        $response = $this->put(
            route('salary-decrees.update', $decree),
            $this->validPayload([
                'benefits' => [
                    ['element_id' => $newElement->id, 'value' => '3000000'],
                ],
            ])
        );

        $response->assertRedirect(route('salary-decrees.index'));

        $this->assertDatabaseMissing('decree_benefits', [
            'decree_id' => $decree->id,
            'element_id' => $this->payrollElement->id,
        ]);

        $this->assertDatabaseHas('decree_benefits', [
            'decree_id' => $decree->id,
            'element_id' => $newElement->id,
            'element_value' => '3000000.00',
        ]);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_decree_and_redirects(): void
    {
        $decree = $this->makeDecree();

        $response = $this->delete(route('salary-decrees.destroy', $decree));

        $response->assertRedirect(route('salary-decrees.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('salary_decrees', ['id' => $decree->id]);
    }

    public function test_destroy_also_deletes_associated_benefits(): void
    {
        $decree = $this->makeDecree();
        DecreeBenefit::factory()->create([
            'decree_id' => $decree->id,
            'element_id' => $this->payrollElement->id,
            'element_value' => 500_000,
        ]);

        $this->delete(route('salary-decrees.destroy', $decree));

        $this->assertDatabaseMissing('decree_benefits', ['decree_id' => $decree->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('salary-decrees.index'));

        $response->assertRedirect(route('login'));
    }
}
