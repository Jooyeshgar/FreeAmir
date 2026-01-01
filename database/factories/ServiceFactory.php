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
            'code' => 'S-'.$this->faker->unique()->numerify('#####'),
            'name' => $this->faker->words(3, true),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'description' => $this->faker->sentence,
            'company_id' => Company::inRandomOrder()->first()->id,
            'vat' => 0,
            'subject_id' => null,
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

    public function withSubjects(): static
    {
        return $this->afterCreating(function (Service $service) {
            $subject = Subject::factory()
                ->state([
                    'name' => $service->name,
                    'parent_id' => $service->serviceGroup?->subject_id,
                    'company_id' => $service->company_id ?? $service->serviceGroup?->company_id,
                ])
                ->withAutoCode()
                ->create();

            $service->saveQuietly(['subject_id' => $subject->id]);
        });
    }
}
