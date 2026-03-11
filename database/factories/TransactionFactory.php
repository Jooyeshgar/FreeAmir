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
        $subject = Subject::withoutGlobalScopes()->inRandomOrder()->first() ?? Subject::factory()->create();
        $document = Document::withoutGlobalScopes()->inRandomOrder()->first() ?? Document::factory()->create();
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        return [
            'value' => $this->faker->randomFloat(2, 100, 1000),
            'subject_id' => $subject->id,
            'document_id' => $document->id,
            'user_id' => $user->id,
            'desc' => $this->faker->paragraph(2),
        ];
    }
}
