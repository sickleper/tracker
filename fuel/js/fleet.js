(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    function getStatusBadge(status) {
        const stateConfig = {
            expired: {
                icon: '✕',
                classes: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 ring-red-200 dark:ring-red-900/50'
            },
            warning: {
                icon: '⚠',
                classes: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 ring-amber-200 dark:ring-amber-900/50'
            },
            missing: {
                icon: '•',
                classes: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 ring-slate-200 dark:ring-slate-700'
            },
            valid: {
                icon: '✓',
                classes: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 ring-emerald-200 dark:ring-emerald-900/50'
            }
        };

        const config = stateConfig[status.state] || stateConfig.valid;

        return `
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset ${config.classes}">
                <span aria-hidden="true">${config.icon}</span>
                ${FuelPage.escapeHtml(status.text)}
            </span>
        `;
    }

    function getRowClasses(rowState) {
        if (rowState === 'expired') {
            return 'row-expired bg-red-50/50 dark:bg-red-900/10 border-l-red-500';
        }

        if (rowState === 'off_road') {
            return 'bg-rose-100/80 dark:bg-rose-900/20 border-l-rose-600';
        }

        if (rowState === 'warning') {
            return 'row-soon bg-amber-50/50 dark:bg-amber-900/10 border-l-amber-500';
        }

        if (rowState === 'missing') {
            return 'bg-slate-50/70 dark:bg-slate-800/20 border-l-slate-300 dark:border-l-slate-700';
        }

        return 'border-l-transparent';
    }

    function renderVehicleRegistryTable() {
        const tbody = $('#vehicleRegistryBody');
        if (!tbody.length) {
            return;
        }

        const vehicles = FuelPage.state.vehicleRegistry || [];
        if (!vehicles.length) {
            tbody.html('<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">No vehicles found.</td></tr>');
            return;
        }

        const rows = vehicles.map(function(vehicle) {
            const offRoadBadge = vehicle.off_road
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300 ring-rose-200 dark:ring-rose-900/50">Off Road</span>`
                : '';
            const defectBadge = vehicle.active_defect_count
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 ring-amber-200 dark:ring-amber-900/50">${Number(vehicle.active_defect_count)} Defect${Number(vehicle.active_defect_count) === 1 ? '' : 's'}</span>`
                : '';

            return `
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors border-l-4 ${getRowClasses(vehicle.row_state)}">
                    <td class="px-6 py-4">
                        <div class="flex flex-col gap-2">
                            <span class="font-mono text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg px-2 py-1 border border-black/5 dark:border-white/5 inline-flex w-fit">
                                ${FuelPage.escapeHtml(vehicle.license_plate)}
                            </span>
                            <div class="flex flex-wrap gap-2">
                                ${offRoadBadge}
                                ${defectBadge}
                            </div>
                            ${vehicle.off_road_reason ? `<div class="text-[11px] font-bold text-rose-600 dark:text-rose-300">${FuelPage.escapeHtml(vehicle.off_road_reason)}</div>` : ''}
                        </div>
                    </td>
                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-slate-100">${FuelPage.escapeHtml(vehicle.user_name)}</td>
                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400 font-medium">${FuelPage.escapeHtml(vehicle.make_model)}</td>
                    <td class="px-6 py-4">
                        <div class="flex flex-wrap gap-2">
                            ${vehicle.statuses.map(getStatusBadge).join('')}
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex gap-3 justify-end">
                            <button type="button" data-vehicle-id="${Number(vehicle.vehicle_id)}" class="edit-vehicle-btn text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="edit_vehicle_docs.php?vehicle_id=${Number(vehicle.vehicle_id)}" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-900 dark:hover:text-emerald-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                                <i class="fas fa-file-invoice"></i> Docs
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.html(rows.join(''));
    }

    FuelPage.refreshVehicleRegistry = function() {
        const tbody = $('#vehicleRegistryBody');
        if (tbody.length) {
            tbody.html('<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">Loading fleet overview...</td></tr>');
        }

        return $.getJSON('fetch_vehicle_registry.php', function(res) {
            FuelPage.state.vehicleRegistry = res.vehicles || [];
            renderVehicleRegistryTable();
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Failed to load vehicle registry.';
            if (tbody.length) {
                tbody.html(`<tr><td colspan="5" class="px-6 py-12 text-center text-red-500 font-bold">${FuelPage.escapeHtml(message)}</td></tr>`);
            }
            Swal.fire({ icon: 'error', title: 'Error', text: message, theme: FuelPage.getSwalTheme() });
        });
    };
})(window, jQuery);
