<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Workhouse;

class WorkhouseSeeder extends Seeder
{
    /**
     * Seed the database with workhouse data.
     *
     * @return void
     */
    public function run()
    {
        Workhouse::factory()->count(10)->create();
    }
}
