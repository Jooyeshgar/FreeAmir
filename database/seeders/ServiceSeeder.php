<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ServiceGroup::withoutGlobalScopes()->where('company_id', getActiveCompany())->get();

        foreach ($groups as $group) {
            Service::factory()->count(10)->withGroup($group)->withSubject()->create();
        }
    }
}
