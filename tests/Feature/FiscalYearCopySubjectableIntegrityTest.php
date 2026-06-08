<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Config;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use App\Models\User;
use App\Services\CustomerGroupService;
use App\Services\CustomerService;
use App\Services\FiscalYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalYearCopySubjectableIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function setActive(Company $company): void
    {
        config(['active-company-id' => $company->id]);
        foreach (Config::withoutGlobalScopes()->where('company_id', $company->id)->get() as $c) {
            config(['amir.'.$c->key => $c->value]);
        }
    }

    private function buildCustomerSubjectTree(Company $company): void
    {
        $this->setActive($company);

        $root = new Subject;
        $root->forceFill([
            'company_id' => $company->id,
            'name' => 'Customers Root',
            'code' => '101',
            'parent_id' => null,
            'type' => 'both',
            'is_permanent' => true,
        ])->save();

        Config::create([
            'company_id' => $company->id,
            'key' => 'cust_subject',
            'value' => (string) $root->id,
            'type' => 'int',
            'category' => 'general',
        ]);
        config(['amir.cust_subject' => $root->id]);
    }

    private function newYearData(Company $source, int $fiscalYear): array
    {
        return collect($source->getAttributes())->except(['id', 'closed_at', 'closed_by', 'fiscal_year'])->merge(['fiscal_year' => $fiscalYear])->toArray();
    }

    private function seedSource(): Company
    {
        $user = User::factory()->create();
        $source = Company::factory()->create(['fiscal_year' => 1402]);
        $source->users()->attach($user);
        $this->actingAs($user);
        $this->buildCustomerSubjectTree($source);

        $groupService = app(CustomerGroupService::class);
        $customerService = app(CustomerService::class);

        $g1 = $groupService->create(['name' => 'Wholesale', 'company_id' => $source->id]);
        $g2 = $groupService->create(['name' => 'Retail', 'company_id' => $source->id]);
        $c1 = $customerService->create(['name' => 'Acme', 'group_id' => $g1->id, 'company_id' => $source->id, 'type' => 'individual']);
        $customerService->create(['name' => 'Globex', 'group_id' => $g1->id, 'company_id' => $source->id, 'type' => 'individual', 'introducer_id' => $c1->id]);
        $customerService->create(['name' => 'Initech', 'group_id' => $g2->id, 'company_id' => $source->id, 'type' => 'individual']);

        return $source;
    }

    public function test_copied_customer_subjects_point_at_the_new_year_entities(): void
    {
        $source = $this->seedSource();

        $target = FiscalYearService::createWithCopiedData(
            $this->newYearData($source, 1403),
            $source->id,
            ['subjects', 'configs', 'customers'],
        );

        $groups = CustomerGroup::withoutGlobalScopes()->where('company_id', $target->id)->get();
        $this->assertCount(2, $groups);
        foreach ($groups as $group) {
            $subject = Subject::withoutGlobalScopes()->find($group->subject_id);
            $this->assertNotNull($subject);
            $this->assertSame($target->id, (int) $subject->company_id);
            $this->assertSame(CustomerGroup::class, $subject->subjectable_type);
            $this->assertSame($group->id, $subject->subjectable_id, 'group subject points back to the new group');
            $this->assertSame($subject->id, $group->subject()->withoutGlobalScopes()->first()?->id);
        }

        $customers = Customer::withoutGlobalScopes()->where('company_id', $target->id)->get();
        $this->assertCount(3, $customers);
        foreach ($customers as $customer) {
            $subject = Subject::withoutGlobalScopes()->find($customer->subject_id);
            $this->assertNotNull($subject);
            $this->assertSame($target->id, (int) $subject->company_id);
            $this->assertSame(Customer::class, $subject->subjectable_type);
            $this->assertSame($customer->id, $subject->subjectable_id, 'customer subject points back to the new customer');

            $groupSubject = Subject::withoutGlobalScopes()->find(
                CustomerGroup::withoutGlobalScopes()->find($customer->group_id)->subject_id
            );
            $this->assertSame($groupSubject->code, substr($subject->code, 0, -3));
        }
    }

    public function test_copying_subjects_without_customers_leaves_no_stale_subjectable_pointers(): void
    {
        $source = $this->seedSource();
        $target = FiscalYearService::createWithCopiedData(
            $this->newYearData($source, 1403),
            $source->id,
            ['subjects', 'configs'],
        );

        $leaked = Subject::withoutGlobalScopes()->where('company_id', $target->id)
            ->whereIn('subjectable_type', [Customer::class, CustomerGroup::class])->get();

        $this->assertCount(0, $leaked, 'no stale customer/group subjectable pointers should survive the copy');

        $this->setActive($target);
        $group = app(CustomerGroupService::class)->create(['name' => 'FreshGroup', 'company_id' => $target->id]);
        $customer = app(CustomerService::class)->create([
            'name' => 'BrandNew', 'group_id' => $group->id, 'company_id' => $target->id, 'type' => 'individual',
        ]);
        $customer->refresh();

        $subject = Subject::withoutGlobalScopes()->find($customer->subject_id);
        $this->assertSame($customer->id, $subject->subjectable_id);
        $this->assertSame(Customer::class, $subject->subjectable_type);
        $this->assertSame($group->subject->code, substr($subject->code, 0, -3));
        $this->assertSame($subject->id, $customer->subject()->withoutGlobalScopes()->first()?->id);
    }
}
