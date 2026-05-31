@php
    $incomeItems = collect($incomeBreakdown ?? [])
        ->map(fn ($amount, $name) => ['name' => $name, 'amount' => (int) $amount, 'type' => __('Income')])
        ->values();

    $costItems = collect($costBreakdown ?? [])
        ->map(fn ($amount, $name) => ['name' => $name, 'amount' => (int) $amount, 'type' => __('Cost')])
        ->values();
@endphp

<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Income Breakdown') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Income by subject') }}</p>
            </div>
        </div>

        <div class="mt-3">
            @if ($incomeItems->isNotEmpty())
                <x-charts.pie-chart :datas="$incomeItems" metric="amount" :label="__('Income')" heightClass="h-64" />
            @else
                <div class="rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                    {{ __('No data available.') }}
                </div>
            @endif
        </div>
    </div>
</article>

<article class="card border border-base-300 bg-base-100/90 shadow-sm">
    <div class="card-body p-4">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="card-title text-base">{{ __('Cost Breakdown') }}</h2>
                <p class="text-xs text-base-content/55">{{ __('Cost by subject') }}</p>
            </div>
        </div>

        <div class="mt-3">
            @if ($costItems->isNotEmpty())
                <x-charts.pie-chart :datas="$costItems" metric="amount" :label="__('Cost')" heightClass="h-64" />
            @else
                <div class="rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                    {{ __('No data available.') }}
                </div>
            @endif
        </div>
    </div>
</article>
