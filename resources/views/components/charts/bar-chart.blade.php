@props([
    'datas' => [],
    'chartId' => null,
    'heightClass' => 'h-64',
    'label' => 'موجودی انبار',
    'backgroundColor' => '#4bb946c4',
    'borderColor' => '#4bb946',
])

@php
    $resolvedChartId = $chartId ?? ($attributes->get('id') ?? 'barChart_' . uniqid());
@endphp

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
                    labels: @json(array_keys($datas)),
                    datasets: [{
                        label: @json($label),
                        data: @json(array_values($datas)),
                        backgroundColor: function(context) {
                            const value = context.raw;
                            return value >= 0 ? @json($backgroundColor) : 'red';
                        },
                        borderColor: function(context) {
                            const value = context.raw;
                            return value >= 0 ? @json($borderColor) : 'red';
                        },
                        borderWidth: 2,
                        borderRadius: 0,
                        borderSkipped: false,
                    }]
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
                            display: false
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
                                label: (ctx) => ` ${ctx.raw.toLocaleString()}`
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: '#166534',
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
