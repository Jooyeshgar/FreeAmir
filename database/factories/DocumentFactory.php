<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companies = Company::pluck('id')->toArray();

        return [
            'number' => DB::table('documents')->max('number') + 1,
            'date' => $this->faker->date(),
            'creator_id' => User::all()->random()->id,
            'title' => $this->faker->persianSentence(),
            'company_id' => $this->faker->randomElement($companies),
        ];
    }
}
