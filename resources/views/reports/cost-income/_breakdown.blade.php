@php
    // Palette shared between the doughnut canvases and the legend swatches below.
    $breakdownPalette = ['#22c55e', '#3b82f6', '#f59e0b', '#a855f7', '#ec4899', '#06b6d4', '#ef4444', '#14b8a6', '#f97316', '#64748b'];

    $breakdownSections = [
        [
            'id' => 'costIncomeIncomeBreakdown',
            'title' => __('Income Breakdown'),
            'subtitle' => __('Income by subject'),
            'centerLabel' => __('Total Income'),
            'items' => collect($incomeBreakdown ?? [])->map(fn ($amount, $name) => ['name' => $name, 'amount' => (int) $amount])->values(),
        ],
        [
            'id' => 'costIncomeCostBreakdown',
            'title' => __('Cost Breakdown'),
            'subtitle' => __('Cost by subject'),
            'centerLabel' => __('Total Cost'),
            'items' => collect($costBreakdown ?? [])->map(fn ($amount, $name) => ['name' => $name, 'amount' => (int) $amount])->values(),
        ],
    ];
@endphp

@foreach ($breakdownSections as $section)
    @php $sectionTotal = max($section['items']->sum('amount'), 1); @endphp
    <article class="card border border-base-300 bg-base-100/90 shadow-sm">
        <div class="card-body p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="card-title text-base">{{ $section['title'] }}</h2>
                    <p class="text-xs text-base-content/55">{{ $section['subtitle'] }}</p>
                </div>
            </div>

            @if ($section['items']->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 items-center gap-5 sm:grid-cols-[13rem_1fr]">
                    <div class="relative mx-auto h-52 w-52">
                        <canvas id="{{ $section['id'] }}" class="h-full w-full"></canvas>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span class="text-lg font-bold tabular-nums">{{ formatNumber($section['items']->sum('amount')) }}</span>
                            <span class="text-xs text-base-content/55">{{ $section['centerLabel'] }}</span>
                        </div>
                    </div>

                    <ul class="space-y-2 text-sm">
                        @foreach ($section['items']->take(6) as $index => $item)
                            <li class="flex items-center justify-between gap-3">
                                <span class="flex min-w-0 items-center gap-2">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full"
                                        style="background: {{ $breakdownPalette[$index % count($breakdownPalette)] }}"></span>
                                    <span class="truncate font-medium">{{ $item['name'] }}</span>
                                </span>
                                <span class="shrink-0 tabular-nums">
                                    {{ formatNumber($item['amount']) }}
                                    <span class="text-base-content/45">{{ formatNumber(round($item['amount'] / $sectionTotal * 100)) }}{{ __('Percent sign') }}</span>
                                </span>
                            </li>
                        @endforeach

                        @if ($section['items']->count() > 6)
                            <li class="flex items-center justify-between gap-3 border-t border-base-300 pt-2 text-xs text-base-content/55">
                                <span>{{ __('Other') }}</span>
                                <span class="tabular-nums">{{ formatNumber($section['items']->slice(6)->sum('amount')) }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                <div class="mt-3 rounded-lg border border-dashed border-base-300 bg-base-200/50 p-5 text-center text-sm text-base-content/60">
                    {{ __('No data available.') }}
                </div>
            @endif
        </div>
    </article>
@endforeach

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const palette = @json($breakdownPalette);
            const sections = @json(collect($breakdownSections)->map(fn ($s) => ['id' => $s['id'], 'items' => $s['items']])->values());
            const instances = {};

            const formatMoney = (value) => {
                const locale = document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US';
                return new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(value);
            };

            const renderCharts = () => {
                if (!window.Chart) {
                    return;
                }

                const theme = window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                    canvasBackgroundColor: '#ffffff',
                    tooltipBackgroundColor: '#111827',
                    tooltipTextColor: '#f8fafc',
                };

                sections.forEach((section) => {
                    const canvas = document.getElementById(section.id);
                    if (!canvas) {
                        return;
                    }

                    const items = (section.items || []).filter((item) => item.amount > 0);
                    if (!items.length) {
                        return;
                    }

                    instances[section.id]?.destroy();
                    instances[section.id] = new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: items.map((item) => item.name),
                            datasets: [{
                                data: items.map((item) => item.amount),
                                backgroundColor: items.map((_, index) => palette[index % palette.length]),
                                borderColor: theme.canvasBackgroundColor || 'transparent',
                                borderWidth: 2,
                                hoverOffset: 4,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '62%',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    rtl: true,
                                    backgroundColor: theme.tooltipBackgroundColor,
                                    titleColor: theme.tooltipTextColor,
                                    bodyColor: theme.tooltipTextColor,
                                    callbacks: {
                                        label: (context) => `${context.label}: ${formatMoney(context.parsed)}`,
                                    },
                                },
                            },
                        },
                    });
                });
            };

            renderCharts();
            window.addEventListener('theme:changed', renderCharts);
        });
    </script>
@endpush
