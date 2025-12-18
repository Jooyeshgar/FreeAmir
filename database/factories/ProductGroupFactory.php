<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductGroup>
 */
class ProductGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'vat' => 0,
            'company_id' => 1,
        ];
    }

    public function withSubjects(): static
    {
        return $this->afterCreating(function (\App\Models\ProductGroup $productGroup) {
            $subjects = [
                'income_subject_id' => [
                    'name' => $productGroup->name,
                    'parent_id' => config('amir.sales_revenue'),
                ],
                'sales_returns_subject_id' => [
                    'name' => $productGroup->name,
                    'parent_id' => config('amir.sales_returns'),
                ],
                'cogs_subject_id' => [
                    'name' => $productGroup->name,
                    'parent_id' => config('amir.cost_of_goods_sold'),
                ],
                'inventory_subject_id' => [
                    'name' => $productGroup->name,
                    'parent_id' => config('amir.inventory'),
                ],
            ];

            $subjectIds = [];

            foreach ($subjects as $field => $attributes) {
                $subject = \App\Models\Subject::factory()
                    ->state(array_merge($attributes, [
                        'company_id' => $productGroup->company_id,
                    ]))
                    ->withAutoCode()
                    ->create();

                $subjectIds[$field] = $subject->id;
            }

            $productGroup->updateQuietly($subjectIds);
        });
    }
}
