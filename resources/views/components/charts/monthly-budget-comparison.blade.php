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
    $lightBackgroundPalette = [
        ['#2563eb', '#f59e0b'], // Forecast income, forecast expense
        ['#059669', '#e11d48'], // Actual income, actual expense
    ];
    $lightBorderPalette = [['#1d4ed8', '#d97706'], ['#047857', '#be123c']];
    $darkBackgroundPalette = [
        ['#60a5fa', '#fbbf24'], // Forecast income, forecast expense
        ['#34d399', '#fb7185'], // Actual income, actual expense
    ];
    $darkBorderPalette = [['#93c5fd', '#fde68a'], ['#6ee7b7', '#fda4af']];
    $normalizedDatasets = collect($datasets)
        ->map(function (array $dataset) use ($labels) {
            $dataset['data'] = collect($labels)->map(fn(string $label) => $dataset['data'][$label] ?? 0)->all();
            $dataset['borderWidth'] = 1;

            return $dataset;
        })
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
                textColor: '#475569',
                mutedTextColor: '#64748b',
                gridColor: 'rgba(148, 163, 184, 0.24)',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };
            const datasets = @json($normalizedDatasets);
            const lightBackgroundPalette = @json($lightBackgroundPalette);
            const lightBorderPalette = @json($lightBorderPalette);
            const darkBackgroundPalette = @json($darkBackgroundPalette);
            const darkBorderPalette = @json($darkBorderPalette);
            const getDatasetColor = (lightPalette, darkPalette, datasetIndex, dataIndex) => {
                const palette = getTheme().isDark ? darkPalette : lightPalette;
                const datasetColors = palette[datasetIndex] ?? palette[0];

                return datasetColors[dataIndex] ?? datasetColors[0];
            };

            datasets.forEach((dataset, datasetIndex) => {
                dataset.backgroundColor = (context) => getDatasetColor(
                    lightBackgroundPalette,
                    darkBackgroundPalette,
                    datasetIndex,
                    context.dataIndex,
                );
                dataset.borderColor = (context) => getDatasetColor(
                    lightBorderPalette,
                    darkBorderPalette,
                    datasetIndex,
                    context.dataIndex,
                );
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
                            color: (context) => getDatasetColor(
                                lightBorderPalette,
                                darkBorderPalette,
                                context.datasetIndex,
                                context.dataIndex,
                            ),
                            font: {
                                weight: 'bold',
                                size: 12,
                            },
                            formatter: (value) => value.toLocaleString(),
                        },
                    },
                },
            });

            window.addEventListener('theme:changed', () => {
                window.__chartInstances[chartId]?.update();
            });
        });
    </script>
@endpush
