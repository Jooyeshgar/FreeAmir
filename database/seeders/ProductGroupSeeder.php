<?php

namespace Database\Seeders;



use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductGroupSeeder extends Seeder
{
    public function run()
    {
        $productGroups = [
            ['id' => 1, 'code' => 'general', 'name' => 'عمومی', 'buyId' => 1, 'sellId' => 1],
        ];

        DB::table('product_groups')->insert($productGroups);
    }
}
