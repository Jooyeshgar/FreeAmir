<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Services\CostIncomeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetProfitLossTool extends AccountingTool
{
    protected string $name = 'get_profit_loss';

    protected string $description = 'Calculate income, expenses, net profit or loss, and profit margin for an authorized fiscal year or Jalali month. Includes a 12-month breakdown. Monetary values use the selected company currency.';

    protected array $permissionGroups = [['reports.cost-income'], ['reports.result'], ['reports.company-overview', 'documents.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->integer()->min(1)->max(12)->description('Optional Jalali month number from 1 to 12.'),
            'period' => $schema->string()->description('Reporting period: current_month, previous_month, or fiscal_year.')->enum(['current_month', 'previous_month', 'fiscal_year']),
            'year' => $schema->integer()->description('Optional four-digit Jalali year. Uses the active fiscal year when omitted.'),
            'fiscal_year_id' => $schema->integer()->description('Optional authorized fiscal year ID. Uses the active fiscal year when omitted.'),
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
        $currency = $contextBlock['active_fiscal_year']['currency'];

        $monthlyRaw = $service->monthlyIncomeAndCostByMonth();

        $monthsList = [];
        for ($m = 1; $m <= 12; $m++) {
            $mName = __(ContextResolver::MONTH_NAMES[$m]);
            $mIncome = (int) ($monthlyRaw['income'][$m] ?? 0);
            $mCost = (int) (-1 * ($monthlyRaw['cost'][$m] ?? 0));
            $mProfit = $mIncome - $mCost;
            $monthsList[$m] = [
                'month_number' => $m,
                'month_name' => $mName,
                'income' => $mIncome,
                'cost' => $mCost,
                'profit' => $mProfit,
                'status' => $mProfit >= 0 ? __('Profitable') : __('Loss-making'),
                'status_code' => $mProfit >= 0 ? 'profitable' : 'loss_making',
            ];
        }

        if ($periodInfo['mode'] === 'month' && $targetMonth !== null) {
            $income = (int) ($monthlyRaw['income'][$targetMonth] ?? 0);
            $cost = -1 * (int) ($monthlyRaw['cost'][$targetMonth] ?? 0);
            $profit = $income - $cost;
            $margin = $income > 0 ? (int) round(($profit / $income) * 100) : 0;
            $monthName = $periodInfo['month_name'];

            $humanSummary = __('For :month (:start to :end): income was :income :currency, expenses were :cost :currency, and :result was :profit :currency (profit margin: :margin%).',
                ['month' => $monthName, 'start' => $periodInfo['start_date'], 'end' => $periodInfo['end_date'],
                    'income' => number_format($income), 'cost' => number_format($cost),
                    'result' => $profit >= 0 ? __('net profit') : __('net loss'),
                    'profit' => number_format(abs($profit)), 'currency' => $currency, 'margin' => $margin]);

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
                'currency' => $currency,
                'total_cost' => $cost,
                'net_profit' => $profit,
                'profit_margin_percent' => $margin,
                'status' => $profit >= 0 ? __('Profitable') : __('Loss-making'),
                'status_code' => $profit >= 0 ? 'profitable' : 'loss_making',
                'monthly_breakdown' => array_values($monthsList),
                'human_summary' => $humanSummary,
            ]);
        }

        $summary = $service->summary();
        $totalIncome = (int) ($summary['totalIncome'] ?? 0);
        $totalCost = (int) ($summary['totalCost'] ?? 0);
        $profit = (int) ($summary['profit'] ?? 0);
        $margin = (int) ($summary['margin'] ?? 0);

        $humanSummary = __('Fiscal year :year: total income was :income :currency, total expenses were :cost :currency, and :result was :profit :currency (profit margin: :margin%).',
            ['year' => $contextBlock['active_fiscal_year']['title'], 'income' => number_format($totalIncome),
                'cost' => number_format($totalCost), 'result' => $profit >= 0 ? __('net profit') : __('net loss'),
                'profit' => number_format(abs($profit)), 'currency' => $currency, 'margin' => $margin]);

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
            'currency' => $currency,
            'profit_margin_percent' => $margin,
            'status' => $profit >= 0 ? __('Profitable') : __('Loss-making'),
            'status_code' => $profit >= 0 ? 'profitable' : 'loss_making',
            'monthly_breakdown' => array_values($monthsList),
            'human_summary' => $humanSummary,
        ]);
    }
}
