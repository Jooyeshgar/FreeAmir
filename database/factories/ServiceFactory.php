<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('#####'),
            'name' => $this->faker->words(3, true),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->sentence,
            'company_id' => Company::inRandomOrder()->first()->id,
            'vat' => 0,
        ];
    }

    public function withGroup(?ServiceGroup $group = null): static
    {
        return $this->state(function () use ($group) {
            $groupToUse = $group ?? ServiceGroup::factory()->create([
                'company_id' => Company::inRandomOrder()->first()->id,
            ]);

            return [
                'group' => $groupToUse->id,
            ];
        });
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (Service $service) {
            $group = ServiceGroup::withoutGlobalScopes()->find($service->group);

            $subject = Subject::factory()
                ->withParent(Subject::withoutGlobalScopes()->find($group->subject_id))
                ->create([
                    'name' => $service->name,
                    'company_id' => $service->company_id,
                ]);

            $service->saveQuietly(['subject_id' => $subject->id]);
        });
    }
}
