<?php

namespace Database\Seeders;

use App\Models\ServiceGroup;
use Illuminate\Database\Seeder;

class ServiceGroupSeeder extends Seeder
{
    public function run(?int $companyId = null): void
    {
        $companyId ??= (int) getActiveCompany();

        if (ServiceGroup::withoutGlobalScopes()->where('company_id', $companyId)->where('name', 'عمومی')->exists()) {
            return;
        }

        ServiceGroup::factory()
            ->withSubject()
            ->create([
                'name' => 'عمومی',
                'vat' => 10,
                'company_id' => $companyId,
            ]);
    }
}
