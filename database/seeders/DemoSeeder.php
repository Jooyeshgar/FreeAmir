<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
    }
}
