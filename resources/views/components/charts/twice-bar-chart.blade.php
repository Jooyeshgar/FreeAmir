@props([
    'firstData' => [],
    'secondData' => [],
    'chartId' => null,
    'heightClass' => 'h-64',
    'firstBackgroundColor' => '#4bb946c4',
    'secondBackgroundColor' => 'red',
    'firstBorderColor' => '#4bb946',
    'secondBorderColor' => 'red',
])

@php
    $resolvedChartId = $chartId ?? ($attributes->get('id') ?? 'barChart_' . uniqid());
    $mergedData = array_unique(array_merge(array_keys($firstData), array_keys($secondData)));
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
                    labels: @json($mergedData),
                    datasets: [{
                            data: @json(array_values($firstData)),
                            backgroundColor: @json($firstBackgroundColor),
                            borderColor: @json($firstBorderColor),
                            borderWidth: 2,
                            borderRadius: 0,
                            borderSkipped: false,
                        },
                        {
                            data: @json(array_values($secondData)),
                            backgroundColor: @json($secondBackgroundColor),
                            borderColor: @json($secondBorderColor),
                            borderWidth: 2,
                            borderRadius: 0,
                            borderSkipped: false,
                        }
                    ]
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
