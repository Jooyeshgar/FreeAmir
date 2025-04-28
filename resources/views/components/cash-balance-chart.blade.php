@props(['labels', 'datas'])

@php
$convertedLabels = [];
foreach($labels as $label) {
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $label)) {
        $parts = explode('-', $label);
        $year = (int)$parts[0];
        $month = (int)$parts[1];
        $day = (int)$parts[2];
        
        $jalaliDate = gregorian_to_jalali($year, $month, $day, '/');

        $convertedLabels[] = $jalaliDate;
    } else {
        $convertedLabels[] = $label;
    }
}
@endphp

<canvas id="cashBalanceLineChart" class="bg-white rounded-[16px]"></canvas>

@pushOnce('footer')
    <script>
        var cashBalanceLineChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('cashBalanceLineChart').getContext('2d');

            const data = {
                labels: {!! json_encode($convertedLabels) !!},
                datasets: [{
                    label: 'نمودار درصدی',
                    data: {!! json_encode($datas) !!},
                    borderColor: '#888',
                    borderWidth: 5,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: function(context) {
                        const value = context.raw;
                        return value >= 0 ? 'green' : 'red';
                    },
                    pointBorderWidth: 3,
                    pointRadius: 6
                }]
            };

            const options = {
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
                        beginAtZero: false,
                        ticks: {
                            display: false
                        }
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
            cashBalanceLineChart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: options,
            });
        });
    </script>
@endPushOnce
