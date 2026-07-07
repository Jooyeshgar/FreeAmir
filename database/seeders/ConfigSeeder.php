<?php

namespace Database\Seeders;

use App\Models\Config;
use App\Models\Scopes\FiscalYearScope;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use RuntimeException;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) getActiveCompany();
        $configs = [
            ['type' => 3, 'category' => 1, 'key' => 'wage', 'value' => '10', 'desc' => 'حقوق پرسنل', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cust_subject', 'value' => '4', 'desc' => 'مشتریان', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cash_book', 'value' => '3', 'desc' => 'موجودی نقدی', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cost', 'value' => '2', 'desc' => 'هزینه ها', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sundry_cost', 'value' => '32', 'desc' => 'هزینه های متفرقه', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'bank', 'value' => '1', 'desc' => 'بانکها', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'income', 'value' => '23', 'desc' => 'درآمد', 'company_id' => 1],
            ['type' => 2, 'category' => 1, 'key' => 'sell_discount', 'value' => '99', 'desc' => 'تخفیفات فروش', 'company_id' => 1],
            ['type' => 2, 'category' => 1, 'key' => 'buy_discount', 'value' => '98', 'desc' => 'تخفیفات خرید', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sell_vat', 'value' => '41', 'desc' => 'مالیات فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'buy_vat', 'value' => '40', 'desc' => 'مالیات خرید', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'inventory', 'value' => '9', 'desc' => 'موجودی کالا', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sales_returns', 'value' => '43', 'desc' => 'برگشت از فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cost_of_goods_sold', 'value' => '105', 'desc' => 'بهای تمام شده کالا فروش رقته', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'cogs_service', 'value' => '106', 'desc' => 'بهای تمام شده خدمات', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'sales_revenue', 'value' => '104', 'desc' => 'درآمد فروش', 'company_id' => 1],
            ['type' => 3, 'category' => 1, 'key' => 'service_revenue', 'value' => '103', 'desc' => 'درآمد خدمات', 'company_id' => 1],
        ];

        $subjectCodes = $this->subjectCodes();
        $subjects = Subject::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('code', array_values($subjectCodes))->get()->keyBy('code');

        foreach ($configs as &$config) {
            $subject = $subjects->get($subjectCodes[$config['key']]);

            if (! $subject) {
                throw new RuntimeException("Missing seeded subject for config [{$config['key']}].");
            }

            $config['company_id'] = $companyId;
            $config['value'] = (string) $subject->id;
        }
        unset($config);

        Config::upsert($configs, ['key', 'company_id'], ['type', 'category', 'value', 'desc']);
        foreach ($configs as $config) {
            config(['amir.'.$config['key'] => $config['value']]);
        }

        foreach (['app_env', 'app_locale', 'app_debug'] as $key) {
            Config::withoutGlobalScope(FiscalYearScope::class)->updateOrCreate(
                ['key' => $key, 'company_id' => null],
                ['value' => null, 'type' => 3, 'category' => 1, 'desc' => __($key)],
            );
        }
    }

    /**
     * Map configuration keys to their seeded accounting subject codes.
     */
    private function subjectCodes(): array
    {
        return [
            'wage' => '040001',
            'cust_subject' => '012',
            'cash_book' => '011',
            'cost' => '040',
            'sundry_cost' => '040013',
            'bank' => '010',
            'income' => '050',
            'sell_discount' => '066002',
            'buy_discount' => '066001',
            'sell_vat' => '023001',
            'buy_vat' => '018001',
            'inventory' => '019',
            'sales_returns' => '061002',
            'cost_of_goods_sold' => '070001',
            'cogs_service' => '070002',
            'sales_revenue' => '050003',
            'service_revenue' => '050002',
        ];
    }
}
