@props([
    'datas' => [],
    'metric' => 'value',
    'label' => null,
    'labels' => [],
    'data' => [],
    'colors' => [],
    'chartId' => null,
    'position' => 'bottom',
    'heightClass' => '',
    'cutout' => '0%',
    'centerValue' => null,
    'centerLabel' => null,
    'dark' => false,
])

@php
    $resolvedChartId = $chartId ?? 'pie-chart-'.uniqid();
    $items = collect($datas)->filter(fn ($item) => data_get($item, $metric, 0) > 0)->values();
    $resolvedLabels = count($labels) ? array_values($labels) : $items->pluck('name')->values()->all();
    $resolvedData = count($data) ? array_values($data) : $items->pluck($metric)->values()->all();
@endphp

<div {{ $attributes->class([$heightClass]) }}>
    <canvas id="{{ $resolvedChartId }}" class="max-w-full"></canvas>
</div>

@push('footer')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const chartLabel = @json($label);
            const position = @json($position);
            const items = @json($items);
            const labels = @json($resolvedLabels);
            const values = @json($resolvedData);
            const baseColors = [
                '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
                '#8B5CF6', '#EC4899', '#22C55E', '#06B6D4',
                '#F97316', '#64748B',
            ];

            function uniqueColors(count) {
                const colors = [];
                let idx = 0;
                while (colors.length < count) {
                    colors.push(baseColors[idx % baseColors.length]);
                    idx++;
                }
                return colors;
            }

            const configuredColors = @json($colors);
            const renderedLabels = values.length ? labels : [@json(__('No data'))];
            const renderedValues = values.length ? values : [1];
            const backgroundColors = values.length
                ? (configuredColors.length ? configuredColors : uniqueColors(values.length))
                : ['#33455b'];

            const ctx = document.getElementById(@json($resolvedChartId));
            if (!ctx || typeof Chart === 'undefined') return;
            const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                textColor: '#475569',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };
            const getChartTheme = () => @json($dark) ? {
                ...getTheme(),
                textColor: '#f8fafc',
                mutedTextColor: '#94a3b8',
            } : getTheme();

            const centerTextPlugin = {
                id: `centerText-${@json($resolvedChartId)}`,
                afterDraw(chart) {
                    const value = @json($centerValue);
                    if (value === null || !chart.chartArea) return;

                    const { ctx: chartContext, chartArea } = chart;
                    const x = (chartArea.left + chartArea.right) / 2;
                    const y = (chartArea.top + chartArea.bottom) / 2;
                    const theme = getChartTheme();
                    chartContext.save();
                    chartContext.textAlign = 'center';
                    chartContext.textBaseline = 'middle';
                    chartContext.fillStyle = theme.textColor;
                    chartContext.font = '700 22px vazir';
                    chartContext.fillText(Number(value).toLocaleString(), x, y - 8);
                    chartContext.fillStyle = theme.mutedTextColor || '#94a3b8';
                    chartContext.font = '10px vazir';
                    chartContext.fillText(@json($centerLabel), x, y + 16);
                    chartContext.restore();
                },
            };

            window.__chartInstances = window.__chartInstances || {};
            window.__chartInstances[@json($resolvedChartId)]?.destroy();
            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: renderedLabels,
                    datasets: [{
                        data: renderedValues,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                        borderColor: () => @json($dark) || getTheme().isDark ? '#15263b' : '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: @json(blank($heightClass)),
                    cutout: @json($cutout),
                    plugins: {
                        legend: {
                            position: position,
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 14,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                                color: function() {
                                    return getChartTheme().textColor;
                                },
                            },
                        },
                        tooltip: {
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
                            callbacks: {
                                title: (ctx) => renderedLabels[ctx[0].dataIndex],
                                label: (ctx) => {
                                    if (!values.length) return @json(__('No data'));
                                    const item = items[ctx.dataIndex];
                                    const value = ctx.raw;
                                    const details = item?.type ? [`${@json(__('Type'))}: ${item.type}`] : [];
                                    details.push(`${chartLabel ? chartLabel + ': ' : ''}${value.toLocaleString()}`);
                                    return details;
                                }
                            }
                        }
                    }
                },
                plugins: [centerTextPlugin],
            });

            window.__chartInstances[@json($resolvedChartId)] = chart;
            window.addEventListener('theme:changed', () => chart.update());
        });
    </script>
@endpush
