<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();

        foreach ([
            ['name' => 'انبار اصلی', 'code' => 'MAIN'],
            ['name' => 'انبار مرکزی', 'code' => 'CENTRAL'],
            ['name' => 'انبار شعبه', 'code' => 'BRANCH'],
            ['name' => 'انبار معیوب', 'code' => 'DAMAGED'],
        ] as $warehouse) {
            Warehouse::withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $companyId, 'name' => $warehouse['name']],
                ['code' => $warehouse['code']],
            );
        }
    }
}
