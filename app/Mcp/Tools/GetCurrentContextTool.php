<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCurrentContextTool extends AccountingTool
{
    protected string $name = 'get_current_context';

    protected string $description = 'دریافت بافت تقویمی و مالی جاری سیستم، شامل تاریخ امروز شمسی و میلادی، ماه جاری شمسی، مشخصات سال مالی فعال و فهرست سال‌های مالی موجود.';

    protected array $permissionGroups = [['invoices.index'], ['reports.cost-income'], ['reports.company-overview'], ['customers.index'], ['customers.show'], ['reports.ledger'], ['reports.trial-balance'], ['reports.result']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('سال شمسی ۴ رقمی مورد نظر (مانند 1404 یا 1405). اختیاری.'),
            'fiscal_year_id' => $schema->integer()->description('شناسه سال مالی (اختیاری).'),
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
            return $y['year'].($y['is_active'] ? ' (فعال)' : '');
        }, $context['available_fiscal_years'] ?? []);

        $humanSummary = sprintf(
            'امروز %s (%s)، ماه جاری: %s (ماه %d). سال مالی: %s (از %s تا %s). سال‌های مالی تعریف‌شده در سیستم: %s.',
            $context['today_jalali'],
            $context['today_gregorian'],
            $context['current_month_name'],
            $context['current_month_number'],
            $context['active_fiscal_year']['title'],
            $context['active_fiscal_year']['start_date'],
            $context['active_fiscal_year']['end_date'],
            ! empty($availableList) ? implode('، ', $availableList) : 'نامشخص'
        );

        return $this->success(array_merge($context, [
            'human_summary' => $humanSummary,
        ]));
    }
}
