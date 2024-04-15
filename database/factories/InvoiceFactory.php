<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'code' => $this->faker->unique()->numerify('INV-####'),
            'date' => $this->faker->date(),
            'document_id' => Document::factory(),
            'customer_id' => Customer::factory(),
            'addition' => $this->faker->randomFloat(2, 0, 1000),
            'subtraction' => $this->faker->randomFloat(2, 0, 1000),
            'tax' => $this->faker->randomFloat(2, 0, 10),
            'cash_payment' => $this->faker->boolean,
            'ship_date' => $this->faker->optional()->dateTime(),
            'ship_via' => $this->faker->company(),
            'permanent' => $this->faker->boolean,
            'description' => $this->faker->sentence(5),
            'is_sell' => $this->faker->boolean,
            'active' => true,
            'vat' => $this->faker->randomNumber(5),
            'amount' => $this->faker->randomFloat(2, 1000, 10000),
        ];
    }
}
