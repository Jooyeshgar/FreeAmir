<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::find(getActiveCompany()) ?? Company::factory()->create();
        $date = jalali_to_gregorian($company->fiscal_year, 1, 1);
        $startOfYear = Carbon::create($date[0], $date[1], $date[2]);
        
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
