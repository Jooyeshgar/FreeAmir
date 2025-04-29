@props(['datas'])

<canvas id="incomeChart" class="bg-white rounded-[16px]"></canvas>

@pushOnce('footer')
    <script>
        var incomeChart = null;
        window.addEventListener("DOMContentLoaded", () => {
            const ctx = document.getElementById('incomeChart').getContext('2d');

            const data = {
                labels: {!! json_encode(array_keys($datas)) !!},
                datasets: [{
                    label: 'درآمد ماهانه',
                    data: {!! json_encode(array_values($datas)) !!},
                    backgroundColor: '#4bb946c4',
                    borderColor: '#4bb946',
                    borderWidth: 2,
                    borderRadius: Number.MAX_VALUE,
                    borderSkipped: false,

                }]
            };

            const options = {
                responsive: true,
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
            incomeChart = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: options,
            });
        });
    </script>
@endPushOnce
