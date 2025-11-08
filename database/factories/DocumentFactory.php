<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => Document::max('number') + 1,
            'date' => $this->faker->date(),
            'creator_id' => User::inRandomOrder()->first()->id,
            'title' => $this->faker->persianSentence(),
            'company_id' => Company::inRandomOrder()->first()->id,
        ];
    }
}
