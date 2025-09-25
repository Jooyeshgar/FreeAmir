<?php

namespace Database\Seeders;

use App\Models\ProductWebsite;
use Illuminate\Database\Seeder;

class ProductWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        ProductWebsite::factory()->count(50)->create();
    }
}
