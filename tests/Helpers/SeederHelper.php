<?php

namespace Tests\Helpers;

use App\Models\Config;
use DB;

trait SeederHelper
{
    private function createConfigs(int $companyId): void
    {
        $configs = [
            ['type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'بانکها', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'cash_book', 'value' => '2', 'desc' => 'موجودی نقدی', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'cust_subject', 'value' => '3', 'desc' => 'مشتریان', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'inventory', 'value' => '4', 'desc' => 'موجودی کالا', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'cost', 'value' => '6', 'desc' => 'هزینه ها', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'sundry_cost', 'value' => '7', 'desc' => 'هزینه های متفرقه', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'cost_of_goods_sold', 'value' => '9', 'desc' => 'بهای تمام شده کالا فروش رقته', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'cogs_service', 'value' => '10', 'desc' => 'بهای تمام شده خدمات', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'buy_vat', 'value' => '12', 'desc' => 'مالیات خرید', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'sell_vat', 'value' => '14', 'desc' => 'مالیات فروش', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'income', 'value' => '17', 'desc' => 'درآمد', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'service_revenue', 'value' => '19', 'desc' => 'درآمد خدمات', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'sales_revenue', 'value' => '20', 'desc' => 'درآمد فروش', 'company_id' => $companyId],
            ['type' => 3, 'category' => 1, 'key' => 'sales_returns', 'value' => '25', 'desc' => 'برگشت از فروش', 'company_id' => $companyId],
            ['type' => 2, 'category' => 1, 'key' => 'buy_discount', 'value' => '27', 'desc' => 'تخفیفات خرید', 'company_id' => $companyId],
            ['type' => 2, 'category' => 1, 'key' => 'sell_discount', 'value' => '28', 'desc' => 'تخفیفات فروش', 'company_id' => $companyId],
        ];

        Config::upsert($configs, ['key', 'company_id'], ['value']);

        foreach ($configs as $config) {
            config(['amir.'.$config['key'] => $config['value']]);
        }
    }

    private function createSubjects(int $companyId): void
    {
        $subjectData = [
            ['id' => 1, 'code' => '010', 'name' => 'بانکها', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 2, 'code' => '011', 'name' => 'موجودیهای نقدی', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 3, 'code' => '012', 'name' => 'بدهکاران/بستانکاران', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 4, 'code' => '019', 'name' => 'موجودی کالا', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 5, 'code' => '062', 'name' => 'خرید', 'parent_id' => null, 'type' => 'debtor', 'company_id' => $companyId],
            ['id' => 6, 'code' => '040', 'name' => 'هزینه ها', 'parent_id' => null, 'type' => 'debtor', 'company_id' => $companyId],
            ['id' => 7, 'code' => '040013', 'name' => 'هزینه های متفرقه', 'parent_id' => 6, 'type' => 'debtor', 'company_id' => $companyId],
            ['id' => 8, 'code' => '070', 'name' => 'بهای تمام شده', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 9, 'code' => '070001', 'name' => 'بهای تمام شده کالا فروش رفته', 'parent_id' => 8, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 10, 'code' => '070002', 'name' => 'بهای تمام شده خدمات', 'parent_id' => 8, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 11, 'code' => '018', 'name' => 'سایر حسابهای دریافتنی', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 12, 'code' => '018001', 'name' => 'مالیات بر ارزش افزوده خرید', 'parent_id' => 11, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 13, 'code' => '023', 'name' => 'سایر حسابهای پرداختنی', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 14, 'code' => '023001', 'name' => 'مالیات بر ارزش افزوده فروش', 'parent_id' => 13, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 15, 'code' => '041', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 16, 'code' => '041001', 'name' => 'قیمت تمام شده کالای فروش رفته', 'parent_id' => 15, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 17, 'code' => '050', 'name' => 'درآمدها', 'parent_id' => null, 'type' => 'creditor', 'company_id' => $companyId],
            ['id' => 18, 'code' => '050001', 'name' => 'درآمد متفرقه', 'parent_id' => 17, 'type' => 'creditor', 'company_id' => $companyId],
            ['id' => 19, 'code' => '050002', 'name' => 'درآمد خدمات', 'parent_id' => 17, 'type' => 'creditor', 'company_id' => $companyId],
            ['id' => 20, 'code' => '050003', 'name' => 'درآمد فروش', 'parent_id' => 17, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 21, 'code' => '060', 'name' => 'فروش', 'parent_id' => null, 'type' => 'creditor', 'company_id' => $companyId],
            ['id' => 22, 'code' => '060001', 'name' => 'فروش', 'parent_id' => 21, 'type' => 'creditor', 'company_id' => $companyId],
            ['id' => 23, 'code' => '061', 'name' => 'برگشت از فروش و تخفیفات', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 24, 'code' => '061001', 'name' => 'تخفیفات فروش', 'parent_id' => 23, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 25, 'code' => '061002', 'name' => 'برگشت از فروش', 'parent_id' => 23, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 26, 'code' => '066', 'name' => 'تخفیفات نقدی', 'parent_id' => null, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 27, 'code' => '066001', 'name' => 'تخفیفات خرید', 'parent_id' => 26, 'type' => 'both', 'company_id' => $companyId],
            ['id' => 28, 'code' => '066002', 'name' => 'تخفیفات فروش', 'parent_id' => 26, 'type' => 'both', 'company_id' => $companyId],
        ];

        DB::table('subjects')->upsert($subjectData, ['id'], ['code', 'name', 'parent_id', 'type', 'company_id']);
    }

    public function importConfigs(int $companyId): void
    {
        $this->createConfigs($companyId);
    }

    public function importSubjects(int $companyId): void
    {
        $this->createSubjects($companyId);
    }
}
