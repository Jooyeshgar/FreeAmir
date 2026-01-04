<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'parent_id' => null,
            'code' => '',
            'name' => $this->faker->name,
            'type' => 'both',
        ];
    }

    public function withParent(Subject $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Subject $subject) {
            $parent = Subject::withoutGlobalScopes()
                ->where('id', $subject->parent_id)
                ->firstOrFail();

            $count = Subject::withoutGlobalScopes()
                ->where('company_id', $subject->company_id)
                ->where('parent_id', $parent->id)
                ->count();

            $next = str_pad($count, 3, '0', STR_PAD_LEFT);

            $subject->code = $parent->code.$next;
            $subject->saveQuietly();
        });
    }
}
