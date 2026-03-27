(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    function setPanelState(selector, message, isError) {
        const panel = $(selector);
        if (!panel.length) {
            return;
        }

        if (!message) {
            panel.addClass('hidden').removeClass('fuel-panel-state-error');
            return;
        }

        panel.find('span').text(message);
        panel.toggleClass('fuel-panel-state-error', Boolean(isError));
        panel.removeClass('hidden');
    }

    FuelPage.setReportPanelState = setPanelState;

    function getChartTheme() {
        const isDark = document.documentElement.classList.contains('dark');

        return {
            textColor: isDark ? '#94a3b8' : '#64748b',
            gridColor: isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)'
        };
    }

    FuelPage.fetchAnomaliesReport = function() {
        const anomaliesCanvas = document.getElementById('anomaliesChart');
        if (!anomaliesCanvas) {
            return;
        }

        const theme = getChartTheme();
        setPanelState('#anomaliesChartState', 'Loading anomaly report...');
        const month = $('#monthFilter').val();
        const year = $('#yearFilter').val();
        const vehicleId = $('#vehicleFilter').val();
        const query = `?month=${month}&year=${year}&vehicle_id=${vehicleId}`;

        $.getJSON(`fetch_fuel_data.php${query}`, function(res) {
            if (FuelPage.state.anomaliesChartInstance) {
                FuelPage.state.anomaliesChartInstance.destroy();
                FuelPage.state.anomaliesChartInstance = null;
            }

            if (!res.success) {
                setPanelState('#anomaliesChartState', res.message || 'Failed to load anomaly report.', true);
                return;
            }

            const data = Array.isArray(res.data) ? res.data : [];
            const labels = [];
            const avgData = [];
            const aboveData = [];
            const belowData = [];

            data.forEach(function(item) {
                labels.push('Week ' + item.yearweek);
                avgData.push(item.overall_weekly_avg);
                aboveData.push(item.anomaly === 'Above Normal' ? item.vehicle_weekly_total_fuel : null);
                belowData.push(item.anomaly === 'Below Normal' ? item.vehicle_weekly_total_fuel : null);
            });

            if (!labels.length) {
                setPanelState('#anomaliesChartState', 'No anomaly data for this filter.');
                return;
            }

            try {
                FuelPage.state.anomaliesChartInstance = new Chart(anomaliesCanvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Weekly Average', data: avgData, borderColor: '#10b981', fill: false, tension: 0.4 },
                            { label: 'Above Normal', data: aboveData, borderColor: '#ef4444', fill: false, borderDash: [5, 5], pointRadius: 6, pointBackgroundColor: '#ef4444' },
                            { label: 'Below Normal', data: belowData, borderColor: '#3b82f6', fill: false, borderDash: [5, 5], pointRadius: 6, pointBackgroundColor: '#3b82f6' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { grid: { color: theme.gridColor }, ticks: { color: theme.textColor, font: { weight: 'bold' } } },
                            x: { grid: { display: false }, ticks: { color: theme.textColor, font: { weight: 'bold' } } }
                        },
                        plugins: { legend: { labels: { color: theme.textColor, font: { weight: 'bold', size: 10 } } } }
                    }
                });
                setPanelState('#anomaliesChartState', '');
            } catch (error) {
                FuelPage.state.anomaliesChartInstance = null;
                setPanelState('#anomaliesChartState', 'Failed to render anomaly chart.', true);
            }
        }).fail(function() {
            if (FuelPage.state.anomaliesChartInstance) {
                FuelPage.state.anomaliesChartInstance.destroy();
                FuelPage.state.anomaliesChartInstance = null;
            }

            setPanelState('#anomaliesChartState', 'Failed to load anomaly report.', true);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load anomaly report.', theme: FuelPage.getSwalTheme() });
        });
    };

    FuelPage.fetchMplReport = function() {
        const mplCanvas = document.getElementById('mplChart');
        if (!mplCanvas) {
            return;
        }

        const theme = getChartTheme();
        setPanelState('#mplChartState', 'Loading efficiency report...');

        $.getJSON('fetch_fuel_data.php?action=mpl', function(res) {
            if (!res.success) {
                setPanelState('#mplChartState', res.message || 'Failed to load efficiency report.', true);
                return;
            }

            if (FuelPage.state.mplChartInstance) {
                FuelPage.state.mplChartInstance.destroy();
                FuelPage.state.mplChartInstance = null;
            }

            if (!Array.isArray(res.labels) || !res.labels.length) {
                setPanelState('#mplChartState', 'No efficiency data available.');
                return;
            }

            const datasets = Array.isArray(res.datasets) ? res.datasets : [];
            if (!datasets.length) {
                setPanelState('#mplChartState', 'No efficiency data available.');
                return;
            }

            try {
                FuelPage.state.mplChartInstance = new Chart(mplCanvas, {
                    type: 'line',
                    data: {
                        labels: res.labels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: theme.textColor, font: { weight: 'bold', size: 10 } } } },
                        scales: {
                            y: { grid: { color: theme.gridColor }, beginAtZero: true, title: { display: true, text: 'KPL', color: theme.textColor, font: { weight: '900' } }, ticks: { color: theme.textColor, font: { weight: 'bold' } } },
                            x: { grid: { display: false }, ticks: { color: theme.textColor, font: { weight: 'bold' } } }
                        }
                    }
                });
                setPanelState('#mplChartState', '');
            } catch (error) {
                FuelPage.state.mplChartInstance = null;
                setPanelState('#mplChartState', 'Failed to render efficiency chart.', true);
            }
        }).fail(function() {
            if (FuelPage.state.mplChartInstance) {
                FuelPage.state.mplChartInstance.destroy();
                FuelPage.state.mplChartInstance = null;
            }

            setPanelState('#mplChartState', 'Failed to load efficiency report.', true);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load efficiency report.', theme: FuelPage.getSwalTheme() });
        });
    };
})(window, jQuery);
