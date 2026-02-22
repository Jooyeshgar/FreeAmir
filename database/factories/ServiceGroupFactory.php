<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceGroupFactory extends Factory
{
    public function definition(): array
    {
        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveCompany() ?? Company::factory()->create()->id;

        return [
            'name' => $this->faker->name,
            'vat' => 0,
            'sstid' => $this->faker->optional()->word,
            'company_id' => $companyId,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (ServiceGroup $group) {
            $companyId = $group->company_id;
            $subjectParent = Subject::withoutGlobalScopes()->find(config('amir.sales_revenue'));
            $cogsParent = Subject::withoutGlobalScopes()->find(config('amir.cogs_service'));

            $subject = Subject::factory()
                ->state([
                    'name' => $group->name,
                    'company_id' => $companyId,
                ])
                ->withParent($subjectParent)
                ->create();

            $cogsSubject = Subject::factory()
                ->state([
                    'name' => $group->name,
                    'company_id' => $companyId,
                ])
                ->withParent($cogsParent)
                ->create();

            $group->updateQuietly([
                'subject_id' => $subject->id,
                'cogs_subject_id' => $cogsSubject->id,
            ]);
        });
    }
}
