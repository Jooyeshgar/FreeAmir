<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Services\CostIncomeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetFinancialSummaryTool extends AccountingTool
{
    protected string $name = 'get_financial_summary';

    protected string $description = 'دریافت خلاصه گزارش مالی کل، شامل درآمدها، هزینه‌ها، سود، مارجین و دسته‌بندی برترین سرفصل‌های درآمد و هزینه.';

    protected array $permissionGroups = [['reports.trial-balance'], ['reports.ledger'], ['reports.company-overview', 'documents.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(20)->description('حداکثر تعداد سرفصل‌ها در لیست تفکیک درآمد و هزینه (پیش‌فرض ۵).'),
            'year' => $schema->integer()->description('سال شمسی ۴ رقمی مورد نظر (مانند 1404 یا 1405). اختیاری.'),
            'fiscal_year_id' => $schema->integer()->description('شناسه سال مالی (اختیاری).'),
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

        $summary = $service->summary();

        $topIncome = array_slice($summary['incomeBreakdown'] ?? [], 0, $limit, true);
        $topCost = array_slice($summary['costBreakdown'] ?? [], 0, $limit, true);

        $formattedTopIncome = [];
        foreach ($topIncome as $subject => $amount) {
            $formattedTopIncome[] = [
                'subject' => $subject,
                'amount' => (int) $amount,
                'formatted' => number_format((int) $amount).' ریال',
            ];
        }

        $formattedTopCost = [];
        foreach ($topCost as $subject => $amount) {
            $formattedTopCost[] = [
                'subject' => $subject,
                'amount' => (int) $amount,
                'formatted' => number_format((int) $amount).' ریال',
            ];
        }

        $result = [
            'fiscal_year_id' => $activeFiscalYearId,
            'total_income' => $summary['totalIncome'] ?? 0,
            'total_cost' => $summary['totalCost'] ?? 0,
            'net_profit' => $summary['profit'] ?? 0,
            'profit_margin_percent' => $summary['margin'] ?? 0,
            'top_income_sources' => $formattedTopIncome,
            'top_expense_categories' => $formattedTopCost,
            'human_summary' => sprintf(
                'گزارش مالی: کل درآمد %s ریال، کل هزینه %s ریال، سود خالص %s ریال با مارجین %d٪.',
                number_format($summary['totalIncome'] ?? 0),
                number_format($summary['totalCost'] ?? 0),
                number_format($summary['profit'] ?? 0),
                $summary['margin'] ?? 0
            ),
        ];

        return $this->success($result);
    }
}
