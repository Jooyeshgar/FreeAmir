<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(0, 1, 10);
        $unit_price = $this->faker->randomFloat(0, 100000, 1000000);

        $product = Product::withoutGlobalScopes()->inRandomOrder()->first();
        $service = Service::withoutGlobalScopes()->inRandomOrder()->first();

        if (! $product && ! $service) {
            $product = Product::factory()->withGroup()->withSubjects()->create();
        }

        $availableTypes = [];
        if ($product) {
            $availableTypes[] = Product::class;
        }
        if ($service) {
            $availableTypes[] = Service::class;
        }

        $itemableType = $this->faker->randomElement($availableTypes);
        $itemableId = $itemableType === Product::class ? $product->id : $service->id;

        return [
            'description' => $this->faker->paragraph(2),
            'itemable_id' => $itemableId,
            'itemable_type' => $itemableType,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'unit_discount' => 0,
            'vat' => 0,
            'amount' => $quantity * $unit_price,
        ];
    }
}
