<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'value' => $this->faker->randomFloat(2, 100, 1000),
            'subject_id' => $this->faker->randomElement(Subject::all()->toArray()),
            'document_id' => $this->faker->randomElement(Document::all()->toArray()),
            'user_id' => $this->faker->randomElement(User::all()->toArray()),
            'desc' => $this->faker->paragraph(2),
        ];
    }
}
