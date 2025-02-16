<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run()
    {
        $productGroups = [
            ['code' => 'general', 'name' => 'عمومی', 'buyId' => 1, 'sellId' => 1, 'company_id' => 1],
        ];

        ProductGroup::insert($productGroups);
    }
}
