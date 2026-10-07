<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();
        $previousActiveFiscalYearId = config('active-fiscal-year-id');
        config(['active-fiscal-year-id' => $fiscalYearId]);

        try {
            $this->call([
                CompanySeeder::class,
                WarehouseSeeder::class,
                SubjectSeeder::class,
                ConfigSeeder::class,
                BankSeeder::class,
                CustomerGroupSeeder::class,
                ProductGroupSeeder::class,
                ServiceGroupSeeder::class,
                OrgChartSeeder::class,
                OrganizationUnitSeeder::class,
                RolesAndPermissionsSeeder::class,
            ]);
        } finally {
            config(['active-fiscal-year-id' => $previousActiveFiscalYearId]);
        }
    }
}
