<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run()
    {
        $productGroup = [
            'name' => 'عمومی',
            'vat' => 10,
            'buyId' => 1,
            'sellId' => 1,
            'company_id' => 1,
        ];

        ProductGroup::create($productGroup);
    }
}
