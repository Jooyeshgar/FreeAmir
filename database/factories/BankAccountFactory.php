<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Subject;
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
            'owner' => $this->faker->name,
            'bank_id' => $this->faker->randomElement($bankIds),
            'company_id' => $this->faker->randomElement($companyIds),
            'bank_branch' => $this->faker->address,
            'bank_address' => $this->faker->streetAddress,
            'bank_phone' => substr($this->faker->phoneNumber, 0, 15),
            'bank_web_page' => $this->faker->url,
            'desc' => $this->faker->paragraph(2),
        ];
    }

    public function withBank(Bank $bank)
    {
        return $this->state([
            'bank_id' => $bank->id,
        ]);
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function ($bankAccount) {
            $bank = Bank::withoutGlobalScopes()->find($bankAccount->bank_id);
            $parentSubject = Subject::withoutGlobalScopes()->find(config('amir.bank') ?? 1);

            Subject::factory()
                ->withParent($parentSubject)
                ->for($bankAccount, 'subjectable')
                ->create([
                    'name' => $bankAccount->name.' - '.$bank->name,
                    'company_id' => $bankAccount->company_id,
                ]);
        });
    }
}
