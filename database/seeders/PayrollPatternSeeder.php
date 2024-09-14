<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PayrollPattern;

class PayrollPatternSeeder extends Seeder
{
    /**
     * Seed the database with payroll pattern data.
     *
     * @return void
     */
    public function run()
    {
        PayrollPattern::factory()->count(10)->create();
    }
}
