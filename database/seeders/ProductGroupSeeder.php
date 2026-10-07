<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(?int $fiscalYearId = null): void
    {
        $fiscalYearId ??= (int) getActiveFiscalYear();

        if (ProductGroup::withoutGlobalScopes()->where('fiscal_year_id', $fiscalYearId)->where('name', 'عمومی')->exists()) {
            return;
        }

        ProductGroup::factory()
            ->withSubjects()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'fiscal_year_id' => $fiscalYearId,
            ]);
    }
}
