<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ProductGroup::withoutGlobalScopes()->get();

        foreach ($groups as $group) {
            Product::factory()->count(10)->withGroup($group)->withSubjects()->create();
        }
    }
}
