<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        
        // Create a user for authentication first
        $this->user = User::factory()->create();
        
        // Create a company without the afterCreating hook interfering
        $this->company = \App\Models\Company::create([
            'name' => 'Test Company',
            'address' => 'Test Address',
            'postal_code' => '1234567890',
            'phone_number' => '12345678',
            'fiscal_year' => 1403,
        ]);
        
        // Attach user to company
        $this->user->companies()->attach($this->company->id);
        
        // Set active company session
        session(['active-company-id' => $this->company->id]);
        
        // Create permissions
        Permission::create(['name' => 'customer-groups.*']);
        Permission::create(['name' => 'customer-groups.index']);
        Permission::create(['name' => 'customer-groups.create']);
        Permission::create(['name' => 'customer-groups.store']);
        Permission::create(['name' => 'customer-groups.edit']);
        Permission::create(['name' => 'customer-groups.update']);
        Permission::create(['name' => 'customer-groups.destroy']);
        
        // Give user permissions for customer groups
        $this->user->givePermissionTo('customer-groups.*');
        
        // Create root subject for customer groups
        $parentSubject = Subject::create([
            'code' => '001',
            'name' => 'Customers Root',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);
        
        // Set config for customer subject parent
        config(['amir.cust_subject' => $parentSubject->id]);
    }

    /** @test */
    public function it_displays_customer_group_index_page()
    {
        CustomerGroup::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->get(route('customer-groups.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customerGroups.index');
        $response->assertViewHas('customerGroups');
    }

    /** @test */
    public function it_displays_customer_group_create_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customer-groups.create'));

        $response->assertStatus(200);
        $response->assertViewIs('customerGroups.create');
        $response->assertViewHas('subjects');
    }

    /** @test */
    public function it_can_create_a_customer_group_with_valid_data()
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

    /** @test */
    public function it_can_create_a_customer_group_with_minimal_data()
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

    /** @test */
    public function it_validates_required_name_field()
    {
        $groupData = [
            'description' => 'Test description',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_validates_name_max_length()
    {
        $groupData = [
            'name' => str_repeat('a', 21), // 21 characters (max is 20)
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_validates_description_max_length()
    {
        $groupData = [
            'name' => 'Test Group',
            'description' => str_repeat('a', 151), // 151 characters (max is 150)
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('description');
    }

    /** @test */
    public function it_creates_subject_for_customer_group_on_creation()
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

    /** @test */
    public function it_displays_customer_group_edit_page()
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

    /** @test */
    public function it_can_update_a_customer_group()
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

    /** @test */
    public function it_updates_subject_when_customer_group_name_is_updated()
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

    /** @test */
    public function it_can_delete_a_customer_group()
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

    /** @test */
    public function it_sets_company_id_from_session_on_creation()
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

    /** @test */
    public function it_accepts_nullable_description()
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

    /** @test */
    public function it_validates_name_format_with_regex()
    {
        $groupData = [
            'name' => 'Test@Group!', // Contains special characters not allowed
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_accepts_valid_name_format()
    {
        $groupData = [
            'name' => 'Test Group 123', // Valid format with letters, spaces, and numbers
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customer-groups.store'), $groupData);

        $response->assertRedirect(route('customer-groups.index'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function customer_group_has_relationship_with_customers()
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
