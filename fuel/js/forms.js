(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    function normalizeDateValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        const normalized = String(value).trim();
        if (!normalized || normalized === '0' || normalized === '0000-00-00') {
            return '';
        }

        return /^\d{4}-\d{2}-\d{2}$/.test(normalized) ? normalized : '';
    }

    function getDriverUsers() {
        return (FuelPage.state.driverRegistry || []).filter(function(user) {
            return Boolean(user.is_driver);
        });
    }

    function buildDriverOptions(includePlaceholder, placeholder) {
        const options = [];

        if (includePlaceholder) {
            options.push(`<option value="">${FuelPage.escapeHtml(placeholder || 'Select Driver')}</option>`);
        }

        getDriverUsers().forEach(function(user) {
            options.push(`<option value="${FuelPage.escapeHtml(user.id)}">${FuelPage.escapeHtml(user.name)}</option>`);
        });

        return options.join('');
    }

    function syncDriverSelect(selector, includePlaceholder, placeholder) {
        const select = $(selector);
        if (!select.length) {
            return;
        }

        const currentValue = String(select.val() ?? '');
        select.html(buildDriverOptions(includePlaceholder, placeholder));

        if (select.find(`option[value="${currentValue}"]`).length) {
            select.val(currentValue);
        }
    }

    function syncDriverDropdowns() {
        syncDriverSelect('#user_id', true, 'Select Driver');
        syncDriverSelect('#edit_user_id', true, 'Select Driver');
        syncDriverSelect('#editFuelLogModal_user_id', false, 'Select Driver');
    }

    function renderDriverRegistryTable() {
        const tbody = $('#driverRegistryBody');
        if (!tbody.length) {
            return;
        }

        const users = FuelPage.state.driverRegistry || [];
        if (!users.length) {
            tbody.html('<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">No drivers found.</td></tr>');
            return;
        }

        const rows = users.map(function(user) {
            const isDriver = Boolean(user.is_driver);
            const isCallout = Boolean(user.is_callout_driver);
            const safeName = FuelPage.escapeHtml(user.name || '');

            return `
                <tr class="table-row-hover transition-colors">
                    <td class="px-6 py-4 text-gray-400 dark:text-gray-600 font-mono text-xs">${FuelPage.escapeHtml(user.id)}</td>
                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">${safeName}</td>
                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium">${FuelPage.escapeHtml(user.email || 'N/A')}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col items-center gap-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" ${isDriver ? 'checked' : ''} onchange="toggleDriverStatus(${Number(user.id)}, this.checked)">
                                <div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                            <span class="px-2 py-0.5 ${isDriver ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500'} text-[8px] font-black rounded uppercase tracking-widest border border-emerald-200/50">
                                ${isDriver ? 'ENABLED' : 'DISABLED'}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex flex-col items-center gap-2">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" ${isCallout ? 'checked' : ''} onchange="toggleCalloutDriver(${Number(user.id)}, this.checked)">
                                <div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="px-2 py-0.5 ${isCallout ? 'bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200/50' : 'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500'} text-[8px] font-black rounded uppercase tracking-widest">
                                ${isCallout ? 'Primary' : 'Regular'}
                            </span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="editDriver(${Number(user.id)})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" onclick="deleteDriver(${Number(user.id)}, '${safeName.replace(/'/g, "\\'")}')" class="text-red-400 hover:text-red-600 font-black uppercase text-[10px] tracking-widest transition-colors">
                                <i class="fas fa-trash-alt"></i> Del
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.html(rows.join(''));
    }

    FuelPage.refreshDriverRegistry = function() {
        const tbody = $('#driverRegistryBody');
        if (tbody.length) {
            tbody.html('<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">Loading driver registry...</td></tr>');
        }

        return $.getJSON('fetch_driver_registry.php', function(res) {
            FuelPage.state.driverRegistry = res.users || [];
            if (window.fuelMainData) {
                window.fuelMainData.drivers = FuelPage.state.driverRegistry;
            }
            renderDriverRegistryTable();
            syncDriverDropdowns();
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Failed to load driver registry.';
            if (tbody.length) {
                tbody.html(`<tr><td colspan="6" class="px-6 py-12 text-center text-red-500 font-bold">${FuelPage.escapeHtml(message)}</td></tr>`);
            }
            Swal.fire({ icon: 'error', title: 'Error', text: message, theme: FuelPage.getSwalTheme() });
        });
    };

    FuelPage.refreshVehiclesData = function() {
        return $.getJSON('populate_vehicles.php', function(data) {
            FuelPage.state.vehiclesData = data;
        });
    };

    FuelPage.refreshServiceSchedule = function() {
        const panel = $('#serviceSchedulePanel');
        if (!panel.length) {
            return $.Deferred().resolve().promise();
        }

        panel.html('<div class="fuel-panel-state fuel-panel-state-muted"><i class="fas fa-tools text-2xl"></i><span>Loading service schedule...</span></div>');

        return $.get('fetch_service_schedule.php', function(html) {
            panel.html(html);
        }).fail(function(xhr) {
            const message = xhr.responseText || 'Failed to load service schedule.';
            panel.html(`<div class="fuel-panel-state fuel-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>${FuelPage.escapeHtml(message)}</span></div>`);
        });
    };

    FuelPage.refreshMaintenanceHistory = function() {
        const panel = $('#maintenanceHistoryPanel');
        if (!panel.length) {
            return $.Deferred().resolve().promise();
        }

        panel.html('<div class="fuel-panel-state fuel-panel-state-muted"><i class="fas fa-clock-rotate-left text-2xl"></i><span>Loading maintenance history...</span></div>');

        return $.get('fetch_maintenance_history.php', function(html) {
            panel.html(html);
        }).fail(function(xhr) {
            const message = xhr.responseText || 'Failed to load maintenance history.';
            panel.html(`<div class="fuel-panel-state fuel-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>${FuelPage.escapeHtml(message)}</span></div>`);
        });
    };

    FuelPage.refreshVehicleReminderSummary = function() {
        const panel = $('#vehicleReminderSummaryPanel');
        if (!panel.length) {
            return $.Deferred().resolve().promise();
        }

        const endpoint = window.fuelMainData?.vehicleReminderEndpoint;
        if (!endpoint) {
            panel.html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-bell text-xl"></i><span>Vehicle reminder feed is not configured.</span></div>');
            return $.Deferred().resolve().promise();
        }

        panel.html('<div class="fuel-panel-state fuel-panel-state-muted"><i class="fas fa-bell text-2xl"></i><span>Loading compliance reminders...</span></div>');

        return $.getJSON(endpoint, function(res) {
            const reminders = Array.isArray(res.reminders) ? res.reminders : [];
            if (!res.success) {
                panel.html(`<div class="fuel-panel-state fuel-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>${FuelPage.escapeHtml(res.message || 'Failed to load reminders.')}</span></div>`);
                return;
            }

            if (!reminders.length) {
                panel.html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-check text-xl"></i><span>No active fleet reminders right now.</span></div>');
                return;
            }

            const items = reminders.map(function(reminder) {
                const days = Number(reminder.days_until ?? 0);
                const overdue = days < 0;
                const badgeClass = overdue
                    ? 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300'
                    : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300';
                const relative = overdue ? `${Math.abs(days)}d overdue` : (days === 0 ? 'due today' : `in ${days}d`);
                const isService = String(reminder.doc_type || '').toLowerCase() === 'service due';
                const targetUrl = isService
                    ? `index.php#maintenance?vehicle_id=${encodeURIComponent(reminder.vehicle_id || '')}&focus=service`
                    : `edit_vehicle_docs.php?vehicle_id=${encodeURIComponent(reminder.vehicle_id || '')}`;
                const targetLabel = isService ? 'Open service row' : 'Open docs';

                return `
                    <a href="${targetUrl}" class="block rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 hover:border-indigo-300 dark:hover:border-indigo-700 hover:shadow-md transition-all">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-mono text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">${FuelPage.escapeHtml(reminder.license_plate || 'Vehicle')}</div>
                                <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">${FuelPage.escapeHtml(reminder.doc_type || 'Reminder')}</div>
                                <div class="mt-2 text-[11px] font-bold text-slate-500 dark:text-slate-400">${FuelPage.escapeHtml(reminder.status || '')} • ${FuelPage.escapeHtml(reminder.date || '')}</div>
                                <div class="mt-3 text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">${targetLabel}</div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${badgeClass}">${FuelPage.escapeHtml(relative)}</span>
                        </div>
                    </a>
                `;
            });

            panel.html(`<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">${items.join('')}</div>`);
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Failed to load reminders.';
            panel.html(`<div class="fuel-panel-state fuel-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>${FuelPage.escapeHtml(message)}</span></div>`);
        });
    };

    FuelPage.focusServiceVehicle = function(vehicleId) {
        if (!vehicleId) {
            return;
        }

        const row = document.getElementById(`service-row-${vehicleId}`);
        if (!row) {
            return;
        }

        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('ring-2', 'ring-indigo-500', 'ring-offset-2', 'dark:ring-offset-slate-950');
        window.setTimeout(function() {
            row.classList.remove('ring-2', 'ring-indigo-500', 'ring-offset-2', 'dark:ring-offset-slate-950');
        }, 2200);
    };

    FuelPage.openEditModal = function(logId) {
        $.getJSON('get_fuel_log_details.php', { log_id: logId }, function(entry) {
            $('#editFuelLogModal input[name="log_id"]').val(logId);
            $('#editFuelLogModal select[name="vehicle_id"]').val(entry.vehicle_id);
            $('#editFuelLogModal select[name="user_id"]').val(entry.user_id);
            $('#editFuelLogModal input[name="date"]').val(normalizeDateValue(entry.date));
            $('#editFuelLogModal input[name="start_mileage"]').val(entry.start_mileage);
            $('#editFuelLogModal input[name="finish_mileage"]').val(entry.finish_mileage);
            $('#editFuelLogModal input[name="fuel_amount"]').val(entry.fuel_amount);
            $('#editFuelLogModal #current_image').attr('src', entry.image_file ? 'uploads/' + entry.image_file : 'uploads/1200px-Jeep_Odometer.jpg');
            FuelPage.openModal('editFuelLogModal');
        }).fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.error || 'Failed to load fuel log details.', theme: FuelPage.getSwalTheme() });
        });
    };

    FuelPage.openVehicleEditModal = function(vehicleId) {
        $.getJSON('get_vehicle.php', { vehicle_id: vehicleId }, function(res) {
            if (!res.success) {
                return;
            }

            $('#vehicle_update_id').val(res.data.vehicle_id);
            $('#edit_license_plate').val(res.data.license_plate);
            $('#edit_make_model').val(res.data.make_model);
            $('#edit_user_id').val(res.data.user_id);
            FuelPage.openModal('editVehicleModal');
        }).fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to load vehicle details.', theme: FuelPage.getSwalTheme() });
        });
    };

    FuelPage.deleteDriver = function(id, name) {
        Swal.fire({
            title: 'Expunge Driver?',
            text: `Permanently remove ${name} from Driver Registry?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, delete it!',
            theme: FuelPage.getSwalTheme()
        }).then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            $.post('delete_driver.php', { id: id }, function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Removed', timer: 1500, showConfirmButton: false, theme: FuelPage.getSwalTheme() }).then(function() {
                        location.reload();
                    });
                    return;
                }

                Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to delete driver.', theme: FuelPage.getSwalTheme() });
            }).fail(function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to delete driver.', theme: FuelPage.getSwalTheme() });
            });
        });
    };

    FuelPage.editDriver = function(id) {
        const drivers = FuelPage.state.driverRegistry || window.fuelMainData?.drivers || [];
        const driver = drivers.find(function(item) {
            return item.id == id;
        });

        if (!driver) {
            return;
        }

        $('#edit_driver_id').val(driver.id);
        $('#edit_driver_name').val(driver.name);
        $('#edit_driver_email').val(driver.email);
        $('#edit_driver_mobile').val(driver.mobile);
        $('#edit_is_callout_driver').val(driver.is_callout_driver ? '1' : '0');
        FuelPage.openModal('editDriverModal');
    };

    FuelPage.toggleCalloutDriver = function(id, status) {
        $.ajax({
            url: 'update_user.php',
            method: 'POST',
            data: { id: id, is_callout_driver: status ? 1 : 0 },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Availability Updated', timer: 1500, showConfirmButton: false, theme: FuelPage.getSwalTheme() });
                    FuelPage.refreshDriverRegistry();
                    return;
                }

                Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: FuelPage.getSwalTheme() });
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to update callout status.', theme: FuelPage.getSwalTheme() });
            }
        });
    };

    FuelPage.toggleDriverStatus = function(id, status) {
        $.ajax({
            url: 'update_user.php',
            method: 'POST',
            data: { id: id, is_driver: status ? 1 : 0 },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Status Updated', timer: 1500, showConfirmButton: false, theme: FuelPage.getSwalTheme() });
                    FuelPage.refreshDriverRegistry();
                    return;
                }

                Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: FuelPage.getSwalTheme() });
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to update driver status.', theme: FuelPage.getSwalTheme() });
            }
        });
    };

    FuelPage.bindAutopopulate = function() {
        $('#vehicle_id_add').on('change', function() {
            const vehicleId = $(this).val();

            if (!vehicleId) {
                return;
            }

            $.getJSON('get_vehicle.php', { vehicle_id: vehicleId }, function(res) {
                if (!res.success) {
                    return;
                }

                $('#user_id').val(res.data.user_id || '');
                $('#start_mileage').val(res.data.last_mileage || '');
            }).fail(function() {
                $('#user_id').val('');
                $('#start_mileage').val('');
            });
        });

        $('#user_id').on('change', function() {
            const userId = $(this).val();

            if (!userId || userId === '0') {
                return;
            }

            const vehicle = FuelPage.state.vehiclesData.find(function(item) {
                return item.user_id == userId;
            });

            if (!vehicle) {
                return;
            }

            $('#vehicle_id_add').val(vehicle.vehicle_id);

            $.getJSON('get_vehicle.php', { vehicle_id: vehicle.vehicle_id }, function(res) {
                if (res.success) {
                    $('#start_mileage').val(res.data.last_mileage || '');
                }
            }).fail(function() {
                $('#start_mileage').val('');
            });
        });
    };

    FuelPage.bindGlobalForms = function(logTable) {
        $('#editDriverForm, #editVehicleForm, #addFuelLogForm, #updatelog, #defectForm, #addVehicleForm, #addUserForm').on('submit', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const actionUrl = form.attr('action');

            submitBtn.prop('disabled', true).addClass('opacity-50');

            if (form.attr('id') === 'addFuelLogForm' && typeof FuelPage.canUseVehicleToday === 'function') {
                const vehicleId = form.find('[name="vehicle_id"]').val();
                const gate = FuelPage.canUseVehicleToday(vehicleId);
                if (!gate.allowed) {
                    if (typeof FuelPage.focusDailyCheckForVehicle === 'function') {
                        FuelPage.focusDailyCheckForVehicle(vehicleId);
                    }
                    Swal.fire({ icon: 'warning', title: 'Daily Check Required', text: gate.message, theme: FuelPage.getSwalTheme() });
                    submitBtn.prop('disabled', false).removeClass('opacity-50');
                    return;
                }
            }

            $.ajax({
                url: actionUrl,
                method: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success || res.status === 'success' || (res.message && res.message.includes('successfully'))) {
                        Swal.fire({ icon: 'success', title: 'Action Verified', text: res.message || 'System updated.', timer: 2000, showConfirmButton: false, theme: FuelPage.getSwalTheme() });

                        const modalId = form.closest('.fixed.inset-0').attr('id');
                        if (modalId) {
                            FuelPage.closeModal(modalId);
                        }

                        if (actionUrl.includes('fuel_log')) {
                            logTable.ajax.reload();
                            if (typeof FuelPage.refreshDailyChecks === 'function') {
                                FuelPage.refreshDailyChecks();
                            }
                        } else if (actionUrl.includes('vehicle')) {
                            FuelPage.refreshVehiclesData();
                            if (typeof FuelPage.refreshVehicleRegistry === 'function') {
                                FuelPage.refreshVehicleRegistry();
                            }
                        } else if (actionUrl.includes('user') || actionUrl.includes('driver')) {
                            FuelPage.refreshDriverRegistry();
                            if (typeof FuelPage.refreshVehicleRegistry === 'function') {
                                FuelPage.refreshVehicleRegistry();
                            }
                        } else if (actionUrl.includes('defect')) {
                            FuelPage.fetchAndDisplayDefects();
                            if (typeof FuelPage.refreshVehicleRegistry === 'function') {
                                FuelPage.refreshVehicleRegistry();
                            }
                            if (typeof FuelPage.refreshDailyChecks === 'function') {
                                FuelPage.refreshDailyChecks();
                            }
                        }
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Action failed.', theme: FuelPage.getSwalTheme() });
                    }
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Server connection failed.',
                        theme: FuelPage.getSwalTheme()
                    });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).removeClass('opacity-50');
                }
            });
        });

        $(document).on('submit', '.service-interval-form', function(e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');

            submitBtn.prop('disabled', true).addClass('opacity-50');

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Action Verified', text: res.message || 'Service schedule updated.', timer: 2000, showConfirmButton: false, theme: FuelPage.getSwalTheme() });
                        $.when(FuelPage.refreshServiceSchedule(), FuelPage.refreshVehicleRegistry(), FuelPage.refreshMaintenanceHistory(), FuelPage.refreshVehicleReminderSummary()).always(function() {
                            FuelPage.refreshVehiclesData();
                        });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Action failed.', theme: FuelPage.getSwalTheme() });
                    }
                },
                error: function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Server connection failed.', theme: FuelPage.getSwalTheme() });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).removeClass('opacity-50');
                }
            });
        });
    };

    window.openEditModal = FuelPage.openEditModal;
    window.openVehicleEditModal = FuelPage.openVehicleEditModal;
    window.deleteDriver = FuelPage.deleteDriver;
    window.editDriver = FuelPage.editDriver;
    window.toggleCalloutDriver = FuelPage.toggleCalloutDriver;
    window.toggleDriverStatus = FuelPage.toggleDriverStatus;
})(window, jQuery);
