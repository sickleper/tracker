$(document).ready(function() {
    
    // --- Globals ---
    let anomaliesChartInstance;
    let mplChartInstance;
    let vehiclesData = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // --- Tab Switching ---
    $('.tab-btn').click(function() {
        const tab = $(this).data('tab');
        $('.tab-btn').removeClass('border-indigo-500 text-indigo-600').addClass('border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200');
        $(this).removeClass('border-transparent text-gray-500').addClass('border-indigo-500 text-indigo-600');
        
        $('.tab-pane').addClass('hidden');
        $(`#tab-${tab}`).removeClass('hidden');
        
        if (tab === 'reports') {
            fetchAnomaliesReport();
            fetchMplReport();
        }
    });

    // --- Function Definitions ---

    function fetchAndDisplayDefects() {
        const container = $('#defectsRow');
        container.html('<div class="flex flex-col items-center justify-center py-20"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500 mb-4"></i><p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Scanning Registry...</p></div>');
        $.getJSON('get_vehicle_defects.php', function(res) {
            if (res.success && res.data.length > 0) {
                container.empty();
                res.data.forEach(defect => {
                    const card = $(`
                        <div class="p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800 mb-3 relative overflow-hidden group hover:bg-white dark:hover:bg-slate-900 hover:shadow-md transition-all">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                                    ${escapeHtml(defect.vehicle?.license_plate || '')}
                                </span>
                                <span class="text-[9px] text-gray-400 dark:text-gray-600 font-bold italic">${escapeHtml(defect.date || '')}</span>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed">${escapeHtml(defect.defect_details || '')}</p>
                        </div>
                    `);
                    container.append(card);
                });
            } else {
                container.html('<div class="text-center py-12 text-gray-300 dark:text-gray-700 italic font-medium">No active safety defects on record.</div>');
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to load defects.';
            container.html(`<div class="text-center py-12 text-red-500 font-bold">${escapeHtml(msg)}</div>`);
            Swal.fire({ icon: 'error', title: 'Error', text: msg, theme: getSwalTheme() });
        });
    }

    function fetchAnomaliesReport() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

        if (!$('#anomaliesChart').length) return;
        const month = $('#monthFilter').val();
        const vehicleId = $('#vehicleFilter').val();
        const query = `?month=${month}&vehicle_id=${vehicleId}`;

        $.getJSON(`fetch_fuel_data.php${query}`, function(data) {
            if (anomaliesChartInstance) anomaliesChartInstance.destroy();
            
            let labels = [], avgData = [], aboveData = [], belowData = [];
            data.forEach(item => {
                labels.push('Week ' + item.yearweek);
                avgData.push(item.overall_weekly_avg);
                aboveData.push(item.anomaly === 'Above Normal' ? item.vehicle_weekly_total_fuel : null);
                belowData.push(item.anomaly === 'Below Normal' ? item.vehicle_weekly_total_fuel : null);
            });
            
            anomaliesChartInstance = new Chart($('#anomaliesChart'), {
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
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { weight: 'bold' } } },
                        x: { grid: { display: false }, ticks: { color: textColor, font: { weight: 'bold' } } }
                    },
                    plugins: { legend: { labels: { color: textColor, font: { weight: 'bold', size: 10 } } } }
                }
            });
        }).fail(function() {
            if (anomaliesChartInstance) {
                anomaliesChartInstance.destroy();
                anomaliesChartInstance = null;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load anomaly report.', theme: getSwalTheme() });
        });
    }

    function fetchMplReport() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

        if (!$('#mplChart').length) return;
        $.getJSON('fetch_fuel_data.php?action=mpl', function(res) {
            if (res.success) {
                if (mplChartInstance) mplChartInstance.destroy();
                mplChartInstance = new Chart($('#mplChart'), {
                    type: 'line',
                    data: {
                        labels: res.labels,
                        datasets: res.datasets
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: textColor, font: { weight: 'bold', size: 10 } } } },
                        scales: { 
                            y: { grid: { color: gridColor }, beginAtZero: true, title: { display: true, text: 'KPL', color: textColor, font: { weight: '900' } }, ticks: { color: textColor, font: { weight: 'bold' } } },
                            x: { grid: { display: false }, ticks: { color: textColor, font: { weight: 'bold' } } }
                        }
                    }
                });
            }
        }).fail(function() {
            if (mplChartInstance) {
                mplChartInstance.destroy();
                mplChartInstance = null;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load efficiency report.', theme: getSwalTheme() });
        });
    }

    window.validateMileage = function() {
        const start = parseFloat($('#start_mileage').val());
        const finish = parseFloat($('#finish_mileage').val());
        if (start > finish) {
            $('#error-message').text("⚠️ ODO ERROR: Finish mileage must be higher than start.");
            return false;
        }
        $('#error-message').text("");
        return true;
    }
    
    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    window.openEditModal = function(logId) {
        $.getJSON('get_fuel_log_details.php', { log_id: logId }, function (entry) {
            $('#editFuelLogModal input[name="log_id"]').val(logId);
            $('#editFuelLogModal select[name="vehicle_id"]').val(entry.vehicle_id);
            $('#editFuelLogModal select[name="user_id"]').val(entry.user_id);
            $('#editFuelLogModal input[name="date"]').val(entry.date);
            $('#editFuelLogModal input[name="start_mileage"]').val(entry.start_mileage);
            $('#editFuelLogModal input[name="finish_mileage"]').val(entry.finish_mileage);
            $('#editFuelLogModal input[name="fuel_amount"]').val(entry.fuel_amount);
            $("#editFuelLogModal #current_image").attr("src", entry.image_file ? 'uploads/' + entry.image_file : 'uploads/1200px-Jeep_Odometer.jpg');
            openModal('editFuelLogModal');
        }).fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error || 'Failed to load fuel log details.', theme: getSwalTheme() });
        });
    };

    window.openVehicleEditModal = function(vehicleId) {
        $.getJSON('get_vehicle.php', { vehicle_id: vehicleId }, function(res) {
            if (res.success) {
                $('#vehicle_update_id').val(res.data.vehicle_id);
                $('#edit_license_plate').val(res.data.license_plate);
                $('#edit_make_model').val(res.data.make_model);
                $('#edit_user_id').val(res.data.user_id);
                openModal('editVehicleModal');
            }
        }).fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to load vehicle details.', theme: getSwalTheme() });
        });
    };

    window.deleteDriver = function(id, name) {
        Swal.fire({
            title: 'Expunge Driver?', text: `Permanently remove ${name} from Driver Registry?`, icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, delete it!', theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_driver.php', { id: id }, function(res) {
                    if (res.success) { Swal.fire({ icon:'success', title:'Removed', timer:1500, showConfirmButton:false, theme: getSwalTheme() }).then(() => location.reload()); }
                    else { Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to delete driver.', theme: getSwalTheme() }); }
                }).fail(function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to delete driver.', theme: getSwalTheme() });
                });
            }
        });
    };

    window.editDriver = function(id) {
        const drivers = window.fuelMainData?.drivers ?? [];
        const driver = drivers.find(d => d.id == id);
        if (driver) {
            $('#edit_driver_id').val(driver.id);
            $('#edit_driver_name').val(driver.name);
            $('#edit_driver_email').val(driver.email);
            $('#edit_driver_mobile').val(driver.mobile);
            $('#edit_is_callout_driver').val(driver.is_callout_driver ? '1' : '0');
            openModal('editDriverModal');
        }
    };

    window.toggleCalloutDriver = function(id, status) {
        $.ajax({
            url: 'update_user.php', method: 'POST', data: { id: id, is_callout_driver: status ? 1 : 0 },
            success: function(res) {
                if (res.success) { Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Availability Updated', timer:1500, showConfirmButton:false, theme: getSwalTheme() }); }
                else { Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() }); }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to update callout status.', theme: getSwalTheme() });
            }
        });
    };

    window.toggleDriverStatus = function(id, status) {
        $.ajax({
            url: 'update_user.php', method: 'POST', data: { id: id, is_driver: status ? 1 : 0 },
            success: function(res) {
                if (res.success) { 
                    Swal.fire({ toast:true, position:'top-end', icon:'success', title:'Status Updated', timer:1500, showConfirmButton:false, theme: getSwalTheme() })
                    .then(() => location.reload()); // Reload to update all driver dropdowns
                }
                else { Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() }); }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to update driver status.', theme: getSwalTheme() });
            }
        });
    };

    window.openModal = function(id) { $(`#${id}`).removeClass('hidden'); document.body.style.overflow = 'hidden'; };
    window.closeModal = function(id) { $(`#${id}`).addClass('hidden'); document.body.style.overflow = ''; };

    // --- Initializations ---

    // Date pickers
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById("date");
    if (dateInput) dateInput.value = today;
    
    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    const currentMonth = new Date().getMonth();
    const monthFilter = $('#monthFilter');
    for (let i = 0; i <= currentMonth; i++) { monthFilter.append($('<option>', { value: i + 1, text: monthNames[i] })); }
    monthFilter.val(currentMonth + 1);

    // Cache vehicle data
    function refreshVehiclesData() {
        return $.getJSON('populate_vehicles.php', function(data) { vehiclesData = data; });
    }
    refreshVehiclesData();

    // Main Fuel Log DataTable
    const logTable = $('#logTable2').DataTable({
        dom: 'Bfrtip', searching: false, responsive: true, autoWidth: false, paging: true, info: false, pageLength: 10,
        buttons: ['csv', 'excel', 'pdf', 'print'], order: [[0, "desc"]],
        ajax: {
            url: "fetch_fuel_logs.php",
            dataSrc: function (json) {
                if (json.logCounts) {
                    const logCountsDiv = $('#logCountsDiv').empty();
                    const container = $('<div class="flex flex-wrap gap-3"></div>');
                    json.logCounts.forEach(function (logCount) {
                        const logBox = $(`<div class="bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 p-3 rounded-2xl shadow-sm text-center min-w-[120px]">
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">${logCount.username}</div>
                            <div class="text-lg font-black text-indigo-600 dark:text-indigo-400">${logCount.log_count} <span class="text-[9px] text-gray-400 uppercase tracking-tighter">logs</span></div>
                        </div>`);
                        container.append(logBox);
                    });
                    logCountsDiv.append(container);
                }
                return json.logs || [];
            }
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
    
    function fuelStatsUrl() {
        const vehicleId = $('#vehicleSelectReport').val() || '';
        return `fetch_fuel_data.php?action=stats&vehicle_id=${encodeURIComponent(vehicleId)}`;
    }

    // Analytics Efficiency Table
    const resultsTable = $('#resultsTable').DataTable({
        searching: false, responsive: true, autoWidth: false, paging: true, info: false, pageLength: 10,
        dom: 'rtp',
        ajax: { url: fuelStatsUrl(), dataSrc: 'data' },
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

    // --- Event Handlers ---

    $('#vehicleSelect').on('change', function () { logTable.ajax.url('fetch_fuel_logs.php?vehicle_id=' + $(this).val()).load(); });
    $('#monthFilter, #vehicleFilter').on('change', fetchAnomaliesReport);
    $('#vehicleSelectReport').on('change', function() { resultsTable.ajax.url(fuelStatsUrl()).load(); });

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    $('#vehicle_id_add').on('change', function() {
        const vehicleId = $(this).val();
        console.log("Vehicle selected ID:", vehicleId);
        if (vehicleId) {
            $.getJSON('get_vehicle.php', { vehicle_id: vehicleId }, function(res) {
                console.log("get_vehicle response:", res);
                if (res.success) {
                    if (res.data.user_id) {
                        console.log("Setting user_id to:", res.data.user_id);
                        $('#user_id').val(res.data.user_id);
                    } else {
                        $('#user_id').val(''); // Clear if unassigned
                    }
                    $('#start_mileage').val(res.data.last_mileage || '');
                }
            }).fail(function() {
                $('#user_id').val('');
                $('#start_mileage').val('');
            });
        }
    });

    $('#user_id').on('change', function() {
        const userId = $(this).val();
        if (userId && userId !== "0") {
            const vehicle = vehiclesData.find(v => v.user_id == userId);
            if (vehicle) {
                $('#vehicle_id_add').val(vehicle.vehicle_id);
                // Also trigger mileage fetch for this vehicle
                $.getJSON('get_vehicle.php', { vehicle_id: vehicle.vehicle_id }, function(res) {
                    if (res.success) {
                        $('#start_mileage').val(res.data.last_mileage || '');
                    }
                }).fail(function() {
                    $('#start_mileage').val('');
                });
            }
        }
    });

    // Global Form Submit Handler
    $('#editDriverForm, #editVehicleForm, #addFuelLogForm, #updatelog, #defectForm, #addVehicleForm, #addUserForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const actionUrl = form.attr('action');
        submitBtn.prop('disabled', true).addClass('opacity-50');

        $.ajax({
            url: actionUrl, 
            method: 'POST', 
            data: new FormData(this), 
            processData: false, 
            contentType: false,
            success: function(res) {
                if (res.success || res.status === 'success' || (res.message && res.message.includes('successfully'))) {
                    Swal.fire({ icon: 'success', title: 'Action Verified', text: res.message || 'System updated.', timer: 2000, showConfirmButton: false, theme: getSwalTheme() });
                    
                    // Close the modal if it exists
                    const modalId = form.closest('.fixed.inset-0').attr('id');
                    if (modalId) closeModal(modalId);

                    // --- Dynamic UI Refresh (No Page Reload) ---
                    if (actionUrl.includes('fuel_log')) {
                        logTable.ajax.reload();
                    } else if (actionUrl.includes('vehicle')) {
                        refreshVehiclesData();
                        // If there was a refreshVehiclesTable function, we would call it here
                        // For now, if we are in the fleet tab, we might need a refresh logic for that list too
                        if (typeof fetchAndDisplayVehicles === 'function') fetchAndDisplayVehicles();
                    } else if (actionUrl.includes('user') || actionUrl.includes('driver')) {
                        // Refresh driver registry
                        location.reload(); // Driver list is currently server-side rendered, so reload might be needed unless we AJAX it
                    } else if (actionUrl.includes('defect')) {
                        fetchAndDisplayDefects();
                    }
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Action failed.', theme: getSwalTheme() });
                }
            },
            error: function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Server connection failed.', theme: getSwalTheme() });
            },
            complete: function() {
                submitBtn.prop('disabled', false).removeClass('opacity-50');
            }
        });
    });
    
    // Initial data load
    fetchAndDisplayDefects();
    fetchMplReport();
    $(document).on('click', '.edit-vehicle-btn', function() { openVehicleEditModal($(this).data('vehicle-id')); });
});
