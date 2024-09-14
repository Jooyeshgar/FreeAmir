<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Personnel;

class PersonnelSeeder extends Seeder
{
    /**
     * Seed the database with personnel data.
     *
     * @return void
     */
    public function run()
    {
        Personnel::factory()->count(10)->create();
    }
}
