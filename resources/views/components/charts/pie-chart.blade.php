@props(['datas', 'metric', 'label', 'position' => 'bottom', 'heightClass' => ''])

@php
    $chartId = 'pie_' . uniqid();
@endphp

<div class="{{ $heightClass }}">
    <canvas id="{{ $chartId }}"></canvas>
</div>

@push('footer')
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const metric = @json($metric);
            const chartLabel = @json($label);
            const position = @json($position);
            const items = @json($datas).filter(i => i[metric] > 0);
            const baseColors = [
                '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
                '#8B5CF6', '#EC4899', '#22C55E', '#06B6D4',
                '#F97316', '#64748B',
            ];

            if (!items.length) return;

            function uniqueColors(count) {
                const colors = [];
                let idx = 0;
                while (colors.length < count) {
                    colors.push(baseColors[idx % baseColors.length]);
                    idx++;
                }
                return colors;
            }

            const backgroundColors = uniqueColors(items.length);

            const values = items.map(i => i[metric]);

            const ctx = document.getElementById(@json($chartId));
            if (!ctx || typeof Chart === 'undefined') return;

            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: items.map(i => i.name),
                    datasets: [{
                        data: values,
                        backgroundColor: backgroundColors,
                        borderWidth: 2,
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: position
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                title: (ctx) => items[ctx[0].dataIndex].name,
                                label: (ctx) => {
                                    const item = items[ctx.dataIndex];
                                    const value = ctx.raw;
                                    return [
                                        `${@json(__('Type'))}: ${item.type}`,
                                        `${chartLabel}: ${value.toLocaleString()}`,
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
