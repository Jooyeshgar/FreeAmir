<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();

        if (! Company::withoutGlobalScopes()->whereKey($companyId)->exists()) {
            throw new RuntimeException("Company with ID {$companyId} does not exist.");
        }

        $previousActiveCompanyId = config('active-company-id');
        config(['active-company-id' => $companyId]);

        try {
            $this->call([
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
            config(['active-company-id' => $previousActiveCompanyId]);
        }
    }
}
