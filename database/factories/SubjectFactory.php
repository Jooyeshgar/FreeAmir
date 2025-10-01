<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subject = Subject::inRandomOrder()->first();

        return [
            'code' => $this->faker->unique()->ean8(),
            'name' => $this->faker->name(),
            'parent_id' => $subject->id,
            'type' => 2,
            'company_id' => 1,
        ];
    }
}
