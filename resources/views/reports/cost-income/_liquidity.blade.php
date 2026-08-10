@if (auth()->user()->can('reports.cost-income.cash-banks') || auth()->user()->can('reports.cost-income.bank-account'))
    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        @can('reports.cost-income.cash-banks')
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">{{ __('Cash and banks balances') }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('Trend across the selected period') }}</p>
                        </div>
                        <div class="flex gap-2">
                            <select id="liquidityType" class="select select-xs select-bordered">
                                @foreach ($cashTypes as $cashType)
                                    <option value="{{ $cashType }}">{{ __($cashType) }}</option>
                                @endforeach
                            </select>
                            <select id="liquidityDuration" class="select select-xs select-bordered">
                                @foreach ([1 => 3, 2 => 6, 3 => 9, 4 => 12] as $duration => $months)
                                    <option value="{{ $duration }}">{{ localizeNumber($months) }} {{ __('Month') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 h-72"><canvas id="liquidityBalanceChart"></canvas></div>
                    <p id="liquidityError" class="mt-2 hidden text-xs text-error">{{ __('Unable to load balance history.') }}</p>
                </div>
            </article>
        @endcan

        @can('reports.cost-income.bank-account')
            <article class="card border border-base-300 bg-base-100/90 shadow-sm">
                <div class="card-body p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="card-title text-base">{{ __('Bank Account Balance') }}</h2>
                            <p class="text-xs text-base-content/55">{{ __('Per-account balance over time') }}</p>
                        </div>
                        @if ($bankAccounts->isNotEmpty())
                            <div class="flex gap-2">
                                <select id="bankAccountSubject" class="select select-xs select-bordered">
                                    @foreach ($bankAccounts as $bankAccount)
                                        <option value="{{ $bankAccount->id }}">{{ $bankAccount->name }}</option>
                                    @endforeach
                                </select>
                                <select id="bankAccountDuration" class="select select-xs select-bordered">
                                    @foreach ([1 => 3, 2 => 6, 3 => 9, 4 => 12] as $duration => $months)
                                        <option value="{{ $duration }}">{{ localizeNumber($months) }} {{ __('Month') }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    @if ($bankAccounts->isEmpty())
                        <div class="mt-4 rounded-lg border border-dashed border-base-300 p-6 text-center text-sm text-base-content/60">
                            {{ __('No bank accounts found') }}
                        </div>
                    @else
                        <div class="mt-3 h-72"><canvas id="bankAccountBalanceChart"></canvas></div>
                        <p id="bankAccountError" class="mt-2 hidden text-xs text-error">{{ __('Unable to load balance history.') }}</p>
                    @endif
                </div>
            </article>
        @endcan
    </section>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const charts = {};
                const formatter = new Intl.NumberFormat(document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-US');
                const chartOptions = () => {
                    const theme = window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                        textColor: '#475569', mutedTextColor: '#64748b', gridColor: 'rgba(148,163,184,.24)'
                    };
                    return {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            datalabels: { display: false },
                            tooltip: { rtl: true, callbacks: { label: (ctx) => formatter.format(ctx.raw) } },
                        },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: theme.mutedTextColor, maxTicksLimit: 8 } },
                            y: { grid: { color: theme.gridColor }, ticks: { color: theme.mutedTextColor, callback: formatter.format } },
                        },
                    };
                };
                const draw = (key, canvasId, data) => {
                    const canvas = document.getElementById(canvasId);
                    if (!canvas || !window.Chart) return;
                    charts[key]?.destroy();
                    charts[key] = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.datas,
                                borderColor: '#0ea5e9',
                                backgroundColor: 'rgba(14,165,233,.12)',
                                fill: true,
                                pointRadius: 2,
                                tension: .35,
                            }],
                        },
                        options: chartOptions(),
                    });
                };
                const load = async (key, canvasId, errorId, url, params) => {
                    const error = document.getElementById(errorId);
                    error?.classList.add('hidden');
                    try {
                        const response = await fetch(`${url}?${new URLSearchParams(params)}`, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error(`HTTP ${response.status}`);
                        draw(key, canvasId, await response.json());
                    } catch (_) {
                        error?.classList.remove('hidden');
                    }
                };

                const type = document.getElementById('liquidityType');
                const liquidityDuration = document.getElementById('liquidityDuration');
                const loadLiquidity = () => load('liquidity', 'liquidityBalanceChart', 'liquidityError',
                    @json(route('reports.cost-income.cash-banks')), { type: type?.value, duration: liquidityDuration?.value });
                if (type && liquidityDuration) {
                    type.addEventListener('change', loadLiquidity);
                    liquidityDuration.addEventListener('change', loadLiquidity);
                    loadLiquidity();
                }

                const bank = document.getElementById('bankAccountSubject');
                const bankDuration = document.getElementById('bankAccountDuration');
                const loadBank = () => load('bank', 'bankAccountBalanceChart', 'bankAccountError',
                    @json(route('reports.cost-income.bank-account')), { subject_id: bank?.value, duration: bankDuration?.value });
                if (bank && bankDuration) {
                    bank.addEventListener('change', loadBank);
                    bankDuration.addEventListener('change', loadBank);
                    loadBank();
                }

                window.addEventListener('theme:changed', () => Object.values(charts).forEach((chart) => chart.update()));
            });
        </script>
    @endpush
@endif
