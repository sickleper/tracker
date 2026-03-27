$(document).ready(function() {
    let inventoryTable;
    let vanValueChart;
    let categoryChart;
    const storageKey = 'toolInventoryActiveTab';

    function getSwalTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
    }

    function setActiveTab(tab, options) {
        const selected = tab || 'overview';
        const settings = options || {};

        if (!$(`.tab-btn[data-tab="${selected}"]`).length) {
            return setActiveTab('overview', settings);
        }

        $('.tab-btn')
            .removeClass('border-indigo-500 text-indigo-600')
            .addClass('border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200');
        $(`.tab-btn[data-tab="${selected}"]`).removeClass('border-transparent text-gray-500').addClass('border-indigo-500 text-indigo-600');
        $('.tab-pane').addClass('hidden');
        $(`#tab-${selected}`).removeClass('hidden');

        if (settings.updateHistory !== false) {
            window.location.hash = selected;
        }

        try {
            window.localStorage.setItem(storageKey, selected);
        } catch (error) {
            // ignore storage failures
        }

        if (selected === 'overview') {
            loadVanToolValues();
        }
        if (selected === 'analytics') {
            loadAnalytics();
        }
    }

    $('.tab-btn').click(function() {
        setActiveTab($(this).data('tab'));
    });

    function showToast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            timer: 1600,
            showConfirmButton: false,
            theme: getSwalTheme()
        });
    }

    function showError(message, title) {
        Swal.fire({ icon: 'error', title: title || 'Error', text: message, theme: getSwalTheme() });
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function loadStats() {
        $('#stat-total-tools, #stat-total-value, #stat-assigned-value, #stat-stocktake-pct').text('--');
        $.getJSON('fetch_tool_data.php?action=stats_summary', function(res) {
            if (!res.success) {
                $('#stat-total-tools, #stat-total-value, #stat-assigned-value, #stat-stocktake-pct').text('Unavailable');
                return;
            }

            const stats = res.stats;
            $('#stat-total-tools').text(stats.total_tools);
            $('#stat-total-value').text('EUR ' + parseFloat(stats.total_value).toLocaleString());

            let assignedTotal = 0;
            res.stats.van_breakdown.forEach(function(van) {
                assignedTotal += parseFloat(van.value);
            });
            $('#stat-assigned-value').text('EUR ' + assignedTotal.toLocaleString());
            $('#stat-stocktake-pct').text('100%');
        }).fail(function() {
            $('#stat-total-tools, #stat-total-value, #stat-assigned-value, #stat-stocktake-pct').text('Unavailable');
        });
    }

    function loadAnalytics() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        $('#vanValueChart').parent().html('<div class="tool-panel-state tool-panel-state-muted"><i class="fas fa-chart-bar text-2xl"></i><span>Loading asset values...</span></div>');
        $('#categoryChart').parent().html('<div class="tool-panel-state tool-panel-state-muted"><i class="fas fa-chart-pie text-2xl"></i><span>Loading category mix...</span></div>');

        $.getJSON('fetch_tool_data.php?action=stats_summary', function(res) {
            if (!res.success) {
                $('#vanValueChart').parent().html('<div class="tool-panel-state tool-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load asset values.</span></div>');
                $('#categoryChart').parent().html('<div class="tool-panel-state tool-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load category mix.</span></div>');
                return;
            }

            const stats = res.stats;
            $('#vanValueChart').parent().html('<canvas id="vanValueChart"></canvas>');
            $('#categoryChart').parent().html('<canvas id="categoryChart"></canvas>');

            if (vanValueChart) {
                vanValueChart.destroy();
            }
            vanValueChart = new Chart(document.getElementById('vanValueChart'), {
                type: 'bar',
                data: {
                    labels: stats.van_breakdown.map(function(van) { return van.label; }),
                    datasets: [{
                        label: 'Asset Value (EUR)',
                        data: stats.van_breakdown.map(function(van) { return van.value; }),
                        backgroundColor: 'rgba(79, 70, 229, 0.8)',
                        borderRadius: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { weight: 'bold' } } },
                        x: { grid: { display: false }, ticks: { color: textColor, font: { weight: 'bold' } } }
                    }
                }
            });

            if (categoryChart) {
                categoryChart.destroy();
            }
            categoryChart = new Chart(document.getElementById('categoryChart'), {
                type: 'doughnut',
                data: {
                    labels: stats.category_breakdown.map(function(category) { return category.label; }),
                    datasets: [{
                        data: stats.category_breakdown.map(function(category) { return category.count; }),
                        backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: textColor, font: { weight: 'bold', size: 10 }, padding: 20 }
                        }
                    }
                }
            });
        }).fail(function() {
            $('#vanValueChart').parent().html('<div class="tool-panel-state tool-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load asset values.</span></div>');
            $('#categoryChart').parent().html('<div class="tool-panel-state tool-panel-state-error"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load category mix.</span></div>');
        });
    }

    function loadTools(selectedToolID) {
        $.getJSON('fetch_tools.php', function(data) {
            const dropdown = $('#tool_id');
            dropdown.empty().append('<option value="">Select a tool</option>');
            data.forEach(function(tool) {
                dropdown.append(`<option value="${tool.ToolID}" ${selectedToolID == tool.ToolID ? 'selected' : ''}>${tool.ToolName}</option>`);
            });
            if (selectedToolID) {
                dropdown.trigger('change');
            }
        }).fail(function() {
            $('#tool_id').empty().append('<option value="">Failed to load tools</option>');
        });
    }

    function loadVanInventory(vehicleID) {
        if (!vehicleID) {
            if (inventoryTable) {
                inventoryTable.clear().draw();
            }
            $('#lastInventoryCheckTime').text('Select a vehicle to load an audit view.');
            return;
        }

        if (inventoryTable) {
            inventoryTable.destroy();
        }

        inventoryTable = $('#inventoryTable').DataTable({
            ajax: {
                url: `fetch_van_inventory.php?vehicle_id=${vehicleID}`,
                dataSrc: function(res) {
                    if (!res.success) {
                        showError(res.message || 'Failed to load van audit inventory.');
                        return [];
                    }
                    return Array.isArray(res.data) ? res.data : [];
                },
                error: function(xhr) {
                    showError(xhr.responseJSON?.message || 'Failed to load van audit inventory.');
                }
            },
            columns: [
                { data: 'InventoryID', className: 'px-6 py-4 text-xs font-mono text-gray-400 dark:text-gray-600' },
                { data: 'ToolName', className: 'px-6 py-4 font-bold text-gray-900 dark:text-gray-100' },
                { data: 'ToolTypeName', className: 'px-6 py-4 text-[10px] uppercase font-black text-gray-400 dark:text-gray-500' },
                { data: 'Quantity', className: 'px-6 py-4 text-center font-black text-indigo-600 dark:text-indigo-400' },
                { data: 'Condition', className: 'px-6 py-4 text-center font-bold text-gray-600 dark:text-gray-400' },
                { data: 'Price', className: 'px-6 py-4 text-right font-mono font-bold dark:text-gray-300', render: function(value) { return `EUR ${parseFloat(value).toFixed(2)}`; } },
                { data: 'Remarks', className: 'px-6 py-4 text-xs text-gray-500 dark:text-gray-500 italic' },
                { data: 'InventoryID', className: 'px-6 py-4 text-right', render: function(value) {
                    return `<button onclick="deleteRecord(${value})" class="text-red-400 hover:text-red-600 font-black uppercase text-[10px] tracking-widest transition-colors"><i class="fas fa-trash-alt"></i> Remove</button>`;
                }}
            ],
            responsive: true,
            dom: 'rtp',
            pageLength: 25,
            language: {
                emptyTable: 'No assigned tools found for this vehicle.'
            }
        });
    }

    function loadVanToolValues() {
        $.getJSON('fetch_van_tool_values.php', function(data) {
            const container = $('#vanCardDeck');
            container.html('<div class="tool-panel-state tool-panel-state-muted col-span-full"><i class="fas fa-shuttle-van text-2xl"></i><span>Loading van asset overview...</span></div>');
            if (!data.success) {
                container.html('<div class="tool-panel-state tool-panel-state-error col-span-full"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load van overview.</span></div>');
                return;
            }

            container.empty();
            if (!Array.isArray(data.vans) || !data.vans.length) {
                container.html('<div class="tool-panel-state tool-panel-state-muted col-span-full"><i class="fas fa-box-open text-2xl"></i><span>No vans with tracked assets yet.</span></div>');
                return;
            }

            data.vans.forEach(function(van) {
                const totalValue = parseFloat(van.total_value) || 0;
                const card = $(`
                    <button type="button" class="van-overview-card card-base p-8 flex flex-col items-center text-center group hover:bg-indigo-600 transition-all duration-300 transform hover:-translate-y-2 cursor-pointer border-none shadow-hard w-full" data-vehicle-id="${van.vehicle_id}">
                        <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-4 group-hover:bg-white/20 group-hover:text-white transition-colors">
                            <i class="fas fa-shuttle-van"></i>
                        </div>
                        <h4 class="text-xl font-black uppercase italic tracking-tighter text-gray-900 dark:text-white group-hover:text-white mb-1">${escapeHtml(van.license_plate)}</h4>
                        <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest group-hover:text-indigo-200 mb-6">${escapeHtml(van.make_model)}</p>
                        <div class="w-full h-px bg-gray-100 dark:bg-slate-800 group-hover:bg-white/10 mb-6"></div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-indigo-400 group-hover:text-indigo-200 mb-1">Estimated Asset Value</div>
                        <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 group-hover:text-white mb-6">EUR ${totalValue.toLocaleString()}</div>
                        <div class="text-[9px] font-black uppercase tracking-widest text-gray-300 dark:text-gray-600 group-hover:text-white/50">Last Stocktake: ${escapeHtml(van.last_inventory_date || 'Never')}</div>
                    </button>
                `);
                container.append(card);
            });
        }).fail(function() {
            $('#vanCardDeck').html('<div class="tool-panel-state tool-panel-state-error col-span-full"><i class="fas fa-triangle-exclamation text-2xl"></i><span>Failed to load van overview.</span></div>');
        });
    }

    $('#tool_id').change(function() {
        const id = $(this).val();
        if (id) {
            $.get(`fetch_tool_info.php?tool_id=${id}`, function(html) {
                $('#tool_info').html(html);
                const price = $('#val').text();
                $('#price').val(price);
            });
        } else {
            $('#tool_info').empty();
        }
    });

    $('#vehicleFilter').change(function() {
        loadVanInventory($(this).val());
        fetchLastInventoryCheck($(this).val());
    });

    $(document).on('click', '.van-overview-card', function() {
        const vehicleId = $(this).data('vehicle-id');
        setActiveTab('inventory');
        $('#vehicleFilter').val(vehicleId).trigger('change');
    });

    function fetchLastInventoryCheck(vid) {
        $.getJSON(`fetch_last_inventory_check.php?vehicle_id=${vid || ''}`, function(data) {
            $('#lastInventoryCheckTime').text(data.success ? `Last Stocktake: ${data.lastCheckTime}` : 'No stocktake on record.');
        });
    }

    $('#assignToolForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: 'process_assign_tool.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    showToast('success', 'Tool assigned');
                    const selectedVehicle = $('#vehicleFilter').val();
                    const assignedVehicle = $('#vehicle_id').val();
                    $('#assignToolForm')[0].reset();
                    $('#tool_info').empty();
                    $('#tool_id').val(null).trigger('change');
                    loadTools();
                    loadStats();
                    loadVanToolValues();
                    if (selectedVehicle && assignedVehicle && String(selectedVehicle) === String(assignedVehicle)) {
                        loadVanInventory(selectedVehicle);
                        fetchLastInventoryCheck(selectedVehicle);
                    }
                    return;
                }

                showError(res.message || 'Failed to assign tool.');
            }
        }).fail(function(xhr) {
            showError(xhr.responseJSON?.message || 'Failed to assign tool.');
        });
    });

    $('#addToolForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: 'add_tool.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    showToast('success', 'Master tool created');
                    closeModal('addToolModal');
                    loadTools(res.toolID);
                    loadStats();
                    return;
                }

                showError(res.message || 'Failed to create tool.');
            }
        }).fail(function(xhr) {
            showError(xhr.responseJSON?.message || 'Failed to create tool.');
        });
    });

    $('#saveInventoryLogButton').click(function() {
        const vid = $('#vehicleFilter').val();
        if (!vid) {
            return Swal.fire('Select Van', 'Please select a vehicle first.', 'warning');
        }

        Swal.fire({
            title: 'Confirm Stocktake?',
            text: "This will record current van contents as verified.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, record it!'
        }).then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: 'insert_van_inventory_to_log.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ vehicle_id: vid }),
                success: function(res) {
                    if (res.success) {
                        Swal.fire('Saved!', 'Inventory check recorded.', 'success');
                        fetchLastInventoryCheck(vid);
                        loadVanToolValues();
                        return;
                    }

                    showError(res.message || 'Failed to record stocktake.');
                }
            }).fail(function(xhr) {
                showError(xhr.responseJSON?.message || 'Failed to record stocktake.');
            });
        });
    });

    window.deleteRecord = function(id) {
        Swal.fire({
            title: 'Remove Assignment?',
            text: "Remove tool from this van's inventory?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, remove it'
        }).then(function(result) {
            if (!result.isConfirmed) {
                return;
            }

            $.post('delete_inventory.php', { id: id }, function(res) {
                if (res.success) {
                    Swal.fire('Removed!', 'Tool removed.', 'success');
                    loadVanInventory($('#vehicleFilter').val());
                    loadStats();
                    loadVanToolValues();
                    return;
                }

                showError(res.message || 'Failed to remove tool.');
            }).fail(function(xhr) {
                showError(xhr.responseJSON?.message || 'Failed to remove tool.');
            });
        });
    };

    window.openModal = function(id) { $(`#${id}`).removeClass('hidden'); };
    window.closeModal = function(id) { $(`#${id}`).addClass('hidden'); };

    let initialTab = (window.location.hash || '').replace('#', '');
    if (!initialTab) {
        try {
            initialTab = window.localStorage.getItem(storageKey) || 'overview';
        } catch (error) {
            initialTab = 'overview';
        }
    }

    loadTools();
    loadStats();
    setActiveTab(initialTab, { updateHistory: false });

    $(window).on('hashchange', function() {
        const hashTab = (window.location.hash || '').replace('#', '');
        if (hashTab) {
            setActiveTab(hashTab, { updateHistory: false });
        }
    });
});
