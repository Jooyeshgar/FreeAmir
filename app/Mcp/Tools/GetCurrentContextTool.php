<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\ContextResolver;
use App\Models\FiscalYear;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetCurrentContextTool extends AccountingTool
{
    protected string $name = 'get_current_context';

    protected string $description = 'Return the current Jalali and Gregorian calendar context, active authorized fiscal year, company currency, available fiscal years, customer and product groups, and warehouses. If fiscal-year selection is ambiguous, return an error with the available fiscal years for the user to choose from.';

    protected array $permissionGroups = [['invoices.index'], ['invoices.show'], ['reports.cost-income'], ['reports.company-overview'], ['customers.index'], ['customers.show'], ['reports.ledger'], ['reports.trial-balance'], ['reports.result'], ['customers.store'], ['products.store'], ['invoices.store']];

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

    protected function report(array $arguments, FiscalYear $fiscalYear): Response
    {
        $context = ContextResolver::getCurrentContext($fiscalYear);

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

        $customerGroupsList = array_map(fn ($group) => __(':name (ID :id)', $group), $context['customer_groups'] ?? []);
        $warehousesList = array_map(fn ($warehouse) => __(':name (ID :id)', $warehouse), $context['warehouses'] ?? []);
        $humanSummary .= ' '.__('Customer groups: :customer_groups. Warehouses: :warehouses.', [
            'customer_groups' => ! empty($customerGroupsList) ? implode(', ', $customerGroupsList) : __('Unknown'),
            'warehouses' => ! empty($warehousesList) ? implode(', ', $warehousesList) : __('Unknown'),
        ]);

        return $this->success(array_merge($context, [
            'human_summary' => $humanSummary,
        ]));
    }

    protected function error(string $message): Response
    {
        return Response::error(json_encode([
            'message' => $message,
            'available_fiscal_years' => ContextResolver::getAvailableFiscalYears(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
