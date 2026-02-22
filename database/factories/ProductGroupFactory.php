<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ProductGroup;
use App\Models\Subject;
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
        $companyId = Company::withoutGlobalScopes()->inRandomOrder()->value('id') ?? getActiveCompany() ?? Company::factory()->create()->id;

        return [
            'name' => $this->faker->name,
            'vat' => 0,
            'company_id' => $companyId,
        ];
    }

    public function withSubjects(): static
    {
        return $this->afterCreating(function (ProductGroup $group) {
            $companyId = $group->company_id;

            $map = [
                'income_subject_id' => config('amir.sales_revenue'),
                'sales_returns_subject_id' => config('amir.sales_returns'),
                'cogs_subject_id' => config('amir.cost_of_goods_sold'),
                'inventory_subject_id' => config('amir.inventory'),
            ];

            $updates = [];

            foreach ($map as $column => $parentId) {
                $parent = Subject::withoutGlobalScopes()->find($parentId);

                $subject = Subject::factory()
                    ->state([
                        'name' => $group->name,
                        'company_id' => $companyId,
                    ])
                    ->withParent($parent)
                    ->create();

                $updates[$column] = $subject->id;
            }

            $group->updateQuietly($updates);
        });
    }
}
