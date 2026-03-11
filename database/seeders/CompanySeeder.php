<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $fiscalYear = jdate("Y", tr_num: 'en');
        $company = Company::updateOrCreate(['id' => 1], [
            'id' => 1,
            'name' => 'نام شرکت',
            'fiscal_year' => $fiscalYear,
        ]);

        $users = User::all();
        foreach ($users as $user) {
            $user->companies()->syncWithoutDetaching([$company->id]);
        }
    }
}
