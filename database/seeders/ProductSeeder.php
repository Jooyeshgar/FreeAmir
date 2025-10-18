<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $products = Product::factory()->count(50)->create();
        foreach ($products as $product) {
            $subject = Subject::create([
                'company_id' => $product->company_id,
                'subjectable_type' => Product::class,
                'subjectable_id' => $product->id,
                'parent_id' => 100,
                'code' => '019001'.$product->id,
                'name' => $product->name,
                'type' => 1,
            ]);
            $product->subject_id = $subject->id;
            $product->update();
        }
    }
}
