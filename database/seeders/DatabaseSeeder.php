<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            SubjectSeeder::class,
            ConfigSeeder::class,
            BankSeeder::class,
            CustomerGroupSeeder::class,
            ProductGroupSeeder::class,
            ServiceGroupSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
