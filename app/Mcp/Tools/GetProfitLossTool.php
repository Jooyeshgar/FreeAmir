<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Services\CostIncomeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetProfitLossTool extends AccountingTool
{
    protected string $name = 'get_profit_loss';

    protected string $description = 'محاسبه سود و زیان (درآمدها، هزینه‌ها، سود خالص و حاشیه سود) برای سال مالی یا ماه مشخص شمسی همراه با بافت تقویمی و تفکیک ماه‌ها.';

    protected array $permissionGroups = [['reports.cost-income'], ['reports.result'], ['reports.company-overview', 'documents.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->integer()->min(1)->max(12)->description('شماره ماه شمسی بین ۱ تا ۱۲ (مثلاً ۱ برای فروردین، ۸ برای آبان).'),
            'period' => $schema->string()->description('بازه زمانی گزارش: current_month (ماه جاری)، previous_month (ماه قبل) یا fiscal_year (کل سال مالی).')->enum(['current_month', 'previous_month', 'fiscal_year']),
            'year' => $schema->integer()->description('سال شمسی ۴ رقمی مورد نظر (مانند 1404 یا 1405). اختیاری؛ در صورت عدم ارسال از سال مالی فعال فعلی استفاده می‌شود.'),
            'fiscal_year_id' => $schema->integer()->description('شناسه سال مالی (اختیاری؛ در صورت عدم ارسال از سال مالی فعال فعلی استفاده می‌شود).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'period' => ['sometimes', 'string', 'in:current_month,previous_month,fiscal_year'],
            'year' => ['sometimes', 'integer'],
            'fiscal_year_id' => ['sometimes', 'integer'],
        ];
    }

    protected function report(array $arguments): Response
    {
        /** @var CostIncomeService $service */
        $service = app(CostIncomeService::class);

        $periodInfo = ContextResolver::resolvePeriod($arguments);
        $contextBlock = ContextResolver::getCurrentContext($arguments);
        $targetMonth = $periodInfo['month'];

        $monthlyRaw = $service->monthlyIncomeAndCostByMonth();

        $monthsList = [];
        for ($m = 1; $m <= 12; $m++) {
            $mName = ContextResolver::MONTH_NAMES[$m];
            $mIncome = (int) ($monthlyRaw['income'][$m] ?? 0);
            $mCost = (int) (-1 * ($monthlyRaw['cost'][$m] ?? 0));
            $mProfit = $mIncome - $mCost;
            $monthsList[$m] = [
                'month_number' => $m,
                'month_name' => $mName,
                'income' => $mIncome,
                'cost' => $mCost,
                'profit' => $mProfit,
                'status' => $mProfit >= 0 ? 'سودده' : 'زیان‌ده',
            ];
        }

        if ($periodInfo['mode'] === 'month' && $targetMonth !== null) {
            $income = (int) ($monthlyRaw['income'][$targetMonth] ?? 0);
            $cost = -1 * (int) ($monthlyRaw['cost'][$targetMonth] ?? 0);
            $profit = $income - $cost;
            $margin = $income > 0 ? (int) round(($profit / $income) * 100) : 0;
            $monthName = $periodInfo['month_name'];

            $humanSummary = sprintf(
                'در %s (از %s تا %s): مجموع درآمد %s ریال، مجموع هزینه‌ها %s ریال و %s %s ریال (حاشیه سود %d٪) بوده است.',
                $monthName,
                $periodInfo['start_date'],
                $periodInfo['end_date'],
                number_format($income),
                number_format($cost),
                $profit >= 0 ? 'سود خالص' : 'زیان خالص',
                number_format(abs($profit)),
                $margin
            );

            return $this->success([
                'context' => $contextBlock,
                'period' => [
                    'mode' => $periodInfo['mode'],
                    'period' => $periodInfo['period'],
                    'month_number' => $targetMonth,
                    'month_name' => $monthName,
                    'start_date' => $periodInfo['start_date'],
                    'end_date' => $periodInfo['end_date'],
                    'is_leap_year' => $periodInfo['is_leap_year'],
                ],
                'total_income' => $income,
                'total_cost' => $cost,
                'net_profit' => $profit,
                'profit_margin_percent' => $margin,
                'status' => $profit >= 0 ? 'سودده' : 'زیان‌ده',
                'monthly_breakdown' => array_values($monthsList),
                'human_summary' => $humanSummary,
            ]);
        }

        $summary = $service->summary();
        $totalIncome = (int) ($summary['totalIncome'] ?? 0);
        $totalCost = (int) ($summary['totalCost'] ?? 0);
        $profit = (int) ($summary['profit'] ?? 0);
        $margin = (int) ($summary['margin'] ?? 0);

        $humanSummary = sprintf(
            'وضعیت کل سال مالی %s: کل درآمد %s ریال، کل هزینه‌ها %s ریال، %s %s ریال (مارجین %d٪).',
            $contextBlock['active_fiscal_year']['title'],
            number_format($totalIncome),
            number_format($totalCost),
            $profit >= 0 ? 'سود خالص' : 'زیان خالص',
            number_format(abs($profit)),
            $margin
        );

        return $this->success([
            'context' => $contextBlock,
            'period' => [
                'mode' => 'fiscal_year',
                'period' => 'fiscal_year',
                'month_number' => null,
                'month_name' => null,
                'start_date' => $periodInfo['start_date'],
                'end_date' => $periodInfo['end_date'],
                'is_leap_year' => $periodInfo['is_leap_year'],
            ],
            'total_income' => $totalIncome,
            'total_cost' => $totalCost,
            'net_profit' => $profit,
            'profit_margin_percent' => $margin,
            'status' => $profit >= 0 ? 'سودده' : 'زیان‌ده',
            'monthly_breakdown' => array_values($monthsList),
            'human_summary' => $humanSummary,
        ]);
    }
}
