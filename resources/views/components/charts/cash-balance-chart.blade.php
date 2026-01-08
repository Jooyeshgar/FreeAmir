@props(['labels', 'datas'])

@php
    $convertedLabels = [];
    foreach ($labels as $label) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $label)) {
            $parts = explode('-', $label);
            $year = (int) $parts[0];
            $month = (int) $parts[1];
            $day = (int) $parts[2];

            $jalaliDate = gregorian_to_jalali($year, $month, $day, '/');

            $convertedLabels[] = $jalaliDate;
        } else {
            $convertedLabels[] = $label;
        }
    }
@endphp

<canvas id="cashBalanceLineChart" class="bg-white w-full h-[18rem]"></canvas>
@pushOnce('footer')
    <script>
        var cashBalanceLineChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('cashBalanceLineChart').getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(0, 255, 200, 0.3)');
            gradient.addColorStop(1, 'rgba(0, 255, 200, 0)');

            cashBalanceLineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($convertedLabels) !!},
                    datasets: [{
                        data: {!! json_encode($datas) !!},
                        borderColor: '#888',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: function(context) {
                            const value = context.raw;
                            return value >= 0 ? 'green' : 'red';
                        },
                        pointBorderWidth: 2,
                        pointRadius: 5,
                    }]
                },
                options: {
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            display: false,
                        },
                        y: {
                            display: false,
                            beginAtZero: true,
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: true,
                            mode: 'nearest',
                            callbacks: {
                                label: function(context) {
                                    let value = context.raw;
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endPushOnce
