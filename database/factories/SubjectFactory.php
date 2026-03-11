<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveCompany() ?? Company::factory()->create()->id;

        return [
            'company_id' => $companyId,
            'parent_id' => null,
            'code' => uniqid('tmp', false),
            'name' => $this->faker->name,
            'type' => 'both',
        ];
    }

    public function withParent(?Subject $parent = null): static
    {
        return $this->state([
            'parent_id' => $parent?->id,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Subject $subject) {
            if (empty($subject->parent_id)) {
                $maxRootCode = Subject::withoutGlobalScopes()->where('company_id', $subject->company_id)->whereNull('parent_id')
                    ->where('id', '!=', $subject->id)->max('code');

                $nextRootCode = ((int) ($maxRootCode ?? 0)) + 1;
                $subject->code = str_pad((string) $nextRootCode, 3, '0', STR_PAD_LEFT);
                $subject->saveQuietly();

                return;
            }

            $parent = Subject::withoutGlobalScopes()->find($subject->parent_id);
            if (! $parent) {
                return;
            }

            $maxChildCode = Subject::withoutGlobalScopes()
                ->where('company_id', $subject->company_id)
                ->where('parent_id', $parent->id)
                ->where('id', '!=', $subject->id)
                ->max('code');

            $nextChildNumber = $maxChildCode ? ((int) substr((string) $maxChildCode, -3)) + 1 : 1;
            $subject->code = $parent->code.str_pad((string) $nextChildNumber, 3, '0', STR_PAD_LEFT);
            $subject->saveQuietly();
        });
    }
}
