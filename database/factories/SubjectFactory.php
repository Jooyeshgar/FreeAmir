<?php

namespace Database\Factories;

use App\Enums\SubjectType;
use App\Models\Company;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => function () {
                $companyId = (int) getActiveCompany();

                if (! Company::withoutGlobalScopes()->whereKey($companyId)->exists()) {
                    throw new \LogicException('An active company is required to create a subject.');
                }

                return $companyId;
            },
            'parent_id' => null,
            'code' => uniqid('tmp', false),
            'name' => $this->faker?->name,
            'type' => SubjectType::BOTH,
        ];
    }

    public function withParent(?Subject $parent = null): static
    {
        $state = [
            'parent_id' => $parent?->id,
        ];

        if ($parent) {
            $state['is_permanent'] = $parent->is_permanent;

            if (! $parent->type->isBoth()) {
                $state['type'] = $parent->type;
            }
        }

        return $this->state($state);
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
