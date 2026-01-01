<?php

namespace Database\Seeders;

use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceGroupSeeder extends Seeder
{
    public function run(): void
    {
        ServiceGroup::factory()->count(10)->withSubject()->create();
    }
}
