<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $customerGroup;
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
        Permission::create(['name' => 'customers.*']);
        Permission::create(['name' => 'customers.index']);
        Permission::create(['name' => 'customers.create']);
        Permission::create(['name' => 'customers.store']);
        Permission::create(['name' => 'customers.edit']);
        Permission::create(['name' => 'customers.update']);
        Permission::create(['name' => 'customers.destroy']);
        Permission::create(['name' => 'customers.show']);
        Permission::create(['name' => 'customer-groups.*']);
        
        // Give user permissions for customers
        $this->user->givePermissionTo('customers.*');
        
        // Create root subject for customer groups
        $parentSubject = Subject::create([
            'code' => '001',
            'name' => 'Customers Root',
            'parent_id' => null,
            'company_id' => $this->company->id,
        ]);
        
        // Set config for customer subject parent
        config(['amir.cust_subject' => $parentSubject->id]);
        
        // Create a customer group with subject
        $this->customerGroup = CustomerGroup::create([
            'name' => 'Test Group',
            'description' => 'Test group description',
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function it_displays_customer_index_page()
    {
        $customers = collect([
            Customer::create(['name' => 'Customer 1', 'group_id' => $this->customerGroup->id, 'company_id' => $this->company->id]),
            Customer::create(['name' => 'Customer 2', 'group_id' => $this->customerGroup->id, 'company_id' => $this->company->id]),
            Customer::create(['name' => 'Customer 3', 'group_id' => $this->customerGroup->id, 'company_id' => $this->company->id]),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
        $response->assertViewHas('customers');
    }

    /** @test */
    public function it_displays_customer_create_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customers.create'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.create');
        $response->assertViewHas('groups');
    }

    /** @test */
    public function it_can_create_a_customer_with_valid_data()
    {
        $customerData = [
            'name' => 'John Doe',
            'phone' => '09123456789',
            'fax' => '02112345678',
            'address' => '123 Test Street',
            'postal_code' => '1234567890',
            'email' => 'john@example.com',
            'ecnmcs_code' => '123456',
            'personal_code' => '789012',
            'web_page' => 'example website',  // Changed to match regex pattern
            'responsible' => 'Manager',
            'group_id' => $this->customerGroup->id,
            'desc' => 'Test customer description',
            'rep_via_email' => 'on',
            'acc_name_1' => 'Account Name 1',
            'acc_no_1' => '1234567890',
            'acc_bank_1' => 'Test Bank 1',
            'acc_name_2' => 'Account Name 2',
            'acc_no_2' => '0987654321',
            'acc_bank_2' => 'Test Bank 2',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success', __('Customer created successfully.'));

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'phone' => '09123456789',
            'email' => 'john@example.com',
            'group_id' => $this->customerGroup->id,
            'rep_via_email' => 1,
        ]);

        // Verify that a subject was created for the customer
        $customer = Customer::where('name', 'John Doe')->first();
        $this->assertNotNull($customer->subject);
        $this->assertEquals('John Doe', $customer->subject->name);
    }

    /** @test */
    public function it_can_create_a_customer_with_minimal_required_data()
    {
        $customerData = [
            'name' => 'Jane Doe',
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'group_id' => $this->customerGroup->id,
        ]);
    }

    /** @test */
    public function it_validates_required_name_field()
    {
        $customerData = [
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_validates_required_group_id_field()
    {
        $customerData = [
            'name' => 'Test Customer',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('group_id');
    }

    /** @test */
    public function it_validates_name_max_length()
    {
        $customerData = [
            'name' => str_repeat('a', 101), // 101 characters
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('name');
    }

    /** @test */
    public function it_validates_phone_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'phone' => '123456', // Invalid phone format
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('phone');
    }

    /** @test */
    public function it_validates_valid_phone_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'phone' => '09123456789', // Valid Iranian mobile number
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHasNoErrors();
    }

    /** @test */
    public function it_validates_email_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'email' => 'invalid-email',
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function it_validates_group_id_exists()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => 999999, // Non-existent group
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('group_id');
    }

    /** @test */
    public function it_sets_rep_via_email_to_zero_when_not_checked()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            // rep_via_email not included
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $this->assertEquals(0, $customer->rep_via_email);
    }

    /** @test */
    public function it_sets_rep_via_email_to_one_when_checked()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'rep_via_email' => 'on',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $this->assertEquals(1, $customer->rep_via_email);
    }

    /** @test */
    public function it_creates_subject_with_correct_parent_on_customer_creation()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $subject = $customer->subject;

        $this->assertNotNull($subject);
        $this->assertEquals($customer->name, $subject->name);
        $this->assertEquals($this->customerGroup->subject_id, $subject->parent_id);
    }

    /** @test */
    public function it_displays_customer_edit_page()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.edit', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.edit');
        $response->assertViewHas('customer');
        $response->assertViewHas('groups');
    }

    /** @test */
    public function it_can_update_a_customer()
    {
        $customer = Customer::create([
            'name' => 'Old Name',
            'group_id' => $this->customerGroup->id,
            'company_id' => $this->company->id,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '09123456789',
            'email' => 'updated@example.com',
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->put(route('customers.update', $customer), $updateData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success', __('Customer updated successfully.'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    /** @test */
    public function it_can_delete_a_customer()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success', __('Customer deleted successfully.'));

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    /** @test */
    public function it_deletes_customer_subject_when_customer_is_deleted()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'company_id' => $this->company->id,
        ]);

        $subjectId = $customer->subject->id;

        $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer));

        $this->assertDatabaseMissing('subjects', [
            'id' => $subjectId,
        ]);
    }

    /** @test */
    public function it_displays_customer_show_page()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'company_id' => $this->company->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.show');
        $response->assertViewHas('customer');
    }

    /** @test */
    public function it_validates_address_max_length()
    {
        $customerData = [
            'name' => 'Test Customer',
            'address' => str_repeat('a', 151), // 151 characters
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('address');
    }

    /** @test */
    public function it_validates_description_max_length()
    {
        $customerData = [
            'name' => 'Test Customer',
            'desc' => str_repeat('a', 151), // 151 characters
            'group_id' => $this->customerGroup->id,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('desc');
    }

    /** @test */
    public function it_stores_nullable_fields_correctly()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'phone' => null,
            'email' => null,
            'address' => null,
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHasNoErrors();
    }
}
