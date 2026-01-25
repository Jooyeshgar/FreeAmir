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
                                color: '#f1f5f9',
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f1f5f9',
                            },
                            ticks: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: @json($resolvedShowLegend)
                        },
                        tooltip: {
                            rtl: true,
                            backgroundColor: '#111827',
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
                            color: @json($datalabelColor),
                            font: {
                                weight: 'bold',
                                size: 12
                            },
                            formatter: (value) => value.toLocaleString()
                        }
                    }
                }
            });
        });
    </script>
@endpush
