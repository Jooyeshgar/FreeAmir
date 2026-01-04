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
            'subject_id' => Subject::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'document_id' => Document::withoutGlobalScopes()->inRandomOrder()->first()->id,
            'user_id' => User::inRandomOrder()->first()->id,
            'desc' => $this->faker->paragraph(2),
        ];
    }
}
