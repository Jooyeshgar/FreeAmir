<div class="relative {{ $heightClass }}">
    <canvas id="{{ $resolvedChartId }}"></canvas>
</div>

@push('footer')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const chartId = @json($resolvedChartId);
            const canvas = document.getElementById(chartId);
            if (!canvas || typeof Chart === 'undefined') return;

            const ctx = canvas.getContext('2d');
            const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                textColor: '#475569',
                mutedTextColor: '#64748b',
                gridColor: 'rgba(148, 163, 184, 0.24)',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };

            window.__chartInstances = window.__chartInstances || {};
            if (window.__chartInstances[chartId]) {
                window.__chartInstances[chartId].destroy();
            }

            window.__chartInstances[chartId] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: @json($normalizedDatasets).map((dataset) => ({
                        ...dataset,
                        backgroundColor: (context) => {
                            const value = context.raw ?? 0;
                            const positive = dataset.backgroundColor ||
                                @json($backgroundColor);
                            const negative = dataset.negativeColor ||
                                @json($negativeColor);
                            return value >= 0 ? positive : negative;
                        },
                        borderColor: (context) => {
                            const value = context.raw ?? 0;
                            const positive = dataset.borderColor ||
                                @json($borderColor);
                            const negative = dataset.negativeBorderColor || dataset
                                .negativeColor || @json($negativeColor);
                            return value >= 0 ? positive : negative;
                        },
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                color: () => getTheme().gridColor,
                            },
                            ticks: {
                                color: () => getTheme().mutedTextColor,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: () => getTheme().gridColor,
                            },
                            ticks: {
                                color: () => getTheme().mutedTextColor,
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: @json($resolvedShowLegend),
                            labels: {
                                color: () => getTheme().textColor,
                            },
                        },
                        tooltip: {
                            rtl: true,
                            backgroundColor: () => getTheme().tooltipBackgroundColor,
                            titleColor: () => getTheme().tooltipTextColor,
                            bodyColor: () => getTheme().tooltipTextColor,
                            titleFont: {
                                size: 12
                            },
                            bodyFont: {
                                size: 12
                            },
                            padding: 10,
                            callbacks: {
                                label: (ctx) =>
                                    `${ctx.dataset.label ? ctx.dataset.label + ': ' : ''}${ctx.raw.toLocaleString()}`
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: (context) => context.dataset.datalabelColor || @json($datalabelColor),
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            formatter: (value) => value.toLocaleString()
                        }
                    }
                }
            });

            window.addEventListener('theme:changed', () => {
                window.__chartInstances[chartId]?.update();
            });
        });
    </script>
@endpush
