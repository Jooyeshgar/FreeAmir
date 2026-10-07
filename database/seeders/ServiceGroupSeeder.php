<?php

namespace Database\Seeders;

use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceGroupSeeder extends Seeder
{
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();

        if (ServiceGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->where('name', 'عمومی')->exists()) {
            return;
        }

        ServiceGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'fiscal_year_id' => $fiscalYearId,
            ]);
    }
}
