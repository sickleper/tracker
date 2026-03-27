(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    function getVehicleLabel(vehicle) {
        const plate = FuelPage.escapeHtml(vehicle?.license_plate || 'Vehicle');
        const model = FuelPage.escapeHtml(vehicle?.make_model || '');
        return model ? `${plate} · ${model}` : plate;
    }

    function buildStatusCard(vehicle, check) {
        const status = check?.status || 'missing';
        const statusMap = {
            pass: {
                badge: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300',
                label: 'Checked',
                helper: check?.checked_by ? `Passed by ${FuelPage.escapeHtml(check.checked_by)}` : 'Passed today'
            },
            fail: {
                badge: 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300',
                label: 'Attention',
                helper: check?.failed_items?.length ? `Failed: ${FuelPage.escapeHtml(check.failed_items.join(', '))}` : 'Failed items recorded'
            },
            missing: {
                badge: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
                label: 'Not Checked',
                helper: 'Complete today\'s check before logging use'
            }
        };

        const meta = statusMap[status] || statusMap.missing;
        return `
            <button type="button" class="daily-check-vehicle-card text-left p-4 rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900/40 hover:border-indigo-300 dark:hover:border-indigo-700 transition-all" data-vehicle-id="${vehicle.vehicle_id}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-mono text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">${FuelPage.escapeHtml(vehicle.license_plate)}</div>
                        <div class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">${FuelPage.escapeHtml(vehicle.make_model || 'Vehicle')}</div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${meta.badge}">${meta.label}</span>
                </div>
                <div class="mt-3 text-[11px] font-bold text-gray-500 dark:text-gray-400">${meta.helper}</div>
            </button>
        `;
    }

    function firstVehicleNeedingCheck(vehicles, checksByVehicle) {
        return (vehicles || []).find(function(vehicle) {
            const check = checksByVehicle[String(vehicle.vehicle_id)];
            return !check || check.status !== 'pass';
        }) || (vehicles || [])[0] || null;
    }

    function updateFuelGateNotice(vehicleId) {
        const notice = $('#dailyCheckFuelGateNotice');
        if (!notice.length) {
            return;
        }

        if (!vehicleId) {
            notice.addClass('hidden').empty();
            return;
        }

        const gate = FuelPage.canUseVehicleToday(vehicleId);
        if (gate.allowed) {
            notice
                .removeClass('hidden border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300')
                .addClass('border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300')
                .html('<i class="fas fa-shield-check mr-2"></i>Daily check passed for this vehicle. Fuel logging is enabled.');
            return;
        }

        notice
            .removeClass('hidden border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300')
            .addClass('border-red-200 bg-red-50 text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300')
            .html(`<i class="fas fa-triangle-exclamation mr-2"></i>${FuelPage.escapeHtml(gate.message)}`);
    }

    function renderDailyCheckSummary(data) {
        const board = $('#dailyCheckStatusBoard');
        if (!board.length) {
            return;
        }

        const checksByVehicle = {};
        (data.checks || []).forEach(function(check) {
            checksByVehicle[String(check.vehicle_id)] = check;
        });

        FuelPage.state.dailyChecksByVehicle = checksByVehicle;
        FuelPage.state.dailyCheckVehicles = data.vehicles || [];

        if (!data.vehicles || !data.vehicles.length) {
            board.html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-car-side text-xl"></i><span>No vehicles available for daily checks.</span></div>');
            return;
        }

        board.html(data.vehicles.map(function(vehicle) {
            return buildStatusCard(vehicle, checksByVehicle[String(vehicle.vehicle_id)]);
        }).join(''));
    }

    function populateDailyCheckForm(vehicleId) {
        const select = $('#dailyCheckVehicleId');
        if (!select.length) {
            return;
        }

        if (vehicleId) {
            select.val(String(vehicleId));
        }

        const currentCheck = FuelPage.state.dailyChecksByVehicle?.[String(select.val())];
        const checks = currentCheck?.checklist || {};

        $('#dailyCheckForm input[type="radio"]').prop('checked', false);
        Object.keys(checks).forEach(function(key) {
            $(`#dailyCheckForm input[name="checks[${key}]"][value="${checks[key]}"]`).prop('checked', true);
        });
        $('#dailyCheckNotes').val(currentCheck?.notes || '');
    }

    function syncDailyCheckVehicleFromFuelLog(vehicleId, options) {
        const select = $('#dailyCheckVehicleId');
        if (!select.length || !vehicleId) {
            return;
        }

        const settings = options || {};
        select.val(String(vehicleId)).trigger('change');

        if (settings.scroll !== false) {
            const formEl = document.getElementById('dailyCheckForm');
            if (formEl) {
                formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    FuelPage.refreshDailyChecks = function() {
        const board = $('#dailyCheckStatusBoard');
        if (board.length) {
            board.html('<div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10"><i class="fas fa-clipboard-check text-xl"></i><span>Loading today\'s vehicle checks...</span></div>');
        }

        return $.getJSON('fetch_daily_vehicle_checks.php', function(res) {
            if (!res.success) {
                board.html(`<div class="fuel-panel-state fuel-panel-state-error !min-h-0 py-10"><i class="fas fa-triangle-exclamation text-xl"></i><span>${FuelPage.escapeHtml(res.message || 'Failed to load daily checks.')}</span></div>`);
                return;
            }

            renderDailyCheckSummary(res);

            const select = $('#dailyCheckVehicleId');
            if (select.length) {
                const currentValue = select.val();
                const options = ['<option value="">Select Vehicle</option>'].concat((res.vehicles || []).map(function(vehicle) {
                    return `<option value="${vehicle.vehicle_id}">${getVehicleLabel(vehicle)}</option>`;
                }));
                select.html(options.join(''));
                const preferredVehicle = currentValue && select.find(`option[value="${currentValue}"]`).length
                    ? currentValue
                    : (firstVehicleNeedingCheck(res.vehicles || [], FuelPage.state.dailyChecksByVehicle)?.vehicle_id || '');
                if (preferredVehicle) {
                    select.val(String(preferredVehicle));
                }
                populateDailyCheckForm(select.val());
            }

            updateFuelGateNotice($('#vehicle_id_add').val());
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Failed to load daily checks.';
            board.html(`<div class="fuel-panel-state fuel-panel-state-error !min-h-0 py-10"><i class="fas fa-triangle-exclamation text-xl"></i><span>${FuelPage.escapeHtml(message)}</span></div>`);
            updateFuelGateNotice($('#vehicle_id_add').val());
        });
    };

    FuelPage.canUseVehicleToday = function(vehicleId) {
        if (!vehicleId) {
            return { allowed: false, message: 'Choose a vehicle first.' };
        }

        const vehicleRow = (FuelPage.state.vehicleRegistry || []).find(function(vehicle) {
            return String(vehicle.vehicle_id) === String(vehicleId);
        });
        if (vehicleRow?.off_road) {
            return {
                allowed: false,
                message: vehicleRow.off_road_reason
                    ? `This vehicle is currently off road: ${vehicleRow.off_road_reason}`
                    : 'This vehicle is currently marked off road and cannot be used.'
            };
        }

        const check = FuelPage.state.dailyChecksByVehicle?.[String(vehicleId)];
        if (!check) {
            return { allowed: false, message: 'Complete today\'s daily vehicle check before logging fuel or mileage.' };
        }

        if (check.status !== 'pass') {
            return { allowed: false, message: 'This vehicle has a failed daily check today. Review the check before logging use.' };
        }

        return { allowed: true };
    };

    FuelPage.bindDailyChecks = function() {
        $(document).on('click', '.daily-check-vehicle-card', function() {
            populateDailyCheckForm($(this).data('vehicle-id'));
        });

        $('#dailyCheckVehicleId').on('change', function() {
            populateDailyCheckForm($(this).val());
        });

        $('#dailyCheckForm').on('submit', function(e) {
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
                        Swal.fire({ icon: 'success', title: 'Daily Check Saved', text: res.message, timer: 2000, showConfirmButton: false, theme: FuelPage.getSwalTheme() });
                        FuelPage.refreshDailyChecks();
                        FuelPage.fetchAndDisplayDefects();
                        if (typeof FuelPage.refreshVehicleRegistry === 'function') {
                            FuelPage.refreshVehicleRegistry();
                        }
                        updateFuelGateNotice($('#vehicle_id_add').val());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to save daily check.', theme: FuelPage.getSwalTheme() });
                    }
                },
                error: function(xhr) {
                    Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to save daily check.', theme: FuelPage.getSwalTheme() });
                },
                complete: function() {
                    submitBtn.prop('disabled', false).removeClass('opacity-50');
                }
            });
        });

        $('#vehicle_id_add').on('change', function() {
            const vehicleId = $(this).val();
            updateFuelGateNotice(vehicleId);

            if (!vehicleId) {
                return;
            }

            const gate = FuelPage.canUseVehicleToday(vehicleId);
            if (!gate.allowed) {
                syncDailyCheckVehicleFromFuelLog(vehicleId, { scroll: false });
            }
        });
    };

    FuelPage.focusDailyCheckForVehicle = function(vehicleId) {
        syncDailyCheckVehicleFromFuelLog(vehicleId, { scroll: true });
        updateFuelGateNotice(vehicleId);
    };
})(window, jQuery);
