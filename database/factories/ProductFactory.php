<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'code' => 'P-'.$this->faker->unique()->numerify('#####'),
            'name' => $this->faker->words(3, true),
            'sstid' => $this->faker->optional()->word,
            'group' => null,
            'location' => $this->faker->optional()->word,
            'quantity' => 0,
            'quantity_warning' => $this->faker->numberBetween(0, 20),
            'oversell' => 0,
            'selling_price' => $this->faker->randomFloat(2, 0, 10000),
            'discount_formula' => null,
            'description' => $this->faker->sentence,
            'company_id' => 1,
            'vat' => 0,
            'average_cost' => 0,
        ];
    }

    public function withGroup(ProductGroup $group): static
    {
        return $this->state([
            'group' => $group->id,
        ]);
    }

    public function withSubjects(): static
    {
        return $this->afterCreating(function (Product $product) {
            $group = ProductGroup::withoutGlobalScopes()->find($product->group);

            $subjectColumns = [
                'income_subject_id',
                'sales_returns_subject_id',
                'cogs_subject_id',
                'inventory_subject_id',
            ];

            foreach ($subjectColumns as $column) {
                $parentId = $group->{$column};

                $subject = Subject::factory()
                    ->withParent(Subject::withoutGlobalScopes()->find($parentId))
                    ->create([
                        'name' => $product->name,
                        'company_id' => $product->company_id,
                    ]);

                $product->{$column} = $subject->id;
            }
            $product->saveQuietly();
        });
    }
}
