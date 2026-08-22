@props([
    'datasets' => [],
    'chartId' => 'monthlyBudgetVarianceChart',
    'heightClass' => 'h-72',
])

@php
    $labels = collect($datasets)
        ->flatMap(fn(array $dataset) => array_keys(data_get($dataset, 'data', [])))
        ->unique()
        ->values()
        ->all();
    $palettes = [
        'background' => [
            'light' => [['#2563eb', '#f59e0b'], ['#059669', '#e11d48']],
            'dark' => [['#60a5fa', '#fbbf24'], ['#34d399', '#fb7185']],
        ],
        'border' => [
            'light' => [['#1d4ed8', '#d97706'], ['#047857', '#be123c']],
            'dark' => [['#93c5fd', '#fde68a'], ['#6ee7b7', '#fda4af']],
        ],
    ];
    $normalizedDatasets = collect($datasets)
        ->map(fn(array $dataset) => [...$dataset,
            'data' => collect($labels)->map(fn(string $label) => $dataset['data'][$label] ?? 0)->all(),
            'borderWidth' => 1,
        ])
        ->values()
        ->all();
@endphp

<div class="relative {{ $heightClass }}">
    <canvas id="{{ $chartId }}"></canvas>
</div>

@push('footer')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chartId = @json($chartId);
            const canvas = document.getElementById(chartId);
            if (!canvas || typeof Chart === 'undefined') return;

            const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                isDark: false,
                mutedTextColor: '#64748b',
                gridColor: 'rgba(148, 163, 184, 0.24)',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };
            const datasets = @json($normalizedDatasets);
            const palettes = @json($palettes);
            const getDatasetColor = (type, datasetIndex, dataIndex) => {
                const palette = palettes[type][getTheme().isDark ? 'dark' : 'light'];
                const datasetColors = palette[datasetIndex] ?? palette[0];

                return datasetColors[dataIndex] ?? datasetColors[0];
            };

            datasets.forEach((dataset, datasetIndex) => {
                ['background', 'border'].forEach((type) => {
                    dataset[`${type}Color`] = (context) => getDatasetColor(type, datasetIndex, context.dataIndex);
                });
            });

            window.__chartInstances = window.__chartInstances || {};
            window.__chartInstances[chartId]?.destroy();

            window.__chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets,
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
                                },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: () => getTheme().gridColor,
                            },
                            ticks: {
                                display: false,
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            rtl: document.documentElement.dir === 'rtl',
                            backgroundColor: () => getTheme().tooltipBackgroundColor,
                            titleColor: () => getTheme().tooltipTextColor,
                            bodyColor: () => getTheme().tooltipTextColor,
                            padding: 10,
                            callbacks: {
                                label: (context) =>
                                    `${context.dataset.label}: ${context.raw.toLocaleString()}`,
                            },
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: (context) => getDatasetColor('border', context.datasetIndex, context.dataIndex),
                            font: {
                                weight: 'bold',
                                size: 12,
                            },
                            formatter: (value) => value.toLocaleString(),
                        },
                    },
                },
            });

            window.addEventListener('theme:changed', () => window.__chartInstances[chartId]?.update());
        });
    </script>
@endpush
