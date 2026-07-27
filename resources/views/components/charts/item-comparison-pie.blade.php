@props(['chartId', 'datasets' => [], 'heightClass' => 'h-72'])

@php
    $labels = collect($datasets)
        ->flatMap(fn(array $dataset) => array_keys($dataset['data'] ?? []))
        ->unique()
        ->values()
        ->all();
    $hasValues = collect($datasets)->contains(
        fn(array $dataset) => collect($dataset['data'] ?? [])->contains(fn($value) => (float) $value > 0),
    );
@endphp

@if ($hasValues)
    <div class="relative {{ $heightClass }}">
        <canvas id="{{ $chartId }}"></canvas>
    </div>
@else
    <div
        class="flex {{ $heightClass }} items-center justify-center rounded-lg border border-dashed border-base-300 text-sm text-base-content/50">
        {{ __('No data available.') }}
    </div>
@endif

@if ($hasValues)
    @push('footer')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const chartId = @json($chartId);
                const canvas = document.getElementById(chartId);
                if (!canvas || typeof Chart === 'undefined') return;

                const labels = @json($labels);
                const sourceDatasets = @json($datasets);
                const colors = [
                    '#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed',
                    '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c',
                ];
                const colorWithAlpha = (color, alpha) => `${color}${alpha}`;
                const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                    textColor: '#475569',
                    axisColor: 'rgba(100, 116, 139, 0.35)',
                    tooltipBackgroundColor: '#111827',
                    tooltipTextColor: '#f8fafc',
                    isDark: false,
                };

                const normalizedDatasets = sourceDatasets.map((dataset) => ({
                    label: dataset.label,
                    data: labels.map((label) => Number(dataset.data[label] ?? 0)),
                    backgroundColor: labels.map((label, index) =>
                        colorWithAlpha(colors[index % colors.length], 'd9')
                    ),
                    borderColor: labels.map((label, index) => colors[index % colors.length]),
                    borderWidth: 2,
                    hoverOffset: 8,
                }));

                const emptySelectionBorderPlugin = {
                    id: 'emptySelectionBorder',
                    afterDraw: (chart) => {
                        const hasVisibleData = chart.data.datasets.some((dataset, datasetIndex) =>
                            chart.isDatasetVisible(datasetIndex) && dataset.data.some((value, dataIndex) =>
                                Number(value) > 0 && chart.getDataVisibility(dataIndex)
                            )
                        );

                        if (hasVisibleData) return;

                        const chartArea = chart.chartArea;
                        const firstArc = chart.getDatasetMeta(0)?.data?.[0];
                        const fallbackRadius = Math.min(chartArea.width, chartArea.height) * 0.42;
                        const radius = firstArc?.outerRadius > 0 ? firstArc.outerRadius : fallbackRadius;
                        const centerX = firstArc?.x ?? (chartArea.left + chartArea.right) / 2;
                        const centerY = firstArc?.y ?? (chartArea.top + chartArea.bottom) / 2;

                        chart.ctx.save();
                        chart.ctx.beginPath();
                        chart.ctx.arc(centerX, centerY, Math.max(radius - 1, 1), 0, Math.PI * 2);
                        chart.ctx.lineWidth = 2;
                        chart.ctx.strokeStyle = getTheme().axisColor;
                        chart.ctx.stroke();
                        chart.ctx.restore();
                    },
                };

                window.__chartInstances = window.__chartInstances || {};
                window.__chartInstances[chartId]?.destroy();

                window.__chartInstances[chartId] = new Chart(canvas.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels,
                        datasets: normalizedDatasets,
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: () => getTheme().textColor,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    padding: 14,
                                },
                            },
                            tooltip: {
                                rtl: true,
                                backgroundColor: () => getTheme().tooltipBackgroundColor,
                                titleColor: () => getTheme().tooltipTextColor,
                                bodyColor: () => getTheme().tooltipTextColor,
                                callbacks: {
                                    label: (context) =>
                                        `${context.dataset.label}: ${Number(context.raw).toLocaleString()}`,
                                },
                            },
                        },
                    },
                    plugins: [emptySelectionBorderPlugin],
                });

                window.addEventListener('theme:changed', () => {
                    window.__chartInstances[chartId]?.update();
                });
            });
        </script>
    @endpush
@endif
