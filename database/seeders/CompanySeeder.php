<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Create the company
        $company = Company::create([
            'id' => 1,
            'name' => 'نام شرکت',
            'address' => '',
            'postal_code' => '',
            'phone_number' => '',
            'fiscal_year' => '1403',
        ]);

        // Attach the company to all users
        $users = User::all();
        foreach ($users as $user) {
            $user->companies()->attach($company->id);
        }
    }
}
