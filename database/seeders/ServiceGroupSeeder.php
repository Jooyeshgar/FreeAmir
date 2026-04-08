<?php

namespace Database\Seeders;

use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceGroupSeeder extends Seeder
{
    public function run(): void
    {
        ServiceGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'company_id' => 1,
            ]);
    }
}
