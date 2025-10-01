<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;


class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'vat' => 10,
            'buyId' => 1,
            'sellId' => 1,
            'company_id' => 1,
            'subject_id' => 9,
        ];
    }
}
