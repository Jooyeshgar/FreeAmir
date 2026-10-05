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
        $creator = User::inRandomOrder()->first() ?? User::factory()->create();
        $company = Company::withoutGlobalScopes()->find(getActiveCompany());

        if (! $company) {
            throw new \LogicException('An active company is required to create a document.');
        }

        return [
            'number' => (Document::withoutGlobalScopes()->max('number') ?? 0) + 1,
            'date' => $this->faker->date(),
            'creator_id' => $creator->id,
            'title' => $this->faker->persianSentence(),
            'company_id' => $company->id,
        ];
    }
}
