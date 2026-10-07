<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use App\Models\FiscalYear;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
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
        $fiscalYearId = FiscalYear::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveFiscalYear() ?? FiscalYear::factory()->create()->id;

        return [
            'name' => $this->faker?->name,
            'description' => $this->faker?->text,
            'fiscal_year_id' => $fiscalYearId,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (CustomerGroup $group) {
            $parent = Subject::withoutGlobalScopes()
                ->where('id', config('amir.cust_subject'))
                ->where('fiscal_year_id', $group->fiscal_year_id)
                ->first();

            $subject = Subject::factory()
                ->withParent($parent)
                ->for($group, 'subjectable')
                ->create([
                    'name' => $group->name,
                    'fiscal_year_id' => $group->fiscal_year_id,
                ]);

            $group->subject_id = $subject->id;
            $group->saveQuietly();
        });
    }
}
