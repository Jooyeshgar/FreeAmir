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

    $convertedDatas = array_map(fn($value) => $value * -1, $datas);
@endphp
<div class="relative w-full h-[18rem]">
    <canvas id="cashBalanceChart"></canvas>
</div>

@pushOnce('footer')
    <script>
        var cashBalanceChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('cashBalanceChart').getContext('2d');

            cashBalanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($convertedLabels) !!},
                    datasets: [{
                        data: {!! json_encode($convertedDatas) !!},
                        borderColor: '#999999',
                        pointRadius: 2,
                        tension: 0.4,
                        backgroundColor: function(context) {
                            const value = context.raw;
                            return value >= 0 ? '#10b981' : '#ef4444';
                        },

                    }]
                },
                options: {
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            display: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            display: true,
                            beginAtZero: true,
                            grid: {
                                display: false
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
                            enabled: true,
                            padding: 10,
                            cornerRadius: 4,
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
