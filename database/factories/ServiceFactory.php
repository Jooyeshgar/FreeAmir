<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    private static array $generatedCodesByFiscalYear = [];

    public function definition(): array
    {
        $fiscalYearId = (int) getActiveFiscalYear();
        if (! FiscalYear::withoutGlobalScopes()->whereKey($fiscalYearId)->exists()) {
            throw new \LogicException('An active fiscal year is required to create a service.');
        }

        self::$generatedCodesByFiscalYear[$fiscalYearId] ??= [];

        do {
            $code = (int) $this->faker->numerify('#####');
        } while (
            in_array($code, self::$generatedCodesByFiscalYear[$fiscalYearId], true)
            || Service::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->where('code', $code)->exists()
        );

        self::$generatedCodesByFiscalYear[$fiscalYearId][] = $code;

        return [
            'code' => $code,
            'name' => $this->faker->persianServiceName(),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->persianSentence(),
            'fiscal_year_id' => $fiscalYearId,
            'vat' => 0,
        ];
    }

    public function withGroup(?ServiceGroup $group = null): static
    {
        return $this->state(function (array $attributes) use ($group) {
            $fiscalYearId = $attributes['fiscal_year_id'] ?? FiscalYear::withoutGlobalScopes()->inRandomOrder()->value('id') ?? FiscalYear::factory()->create()->id;

            $groupToUse = $group;

            if (! $groupToUse || $groupToUse->fiscal_year_id !== $fiscalYearId) {
                $groupToUse = ServiceGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)
                    ->whereNotNull('subject_id')
                    ->whereNotNull('cogs_subject_id')
                    ->whereNotNull('sales_returns_subject_id')
                    ->inRandomOrder()
                    ->first();
            }

            if (! $groupToUse) {
                $groupToUse = ServiceGroup::factory()->withSubject()->create(['fiscal_year_id' => $fiscalYearId]);
            }

            return [
                'group' => $groupToUse->id,
                'fiscal_year_id' => $fiscalYearId,
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
                ->for($service, 'subjectable')
                ->create([
                    'name' => $service->name,
                    'fiscal_year_id' => $service->fiscal_year_id,
                ]);

            $cogsSubject = Subject::factory()
                ->withParent($cogsParent)
                ->for($service, 'subjectable')
                ->create([
                    'name' => $service->name,
                    'fiscal_year_id' => $service->fiscal_year_id,
                ]);

            $salesReturnsSubject = Subject::factory()
                ->withParent($salesReturnsParent)
                ->for($service, 'subjectable')
                ->create([
                    'name' => $service->name,
                    'fiscal_year_id' => $service->fiscal_year_id,
                ]);

            $service->updateQuietly([
                'subject_id' => $subject->id,
                'cogs_subject_id' => $cogsSubject->id,
                'sales_returns_subject_id' => $salesReturnsSubject->id,
            ]);
        });
    }
}
