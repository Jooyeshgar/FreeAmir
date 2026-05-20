<?php

namespace Database\Seeders;

use App\Models\OrgChart;
use Illuminate\Database\Seeder;

class OrgChartSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1;

        $tree = [
            [
                'title' => 'مدیرعامل',
                'description' => 'بالاترین مقام اجرایی سازمان',
                'children' => [
                    [
                        'title' => 'مدیر مالی',
                        'description' => 'مسئول نظارت بر امور مالی و حسابداری',
                        'children' => [
                            ['title' => 'حسابدار ارشد', 'description' => 'مسئول ثبت و پردازش اسناد مالی'],
                            ['title' => 'کارشناس مالی', 'description' => 'تحلیل و گزارش‌ دهی مالی'],
                        ],
                    ],
                    [
                        'title' => 'مدیر عملیات',
                        'description' => 'مسئول هماهنگی عملیات روزانه سازمان',
                        'children' => [
                            ['title' => 'سرپرست انبار', 'description' => 'نظارت بر ورود، خروج و موجودی انبار'],
                            ['title' => 'کارشناس فروش', 'description' => 'پیگیری فروش و ارتباط با مشتریان'],
                        ],
                    ],
                    [
                        'title' => 'مدیر منابع انسانی',
                        'description' => 'مسئول جذب، آموزش و نگهداشت نیروی انسانی',
                        'children' => [
                            ['title' => 'کارشناس منابع انسانی', 'description' => 'رسیدگی به امور پرسنلی و حقوق و دستمزد'],
                        ],
                    ],
                ],
            ],
        ];

        $this->seedNodes($tree, null, $companyId);
    }

    private function seedNodes(array $nodes, ?int $parentId, int $companyId): void
    {
        foreach ($nodes as $node) {
            $record = OrgChart::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $companyId, 'title' => $node['title']],
                [
                    'parent_id' => $parentId,
                    'description' => $node['description'] ?? null,
                ]
            );

            if (! empty($node['children'])) {
                $this->seedNodes($node['children'], $record->id, $companyId);
            }
        }
    }
}
