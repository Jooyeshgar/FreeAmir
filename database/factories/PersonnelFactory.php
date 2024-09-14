<?php

namespace Database\Factories;

use App\Models\Personnel;
use App\Models\Bank;
use App\Models\OrganizationalChart;
use App\Models\Workhouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Personnel>
 */
class PersonnelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'personnel_code' => $this->faker->unique()->numerify('P######'),
            'father_name' => $this->faker->firstName,
            'nationality' => $this->faker->randomElement(['iranian', 'non_iranian']),
            'identity_number' => $this->faker->unique()->numerify('ID######'),
            'national_code' => $this->faker->unique()->numerify('NC######'),
            'passport_number' => $this->faker->unique()->bothify('P#######'),
            'marital_status' => $this->faker->randomElement(['single', 'married', 'divorced', 'widowed']),
            'gender' => $this->faker->randomElement(['female', 'male', 'other']),
            'contact_number' => $this->faker->phoneNumber,
            'address' => $this->faker->address,
            'insurance_number' => $this->faker->unique()->numerify('INS######'),
            'insurance_type' => $this->faker->randomElement(['social_security', 'other']),
            'children_count' => $this->faker->numberBetween(0, 5),
            'bank_id' => Bank::factory(),
            'account_number' => $this->faker->bankAccountNumber,
            'card_number' => $this->faker->creditCardNumber,
            'iban' => $this->faker->iban,
            'detailed_code' => $this->faker->word,
            'contract_start_date' => $this->faker->date(),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contract']),
            'contract_type' => $this->faker->randomElement(['official', 'contract', 'other']),
            'birth_place' => $this->faker->city,
            'organizational_chart_id' => OrganizationalChart::factory(),
            'military_status' => $this->faker->randomElement(['not_subject', 'completed', 'in_progress']),
            'workhouse_id' => Workhouse::factory(),
        ];
    }
}
