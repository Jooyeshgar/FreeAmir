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
            ConfigSeeder::class,
            SubjectSeeder::class,
            BankSeeder::class,
            CustomerGroupSeeder::class,
            ProductGroupSeeder::class,
            RolesAndPermissionsSeeder::class,
            CompanySeeder::class,
        ]);
    }
}
