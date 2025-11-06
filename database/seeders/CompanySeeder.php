<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'id' => 1,
            'name' => 'نام شرکت',
            'fiscal_year' => '1403',
        ]);

        $users = User::all();
        foreach ($users as $user) {
            $user->companies()->attach($company->id);
        }
    }
}
