@props(['labels', 'datas'])

<div class="h-[25rem]">
    <canvas id="accountBalanceChart" class="bg-white w-full"></canvas>
</div>

@pushOnce('footer')
    <script>
        var accountBalanceChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('accountBalanceChart').getContext('2d');

            const data = {
                labels: {!! json_encode($labels) !!},
                datasets: [{
                    label: 'نمودار درصدی',
                    data: {!! json_encode($datas) !!},
                    borderColor: '#888',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: function(context) {
                        const value = context.raw;
                        return value >= 0 ? 'green' : 'red';
                    },
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            };

            const options = {
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: {
                            display: true,
                            color: '#e0e0e0',
                        },
                    },
                    y: {
                        grid: {
                            display: true,
                            color: '#e0e0e0',
                        },
                        beginAtZero: true,
                    }
                },
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: true,
                    },
                    datalabels: {
                        align: 'top',
                        anchor: 'end',
                        color: function(context) {
                            const value = context.dataset.data[context.dataIndex];
                            return value >= 0 ? 'green' : 'red';
                        },
                        font: {
                            weight: 'bold',
                            size: 14,
                        },
                        formatter: (value) => value + '%',
                    }
                }
            };
            accountBalanceChart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: options,
            });
        });
    </script>
@endPushOnce
