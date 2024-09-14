<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BenefitsDeduction;

class BenefitsDeductionSeeder extends Seeder
{
    /**
     * Seed the database with benefits and deductions data.
     *
     * @return void
     */
    public function run()
    {
        BenefitsDeduction::factory()->count(10)->create();
    }
}
