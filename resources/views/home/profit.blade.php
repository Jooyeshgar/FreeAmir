@php
    $profitLabels = array_unique(array_merge(array_keys($totalIncomesData), array_keys($totalCostsData)));
    $alignedIncomesData = array_fill_keys($profitLabels, 0);
    $alignedCostsData = array_fill_keys($profitLabels, 0);
    foreach ($totalIncomesData as $k => $v) {
        $alignedIncomesData[$k] = $v;
    }
    foreach ($totalCostsData as $k => $v) {
        $alignedCostsData[$k] = $v;
    }
@endphp

<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Profit and loss') }}</h2>
                <p class="text-xs text-base-content/55">
                    <span class="font-semibold {{ $profit >= 0 ? 'text-success' : 'text-error' }}">
                        {{ formatNumber($profit) }} {{ config('amir.currency') ?? __('Rial') }}
                    </span>
                </p>
            </div>
        </div>

        <div class="mt-3">
            <x-charts.bar-chart chart-id="profitChart" heightClass="h-64" :datasets="[
                ['data' => $alignedIncomesData, 'backgroundColor' => '#22c55ecc', 'borderColor' => '#22c55e'],
                ['data' => $alignedCostsData, 'backgroundColor' => '#ef4444cc', 'borderColor' => '#ef4444'],
            ]" />
        </div>
    </div>
</article>
