<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use Illuminate\Database\Seeder;

class OrganizationUnitSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1;

        $tree = [
            [
                'name' => 'مدیریت',
                'code' => 'MGT',
                'description' => 'واحد مدیریت ارشد سازمان',
                'children' => [
                    [
                        'name' => 'امور مالی',
                        'code' => 'FIN',
                        'description' => 'واحد مسئول امور مالی، بودجه و گزارش‌ های مالی',
                        'children' => [
                            [
                                'name' => 'حسابداری',
                                'code' => 'ACC',
                                'description' => 'واحد ثبت اسناد حسابداری و تهیه صورت‌ های مالی',
                            ],
                        ],
                    ],
                    [
                        'name' => 'فروش و بازاریابی',
                        'code' => 'SALES',
                        'description' => 'واحد مسئول فروش محصولات و ارتباط با مشتریان',
                    ],
                    [
                        'name' => 'انبار و لجستیک',
                        'code' => 'WH',
                        'description' => 'واحد مسئول دریافت، نگهداری و ارسال کالا',
                    ],
                    [
                        'name' => 'منابع انسانی',
                        'code' => 'HR',
                        'description' => 'واحد مسئول امور پرسنلی، جذب و آموزش نیروی انسانی',
                    ],
                ],
            ],
        ];

        $this->seedUnits($tree, null, $companyId);
    }

    private function seedUnits(array $units, ?int $parentId, int $companyId): void
    {
        foreach ($units as $unit) {
            $record = OrganizationUnit::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'parent_id' => $parentId,
                    'description' => $unit['description'] ?? null,
                    'is_active' => true,
                ]
            );

            if (! empty($unit['children'])) {
                $this->seedUnits($unit['children'], $record->id, $companyId);
            }
        }
    }
}
