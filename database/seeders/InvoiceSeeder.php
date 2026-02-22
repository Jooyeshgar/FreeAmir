<?php

namespace Database\Seeders;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $startOfYear = Carbon::create(2024, 3, 21); // 1403-01-01 in Jalali

        $randomDateInMonth = function (int $monthOffset) use ($startOfYear) {
            $monthStart = (clone $startOfYear)->addMonths($monthOffset)->startOfMonth();
            $monthEnd = (clone $monthStart)->endOfMonth();

            return fake()->dateTimeBetween($monthStart, $monthEnd);
        };

        foreach (range(0, 11) as $monthOffset) {
            Invoice::factory()->approved()->count(4)->state(fn () => ['date' => $randomDateInMonth($monthOffset)])->create();

            Invoice::factory()->approved()->buyServicesOnly()->count(3)->state(fn () => ['date' => $randomDateInMonth($monthOffset)])->create();

            Invoice::factory()->approved()->sellServicesOnly()->count(3)->state(fn () => ['date' => $randomDateInMonth($monthOffset)])->create();

            Invoice::factory()->unapproved()->buyServicesOnly()->count(1)->state(fn () => ['date' => $randomDateInMonth($monthOffset)])->create();

            Invoice::factory()->unapproved()->sellServicesOnly()->count(1)->state(fn () => ['date' => $randomDateInMonth($monthOffset)])->create();
        }

        Invoice::factory()->approved()->buyServicesOnly()->count(20)->create();
        Invoice::factory()->approved()->sellServicesOnly()->count(20)->create();
    }
}
