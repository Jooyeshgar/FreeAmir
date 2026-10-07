<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();

        if (! FiscalYear::withoutGlobalScopes()->whereKey($fiscalYearId)->exists()) {
            throw new RuntimeException("Fiscal year with ID {$fiscalYearId} does not exist.");
        }

        $previousActiveFiscalYearId = config('active-fiscal-year-id');
        config(['active-fiscal-year-id' => $fiscalYearId]);

        try {
            $this->call([
                WarehouseSeeder::class,
                BankAccountSeeder::class,
                CustomerSeeder::class,
                ProductSeeder::class,
                ServiceSeeder::class,
                InvoiceSeeder::class,
                CommentSeeder::class,
                DocumentFileSeeder::class,
                AttendanceLogSeeder::class,
                PersonnelRequestSeeder::class,
                PayrollElementSeeder::class,
                SalaryDecreeSeeder::class,
                MonthlyAttendanceSeeder::class,
                PayrollSeeder::class,
                HomeSeeder::class,
            ]);
        } finally {
            config(['active-fiscal-year-id' => $previousActiveFiscalYearId]);
        }
    }
}
