<?php

namespace Tests\Feature;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Config;
use App\Models\ProductGroup;
use App\Models\ServiceGroup;
use App\Models\Subject;
use Database\Seeders\ConfigSeeder;
use Database\Seeders\ProductGroupSeeder;
use Database\Seeders\ServiceGroupSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopedSeedersTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(int $companyId): void
    {
        config(['active-company-id' => $companyId]);

        $this->seed(SubjectSeeder::class);
        $this->seed(ConfigSeeder::class);
    }

    private function subjectSnapshot(int $companyId): array
    {
        $subjects = Subject::withoutGlobalScopes()->where('company_id', $companyId)->get()->keyBy('id');

        return $subjects->mapWithKeys(fn (Subject $subject) => [
            $subject->code => [
                'name' => $subject->name,
                'parent_code' => $subject->parent_id
                    ? $subjects->get($subject->parent_id)?->code
                    : null,
                'type' => $subject->type,
                'is_permanent' => (bool) $subject->is_permanent,
            ],
        ])->sortKeys()->all();
    }

    private function configSnapshot(int $companyId): array
    {
        $subjects = Subject::withoutGlobalScopes()->where('company_id', $companyId)->get()->keyBy('id');

        return Config::withoutGlobalScopes()->where('company_id', $companyId)->get()->mapWithKeys(fn (Config $config) => [
            $config->key => [
                'type' => (string) $config->type,
                'category' => (string) $config->category,
                'description' => $config->desc,
                'subject_code' => $subjects->get((int) $config->value)?->code,
            ],
        ])->sortKeys()->all();
    }

    public function test_subjects_and_configs_are_seeded_equally_for_a_specific_company(): void
    {
        Company::factory()->create(['id' => 1]);
        Company::factory()->create(['id' => 42]);

        $this->seedCompany(1);

        $defaultSubjects = $this->subjectSnapshot(1);
        $defaultConfigs = $this->configSnapshot(1);

        $this->assertNotEmpty($defaultSubjects);
        $this->assertNotEmpty($defaultConfigs);

        $this->seedCompany(42);

        $this->assertSame($defaultSubjects, $this->subjectSnapshot(42));
        $this->assertSame($defaultConfigs, $this->configSnapshot(42));

        $this->assertSame($defaultSubjects, $this->subjectSnapshot(1));
        $this->assertSame($defaultConfigs, $this->configSnapshot(1));

        $defaultSubject = Subject::withoutGlobalScopes()->where('company_id', 1)->where('code', '050003')->firstOrFail();
        $companySubject = Subject::withoutGlobalScopes()->where('company_id', 42)->where('code', '050003')->firstOrFail();

        $this->assertNotSame($defaultSubject->id, $companySubject->id);
    }

    public function test_specific_company_parent_and_config_references_are_company_scoped(): void
    {
        Company::factory()->create(['id' => 42]);
        $this->seedCompany(42);

        $subjectIds = Subject::withoutGlobalScopes()->where('company_id', 42)->pluck('id');

        $this->assertNotEmpty($subjectIds);
        $this->assertGreaterThan(0, Config::withoutGlobalScopes()->where('company_id', 42)->count());

        $foreignParentCount = Subject::withoutGlobalScopes()->where('company_id', 42)->whereNotNull('parent_id')->whereNotIn('parent_id', $subjectIds)->count();
        $foreignConfigCount = Config::withoutGlobalScopes()->where('company_id', 42)->whereNotIn('value', $subjectIds->map(fn ($id) => (string) $id))->count();

        $this->assertSame(0, $foreignParentCount);
        $this->assertSame(0, $foreignConfigCount);
    }

    public function test_profit_and_loss_subjects_and_seeded_group_children_use_their_normal_balance_types(): void
    {
        Company::factory()->create(['id' => 1]);
        $this->seedCompany(1);

        $expectedTypes = [
            '040' => SubjectType::DEBTOR,
            '041' => SubjectType::DEBTOR,
            '041001' => SubjectType::DEBTOR,
            '050' => SubjectType::CREDITOR,
            '050001' => SubjectType::CREDITOR,
            '050002' => SubjectType::CREDITOR,
            '050003' => SubjectType::CREDITOR,
            '060' => SubjectType::CREDITOR,
            '060001' => SubjectType::CREDITOR,
            '061' => SubjectType::DEBTOR,
            '061001' => SubjectType::DEBTOR,
            '061002' => SubjectType::DEBTOR,
            '062' => SubjectType::DEBTOR,
            '066' => SubjectType::BOTH,
            '066001' => SubjectType::CREDITOR,
            '066002' => SubjectType::DEBTOR,
            '070' => SubjectType::DEBTOR,
            '070001' => SubjectType::DEBTOR,
            '070002' => SubjectType::DEBTOR,
        ];

        $subjects = Subject::withoutGlobalScopes()->where('company_id', 1)->whereIn('code', array_keys($expectedTypes))->get()->keyBy('code');

        foreach ($expectedTypes as $code => $expectedType) {
            $this->assertSame($expectedType, $subjects->get($code)?->type, "Unexpected type for seeded subject {$code}.");
        }

        $this->seed(ProductGroupSeeder::class);
        $this->seed(ServiceGroupSeeder::class);

        $productGroup = ProductGroup::withoutGlobalScopes()->where('company_id', 1)->firstOrFail();
        $serviceGroup = ServiceGroup::withoutGlobalScopes()->where('company_id', 1)->firstOrFail();

        $this->assertSame(SubjectType::CREDITOR, $productGroup->incomeSubject->type);
        $this->assertSame(SubjectType::DEBTOR, $productGroup->salesReturnsSubject->type);
        $this->assertSame(SubjectType::DEBTOR, $productGroup->cogsSubject->type);
        $this->assertSame(SubjectType::DEBTOR, $productGroup->inventorySubject->type);
        $this->assertSame(SubjectType::CREDITOR, $serviceGroup->subject->type);
        $this->assertSame(SubjectType::DEBTOR, $serviceGroup->cogsSubject->type);
        $this->assertSame(SubjectType::DEBTOR, $serviceGroup->salesReturnsSubject->type);

        $this->assertFalse((bool) $productGroup->incomeSubject->is_permanent);
        $this->assertFalse((bool) $productGroup->salesReturnsSubject->is_permanent);
        $this->assertFalse((bool) $productGroup->cogsSubject->is_permanent);
        $this->assertTrue((bool) $productGroup->inventorySubject->is_permanent);
        $this->assertFalse((bool) $serviceGroup->subject->is_permanent);
        $this->assertFalse((bool) $serviceGroup->cogsSubject->is_permanent);
        $this->assertFalse((bool) $serviceGroup->salesReturnsSubject->is_permanent);
    }
}
