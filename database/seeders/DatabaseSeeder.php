<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();
        $previousActiveCompanyId = config('active-company-id');
        config(['active-company-id' => $companyId]);

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
            config(['active-company-id' => $previousActiveCompanyId]);
        }
    }
}
