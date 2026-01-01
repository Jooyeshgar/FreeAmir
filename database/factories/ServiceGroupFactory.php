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
            'code' => $this->faker->unique()->numerify('SG-###'),
            'name' => $this->faker->name,
            'vat' => 0,
            'sstid' => $this->faker->optional()->word,
            'company_id' => 1,
            'subject_id' => null,
        ];
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (ServiceGroup $serviceGroup) {
            $subjectDetail = [
                'name' => $serviceGroup->name,
                'parent_id' => config('amir.services_revenue'),
            ];

            $subject = Subject::factory()
                ->state(array_merge($subjectDetail, [
                    'company_id' => $serviceGroup->company_id,
                ]))
                ->withAutoCode()
                ->create();

            $serviceGroup->updateQuietly(['subject_id' => $subject->id]);
        });
    }
}
