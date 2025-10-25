<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'code' => '019001'.$this->faker->unique()->numerify('###'),
            'company_id' => session('active-company-id'),
            'name' => $this->faker->name,
            'group' => 1,
            'location' => $this->faker->streetAddress,
            'quantity' => $this->faker->numberBetween(10, 1000),
            'quantity_warning' => $this->faker->randomDigitNotNull,
            'oversell' => $this->faker->boolean,
            'purchace_price' => $this->faker->randomFloat(2, 0, 1000),
            'selling_price' => $this->faker->randomFloat(2, 0, 1000),
            'discount_formula' => $this->faker->word,
            'description' => $this->faker->persianSentence(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function ($product) {
            $subject = Subject::create([
                'company_id' => $product->company_id,
                'subjectable_type' => Product::class,
                'subjectable_id' => $product->id,
                'parent_id' => 100,
                'code' => '019001'.$product->id,
                'name' => $product->name,
                'type' => 1,
            ]);
            $product->subject_id = $subject->id;
            $product->update();
        });
    }
}
