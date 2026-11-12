<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();

        if (CustomerGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->where('name', 'عمومی')->exists()) {
            return;
        }

        CustomerGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'description' => 'گروه مشتریان عمومی',
                'fiscal_year_id' => $fiscalYearId,
            ]);
    }
}
