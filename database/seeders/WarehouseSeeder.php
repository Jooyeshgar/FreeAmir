<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();

        foreach ([
            ['name' => 'انبار اصلی', 'code' => 'MAIN'],
            ['name' => 'انبار مرکزی', 'code' => 'CENTRAL'],
            ['name' => 'انبار شعبه', 'code' => 'BRANCH'],
            ['name' => 'انبار معیوب', 'code' => 'DAMAGED'],
        ] as $warehouse) {
            Warehouse::withoutGlobalScopes()->updateOrCreate(
                ['fiscal_year_id' => $fiscalYearId, 'name' => $warehouse['name']],
                ['code' => $warehouse['code']],
            );
        }
    }
}
