<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PayrollElement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PayrollElementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo(
            Permission::firstOrCreate(['name' => 'payroll-elements.*'])
        );

        $this->actingAs($this->user);
        $this->withCookies(['active-company-id' => $this->companyId]);
    }

    private function makeElement(array $overrides = []): PayrollElement
    {
        return PayrollElement::factory()->create(array_merge([
            'company_id' => $this->companyId,
            'is_system_locked' => false,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Housing Allowance',
            'system_code' => 'HOUSING_ALLOWANCE',
            'category' => 'earning',
            'calc_type' => 'fixed',
            'formula' => null,
            'default_amount' => 2_000_000,
            'is_taxable' => false,
            'is_insurable' => true,
            'show_in_payslip' => true,
            'gl_account_code' => '3210',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // index
    // ----------------------------------------------------------------

    public function test_index_lists_payroll_elements_for_active_company(): void
    {
        $this->makeElement(['title' => 'Housing Allowance']);
        $this->makeElement(['title' => 'Overtime Pay']);

        $response = $this->get(route('payroll-elements.index'));

        $response->assertStatus(200);
        $response->assertSee('Housing Allowance');
        $response->assertSee('Overtime Pay');
    }

    public function test_index_filters_by_category(): void
    {
        $this->makeElement(['title' => 'Bonus', 'category' => 'earning']);
        $this->makeElement(['title' => 'Tax Deduction', 'category' => 'deduction']);

        $response = $this->get(route('payroll-elements.index', ['category' => 'deduction']));

        $response->assertStatus(200);
        $response->assertSee('Tax Deduction');
    }

    public function test_index_filters_by_title(): void
    {
        $this->makeElement(['title' => 'Housing Allowance']);
        $this->makeElement(['title' => 'Food Allowance']);

        $response = $this->get(route('payroll-elements.index', ['title' => 'Housing']));

        $response->assertStatus(200);
        $response->assertSee('Housing Allowance');
    }

    // ----------------------------------------------------------------
    // create / store
    // ----------------------------------------------------------------

    public function test_create_returns_200(): void
    {
        $response = $this->get(route('payroll-elements.create'));

        $response->assertStatus(200);
    }

    public function test_store_creates_a_payroll_element_and_redirects(): void
    {
        $response = $this->post(route('payroll-elements.store'), $this->validPayload());

        $response->assertRedirect(route('payroll-elements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_elements', [
            'company_id' => $this->companyId,
            'title' => 'Housing Allowance',
            'system_code' => 'HOUSING_ALLOWANCE',
            'category' => 'earning',
            'calc_type' => 'fixed',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('payroll-elements.store'), []);

        $response->assertSessionHasErrors(['title', 'system_code', 'category', 'calc_type']);
    }

    public function test_store_rejects_invalid_system_code(): void
    {
        $response = $this->post(route('payroll-elements.store'), $this->validPayload([
            'system_code' => 'INVALID_CODE',
        ]));

        $response->assertSessionHasErrors(['system_code']);
    }

    public function test_store_rejects_invalid_category(): void
    {
        $response = $this->post(route('payroll-elements.store'), $this->validPayload([
            'category' => 'bonus',
        ]));

        $response->assertSessionHasErrors(['category']);
    }

    public function test_store_rejects_invalid_calc_type(): void
    {
        $response = $this->post(route('payroll-elements.store'), $this->validPayload([
            'calc_type' => 'daily',
        ]));

        $response->assertSessionHasErrors(['calc_type']);
    }

    public function test_store_allows_nullable_optional_fields(): void
    {
        $payload = $this->validPayload([
            'formula' => null,
            'default_amount' => null,
            'gl_account_code' => null,
        ]);

        $response = $this->post(route('payroll-elements.store'), $payload);

        $response->assertRedirect(route('payroll-elements.index'));
        $this->assertDatabaseHas('payroll_elements', [
            'company_id' => $this->companyId,
            'default_amount' => null,
            'gl_account_code' => null,
        ]);
    }

    // ----------------------------------------------------------------
    // edit / update
    // ----------------------------------------------------------------

    public function test_edit_returns_200(): void
    {
        $element = $this->makeElement();

        $response = $this->get(route('payroll-elements.edit', $element));

        $response->assertStatus(200);
        $response->assertSee($element->title);
    }

    public function test_update_modifies_payroll_element_and_redirects(): void
    {
        $element = $this->makeElement(['title' => 'Old Title', 'calc_type' => 'fixed']);

        $response = $this->put(
            route('payroll-elements.update', $element),
            $this->validPayload(['title' => 'New Title', 'calc_type' => 'percentage'])
        );

        $response->assertRedirect(route('payroll-elements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_elements', [
            'id' => $element->id,
            'title' => 'New Title',
            'calc_type' => 'percentage',
        ]);
    }

    public function test_update_validates_required_fields(): void
    {
        $element = $this->makeElement();

        $response = $this->put(route('payroll-elements.update', $element), []);

        $response->assertSessionHasErrors(['title', 'system_code', 'category', 'calc_type']);
    }

    // ----------------------------------------------------------------
    // destroy
    // ----------------------------------------------------------------

    public function test_destroy_deletes_payroll_element_and_redirects(): void
    {
        $element = $this->makeElement(['is_system_locked' => false]);

        $response = $this->delete(route('payroll-elements.destroy', $element));

        $response->assertRedirect(route('payroll-elements.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('payroll_elements', ['id' => $element->id]);
    }

    public function test_destroy_refuses_to_delete_system_locked_element(): void
    {
        $element = $this->makeElement(['is_system_locked' => true]);

        $response = $this->delete(route('payroll-elements.destroy', $element));

        $response->assertRedirect(route('payroll-elements.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('payroll_elements', ['id' => $element->id]);
    }

    // ----------------------------------------------------------------
    // guest access
    // ----------------------------------------------------------------

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        auth()->logout();

        $response = $this->get(route('payroll-elements.index'));

        $response->assertRedirect(route('login'));
    }
}
