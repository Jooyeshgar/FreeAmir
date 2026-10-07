<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\FiscalYear;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $creator = User::inRandomOrder()->first() ?? User::factory()->create();
        $fiscalYear = FiscalYear::withoutGlobalScopes()->find(getActiveFiscalYear());

        if (! $fiscalYear) {
            throw new \LogicException('An active fiscal year is required to create a document.');
        }

        return [
            'number' => (Document::withoutGlobalScopes()->max('number') ?? 0) + 1,
            'date' => $this->faker->date(),
            'creator_id' => $creator->id,
            'title' => $this->faker->persianSentence(),
            'fiscal_year_id' => $fiscalYear->id,
        ];
    }
}
