<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) getActiveCompany();

        if (ProductGroup::withoutGlobalScopes()->where('company_id', $companyId)->where('name', 'عمومی')->exists()) {
            return;
        }

        ProductGroup::factory()
            ->withSubjects()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'company_id' => $companyId,
            ]);
    }
}
