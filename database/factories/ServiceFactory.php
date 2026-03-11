<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    private static array $generatedCodesByCompany = [];

    public function definition(): array
    {
        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id')
            ?? Company::factory()->create()->id;

        self::$generatedCodesByCompany[$companyId] ??= [];

        do {
            $code = (int) $this->faker->numerify('#####');
        } while (
            in_array($code, self::$generatedCodesByCompany[$companyId], true)
            || Service::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()
        );

        self::$generatedCodesByCompany[$companyId][] = $code;

        return [
            'code' => $code,
            'name' => $this->faker->words(3, true),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->sentence,
            'company_id' => $companyId,
            'vat' => 0,
        ];
    }

    public function withGroup(?ServiceGroup $group = null): static
    {
        return $this->state(function (array $attributes) use ($group) {
            $companyId = $attributes['company_id'] ?? Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? Company::factory()->create()->id;

            $groupToUse = $group;

            if (! $groupToUse || $groupToUse->company_id !== $companyId) {
                $groupToUse = ServiceGroup::withoutGlobalScopes()->where('company_id', $companyId)
                    ->whereNotNull('subject_id')
                    ->whereNotNull('cogs_subject_id')
                    ->whereNotNull('sales_returns_subject_id')
                    ->inRandomOrder()
                    ->first();
            }

            if (! $groupToUse) {
                $groupToUse = ServiceGroup::factory()->withSubject()->create(['company_id' => $companyId]);
            }

            return [
                'group' => $groupToUse->id,
                'company_id' => $companyId,
            ];
        });
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (Service $service) {
            $group = ServiceGroup::withoutGlobalScopes()->find($service->group);
            $subjectParent = Subject::withoutGlobalScopes()->find($group?->subject_id);
            $cogsParent = Subject::withoutGlobalScopes()->find($group?->cogs_subject_id);
            $salesReturnsParent = Subject::withoutGlobalScopes()->find($group?->sales_returns_subject_id);

            $subject = Subject::factory()
                ->withParent($subjectParent)
                ->create([
                    'name' => $service->name,
                    'company_id' => $service->company_id,
                ]);

            $cogsSubject = Subject::factory()
                ->withParent($cogsParent)
                ->create([
                    'name' => $service->name,
                    'company_id' => $service->company_id,
                ]);

            $salesReturnsSubject = Subject::factory()
                ->withParent($salesReturnsParent)
                ->create([
                    'name' => $service->name,
                    'company_id' => $service->company_id,
                ]);

            $service->updateQuietly([
                'subject_id' => $subject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'sales_returns_subject_id' => $salesReturnsSubject->id,
            ]);
        });
    }
}
