<!DOCTYPE html>
<html dir="rtl" lang="fa">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link rel="stylesheet" href="{{ resource_path('css/warehouse-report.css') }}">
</head>

<body>
    <htmlpageheader name="reportHeader">
        <table class="report-header">
            <tr>
                <td class="company">{{ $company?->name }}</td>
                <td class="title">{{ __('Warehouse Report') }}</td>
                <td class="date">{{ __('Report Date') }}: {{ localizeNumber($generatedAtTime) }}  {{ localizeNumber($generatedAtDate) }}</td>
            </tr>
        </table>
        <div class="filters">
            @foreach ($filterSummary as $item)
                <span class="chip">{{ $item['label'] }}:</span> {{ $item['value'] }}
                @if (!$loop->last)
                    &nbsp;|&nbsp;
                @endif
            @endforeach
        </div>
    </htmlpageheader>

    <htmlpagefooter name="reportFooter">
        <div class="page-footer">
            {{ __('Page') }} {PAGENO} {{ __('of') }} {nbpg}
        </div>
    </htmlpagefooter>

    <table class="report {{ $portrait ? 'portrait' : 'landscape' }}">
        <thead>
            <tr>
                <th class="col-index">{{ __('Row') }}</th>
                @foreach ($visible as $col)
                    <th class="col-{{ $col }}">{{ $columnLabels[$col] }}</th>
                @endforeach
                @if ($addDesc)
                    <th class="col-desc">{{ __('Desc') }}</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td class="col-index">{{ localizeNumber($loop->iteration) }}</td>
                    @foreach ($visible as $col)
                        @if (in_array($col, $numeric, true))
                            <td class="col-{{ $col }}">{{ formatNumber($row[$col]) }}</td>
                        @elseif ($col === 'code')
                            <td class="col-{{ $col }}"><bdi dir="rtl">{{ localizeNumber($row['code']) }}</bdi></td>
                        @else
                            <td class="col-{{ $col }}">{{ $row[$col] }}</td>
                        @endif
                    @endforeach
                    @if ($addDesc)
                        <td class="col-desc"></td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ count($visible) + 1 + ($addDesc ? 1 : 0) }}">{{ __('No products match the selected filters.') }}</td>
                </tr>
            @endforelse

            @if ($rows->isNotEmpty() && $showTotalRow)
                <tr class="total-row">
                    @foreach ($totalRow as $cell)
                        @if ($cell['type'] === 'merge')
                            <td class="total-label" colspan="{{ $cell['colspan'] }}">{{ $cell['label'] }}</td>
                        @else
                            <td class="col-{{ $cell['col'] }}">{{ formatNumber($cell['value']) }}</td>
                        @endif
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>

</html>
