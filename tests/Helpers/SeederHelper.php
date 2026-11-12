<?php

namespace Tests\Helpers;

use App\Models\Config;
use DB;

trait SeederHelper
{
    private function createConfigs(int $fiscalYearId): void
    {
        $configs = [
            ['type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'بانکها', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'cash_book', 'value' => '2', 'desc' => 'موجودی نقدی', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'cust_subject', 'value' => '3', 'desc' => 'مشتریان', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'inventory', 'value' => '4', 'desc' => 'موجودی کالا', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'cost', 'value' => '6', 'desc' => 'هزینه ها', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'sundry_cost', 'value' => '7', 'desc' => 'هزینه های متفرقه', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'cost_of_goods_sold', 'value' => '9', 'desc' => 'بهای تمام شده کالا فروش رقته', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'cogs_service', 'value' => '10', 'desc' => 'بهای تمام شده خدمات', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'buy_vat', 'value' => '12', 'desc' => 'مالیات خرید', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'sell_vat', 'value' => '14', 'desc' => 'مالیات فروش', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'income', 'value' => '17', 'desc' => 'درآمد', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'service_revenue', 'value' => '19', 'desc' => 'درآمد خدمات', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'sales_revenue', 'value' => '20', 'desc' => 'درآمد فروش', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'sales_returns', 'value' => '25', 'desc' => 'برگشت از فروش', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 2, 'category' => 1, 'key' => 'buy_discount', 'value' => '27', 'desc' => 'تخفیفات خرید', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 2, 'category' => 1, 'key' => 'sell_discount', 'value' => '28', 'desc' => 'تخفیفات فروش', 'fiscal_year_id' => $fiscalYearId],
            ['type' => 3, 'category' => 1, 'key' => 'beginning_inventory', 'value' => '29', 'desc' => 'تراز افتتاحیه', 'fiscal_year_id' => $fiscalYearId],
        ];

        Config::upsert($configs, ['key', 'fiscal_year_id'], ['value']);

        foreach ($configs as $config) {
            config(['amir.'.$config['key'] => $config['value']]);
        }
    }

    private function createSubjects(int $fiscalYearId): void
    {
        $subjectData = [
            ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 2, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 3, 'code' => '012', 'name' => 'بدهکاران/بستانکاران', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 4, 'code' => '019', 'name' => 'موجودی کالا', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 5, 'code' => '062', 'name' => 'خرید', 'parent_id' => null, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 6, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 7, 'code' => '040013', 'name' => 'هزینه های متفرقه', 'parent_id' => 6, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 8, 'code' => '070', 'name' => 'بهای تمام شده', 'parent_id' => null, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 9, 'code' => '070001', 'name' => 'بهای تمام شده کالا فروش رفته', 'parent_id' => 8, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 10, 'code' => '070002', 'name' => 'بهای تمام شده خدمات', 'parent_id' => 8, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 11, 'code' => '018', 'name' => 'سایر حسابهای دریافتنی', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 12, 'code' => '018001', 'name' => 'مالیات بر ارزش افزوده خرید', 'parent_id' => 11, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 13, 'code' => '023', 'name' => 'سایر حسابهای پرداختنی', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 14, 'code' => '023001', 'name' => 'مالیات بر ارزش افزوده فروش', 'parent_id' => 13, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 15, 'code' => '041', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => null, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 16, 'code' => '041001', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => 15, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 17, 'code' => '050', 'name' => 'درآمدها', 'parent_id' => null, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 18, 'code' => '050001', 'name' => 'درآمد متفرقه', 'parent_id' => 17, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 19, 'code' => '050002', 'name' => 'درآمد خدمات', 'parent_id' => 17, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 20, 'code' => '050003', 'name' => 'درآمد فروش', 'parent_id' => 17, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 21, 'code' => '060', 'name' => 'فروش', 'parent_id' => null, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 22, 'code' => '060001', 'name' => 'فروش', 'parent_id' => 21, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 23, 'code' => '061', 'name' => 'برگشت از فروش و تخفیفات', 'parent_id' => null, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 24, 'code' => '061001', 'name' => 'تخفیفات فروش', 'parent_id' => 23, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 25, 'code' => '061002', 'name' => 'برگشت از فروش', 'parent_id' => 23, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 26, 'code' => '066', 'name' => 'تخفیفات نقدی', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 27, 'code' => '066001', 'name' => 'تخفیفات خرید', 'parent_id' => 26, 'type' => 2, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 28, 'code' => '066002', 'name' => 'تخفیفات فروش', 'parent_id' => 26, 'type' => 1, 'fiscal_year_id' => $fiscalYearId],
            ['id' => 29, 'code' => '067001', 'name' => 'تراز افتتاحیه', 'parent_id' => null, 'type' => 3, 'fiscal_year_id' => $fiscalYearId],
        ];

        DB::table('subjects')->upsert($subjectData, ['id'], ['code', 'name', 'parent_id', 'type', 'fiscal_year_id']);
    }

    public function importConfigs(int $fiscalYearId): void
    {
        $this->createConfigs($fiscalYearId);
    }

    public function importSubjects(int $fiscalYearId): void
    {
        $this->createSubjects($fiscalYearId);
    }
}
