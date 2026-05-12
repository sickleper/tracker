(function(window, $) {
    const FuelPage = window.FuelPage = window.FuelPage || {};

    function severityConfig(severity) {
        const map = {
            low: 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
            medium: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
            high: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
            critical: 'bg-rose-100 dark:bg-rose-900/30 text-rose-700 dark:text-rose-300'
        };

        return map[severity] || map.medium;
    }

    FuelPage.fetchAndDisplayDefects = function() {
        const container = $('#defectsRow');

        container.html('<div class="flex flex-col items-center justify-center py-20"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500 mb-4"></i><p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Scanning Registry...</p></div>');

        $.getJSON('get_vehicle_defects.php', function(res) {
            if (res.success && res.data.length > 0) {
                container.empty();

                if (res.partial && res.message) {
                    container.append(`
                        <div class="rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-[11px] font-bold text-amber-700 dark:text-amber-300">
                            <i class="fas fa-triangle-exclamation mr-2"></i>${FuelPage.escapeHtml(res.message)}
                        </div>
                    `);
                }

                res.data.forEach(function(defect) {
                    const severity = FuelPage.escapeHtml((defect.severity || 'medium').toUpperCase());
                    const offRoadBadge = defect.off_road
                        ? '<span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-300 rounded-lg">Off Road</span>'
                        : '';
                    const hasResolveRef = Boolean(defect.id || defect.key);
                    const resolveButton = hasResolveRef
                        ? `<button type="button" class="resolve-defect-btn text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200 font-black uppercase text-[10px] tracking-widest transition-colors" data-defect-id="${FuelPage.escapeHtml(defect.id || '')}" data-defect-key="${FuelPage.escapeHtml(defect.key || '')}"><i class="fas fa-check-circle"></i> Mark Rectified</button>`
                        : '';
                    const card = $(`
                        <div class="p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800 mb-3 relative overflow-hidden group hover:bg-white dark:hover:bg-slate-900 hover:shadow-md transition-all">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg">
                                    ${FuelPage.escapeHtml(defect.vehicle?.license_plate || '')}
                                </span>
                                <span class="text-[9px] text-gray-400 dark:text-gray-600 font-bold italic">${FuelPage.escapeHtml(defect.date || '')}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-lg ${severityConfig(defect.severity)}">${severity}</span>
                                ${offRoadBadge}
                                ${defect.source ? `<span class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-300 rounded-lg">${FuelPage.escapeHtml(String(defect.source).replace('_', ' '))}</span>` : ''}
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed">${FuelPage.escapeHtml(defect.defect_details || '')}</p>
                            ${defect.notes ? `<p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">${FuelPage.escapeHtml(defect.notes)}</p>` : ''}
                            ${resolveButton ? `<div class="mt-4 flex justify-end">${resolveButton}</div>` : ''}
                        </div>
                    `);

                    container.append(card);
                });
            } else {
                if (res.success && res.partial && res.message) {
                    container.html(`
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/30 px-4 py-3 text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                <i class="fas fa-triangle-exclamation mr-2"></i>${FuelPage.escapeHtml(res.message)}
                            </div>
                            <div class="text-center py-8 text-gray-300 dark:text-gray-700 italic font-medium">No active local safety defects on record.</div>
                        </div>
                    `);
                    return;
                }

                container.html('<div class="text-center py-12 text-gray-300 dark:text-gray-700 italic font-medium">No active safety defects on record.</div>');
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to load defects.';
            container.html(`<div class="text-center py-12 text-red-500 font-bold">${FuelPage.escapeHtml(msg)}</div>`);
            Swal.fire({ icon: 'error', title: 'Error', text: msg, theme: FuelPage.getSwalTheme() });
        });
    };

    $(document).on('click', '.resolve-defect-btn', function() {
        const defectId = $(this).data('defect-id');
        const defectKey = $(this).data('defect-key');

        $.post('resolve_defect.php', { defect_id: defectId, defect_key: defectKey }, function(res) {
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Defect Updated', text: res.message, timer: 1800, showConfirmButton: false, theme: FuelPage.getSwalTheme() });
                FuelPage.fetchAndDisplayDefects();
                if (typeof FuelPage.refreshVehicleRegistry === 'function') {
                    FuelPage.refreshVehicleRegistry();
                }
                if (typeof FuelPage.refreshDailyChecks === 'function') {
                    FuelPage.refreshDailyChecks();
                }
                return;
            }

            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to resolve defect.', theme: FuelPage.getSwalTheme() });
        }).fail(function(xhr) {
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to resolve defect.', theme: FuelPage.getSwalTheme() });
        });
    });
})(window, jQuery);
