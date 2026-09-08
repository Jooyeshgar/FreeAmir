<?php

namespace Tests\Feature;

use App\Models\Company;
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

    protected $customer;

    protected $company;

    protected int $companyId;

    protected function setUp(): void
    {
        parent::setUp();
        $company = Company::factory()->create();
        $this->companyId = $company->id;

        $this->user = User::factory()->create();
        $company->users()->attach($this->user);

        $this->user->givePermissionTo([
            Permission::firstOrCreate(['name' => 'customers.index']),
            Permission::firstOrCreate(['name' => 'customers.create']),
            Permission::firstOrCreate(['name' => 'customers.store']),
            Permission::firstOrCreate(['name' => 'customers.show']),
            Permission::firstOrCreate(['name' => 'customers.edit']),
            Permission::firstOrCreate(['name' => 'customers.update']),
            Permission::firstOrCreate(['name' => 'customers.destroy']),
        ]);

        $this->withCookies(['active-company-id' => $this->companyId]);

        $this->customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $this->customer = Customer::factory()->withGroup($this->customerGroup)->withSubject()->create(['company_id' => $this->companyId]);
    }

    public function test_it_displays_customer_index_page()
    {
        $response = $this->actingAs($this->user)->get(route('customers.index'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.index');
        $response->assertViewHas('customers');
    }

    public function test_it_displays_customer_create_page()
    {
        $response = $this->actingAs($this->user)
            ->get(route('customers.create'));

        $response->assertStatus(200);
        $response->assertViewIs('customers.create');
        $response->assertViewHas('groups');
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*name="subject_code")(?![^>]*\bdisabled\b)[^>]*>/',
            $response->getContent()
        );
    }

    public function test_it_can_create_a_customer_with_valid_data()
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
            'type' => 'individual',
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

    public function test_it_can_create_a_customer_with_an_explicit_subject_code(): void
    {
        $subjectCode = $this->customerGroup->subject->code.'987';

        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Coded Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($subjectCode),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'Coded Customer')->firstOrFail();

        $this->assertSame($subjectCode, $customer->subject->code);
        $this->assertSame($customer->subject->id, $customer->subject_id);
    }

    public function test_it_reuses_an_existing_subject_code_and_reports_different_names(): void
    {
        $subject = Subject::factory()
            ->withParent($this->customerGroup->subject)
            ->create([
                'company_id' => $this->companyId,
                'name' => 'Existing Account',
            ]);
        $subjectCount = Subject::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Linked Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($subject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('Customer created successfully and customer ":customer" was linked to subject ":subject" successfully.', [
            'customer' => 'Linked Customer',
            'subject' => 'Existing Account',
        ]));

        $customer = Customer::where('name', 'Linked Customer')->firstOrFail();

        $this->assertSame($subject->id, $customer->subject_id);
        $this->assertSame($subject->id, $customer->subject->id);
        $this->assertSame('Existing Account', $customer->subject->name);
        $this->assertSame($subjectCount, Subject::withoutGlobalScopes()->count());
    }

    public function test_it_does_not_reassign_a_subject_that_belongs_to_another_customer(): void
    {
        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Conflicting Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($this->customer->subject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasErrors('subject_code');
        $this->assertDatabaseMissing('customers', ['name' => 'Conflicting Customer']);
        $this->assertSame($this->customer->id, $this->customer->subject->subjectable_id);
    }

    public function test_it_uses_the_standard_success_message_when_customer_and_existing_subject_names_match(): void
    {
        $subject = Subject::factory()
            ->withParent($this->customerGroup->subject)
            ->create([
                'company_id' => $this->companyId,
                'name' => 'Matching Name',
            ]);
        $subjectCount = Subject::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Matching Name',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($subject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('Customer created successfully.'));

        $customer = Customer::where('name', 'Matching Name')->firstOrFail();

        $this->assertSame($subject->id, $customer->subject_id);
        $this->assertSame($subjectCount, Subject::withoutGlobalScopes()->count());
    }

    public function test_it_rejects_a_non_numeric_subject_code(): void
    {
        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Invalid Code Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => '001/A01',
            'type' => 'individual',
        ]);

        $response->assertSessionHasErrors('subject_code');
        $this->assertDatabaseMissing('customers', ['name' => 'Invalid Code Customer']);
    }

    public function test_it_rejects_a_subject_code_outside_the_selected_customer_group(): void
    {
        $otherGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
        $foreignGroupCode = $otherGroup->subject->code.'777';

        $response = $this->actingAs($this->user)->post(route('customers.store'), [
            'name' => 'Wrong Group Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($foreignGroupCode),
            'type' => 'individual',
        ]);

        $response->assertSessionHasErrors('subject_code');
        $this->assertDatabaseMissing('customers', ['name' => 'Wrong Group Customer']);
        $this->assertDatabaseMissing('subjects', [
            'company_id' => $this->companyId,
            'code' => $foreignGroupCode,
        ]);
    }

    public function test_change_subject_name_on_changing_customer_name()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $this->actingAs($this->user)->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $subject = $customer->subject;

        $this->assertNotNull($subject);
        $this->assertEquals($customer->name, $subject->name);

        $newCustomerData = [
            'name' => 'new name',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $this->actingAs($this->user)->put(route('customers.update', $customer), $newCustomerData);

        $customer = Customer::where('name', 'new name')->first();
        $subject = $customer->subject;

        $this->assertNotNull($subject);
        $this->assertEquals($customer->name, $subject->name);
    }

    public function test_change_subject_code_on_changing_customer_group()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $this->actingAs($this->user)->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $subject = $customer->subject;

        $this->assertNotNull($subject);
        $this->assertEquals($customer->name, $subject->name);
        $this->assertEquals($this->customerGroup->subject_id, $subject->parent_id);
        $this->assertStringStartsWith($this->customerGroup->subject->code, $subject->code);

        $newCustomerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);

        $newCustomerData = [
            'name' => 'new name with new customer group',
            'group_id' => $newCustomerGroup->id,
            'type' => 'individual',
        ];

        $this->actingAs($this->user)->put(route('customers.update', $customer), $newCustomerData);

        $newCustomer = Customer::where('name', 'new name with new customer group')->first();
        $newSubject = $newCustomer->subject;

        $this->assertNotNull($newSubject);
        $this->assertEquals($newCustomer->name, $newSubject->name);

        // The subject must be re-parented to the new group's subject and its
        // hierarchical code must be regenerated under that new parent.
        $this->assertEquals($newCustomerGroup->subject_id, $newSubject->parent_id);
        $this->assertStringStartsWith($newCustomerGroup->subject->code, $newSubject->code);
        $this->assertStringStartsNotWith($this->customerGroup->subject->code, $newSubject->code);

        $this->assertDatabaseHas('subjects', ['name' => 'new name with new customer group']);
        $this->assertDatabaseHas('subjects', ['name' => 'new name with new customer group']);

        $this->assertDatabaseMissing('subjects', ['name' => 'Test Customer']);
        $this->assertDatabaseMissing('customers', ['name' => 'Test Customer']);
    }

    public function test_it_can_create_a_customer_with_minimal_required_data()
    {
        $customerData = [
            'name' => 'Jane Doe',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
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

    public function test_it_validates_required_name_field()
    {
        $customerData = [
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_validates_required_group_id_field()
    {
        $customerData = [
            'name' => 'Test Customer',
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('group_id');
    }

    public function test_it_validates_name_max_length()
    {
        $customerData = [
            'name' => str_repeat('a', 101), // 101 characters
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('name');
    }

    public function test_it_validates_phone_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'phone' => 123456, // Invalid phone format
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('phone');
    }

    public function test_it_validates_valid_phone_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'phone' => '09123456789', // Valid Iranian mobile number
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_it_validates_email_format()
    {
        $customerData = [
            'name' => 'Test Customer',
            'email' => 'invalid-email',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('email');
    }

    public function test_it_validates_group_id_exists()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => 999999, // Non-existent group
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('group_id');
    }

    public function test_it_sets_rep_via_email_to_zero_when_not_checked()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
            // rep_via_email not included
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $this->assertEquals(0, $customer->rep_via_email);
    }

    public function test_it_sets_rep_via_email_to_one_when_checked()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'rep_via_email' => 'on',
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $this->assertEquals(1, $customer->rep_via_email);
    }

    public function test_it_creates_subject_with_correct_parent_on_customer_creation()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $customer = Customer::where('name', 'Test Customer')->first();
        $subject = $customer->subject;

        $this->assertNotNull($subject);
        $this->assertEquals($customer->name, $subject->name);
        $this->assertEquals($this->customerGroup->subject_id, $subject->parent_id);
    }

    public function test_it_displays_customer_edit_page()
    {
        $response = $this->actingAs($this->user)->get(route('customers.edit', $this->customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.edit');
        $response->assertViewHas('customer');
        $response->assertViewHas('groups');
        $this->assertMatchesRegularExpression(
            '/<input(?=[^>]*name="subject_code")(?=[^>]*value="'.preg_quote(substr($this->customer->subject->code, -3), '/').'\")(?![^>]*\bdisabled\b)[^>]*>/',
            $response->getContent()
        );
    }

    public function test_it_can_update_a_customer()
    {
        $updateData = [
            'name' => 'Updated Name',
            'phone' => '09123456789',
            'email' => 'updated@example.com',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)->put(route('customers.update', $this->customer), $updateData);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success', __('Customer updated successfully.'));

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_it_can_update_a_customer_with_its_current_subject_code_without_creating_a_subject(): void
    {
        $subject = $this->customer->subject;
        $subjectName = $subject->name;
        $subjectCount = Subject::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Renamed Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($subject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('Customer updated successfully.'));

        $this->customer->refresh();
        $subject->refresh();

        $this->assertSame($subject->id, $this->customer->subject_id);
        $this->assertSame($subjectName, $subject->name);
        $this->assertSame($subjectCount, Subject::withoutGlobalScopes()->count());
    }

    public function test_it_can_link_an_existing_subject_when_updating_a_customer(): void
    {
        $oldSubject = $this->customer->subject;
        $newSubject = Subject::factory()
            ->withParent($this->customerGroup->subject)
            ->create([
                'company_id' => $this->companyId,
                'name' => 'Shared Account Name',
            ]);

        $response = $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Updated Customer Name',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($newSubject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('Customer updated successfully.'));

        $this->customer->refresh();
        $oldSubject->refresh();
        $newSubject->refresh();

        $this->assertSame($newSubject->id, $this->customer->subject_id);
        $this->assertSame($newSubject->id, $this->customer->subject->id);
        $this->assertSame('Shared Account Name', $newSubject->name);
        $this->assertNull($oldSubject->subjectable_type);
        $this->assertNull($oldSubject->subjectable_id);
    }

    public function test_it_can_change_to_an_unused_subject_code_without_creating_another_subject(): void
    {
        $subject = $this->customer->subject;
        $newCode = $this->customerGroup->subject->code.'986';
        $subjectCount = Subject::withoutGlobalScopes()->count();

        $response = $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Stable Customer',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($newCode),
            'type' => 'individual',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', __('Customer updated successfully.'));

        $this->customer->refresh();
        $subject->refresh();

        $this->assertSame($subject->id, $this->customer->subject_id);
        $this->assertSame($newCode, $subject->code);
        $this->assertSame($subjectCount, Subject::withoutGlobalScopes()->count());
    }

    public function test_update_rolls_back_when_the_subject_code_belongs_to_another_customer(): void
    {
        $originalName = $this->customer->name;
        $originalSubject = $this->customer->subject;
        $otherCustomer = Customer::factory()
            ->withGroup($this->customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId]);

        $response = $this->actingAs($this->user)->put(route('customers.update', $this->customer), [
            'name' => 'Name Must Roll Back',
            'group_id' => $this->customerGroup->id,
            'subject_code' => formatCode($otherCustomer->subject->code),
            'type' => 'individual',
        ]);

        $response->assertSessionHasErrors('subject_code');

        $this->customer->refresh();
        $originalSubject->refresh();
        $otherCustomer->subject->refresh();

        $this->assertSame($originalName, $this->customer->name);
        $this->assertSame($originalSubject->id, $this->customer->subject_id);
        $this->assertSame($this->customer->id, $originalSubject->subjectable_id);
        $this->assertSame($otherCustomer->id, $otherCustomer->subject->subjectable_id);
    }

    public function test_it_can_delete_a_customer()
    {
        $customer = Customer::factory()->withGroup($this->customerGroup)->withSubject()->create();

        $response = $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));

        $this->assertModelMissing($customer);
    }

    public function test_it_deletes_customer_subject_when_customer_is_deleted()
    {
        $customer = Customer::factory()->withGroup($this->customerGroup)->withSubject()->create();

        $this->actingAs($this->user)
            ->delete(route('customers.destroy', $customer));

        $this->assertModelMissing($customer->subject());
    }

    public function test_it_displays_customer_show_page()
    {
        $response = $this->actingAs($this->user)->get(route('customers.show', $this->customer));

        $response->assertStatus(200);
        $response->assertViewIs('customers.show');
        $response->assertViewHas('customer');
    }

    public function test_it_validates_address_max_length()
    {
        $customerData = [
            'name' => 'Test Customer',
            'address' => str_repeat('a', 151), // 151 characters
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('address');
    }

    public function test_it_validates_description_max_length()
    {
        $customerData = [
            'name' => 'Test Customer',
            'desc' => str_repeat('a', 151), // 151 characters
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
        ];

        $response = $this->actingAs($this->user)
            ->post(route('customers.store'), $customerData);

        $response->assertSessionHasErrors('desc');
    }

    public function test_it_stores_nullable_fields_correctly()
    {
        $customerData = [
            'name' => 'Test Customer',
            'group_id' => $this->customerGroup->id,
            'type' => 'individual',
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
