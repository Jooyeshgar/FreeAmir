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
        $fiscalYearId = (int) getActiveFiscalYear();
        $currentYear = jdate('Y', tr_num: 'en');

        $companyId = Company::updateOrCreate(
            ['name' => 'نام شرکت']
        )->id;

        $fiscalYear = $fiscalYearId === 1 ? FiscalYear::updateOrCreate(['id' => $fiscalYearId], [
            'id' => $fiscalYearId,
            'year' => $currentYear,
            'company_id' => $companyId,
        ]) : FiscalYear::find($fiscalYearId);

        if (! $fiscalYear) {
            throw new RuntimeException("Fiscal year with ID {$fiscalYearId} does not exist.");
        }

        if ($fiscalYearId === 1) {
            $users = User::all();
            foreach ($users as $user) {
                $user->fiscalYears()->syncWithoutDetaching([$fiscalYear->id]);
            }
        }
    }
}
