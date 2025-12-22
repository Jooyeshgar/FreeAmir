<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        // session(['active-company-id' => 1]);
        $bankIds = Bank::pluck('id')->toArray();

        return [
            'company_id' => session('active-company-id'),
            'name' => $this->faker->name,
            'phone' => substr($this->faker->phoneNumber, 0, 15),
            'cell' => substr($this->faker->phoneNumber, 0, 15),
            'fax' => substr($this->faker->phoneNumber, 0, 15),
            'address' => $this->faker->address,
            'postal_code' => $this->faker->postcode,
            'email' => $this->faker->unique()->safeEmail(),
            'ecnmcs_code' => $this->faker->numerify('######'),
            'personal_code' => $this->faker->numerify('######'),
            'web_page' => substr($this->faker->url, 0, 50),
            'responsible' => $this->faker->name,
            'connector' => $this->faker->name,
            'group_id' => $this->faker->randomElement(CustomerGroup::pluck('id')->toArray()),
            'desc' => $this->faker->persianSentence(),
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'credit' => $this->faker->randomFloat(2, 0, 10000),
            'rep_via_email' => $this->faker->boolean,
            'acc_name_1' => $this->faker->name,
            'acc_no_1' => $this->faker->bankAccountNumber,
            'acc_bank_1' => $this->faker->randomElement($bankIds),
            'acc_name_2' => $this->faker->name,
            'acc_no_2' => $this->faker->bankAccountNumber,
            'acc_bank_2' => $this->faker->randomElement($bankIds),
            'type_buyer' => $this->faker->boolean,
            'type_seller' => $this->faker->boolean,
            'type_mate' => $this->faker->boolean,
            'type_agent' => $this->faker->boolean,
            'introducer_id' => $this->faker->randomElement(Customer::pluck('id')->toArray()),
            'commission' => $this->faker->randomFloat(2, 0, 100),
            'marked' => $this->faker->boolean,
            'reason' => $this->faker->text,
            'disc_rate' => $this->faker->randomFloat(2, 0, 100),
        ];
    }

    public function withGroup(?CustomerGroup $group = null): static
    {
        return $this->state(function () use ($group) {
            $groupToUse = $group ?? CustomerGroup::factory()->create();

            return [
                'group_id' => $groupToUse->id,
            ];
        });
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (Customer $customer) {
            $companyId = $customer->company_id ?? session('active-company-id');
            $parentId = $customer->group?->subject_id ?? null;

            Subject::factory()
                ->state([
                    'name' => $customer->name,
                    'parent_id' => $parentId,
                    'company_id' => $companyId,
                ])
                ->for($customer, 'subjectable') // <--- This handles the MorphOne magic
                ->withAutoCode()
                ->create();

        });
    }
}
