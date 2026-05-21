@php
    $totalBankBalance = collect($topTenBankAccountBalances ?? [])->sum() * -1;
    $totalMonthlyIncome = collect($monthlyIncome ?? [])->sum();
    $totalMonthlyCost = collect($monthlyCost ?? [])->sum();
    $profitValue = (int) ($profit ?? 0);
    $currency = config('amir.currency') ?? __('Rial');

    $financialCards = [
        [
            'title' => __('Total Bank Balance'),
            'value' => $totalBankBalance,
            'suffix' => $currency,
            'tone' => 'primary',
            'icon' => 'M3 10h18M5 6h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Zm12 8h2',
        ],
        [
            'title' => __('Income') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyIncome,
            'suffix' => $currency,
            'tone' => 'success',
            'icon' => 'M4 17l6-6 4 4 8-8M14 7h6v6',
        ],
        [
            'title' => __('Cost') . ' (' . __('Year to date') . ')',
            'value' => $totalMonthlyCost,
            'suffix' => $currency,
            'tone' => 'error',
            'icon' => 'M4 7l6 6 4-4 8 8M14 17h6v-6',
        ],
        [
            'title' => __('Profit and loss'),
            'value' => abs($profitValue),
            'suffix' => $profitValue >= 0 ? __('Profit') : __('Loss'),
            'tone' => $profitValue >= 0 ? 'success' : 'error',
            'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8v8m0 0v2',
        ],
    ];
@endphp

<section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($financialCards as $card)
        @php
            $tone = [
                'info' => ['card' => 'border-sky-500/25 bg-sky-500/10', 'icon' => 'bg-sky-500/15 text-sky-500'],
                'error' => ['card' => 'border-rose-500/25 bg-rose-500/10', 'icon' => 'bg-rose-500/15 text-rose-500'],
                'success' => ['card' => 'border-emerald-500/25 bg-emerald-500/10', 'icon' => 'bg-emerald-500/15 text-emerald-500'],
                'primary' => ['card' => 'border-primary/25 bg-primary/10', 'icon' => 'bg-primary/15 text-primary'],
                'warning' => ['card' => 'border-amber-500/25 bg-amber-500/10', 'icon' => 'bg-amber-500/15 text-amber-500'],
                'secondary' => ['card' => 'border-violet-500/25 bg-violet-500/10', 'icon' => 'bg-violet-500/15 text-violet-500'],
            ][$card['tone']] ?? ['card' => 'border-base-300 bg-base-200', 'icon' => 'bg-base-300 text-base-content'];
        @endphp
        <article class="card border shadow-sm {{ $tone['card'] }}">
            <div class="card-body gap-3 p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="rounded-lg p-2 {{ $tone['icon'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" />
                        </svg>
                    </div>
                    <span class="text-xs text-base-content/60 text-right">{{ $card['title'] }}</span>
                </div>

                <div>
                    <div class="text-xl font-bold leading-8 text-base-content">
                        {{ formatNumber($card['value']) }}
                    </div>
                    <div class="text-xs text-base-content/60">
                        {{ $card['suffix'] }}
                    </div>
                </div>
            </div>
        </article>
    @endforeach
</section>
