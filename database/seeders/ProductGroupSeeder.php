<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        ProductGroup::factory()
            ->withSubjects()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'company_id' => 1,
            ]);
    }
}
