<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        $bankIds = Bank::withoutGlobalScopes()->pluck('id')->toArray();

        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveCompany() ?? Company::factory()->create()->id;
        $group = CustomerGroup::withoutGlobalScopes()->where('company_id', $companyId)->whereNotNull('subject_id')->inRandomOrder()->first();

        $customerIds = Customer::withoutGlobalScopes()->pluck('id')->toArray();

        return [
            'company_id' => $companyId,
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
            'group_id' => $group?->id,
            'desc' => $this->faker->persianSentence(),
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'credit' => $this->faker->randomFloat(2, 0, 10000),
            'rep_via_email' => $this->faker->boolean,
            'acc_name_1' => $this->faker->name,
            'acc_no_1' => $this->faker->bankAccountNumber,
            'acc_bank_1' => empty($bankIds) ? '' : (string) $this->faker->randomElement($bankIds),
            'acc_name_2' => $this->faker->name,
            'acc_no_2' => $this->faker->bankAccountNumber,
            'acc_bank_2' => empty($bankIds) ? '' : (string) $this->faker->randomElement($bankIds),
            'type_buyer' => $this->faker->boolean,
            'type_seller' => $this->faker->boolean,
            'type_mate' => $this->faker->boolean,
            'type_agent' => $this->faker->boolean,
            'introducer_id' => empty($customerIds) ? null : $this->faker->randomElement($customerIds),
            'commission' => $this->faker->randomFloat(2, 0, 100),
            'marked' => $this->faker->boolean,
            'reason' => $this->faker->text,
            'disc_rate' => $this->faker->randomFloat(2, 0, 100),
        ];
    }

    public function withGroup(?CustomerGroup $group = null): static
    {
        $group ??= CustomerGroup::factory()->withSubject()->create();

        return $this->state([
            'group_id' => $group->id,
        ]);
    }

    public function withSubject(): static
    {
        return $this->afterCreating(function (Customer $customer) {
            $group = CustomerGroup::withoutGlobalScopes()->find($customer->group_id);

            Subject::factory()
                ->withParent(Subject::withoutGlobalScopes()->find($group->subject_id))
                ->for($customer, 'subjectable')
                ->create([
                    'name' => $customer->name,
                    'company_id' => $customer->company_id,
                ]);
        });
    }
}
