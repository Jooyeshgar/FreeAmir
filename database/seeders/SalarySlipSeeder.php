<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalarySlip;

class SalarySlipSeeder extends Seeder
{
    /**
     * Seed the database with salary slip data.
     *
     * @return void
     */
    public function run()
    {
        SalarySlip::factory()->count(10)->create()->each(function ($salarySlip) {
            // Attach random benefits deductions to each salary slip
            $benefitDeductions = \App\Models\BenefitsDeduction::all()->random(3);
            foreach ($benefitDeductions as $benefitDeduction) {
                $salarySlip->benefitsDeductions()->attach($benefitDeduction->id, ['amount' => rand(50, 200)]);
            }
        });
    }
}
