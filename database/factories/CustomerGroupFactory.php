<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveCompany() ?? Company::factory()->create()->id;

        return [
            'name' => $this->faker->name,
            'description' => $this->faker->text,
            'company_id' => $companyId,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (CustomerGroup $group) {
            $parent = Subject::withoutGlobalScopes()
                ->where('id', config('amir.cust_subject'))
                ->where('company_id', $group->company_id)
                ->first();

            $subject = Subject::factory()
                ->withParent($parent)
                ->create([
                    'name' => $group->name,
                    'company_id' => $group->company_id,
                ]);

            $group->subject_id = $subject->id;
            $group->saveQuietly();
        });
    }
}
