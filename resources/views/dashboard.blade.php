<x-layouts.app :title="'Dashboard'">
    <section class="chart-card">
        <div class="chart-card-header">
            <label for="chartRange">Periodo:</label>
            <select id="chartRange" class="chart-select">
                <option value="5">5 dias</option>
                <option value="7" selected>7 dias</option>
                <option value="15">15 dias</option>
                <option value="30">30 dias</option>
            </select>
        </div>

        <div class="chart-wrap">
            <canvas id="dashboardBarChart"></canvas>
        </div>
    </section>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>window.Chart || document.write('<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"><\/script>');</script>
        <script>
            (function () {
                const sourceSeries = @json($chartSeries);
                const rangeSelect = document.getElementById('chartRange');
                const canvas = document.getElementById('dashboardBarChart');

                if (!sourceSeries || !rangeSelect || !canvas || typeof Chart === 'undefined') {
                    return;
                }

                function extractByDays(days) {
                    const subset = sourceSeries.slice(-days);
                    return {
                        labels: subset.map(item => item.label),
                        values: subset.map(item => item.total),
                    };
                }

                function themeOptions() {
                    const isDark = document.body.classList.contains('dark-mode');

                    return {
                        tickColor: isDark ? '#cbd5e1' : '#4b5563',
                        gridColor: isDark ? '#334155' : '#eef2f7',
                    };
                }

                const initial = extractByDays(Number(rangeSelect.value));

                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: initial.labels,
                        datasets: [{
                            label: 'Movimentacoes',
                            data: initial.values,
                            backgroundColor: '#4682B4',
                            borderRadius: 6,
                            maxBarThickness: 28,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false,
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                },
                                ticks: {
                                    color: themeOptions().tickColor,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    color: themeOptions().tickColor,
                                },
                                grid: {
                                    color: themeOptions().gridColor,
                                },
                            },
                        },
                    },
                });

                function updateChartTheme() {
                    const nextTheme = themeOptions();
                    chart.options.scales.x.ticks.color = nextTheme.tickColor;
                    chart.options.scales.y.ticks.color = nextTheme.tickColor;
                    chart.options.scales.y.grid.color = nextTheme.gridColor;
                    chart.update();
                }

                rangeSelect.addEventListener('change', function () {
                    const next = extractByDays(Number(rangeSelect.value));
                    chart.data.labels = next.labels;
                    chart.data.datasets[0].data = next.values;
                    chart.update();
                });

                window.addEventListener('dashboard-theme-change', updateChartTheme);
            })();
        </script>
    @endpush
</x-layouts.app>
