<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\FiscalYear;
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
        ]) : Company::find($companyId);

        if (! $company) {
            throw new RuntimeException("Company with ID {$companyId} does not exist.");
        }

        $year = FiscalYear::firstOrCreate(['company_id' => $company->id, 'year' => $fiscalYear]);
        config(['active-fiscal-year-id' => $year->id]);

        if ($companyId === 1) {
            $users = User::all();
            foreach ($users as $user) {
                $user->companies()->syncWithoutDetaching([$company->id]);
                $user->fiscalYears()->syncWithoutDetaching([$year->id]);
            }
        }
    }
}
