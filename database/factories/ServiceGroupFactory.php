<?php

namespace Database\Factories;

use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'vat' => 0,
            'sstid' => $this->faker->optional()->word,
            'company_id' => 1,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (ServiceGroup $group) {
            $companyId = $group->company_id;
            $parent = Subject::withoutGlobalScopes()->find(config('amir.sales_revenue'));

            $subject = Subject::factory()
                ->state([
                    'name' => $group->name,
                    'company_id' => $companyId,
                ])
                ->withParent($parent)
                ->create();

            $group->updateQuietly(['subject_id' => $subject->id]);
        });
    }
}
