<?php

namespace App\Mcp\Tools;

use App\Enums\InvoiceStatus;
use App\Mcp\Support\ContextResolver;
use App\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;

class GetInvoicesSummaryTool extends AccountingTool
{
    protected string $name = 'get_invoices_summary';

    protected string $description = 'گزارش و خلاصه فاکتورهای فروش و خرید، مجموع مبالغ، تفکیک ماهانه ۱۲ ماهه و فهرست آخرین فاکتورها با تاریخ‌های شمسی و میلادی.';

    protected array $permissionGroups = [['invoices.index'], ['invoices.show']];

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('فیلتر نوع فاکتور: all (همه)، sell (فروش) یا buy (خرید).')->enum(['all', 'sell', 'buy']),
            'month' => $schema->integer()->min(1)->max(12)->description('شماره ماه شمسی بین ۱ تا ۱۲ (مثلاً ۱ برای فروردین، ۸ برای آبان).'),
            'period' => $schema->string()->description('بازه زمانی گزارش: current_month (ماه جاری)، previous_month (ماه قبل) یا fiscal_year (کل سال مالی).')->enum(['current_month', 'previous_month', 'fiscal_year']),
            'status' => $schema->string()->description('فیلتر بر اساس وضعیت فاکتور (اختیاری؛ مانند pending، pre_invoice، approved، rejected، paid یا کد عددی وضعیت سیستم).'),
            'limit' => $schema->integer()->min(1)->max(50)->description('حداکثر تعداد فاکتورها در فهرست جزئیات (پیش‌فرض ۱۰).'),
            'year' => $schema->integer()->description('سال شمسی ۴ رقمی مورد نظر (مانند 1404 یا 1405). اختیاری؛ در صورت عدم ارسال از سال مالی فعال فعلی استفاده می‌شود.'),
            'fiscal_year_id' => $schema->integer()->description('شناسه سال مالی (اختیاری).'),
        ];
    }

    protected function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:all,sell,buy'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'period' => ['sometimes', 'string', 'in:current_month,previous_month,fiscal_year'],
            'status' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'year' => ['sometimes', 'integer'],
            'fiscal_year_id' => ['sometimes', 'integer'],
        ];
    }

    protected function report(array $arguments): Response
    {
        $filterType = $arguments['type'] ?? 'all';
        $statusFilter = isset($arguments['status']) ? (string) $arguments['status'] : null;
        $status = $statusFilter !== null ? InvoiceStatus::tryFromName($statusFilter) : null;
        if ($statusFilter !== null && $status === null) {
            return $this->error('وضعیت فاکتور نامعتبر است.');
        }
        $limit = isset($arguments['limit']) ? max(1, min(50, (int) $arguments['limit'])) : 10;

        $periodInfo = ContextResolver::resolvePeriod($arguments);
        $contextBlock = ContextResolver::getCurrentContext($arguments);

        /** @var Builder $baseQuery */
        $baseQuery = Invoice::query();

        if ($statusFilter !== null && $statusFilter !== '') {
            $baseQuery->where('status', $status->value);
        }

        // If period is scoped to a specific Jalali month
        $scopedQuery = clone $baseQuery;
        if ($periodInfo['mode'] === 'month' && ! empty($periodInfo['start_date']) && ! empty($periodInfo['end_date'])) {
            $scopedQuery->whereBetween('date', [$periodInfo['start_date'], $periodInfo['end_date']]);
        }

        // Aggregated stats for the targeted scope
        $sellQuery = (clone $scopedQuery)->where('invoice_type', 2);
        $totalSellCount = $sellQuery->count();
        $totalSellAmount = (int) $sellQuery->sum('amount');

        $buyQuery = (clone $scopedQuery)->where('invoice_type', 1);
        $totalBuyCount = $buyQuery->count();
        $totalBuyAmount = (int) $buyQuery->sum('amount');

        // List query with type filter
        $listQuery = clone $scopedQuery;
        if ($filterType === 'sell') {
            $listQuery->where('invoice_type', 2);
        } elseif ($filterType === 'buy') {
            $listQuery->where('invoice_type', 1);
        }

        $canReadCustomers = ContextResolver::can('customers.index');
        if ($canReadCustomers) {
            $listQuery->with('customer');
        }
        $recentInvoices = $listQuery
            ->latest('id')
            ->take($limit)
            ->get();

        $invoicesList = [];
        foreach ($recentInvoices as $inv) {
            $typeLabel = match ((int) ($inv->invoice_type?->value ?? $inv->invoice_type)) {
                1 => 'خرید',
                2 => 'فروش',
                3 => 'برگشت از خرید',
                4 => 'برگشت از فروش',
                default => 'سایر',
            };

            $statusLabel = $inv->status instanceof InvoiceStatus ? $inv->status->label() : 'نامشخص';

            $rawDate = $inv->date ? $inv->date->format('Y-m-d') : null;
            $jalaliDate = ContextResolver::toJalaliDateString($rawDate);
            $jalaliMonthName = null;

            if ($jalaliDate !== null) {
                $parts = explode('/', $jalaliDate);
                $mNum = (int) ($parts[1] ?? 0);
                $jalaliMonthName = ContextResolver::MONTH_NAMES[$mNum] ?? null;
            }

            $invoicesList[] = [
                'id' => $inv->id,
                'number' => $inv->number,
                'type' => $typeLabel,
                'customer' => $canReadCustomers ? ($inv->customer?->name ?? 'نامشخص') : null,
                'date' => $rawDate,
                'jalali_date' => $jalaliDate,
                'jalali_month_name' => $jalaliMonthName,
                'amount' => (int) $inv->amount,
                'formatted_amount' => number_format((int) $inv->amount).' ریال',
                'status' => $statusLabel,
                'status_code' => $inv->status?->value,
            ];
        }

        // 12-month breakdown for the entire fiscal year via single aggregated database query
        $fiscalYearNumber = $periodInfo['fiscal_year_number'];
        $selects = [];
        for ($m = 1; $m <= 12; $m++) {
            $range = ContextResolver::getJalaliMonthGregorianRange($fiscalYearNumber, $m);
            $start = $range['start'];
            $end = $range['end'];
            $selects[] = "COUNT(CASE WHEN invoice_type = 2 AND date BETWEEN '{$start}' AND '{$end}' THEN 1 END) AS s_cnt_{$m}";
            $selects[] = "COALESCE(SUM(CASE WHEN invoice_type = 2 AND date BETWEEN '{$start}' AND '{$end}' THEN amount ELSE 0 END), 0) AS s_amt_{$m}";
            $selects[] = "COUNT(CASE WHEN invoice_type = 1 AND date BETWEEN '{$start}' AND '{$end}' THEN 1 END) AS p_cnt_{$m}";
            $selects[] = "COALESCE(SUM(CASE WHEN invoice_type = 1 AND date BETWEEN '{$start}' AND '{$end}' THEN amount ELSE 0 END), 0) AS p_amt_{$m}";
        }

        $stats = (clone $baseQuery)->selectRaw(implode(', ', $selects))->first();

        $monthlyBreakdown = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyBreakdown[] = [
                'month_number' => $m,
                'month_name' => ContextResolver::MONTH_NAMES[$m],
                'sales_count' => (int) ($stats->{"s_cnt_{$m}"} ?? 0),
                'sales_amount' => (int) ($stats->{"s_amt_{$m}"} ?? 0),
                'purchases_count' => (int) ($stats->{"p_cnt_{$m}"} ?? 0),
                'purchases_amount' => (int) ($stats->{"p_amt_{$m}"} ?? 0),
            ];
        }

        // Format human summary
        if ($periodInfo['mode'] === 'month') {
            $periodTitle = $periodInfo['month_name'];
            $humanSummary = sprintf(
                'در %s (از %s تا %s): %d فاکتور فروش به مبلغ %s ریال و %d فاکتور خرید به مبلغ %s ریال ثبت شده است.',
                $periodTitle,
                $periodInfo['start_date'],
                $periodInfo['end_date'],
                $totalSellCount,
                number_format($totalSellAmount),
                $totalBuyCount,
                number_format($totalBuyAmount)
            );
        } else {
            $humanSummary = sprintf(
                'خلاصه سال مالی %s: مجموعاً %d فاکتور فروش به مبلغ %s ریال و %d فاکتور خرید به مبلغ %s ریال ثبت شده است.',
                $contextBlock['active_fiscal_year']['title'],
                $totalSellCount,
                number_format($totalSellAmount),
                $totalBuyCount,
                number_format($totalBuyAmount)
            );
        }

        return $this->success([
            'context' => $contextBlock,
            'period' => [
                'mode' => $periodInfo['mode'],
                'period' => $periodInfo['period'],
                'month_number' => $periodInfo['month'],
                'month_name' => $periodInfo['month_name'],
                'start_date' => $periodInfo['start_date'],
                'end_date' => $periodInfo['end_date'],
                'is_leap_year' => $periodInfo['is_leap_year'],
            ],
            'summary' => [
                'total_sales_count' => $totalSellCount,
                'total_sales_amount' => $totalSellAmount,
                'formatted_sales_amount' => number_format($totalSellAmount).' ریال',
                'total_purchases_count' => $totalBuyCount,
                'total_purchases_amount' => $totalBuyAmount,
                'formatted_purchases_amount' => number_format($totalBuyAmount).' ریال',
            ],
            'filter_applied' => [
                'type' => $filterType,
                'status' => $statusFilter,
            ],
            'monthly_breakdown' => array_values($monthlyBreakdown),
            'recent_invoices' => $invoicesList,
            'human_summary' => $humanSummary,
        ]);
    }
}
