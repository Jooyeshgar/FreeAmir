<!DOCTYPE html>
<html dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="stylesheet" href="{{ resource_path('css/inventoryTurnover.css') }}">
</head>

<body>
    <htmlpageheader name="reportHeader">
        <table class="header">
            <tr>
                <td class="report-meta">
                    {{ __('Report') }}: {{ __('Product Inventory Turnover') }}<br>
                    {{ __('Product group') }}: {{ $productGroups->firstWhere('id', $filters['product_group'])?->name ?? __('All Groups') }}<br>
                    {{ __('Warehouse') }}: {{ $warehouses->firstWhere('id', $filters['warehouse_id'])?->name ?? __('All Warehouses') }}<br>
                    {{ __('From') }} {{ localizeNumber(convertToJalali($filters['start_date'], true)) }} {{ __('to') }} {{ localizeNumber(convertToJalali($filters['end_date'], true)) }}
                </td>
                <td class="company">{{ $company?->name }}</td>
                <td class="page-meta">
                    {{ __('Created at') }}: {{ localizeNumber($generatedAt) }}<br>
                    {{ __('Page') }}: {PAGENO} {{ __('of') }} {nbpg}
                </td>
            </tr>
        </table>
    </htmlpageheader>

    <table class="report">
        <thead>
            <tr>
                <th rowspan="2">{{ __('Subject Code') }}</th>
                <th rowspan="2">{{ __('Product name') }}</th>
                <th colspan="2">{{ __('Remaining from past') }}</th>
                <th colspan="2">{{ __('Imported') }}</th>
                <th colspan="2">{{ __('Exported') }}</th>
                <th colspan="2">{{ __('Remaining') }}</th>
            </tr>
            <tr>
                @foreach (range(1, 4) as $group)
                    <th>{{ __('Quantity') }}</th>
                    <th>{{ __('Total Amount') }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ formatCode($row['subject_code']) }}</td>
                    <td class="name">{{ $row['product_name'] }}</td>
                    <td>{{ formatNumber($row['opening_quantity']) }}</td>
                    <td>{{ formatNumber($row['opening_balance']) }}</td>
                    <td>{{ formatNumber($row['imported_quantity']) }}</td>
                    <td>{{ formatNumber($row['imported_balance']) }}</td>
                    <td>{{ formatNumber($row['exported_quantity']) }}</td>
                    <td>{{ formatNumber($row['exported_balance']) }}</td>
                    <td>{{ formatNumber($row['remaining_quantity']) }}</td>
                    <td>{{ formatNumber($row['remaining_balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">{{ __('No products found.') }}</td>
                </tr>
            @endforelse
            <tr class="total">
                <td colspan="2">{{ __('Total') }}</td>
                <td>{{ formatNumber($totals['opening_quantity']) }}</td>
                <td>{{ formatNumber($totals['opening_balance']) }}</td>
                <td>{{ formatNumber($totals['imported_quantity']) }}</td>
                <td>{{ formatNumber($totals['imported_balance']) }}</td>
                <td>{{ formatNumber($totals['exported_quantity']) }}</td>
                <td>{{ formatNumber($totals['exported_balance']) }}</td>
                <td>{{ formatNumber($totals['remaining_quantity']) }}</td>
                <td>{{ formatNumber($totals['remaining_balance']) }}</td>
            </tr>
        </tbody>
    </table>
</body>

</html>
