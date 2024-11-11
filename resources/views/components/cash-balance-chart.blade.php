@props(['labels', 'datas'])

<canvas id="cashBalanceLineChart" class="bg-white rounded-[16px]"></canvas>

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
                    labels: {!! json_encode($labels) !!},
                    datasets: [{
                        data: {!! json_encode($datas) !!},
                        fill: true,
                        backgroundColor: gradient,
                        borderColor: '#00cca3',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#00cca3',
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        lineTension: 0.4,
                    }]
                },
                options: {
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    responsive: true,
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
