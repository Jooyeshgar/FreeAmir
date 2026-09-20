@php
    $totalBankBalance = collect($topTenBankAccountBalances ?? [])->sum() * -1;
    $totalMonthlyIncome = collect($monthlyIncome ?? [])->sum();
    $totalMonthlyCost = collect($monthlyCost ?? [])->sum();
    $profitValue = (int) ($profit ?? 0);
    $currency = config('amir.currency') ?? __('Rial');
    $profitPeriod = collect([
        filled($profitFilters['start_date_input'] ?? null) ? __('From') . ' ' . localizeNumber($profitFilters['start_date_input']) : null,
        filled($profitFilters['end_date_input'] ?? null) ? __('to') . ' ' . localizeNumber($profitFilters['end_date_input']) : null,
    ])->filter()->implode(' ');

    $incomeSeries = array_values($monthlyIncome ?? []);
    $costSeries = array_values($monthlyCost ?? []);
    $financialCards = [
        [
            'title' => __('Total Bank Balance'),
            'value' => $totalBankBalance,
            'suffix' => $currency,
            'tone' => 'primary',
            'icon' => 'bank',
        ],
        [
            'title' => __('Income') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyIncome,
            'suffix' => $currency,
            'tone' => 'success',
            'series' => $incomeSeries,
            'icon' => 'income',
        ],
        [
            'title' => __('Cost') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyCost,
            'suffix' => $currency,
            'tone' => 'error',
            'series' => $costSeries,
            'icon' => 'cost',
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($financialCards as $card)
        <x-metric-card :card="$card" />
    @endforeach

    <form action="{{ route('reports.company-overview') }}" method="GET" data-profit-card data-profit-filter
        class="card relative overflow-hidden rounded-lg border shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md
            {{ $profitValue >= 0
                ? 'border-emerald-200/80 bg-gradient-to-br from-base-100 via-emerald-50/70 to-emerald-100/40 shadow-emerald-900/5 dark:border-emerald-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-emerald-950/25 dark:shadow-black/20'
                : 'border-rose-200/80 bg-gradient-to-br from-base-100 via-rose-50/70 to-rose-100/40 shadow-rose-900/5 dark:border-rose-400/20 dark:from-slate-900/95 dark:via-slate-900/90 dark:to-rose-950/25 dark:shadow-black/20' }}">
        <div class="card-body gap-3 p-4">
            <div class="flex flex-row-reverse items-start justify-between gap-2">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1
                        {{ $profitValue >= 0
                            ? 'bg-emerald-500/10 text-emerald-600 ring-emerald-500/15 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-300/10'
                            : 'bg-rose-500/10 text-rose-600 ring-rose-500/15 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-300/10' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-[1.15rem] w-[1.15rem]" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 17l6-6 4 4 8-8m0 0h-5m5 0v5" />
                    </svg>
                </div>
                <span class="min-w-0 text-right text-xs font-medium leading-5 text-base-content/60 dark:text-slate-300/70">
                    {{ __('Profit and loss') }}
                </span>
            </div>

            <div class="min-w-0" data-profit-metric>
                <div class="truncate text-xl font-bold leading-8 text-base-content tabular-nums dark:text-slate-50">
                    {{ formatNumber(abs($profitValue)) }}
                </div>
                <div class="text-xs text-base-content/60 dark:text-slate-400">
                    {{ $profitValue >= 0 ? __('Profit') : __('Loss') }}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="[&_.input]:input-sm">
                    <x-date-picker name="start_date" value="{{ $profitFilters['start_date_input'] ?? '' }}"
                        placeholder="{{ __('Start date') }}" readonly="true" />
                </div>
                <div class="[&_.input]:input-sm">
                    <x-date-picker name="end_date" value="{{ $profitFilters['end_date_input'] ?? '' }}"
                        placeholder="{{ __('End date') }}" readonly="true" />
                </div>
            </div>

            <div class="flex items-center justify-between gap-2">
                <span class="min-w-0 truncate text-xs text-base-content/50 dark:text-slate-400/80">
                    {{ $profitPeriod }}
                </span>
                <div class="flex shrink-0 gap-1">
                    <a href="{{ route('reports.company-overview') }}" class="btn btn-sm btn-ghost">{{ __('Reset') }}</a>
                    <button type="submit" class="btn btn-sm btn-neutral">{{ __('Apply') }}</button>
                </div>
            </div>
        </div>
    </form>
</section>
