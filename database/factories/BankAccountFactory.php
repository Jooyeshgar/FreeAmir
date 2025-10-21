<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        $bankIds = Bank::withoutGlobalScopes()->where('company_id', 1)->pluck('id')->toArray();
        $companyIds = Company::pluck('id')->toArray();

        return [
            'name' => $this->faker->name,
            'number' => uniqid('BA-'),
            'type' => $this->faker->randomDigit(),
            'owner' => $this->faker->name(),
            'bank_id' => $this->faker->randomElement($bankIds),
            'company_id' => $this->faker->randomElement($companyIds),
            'bank_branch' => $this->faker->address,
            'bank_address' => $this->faker->address,
            'bank_phone' => substr($this->faker->phoneNumber, 0, 15),
            'bank_web_page' => $this->faker->url,
            'desc' => $this->faker->persianSentence(),
        ];
    }
}
