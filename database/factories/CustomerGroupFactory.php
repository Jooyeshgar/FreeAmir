<?php

namespace Database\Factories;

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

        return [
            'name' => $this->faker->name,
            'description' => $this->faker->text,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (CustomerGroup $customerGroup) {
            $companyId = $customerGroup->company_id ?? session('active-company-id');
            $parentId = $customerGroup->group?->subject_id ?? null;

            Subject::factory()
                ->state([
                    'name' => $customerGroup->name,
                    'parent_id' => $parentId,
                    'company_id' => $companyId,
                ])
                ->for($customerGroup, 'subjectable') // <--- This handles the MorphOne magic
                ->withAutoCode()
                ->create();

        });
    }
}
