<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) getActiveCompany();
        $fiscalYear = jdate('Y', tr_num: 'en');

        $company = $companyId === 1 ? Company::updateOrCreate(['id' => $companyId], [
            'id' => $companyId,
            'name' => 'نام شرکت',
            'fiscal_year' => $fiscalYear,
        ]) : Company::find($companyId);

        if (! $company) {
            throw new RuntimeException("Company with ID {$companyId} does not exist.");
        }

        if ($companyId === 1) {
            $users = User::all();
            foreach ($users as $user) {
                $user->companies()->syncWithoutDetaching([$company->id]);
            }
        }
    }
}
