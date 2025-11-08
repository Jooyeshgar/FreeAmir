<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $configs = [
            ['type' => 3, 'category' => 1, 'key' => 'cust_subject', 'value' => '4', 'desc' => 'مشتریان', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cash_book', 'value' => '3', 'desc' => 'موجودی نقدی', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'بانکها', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cash', 'value' => '59', 'desc' => 'پول نقد', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'income', 'value' => '23', 'desc' => 'درآمد', 'company_id' => 1],
            ['type' => 2, 'category' => 1, 'key' => 'sell_discount', 'value' => '55', 'desc' => 'تخفیفات فروش', 'company_id' => 1],
            ['type' => 2, 'category' => 1, 'key' => 'buy_discount', 'value' => '98', 'desc' => 'تخفیفات خرید', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sell_vat', 'value' => '41', 'desc' => 'مالیات فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'buy_vat', 'value' => '40', 'desc' => 'مالیات خرید', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'inventory', 'value' => '9', 'desc' => 'موجودی کالا', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sales_returns', 'value' => '43', 'desc' => 'برگشت از فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cost_of_goods_sold', 'value' => '102', 'desc' => 'بهای تمام شده کالا فروش رقته', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sales_revenue', 'value' => '104', 'desc' => 'درآمد فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'service_revenue', 'value' => '103', 'desc' => 'درآمد خدمات', 'company_id' => 1],
        ];

        Config::insert($configs);
        foreach ($configs as $config) {
            config(['amir.'.$config['key'] => $config['value']]);
        }
    }
}
