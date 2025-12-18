<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductGroup;
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

    /**
     * Attach one or more websites after creating the product.
     * Usage: Product::factory()->withWebsites(2)->create();
     */
    public function withWebsites(int $count = 1)
    {
        return $this->afterCreating(function (Product $product) use ($count) {
            \App\Models\ProductWebsite::factory()->count($count)->create(['product_id' => $product->id]);
        });
    }

    public function withGroup(?ProductGroup $group = null): static
    {
        return $this->state(function () use ($group) {
            $groupToUse = $group ?? ProductGroup::factory()->create();

            return [
                'group' => $groupToUse->id,
            ];
        });
    }

    /**
     * Create accounting subjects for this product (income, sales returns, cogs, inventory).
     * Subjects will have their parent set from the product group when available.
     */
    public function withSubjects(): static
    {
        return $this->afterCreating(function (Product $product) {
            $group = $product->productGroup;
            $companyId = $product->company_id ?? $group?->company_id ?? session('active-company-id');

            $subjectColumns = [
                'income_subject_id',
                'sales_returns_subject_id',
                'cogs_subject_id',
                'inventory_subject_id',
            ];

            foreach ($subjectColumns as $column) {
                $subject = \App\Models\Subject::factory()
                    ->state([
                        'name' => $product->name,
                        'parent_id' => $group?->{$column},
                        'company_id' => $companyId,
                    ])
                    ->withAutoCode()
                    ->create();
                $product->{$column} = $subject->id;
            }
            $product->saveQuietly();
        });
    }
}
