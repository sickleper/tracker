$(document).ready(function() {
    const FuelPage = window.FuelPage || {};

    function focusRequestedServiceRow() {
        const currentParams = new URLSearchParams(window.location.search);
        const currentHash = window.location.hash || '';
        const hashParts = currentHash.split('?');
        const hashParams = new URLSearchParams(hashParts[1] || '');
        const vehicleId = hashParams.get('vehicle_id') || currentParams.get('vehicle_id') || '';
        const focus = hashParams.get('focus') || currentParams.get('focus') || '';

        if (focus !== 'service' || !vehicleId || typeof FuelPage.focusServiceVehicle !== 'function') {
            return;
        }

        window.setTimeout(function() {
            FuelPage.focusServiceVehicle(vehicleId);
        }, 250);
    }

    function fuelStatsUrl() {
        const vehicleId = $('#vehicleSelectReport').val() || '';
        return `fetch_fuel_data.php?action=stats&vehicle_id=${encodeURIComponent(vehicleId)}`;
    }

    function ensureResultsTable() {
        if (FuelPage.state.resultsTableInstance) {
            return FuelPage.state.resultsTableInstance;
        }

        FuelPage.state.resultsTableInstance = $('#resultsTable').DataTable({
            searching: false,
            responsive: true,
            autoWidth: false,
            paging: true,
            info: false,
            pageLength: 10,
            processing: true,
            dom: 'rtp',
            ajax: {
                url: fuelStatsUrl(),
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to load weekly performance.';
                    FuelPage.setReportPanelState('#resultsTableState', message, true);
                },
                dataSrc: function(res) {
                    if (!res.success) {
                        FuelPage.setReportPanelState('#resultsTableState', res.message || 'Failed to load weekly performance.', true);
                        return [];
                    }
                    const rows = Array.isArray(res.data) ? res.data : [];
                    if (rows.length) {
                        FuelPage.setReportPanelState('#resultsTableState', '');
                    } else {
                        FuelPage.setReportPanelState('#resultsTableState', 'No weekly performance data available yet.');
                    }
                    return rows;
                }
            },
            language: {
                processing: 'Loading report...',
                emptyTable: 'No fleet performance data available yet.',
                zeroRecords: 'No fleet performance data matches this filter.'
            },
            columns: [
                { data: 'name', className: 'px-6 py-4 font-bold text-gray-900 dark:text-gray-100' },
                { data: 'license_plate', className: 'px-6 py-4 text-gray-500 dark:text-gray-400 font-mono text-[10px] font-black uppercase' },
                { data: 'total_miles', className: 'px-6 py-4 text-right text-gray-900 dark:text-gray-300 font-mono', render: (d) => parseFloat(d).toLocaleString() + ' km' },
                { data: 'total_liters', className: 'px-6 py-4 text-right text-gray-900 dark:text-gray-300 font-mono', render: (d) => parseFloat(d).toLocaleString() + ' L' },
                { data: null, className: 'px-6 py-4 text-center text-gray-400 font-black text-[10px] uppercase', render: (d,t,r) => `${r.year}-W${r.week}` },
                { data: 'mpl', className: 'px-6 py-4 text-right text-indigo-600 dark:text-indigo-400 font-black', render: (d) => parseFloat(d).toFixed(2) },
                { data: 'mpg', className: 'px-6 py-4 text-right text-amber-600 dark:text-amber-400 font-black', render: (d) => parseFloat(d).toFixed(2) }
            ]
        });

        return FuelPage.state.resultsTableInstance;
    }

    function activateTab(tab, options) {
        const settings = options || {};
        const selectedTab = tab || 'logs';
        const tabButton = $(`.tab-btn[data-tab="${selectedTab}"]`);

        if (!tabButton.length) {
            return activateTab('logs', settings);
        }

        $('.tab-btn')
            .removeClass('border-indigo-500 text-indigo-600')
            .addClass('border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200');

        tabButton.removeClass('border-transparent text-gray-500').addClass('border-indigo-500 text-indigo-600');

        $('.tab-pane').addClass('hidden');
        $(`#tab-${selectedTab}`).removeClass('hidden');

        if (settings.updateHistory !== false) {
            window.location.hash = selectedTab;
        }

        try {
            window.localStorage.setItem('fuelActiveTab', selectedTab);
        } catch (error) {
            // Ignore storage failures; tab state is still reflected in the URL hash.
        }

        if (selectedTab === 'maintenance') {
            FuelPage.fetchAndDisplayDefects();
            if (typeof FuelPage.refreshMaintenanceHistory === 'function') {
                FuelPage.refreshMaintenanceHistory();
            }
            if (typeof FuelPage.refreshVehicleReminderSummary === 'function') {
                FuelPage.refreshVehicleReminderSummary();
            }
            focusRequestedServiceRow();
        }

        if (selectedTab === 'reports') {
            FuelPage.fetchAnomaliesReport();
            FuelPage.fetchMplReport();
            const resultsTable = ensureResultsTable();
            FuelPage.setReportPanelState('#resultsTableState', 'Loading weekly performance...');
            resultsTable.ajax.url(fuelStatsUrl()).load();
        }
    }

    $('.tab-btn').click(function() {
        activateTab($(this).data('tab'));
    });

    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById("date");
    if (dateInput) {
        dateInput.value = today;
    }
    
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const currentMonth = new Date().getMonth();
    const currentYear = new Date().getFullYear();
    const monthFilter = $('#monthFilter');
    const yearFilter = $('#yearFilter');
    for (let i = 0; i <= currentMonth; i++) {
        monthFilter.append($('<option>', { value: i + 1, text: monthNames[i] }));
    }
    monthFilter.val(currentMonth + 1);
    for (let i = 0; i < 5; i++) {
        const year = currentYear - i;
        yearFilter.append($('<option>', { value: year, text: year }));
    }
    yearFilter.val(currentYear);

    let initialTab = (window.location.hash || '').replace('#', '');
    if (!initialTab) {
        try {
            initialTab = window.localStorage.getItem('fuelActiveTab') || 'logs';
        } catch (error) {
            initialTab = 'logs';
        }
    }
    activateTab(initialTab, { updateHistory: false });

    FuelPage.refreshVehiclesData();
    FuelPage.refreshDriverRegistry();
    FuelPage.refreshVehicleRegistry();
    FuelPage.refreshDailyChecks();

    $('#logCountsDiv').html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-gas-pump text-xl"></i><span>Loading log summary...</span></div>');

    const logTable = $('#logTable2').DataTable({
        dom: 'Bfrtip', searching: false, responsive: true, autoWidth: false, paging: true, info: false, pageLength: 10, processing: true,
        buttons: ['csv', 'excel', 'pdf', 'print'], order: [[0, "desc"]],
        ajax: {
            url: "fetch_fuel_logs.php",
            error: function() {
                $('#logCountsDiv').html('<div class="fuel-panel-state fuel-panel-state-error !min-h-0 py-10"><i class="fas fa-triangle-exclamation text-xl"></i><span>Failed to load fuel summary.</span></div>');
            },
            dataSrc: function (json) {
                if (json.logCounts) {
                    const logCountsDiv = $('#logCountsDiv').empty();
                    const container = $('<div class="flex flex-wrap gap-3"></div>');
                    if (json.logCounts.length) {
                        json.logCounts.forEach(function (logCount) {
                            const logBox = $(`<div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 p-3 rounded-2xl shadow-sm text-center min-w-[120px]">
                                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">${logCount.username}</div>
                                <div class="text-lg font-black text-indigo-600 dark:text-indigo-400">${logCount.log_count} <span class="text-[9px] text-gray-400 uppercase tracking-tighter">logs</span></div>
                            </div>`);
                            container.append(logBox);
                        });
                        logCountsDiv.append(container);
                    } else {
                        logCountsDiv.html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-receipt text-xl"></i><span>No driver log summary yet.</span></div>');
                    }
                }
                return json.logs || [];
            }
        },
        language: {
            processing: 'Loading fuel logs...',
            emptyTable: 'No fuel logs recorded yet.',
            zeroRecords: 'No fuel logs match this filter.'
        },
        columns: [
            { "data": "date", "className": "px-6 py-4" },
            { "data": "driver_name", "className": "px-6 py-4", "render": (d) => `<span class="font-bold text-gray-900 dark:text-gray-100">${d}</span>` },
            { "data": "license_plate", "className": "px-6 py-4", "render": (d) => `<span class="bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 px-2 py-1 rounded-lg font-mono text-[10px] font-black uppercase border border-black/5 dark:border-white/5">${d}</span>` },
            { "data": "start_mileage", "className": "px-6 py-4 text-right font-mono text-gray-500" },
            { "data": "finish_mileage", "className": "px-6 py-4 text-right font-mono text-gray-500" },
            { "data": "fuel_amount", "className": "px-6 py-4 text-right font-black text-indigo-600 dark:text-indigo-400" },
            { "data": "image_file", "className": "px-6 py-4 text-center", "orderable": false, "render": (d) => d ? `<img src="uploads/${d}" class="h-10 w-10 object-cover rounded-xl mx-auto shadow-md cursor-pointer hover:scale-110 transition-transform" onclick="Swal.fire({imageUrl: 'uploads/${d}', showConfirmButton: false, customClass: {popup:'rounded-3xl shadow-2xl'}, theme: getSwalTheme()})">` : `<i class="fas fa-camera text-gray-200 dark:text-slate-800"></i>` },
            { "data": null, "className": "px-6 py-4 text-right font-black text-gray-900 dark:text-gray-100", "orderable": false, "render": (d,t,r) => (parseFloat(r.finish_mileage) - parseFloat(r.start_mileage)).toLocaleString() + ' km' },
            { "data": "log_id", "className": "px-6 py-4 text-right", "orderable": false, "render": (d) => `<button type="button" onclick="openEditModal(${d})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">Edit</button>` }
        ]
    });

    $('#vehicleSelect').on('change', function () { logTable.ajax.url('fetch_fuel_logs.php?vehicle_id=' + $(this).val()).load(); });
    $('#monthFilter, #yearFilter, #vehicleFilter').on('change', FuelPage.fetchAnomaliesReport);
    $('#vehicleSelectReport').on('change', function() {
        const resultsTable = ensureResultsTable();
        resultsTable.ajax.url(fuelStatsUrl()).load();
    });
    FuelPage.bindAutopopulate();
    FuelPage.bindGlobalForms(logTable);
    FuelPage.bindDailyChecks();
    
    FuelPage.fetchAndDisplayDefects();
    $(document).on('click', '.edit-vehicle-btn', function() { openVehicleEditModal($(this).data('vehicle-id')); });
    $(window).on('hashchange', function() {
        const hashTab = (window.location.hash || '').replace('#', '');
        if (hashTab) {
            activateTab(hashTab, { updateHistory: false });
        }
    });
});
