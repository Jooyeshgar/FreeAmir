@props([
    'labels' => [],
    'datasets' => [],
    'chartId' => null,
    'heightClass' => 'h-64',
    'showLegend' => false,
    'tabs' => [],
    'activeTab' => null,
])

@php
    $resolvedChartId = $chartId ?? 'line-chart-'.uniqid();
    $resolvedActiveTab = $activeTab ?? array_key_first($tabs);
@endphp

<div
    {{ $attributes->class(['relative w-full min-w-0 overflow-hidden', $heightClass]) }}
    dir="ltr"
    @if (count($tabs))
        data-chart-tabs='@json($tabs)'
        data-active-tab="{{ $resolvedActiveTab }}"
    @endif
>
    <canvas id="{{ $resolvedChartId }}" class="max-w-full"></canvas>
</div>

@push('footer')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chartId = @json($resolvedChartId);
            const canvas = document.getElementById(chartId);
            if (!canvas || typeof Chart === 'undefined') return;

            const context = canvas.getContext('2d');
            const chartContainer = canvas.parentElement;
            const tabDatasets = chartContainer.dataset.chartTabs ? JSON.parse(chartContainer.dataset.chartTabs) : null;
            const activeTab = chartContainer.dataset.activeTab;
            const initialDatasets = tabDatasets?.[activeTab]?.datasets ?? @json($datasets);
            const getTheme = () => window.getFreeAmirChartTheme ? window.getFreeAmirChartTheme() : {
                textColor: '#475569',
                mutedTextColor: '#64748b',
                gridColor: 'rgba(148, 163, 184, 0.24)',
                tooltipBackgroundColor: '#111827',
                tooltipTextColor: '#f8fafc',
            };
            const gradient = context.createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight || 256);
            gradient.addColorStop(0, 'rgba(22, 163, 148, 0.3)');
            gradient.addColorStop(1, 'rgba(22, 163, 148, 0)');

            window.__chartInstances = window.__chartInstances || {};
            window.__chartInstances[chartId]?.destroy();
            window.__chartInstances[chartId] = new Chart(context, {
                type: 'line',
                data: {
                    labels: @json($labels),
                    datasets: initialDatasets.map((dataset) => ({
                        borderColor: '#16a394',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#16a394',
                        pointBorderWidth: 3,
                        tension: 0.38,
                        fill: true,
                        ...dataset,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                color: () => getTheme().mutedTextColor,
                                font: { size: 11 },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: { color: () => getTheme().gridColor },
                            ticks: {
                                color: () => getTheme().mutedTextColor,
                                precision: 0,
                                callback: (value) => value.toLocaleString(),
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: @json($showLegend),
                            labels: { color: () => getTheme().textColor },
                        },
                        tooltip: {
                            rtl: @json(app()->isLocale('fa')),
                            backgroundColor: () => getTheme().tooltipBackgroundColor,
                            titleColor: () => getTheme().tooltipTextColor,
                            bodyColor: () => getTheme().tooltipTextColor,
                            padding: 10,
                            callbacks: {
                                label: (item) => `${item.dataset.label ? item.dataset.label + ': ' : ''}${item.raw.toLocaleString()}`,
                            },
                        },
                    },
                },
            });

            if (tabDatasets) {
                const chart = window.__chartInstances[chartId];
                const applyDatasetDefaults = (dataset) => ({
                    borderColor: '#16a394',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#16a394',
                    pointBorderWidth: 3,
                    tension: 0.38,
                    fill: true,
                    ...dataset,
                });

                document.querySelectorAll(`[data-chart-target="${chartId}"]`).forEach((button) => {
                    button.addEventListener('click', () => {
                        const tab = button.dataset.chartTab;
                        if (!tabDatasets[tab]) return;

                        chart.data.datasets = tabDatasets[tab].datasets.map(applyDatasetDefaults);
                        chart.update();
                        document.querySelectorAll(`[data-chart-target="${chartId}"]`).forEach((tabButton) => {
                            const selected = tabButton === button;
                            tabButton.dataset.active = selected ? 'true' : 'false';
                            tabButton.setAttribute('aria-selected', selected ? 'true' : 'false');
                        });
                    });
                });
            }

            window.addEventListener('theme:changed', () => window.__chartInstances[chartId]?.update());
        });
    </script>
@endpush
