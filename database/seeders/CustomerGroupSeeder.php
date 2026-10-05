<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();

        if (CustomerGroup::withoutGlobalScopes()->where('fiscal_year_id', getActiveFiscalYear())->where('name', 'عمومی')->exists()) {
            return;
        }

        CustomerGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'description' => 'گروه مشتریان عمومی',
                'company_id' => $companyId,
            ]);
    }
}
