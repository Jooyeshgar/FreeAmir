<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrganizationalChart;

class OrganizationalChartSeeder extends Seeder
{
    /**
     * Seed the database with organizational chart data.
     *
     * @return void
     */
    public function run()
    {
        OrganizationalChart::factory()->count(10)->create();
    }
}
