<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            // Add your configurations here
             ['key' => 'config1', 'value' => 'value1', 'desc' => 'description1', 'type' => 'type1', 'category' => 'category1'],
            // ...
        ];

        DB::table('configs')->insert($configs);
    }
}
