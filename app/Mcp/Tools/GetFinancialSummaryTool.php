<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Services\CostIncomeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetFinancialSummaryTool extends AccountingTool
{
    protected string $name = 'get_financial_summary';

    protected string $description = 'Return a financial summary for an authorized fiscal year, including income, expenses, net profit, margin, and the top income and expense accounts. Monetary values use the selected company currency.';

    protected array $permissionGroups = [['reports.trial-balance'], ['reports.ledger'], ['reports.company-overview', 'documents.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(20)->description('Maximum number of top income and expense accounts to return (default: 5).'),
            'year' => $schema->integer()->description('Optional four-digit Jalali year.'),
            'fiscal_year_id' => $schema->integer()->description('Optional authorized fiscal year ID.'),
        ];
    }

    protected function rules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'year' => ['sometimes', 'integer'],
            'fiscal_year_id' => ['sometimes', 'integer'],
        ];
    }

    protected function report(array $arguments): Response
    {
        /** @var CostIncomeService $service */
        $service = app(CostIncomeService::class);
        $limit = isset($arguments['limit']) ? max(1, min(20, (int) $arguments['limit'])) : 5;
        $activeFiscalYearId = ContextResolver::resolveFiscalYearId($arguments);
        $currency = ContextResolver::resolveFiscalYearCurrency($arguments);

        $summary = $service->summary();

        $topIncome = array_slice($summary['incomeBreakdown'] ?? [], 0, $limit, true);
        $topCost = array_slice($summary['costBreakdown'] ?? [], 0, $limit, true);

        $formattedTopIncome = [];
        foreach ($topIncome as $subject => $amount) {
            $formattedTopIncome[] = [
                'subject' => $subject,
                'amount' => (int) $amount,
                'formatted' => number_format((int) $amount).' '.$currency,
            ];
        }

        $formattedTopCost = [];
        foreach ($topCost as $subject => $amount) {
            $formattedTopCost[] = [
                'subject' => $subject,
                'amount' => (int) $amount,
                'formatted' => number_format((int) $amount).' '.$currency,
            ];
        }

        $result = [
            'fiscal_year_id' => $activeFiscalYearId,
            'total_income' => $summary['totalIncome'] ?? 0,
            'total_cost' => $summary['totalCost'] ?? 0,
            'net_profit' => $summary['profit'] ?? 0,
            'profit_margin_percent' => $summary['margin'] ?? 0,
            'currency' => $currency,
            'top_income_sources' => $formattedTopIncome,
            'top_expense_categories' => $formattedTopCost,
            'human_summary' => __('Financial summary: total income :income :currency, total expenses :cost :currency, net profit :profit :currency, with a :margin% margin.',
                ['income' => number_format($summary['totalIncome'] ?? 0), 'cost' => number_format($summary['totalCost'] ?? 0),
                    'profit' => number_format($summary['profit'] ?? 0), 'currency' => $currency, 'margin' => $summary['margin'] ?? 0]),
        ];

        return $this->success($result);
    }
}
