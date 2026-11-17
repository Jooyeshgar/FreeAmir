<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCurrentContextTool extends AccountingTool
{
    protected string $name = 'get_current_context';

    protected string $description = 'Return the current Jalali and Gregorian calendar context, active authorized fiscal year, company currency, and available fiscal years.';

    protected array $permissionGroups = [['invoices.index'], ['invoices.show'], ['reports.cost-income'], ['reports.company-overview'], ['customers.index'], ['customers.show'], ['reports.ledger'], ['reports.trial-balance'], ['reports.result']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Optional four-digit Jalali year.'),
            'fiscal_year_id' => $schema->integer()->description('Optional authorized fiscal year ID.'),
        ];
    }

    protected function rules(): array
    {
        return [
            'year' => ['sometimes', 'integer'],
            'fiscal_year_id' => ['sometimes', 'integer'],
        ];
    }

    protected function report(array $arguments): Response
    {
        $context = ContextResolver::getCurrentContext($arguments);

        $availableList = array_map(function ($y) {
            return $y['year'].($y['is_active'] ? ' ('.__('Active').')' : '');
        }, $context['available_fiscal_years'] ?? []);

        $humanSummary = __('Today is :jalali_date (:gregorian_date). Current month: :month (month :month_number). Fiscal year: :fiscal_year (from :start to :end), currency: :currency. Available fiscal years: :available_years.', [
            'jalali_date' => $context['today_jalali'],
            'gregorian_date' => $context['today_gregorian'],
            'month' => $context['current_month_name'],
            'month_number' => $context['current_month_number'],
            'fiscal_year' => $context['active_fiscal_year']['title'],
            'start' => $context['active_fiscal_year']['start_date'],
            'end' => $context['active_fiscal_year']['end_date'],
            'currency' => $context['active_fiscal_year']['currency'],
            'available_years' => ! empty($availableList) ? implode(', ', $availableList) : __('Unknown'),
        ]);

        return $this->success(array_merge($context, [
            'human_summary' => $humanSummary,
        ]));
    }
}
