<?php

namespace Database\Seeders;

use App\Services\ProductGroupService;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run()
    {
        $productGroupService = app(ProductGroupService::class);

        $productGroupService->create([
            'name' => 'عمومی',
            'vat' => 10,
            'buyId' => 1,
            'sellId' => 1,
            'company_id' => 1,
        ]);
    }
}
