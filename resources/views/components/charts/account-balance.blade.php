@props(['labels', 'datas'])

<div class="h-[25rem]">
    <canvas id="accountBalanceChart" class="w-full rounded-lg bg-white dark:bg-slate-900"></canvas>
</div>

@pushOnce('footer')
    <script>
        var accountBalanceChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('accountBalanceChart').getContext('2d');
            const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                textColor: '#475569',
                mutedTextColor: '#64748b',
                gridColor: 'rgba(148, 163, 184, 0.24)',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };

            const data = {
                labels: {!! json_encode($labels) !!},
                datasets: [{
                    label: 'نمودار درصدی',
                    data: {!! json_encode($datas) !!},
                    borderColor: function() {
                        return getTheme().isDark ? '#38bdf8' : '#0ea5e9';
                    },
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: function() {
                        return getTheme().isDark ? '#0f172a' : '#fff';
                    },
                    pointBorderColor: function(context) {
                        const value = context.raw;
                        return value >= 0 ? '#22c55e' : '#ef4444';
                    },
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            };

            const options = {
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: function() {
                                return getTheme().gridColor;
                            },
                        },
                        ticks: {
                            color: function() {
                                return getTheme().mutedTextColor;
                            },
                        },
                    },
                    y: {
                        grid: {
                            display: true,
                            color: function() {
                                return getTheme().gridColor;
                            },
                        },
                        beginAtZero: true,
                        ticks: {
                            color: function() {
                                return getTheme().mutedTextColor;
                            },
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: true,
                        rtl: true,
                        backgroundColor: function() {
                            return getTheme().tooltipBackgroundColor;
                        },
                        titleColor: function() {
                            return getTheme().tooltipTextColor;
                        },
                        bodyColor: function() {
                            return getTheme().tooltipTextColor;
                        },
                    },
                    datalabels: {
                        align: 'top',
                        anchor: 'end',
                        color: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? '#22c55e' : '#ef4444';
                        },
                        font: {
                            weight: 'bold',
                            size: 14,
                        },
                        formatter: (value) => value + '%',
                    }
                }
            };
            accountBalanceChart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: options,
            });

            window.addEventListener('theme:changed', () => accountBalanceChart?.update());
        });
    </script>
@endPushOnce
