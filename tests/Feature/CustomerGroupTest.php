<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Cookie;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerGroupTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);

        $companyId = Company::withoutGlobalScopes()->orderBy('id')->value('id') ?? 1;
        Cache::forever('active_company_id', $companyId);
        Cookie::queue('active-company-id', (string) $companyId);
        $_COOKIE['active-company-id'] = (string) $companyId;

        $this->company = Company::find($companyId);
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'customer-groups.index']),
            Permission::firstOrCreate(['name' => 'customer-groups.create']),
            Permission::firstOrCreate(['name' => 'customer-groups.store']),
            Permission::firstOrCreate(['name' => 'customer-groups.show']),
            Permission::firstOrCreate(['name' => 'customer-groups.edit']),
            Permission::firstOrCreate(['name' => 'customer-groups.update']),
            Permission::firstOrCreate(['name' => 'customer-groups.destroy']),
        ]);

        $this->actingAs($this->user);
    }

    public function test_it_displays_customer_group_index_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customer-groups.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customerGroups.index');
        $response->assertViewHas('customerGroups');
    }

    public function test_it_displays_customer_group_create_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customer-groups.create'));

        $response->assertStatus(200);
        $response->assertViewIs('customerGroups.create');
        $response->assertViewHas('subjects');
    }

    public function test_it_can_create_a_customer_group_with_valid_data()
    {
        $groupData = [
            'name' => 'Wholesale Customers',
            'description' => 'Customers who buy in bulk',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHas('success', __('Customer group created successfully.'));

        $this->assertDatabaseHas('customer_groups', [
            'name' => 'Wholesale Customers',
            'description' => 'Customers who buy in bulk',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_it_can_create_a_customer_group_with_minimal_data()
    {
        $groupData = [
            'name' => 'Retail Customers',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_groups', [
            'name' => 'Retail Customers',
        ]);
    }

    public function test_it_validates_required_name_field()
    {
        $groupData = [
            'description' => 'Test description',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_validates_name_max_length()
    {
        $groupData = [
            'name' => str_repeat('a', 21), // 21 characters (max is 20)
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_validates_description_max_length()
    {
        $groupData = [
            'name' => 'Test Group',
            'description' => str_repeat('a', 151), // 151 characters (max is 150)
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('description');
    }

    public function test_it_creates_subject_for_customer_group_on_creation()
    {
        $groupData = [
            'name' => 'Premium Customers',
            'description' => 'High value customers',  // Fixed: removed hyphen
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customer-groups.index'));

        $customerGroup = CustomerGroup::where('name', 'Premium Customers')->first();

        $this->assertNotNull($customerGroup);
        $this->assertNotNull($customerGroup->subject);
        $this->assertEquals('Premium Customers', $customerGroup->subject->name);
        $this->assertNotNull($customerGroup->subject_id);
        $this->assertEquals($customerGroup->subject_id, $customerGroup->subject->id);
    }

    public function test_it_displays_customer_group_edit_page()
    {
        $customerGroup = CustomerGroup::create([
            'name' => 'Test Group',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customer-groups.edit', $customerGroup));

        $response->assertStatus(200);
        $response->assertViewIs('customerGroups.edit');
        $response->assertViewHas('customerGroup');
        $response->assertViewHas('subjects');
    }

    public function test_it_can_update_a_customer_group()
    {
        $customerGroup = CustomerGroup::create([
            'name' => 'Old Name',
            'description' => 'Old description',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('customer-groups.update', $customerGroup), $updateData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHas('success', __('Customer group updated successfully.'));

        $this->assertDatabaseHas('customer_groups', [
            'id' => $customerGroup->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);
    }

    public function test_it_updates_subject_when_customer_group_name_is_updated()
    {
        $customerGroup = CustomerGroup::create([
            'name' => 'Original Name',
            'description' => 'Test description',
        ]);

        $originalSubjectId = $customerGroup->subject_id;

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Test description',
        ];

        $response = $this->actingAs($this->user)
            ->put(route('customer-groups.update', $customerGroup), $updateData);

        $customerGroup->refresh();

        $this->assertEquals('Updated Name', $customerGroup->subject->name);
        $this->assertEquals($originalSubjectId, $customerGroup->subject_id);
    }

    public function test_it_can_delete_a_customer_group()
    {
        $customerGroup = CustomerGroup::create([
            'name' => 'Test Group',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('customer-groups.destroy', $customerGroup));

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHas('success', __('Customer group deleted successfully.'));

        $this->assertDatabaseMissing('customer_groups', [
            'id' => $customerGroup->id,
        ]);
    }

    public function test_it_sets_company_id_from_session_on_creation()
    {
        // This test verifies that company_id is automatically set from session
        // during customer group creation
        $groupData = [
            'name' => 'Auto Company Group',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));

        // Should use the company_id from session (set in setUp)
        $this->assertDatabaseHas('customer_groups', [
            'name' => 'Auto Company Group',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_it_accepts_nullable_description()
    {
        $groupData = [
            'name' => 'Test Group',
            'description' => null,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customer_groups', [
            'name' => 'Test Group',
            'description' => null,
        ]);
    }

    public function test_it_validates_name_format_with_regex()
    {
        $groupData = [
            'name' => 'Test@Group!', // Contains special characters not allowed
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_accepts_valid_name_format()
    {
        $groupData = [
            'name' => 'Test Group 123', // Valid format with letters, spaces, and numbers
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_customer_group_has_relationship_with_customers()
    {
        $customerGroup = CustomerGroup::create([
            'name' => 'Test Group',
        ]);

        $customer1 = Customer::create(['name' => 'Customer 1', 'group_id' => $customerGroup->id, 'company_id' => $this->company->id]);
        $customer2 = Customer::create(['name' => 'Customer 2', 'group_id' => $customerGroup->id, 'company_id' => $this->company->id]);

        $this->assertCount(2, $customerGroup->customers);
        $this->assertTrue($customerGroup->customers->contains($customer1));
        $this->assertTrue($customerGroup->customers->contains($customer2));
    }
}
