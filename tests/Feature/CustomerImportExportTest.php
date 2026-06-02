<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected CustomerGroup $customerGroup;

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
            Permission::firstOrCreate(['name' => 'customers.export']),
            Permission::firstOrCreate(['name' => 'customers.import']),
            Permission::firstOrCreate(['name' => 'customers.import.store']),
        ]);

        $this->withCookies(['active-company-id' => $this->companyId]);
        // Mirror the active company for direct model access in the test body
        // (the cookie alone only takes effect during HTTP requests).
        config(['active-company-id' => $this->companyId]);

        $this->customerGroup = CustomerGroup::factory()->withSubject()->create(['company_id' => $this->companyId]);
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('customers.csv', $csv);
    }

    public function test_export_returns_csv_with_customers(): void
    {
        $customer = Customer::factory()
            ->withGroup($this->customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId, 'name' => 'Acme Co']);

        $response = $this->actingAs($this->user)->get(route('customers.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('name,group_name,subject_code', $content);
        $this->assertStringContainsString('Acme Co', $content);
        $this->assertStringContainsString($customer->subject->code, $content);
    }

    public function test_import_creates_new_group_and_customer_with_auto_subject_code(): void
    {
        $csv = "name,group_name,subject_code,type\n".
            "New Customer,Brand New Group,,individual\n";

        $response = $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customer_groups', ['name' => 'Brand New Group', 'company_id' => $this->companyId]);

        $customer = Customer::where('name', 'New Customer')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->subject);

        // Auto-generated code must sit under the new group's subject.
        $group = CustomerGroup::with('subject')->where('name', 'Brand New Group')->first();
        $this->assertSame($group->subject->code, substr($customer->subject->code, 0, -3));
    }

    public function test_import_reuses_existing_group(): void
    {
        $csv = "name,group_name,subject_code\n".
            "Reuse Customer,{$this->customerGroup->name},\n";

        $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)])
            ->assertSessionHas('success');

        // No duplicate group should have been created for the existing name.
        $this->assertSame(1, CustomerGroup::withoutGlobalScopes()
            ->where('company_id', $this->companyId)
            ->where('name', $this->customerGroup->name)
            ->count());

        $customer = Customer::where('name', 'Reuse Customer')->first();
        $this->assertSame($this->customerGroup->id, $customer->group_id);
    }

    public function test_import_accepts_subject_code_that_is_child_of_group(): void
    {
        $this->customerGroup->loadMissing('subject');
        $validCode = $this->customerGroup->subject->code.'007';

        $csv = "name,group_name,subject_code\n".
            "Coded Customer,{$this->customerGroup->name},{$validCode}\n";

        $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)])
            ->assertSessionHas('success');

        $customer = Customer::where('name', 'Coded Customer')->first();
        $this->assertSame($validCode, $customer->subject->code);
    }

    public function test_import_rejects_subject_code_not_child_of_group_and_rolls_back(): void
    {
        // Code whose parent portion does not match the group's subject code.
        $badCode = '999007';

        $csv = "name,group_name,subject_code\n".
            "Bad Customer,{$this->customerGroup->name},{$badCode}\n";

        $response = $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)]);

        $response->assertRedirect(route('customers.import'));
        $response->assertSessionHas('error');

        // Transaction rolled back: nothing imported.
        $this->assertDatabaseMissing('customers', ['name' => 'Bad Customer']);
    }

    public function test_import_updates_existing_customer_when_subject_code_has_a_customer(): void
    {
        $existing = Customer::factory()
            ->withGroup($this->customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId, 'name' => 'Old Name', 'phone' => '111']);

        $code = $existing->subject->code;

        $csv = "name,group_name,subject_code,phone\n".
            "Updated Name,{$this->customerGroup->name},{$code},222\n";

        $response = $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success');

        // No new customer was created; the existing one was updated in place.
        $this->assertSame(1, Customer::where('subject_id', $existing->subject->id)->count());

        $existing->refresh();
        $this->assertSame('Updated Name', $existing->name);
        $this->assertSame('222', $existing->phone);
        // The subject is kept (same code) but its name follows the customer.
        $this->assertSame($code, $existing->subject->code);
        $this->assertSame('Updated Name', $existing->subject->name);
    }

    public function test_import_links_customer_to_orphan_subject(): void
    {
        $this->customerGroup->loadMissing('subject');

        // A subject under the group with no customer attached. The factory derives the
        // child code from the parent, so we read it back for the CSV below.
        $orphan = Subject::factory()
            ->withParent($this->customerGroup->subject)
            ->create([
                'company_id' => $this->companyId,
                'name' => 'Orphan Subject',
            ]);

        $csv = "name,group_name,subject_code\n".
            "Linked Customer,{$this->customerGroup->name},{$orphan->code}\n";

        $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)])
            ->assertSessionHas('success');

        $customer = Customer::where('name', 'Linked Customer')->first();
        $this->assertNotNull($customer);

        // The existing orphan subject is reused, not a new one.
        $this->assertSame($orphan->id, $customer->subject_id);
        $this->assertSame($orphan->code, $customer->subject->code);

        $orphan->refresh();
        $this->assertSame($customer->id, $orphan->subjectable_id);
        $this->assertSame($customer->getMorphClass(), $orphan->subjectable_type);
    }

    public function test_import_rejects_duplicate_customer_name_in_group(): void
    {
        Customer::factory()
            ->withGroup($this->customerGroup)
            ->withSubject()
            ->create(['company_id' => $this->companyId, 'name' => 'Duplicate Name']);

        $csv = "name,group_name,subject_code\n".
            "Duplicate Name,{$this->customerGroup->name},\n";

        $response = $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)]);

        $response->assertRedirect(route('customers.import'));
        $response->assertSessionHas('error');
    }

    public function test_import_rolls_back_all_rows_when_one_row_fails(): void
    {
        // First row is valid, second row has an invalid subject code.
        $csv = "name,group_name,subject_code\n".
            "Good Row,{$this->customerGroup->name},\n".
            "Bad Row,{$this->customerGroup->name},999007\n";

        $this->actingAs($this->user)
            ->post(route('customers.import.store'), ['file' => $this->upload($csv)])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('customers', ['name' => 'Good Row']);
        $this->assertDatabaseMissing('customers', ['name' => 'Bad Row']);
    }
}
