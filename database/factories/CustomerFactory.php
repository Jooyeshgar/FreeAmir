<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Bank;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition()
    {
        $bankIds = Bank::pluck('id')->toArray();
        return [
            'code' => $this->faker->unique()->numerify('#####'),
            'name' => $this->faker->name,
            'subject_id' => $this->faker->randomElement(Customer::pluck('id')->toArray()),
            'phone' => substr($this->faker->phoneNumber, 0, 15),
            'cell' => substr($this->faker->phoneNumber, 0, 15),
            'fax' => substr($this->faker->phoneNumber, 0, 15),
            'address' => $this->faker->address,
            'postal_code' => $this->faker->postcode,
            'email' => substr($this->faker->unique()->safeEmail, 0, 10),
            'ecnmcs_code' => $this->faker->numerify('######'),
            'personal_code' => $this->faker->numerify('######'),
            'web_page' => substr($this->faker->url, 0, 50),
            'responsible' => $this->faker->name,
            'connector' => $this->faker->name,
            'group_id' => 1,
            'desc' => $this->faker->text,
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
}
