<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {

        $company = [
            ['id' => 1, 'name' => 'نام شرکت', 'address' => '', 'postal_code' => '', 'phone_number' => '', 'fiscal_year' => '1403'],
        ];

        Company::insert($company);
    }
}
