<?php 
$pageTitle = "Tool Inventory Dashboard";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// FEATURE GATE: Redirect if module is disabled
if (!featureEnabled('module_tool_inventory_enabled')) {
    header('Location: ../index.php?error=feature_disabled');
    exit();
}

include '../header.php';
include '../nav.php';

// Fetch initial lookup data
$vRes = makeApiCall('/api/fuel/vehicles');
$lookupRes = makeApiCall('/api/tools/lookups');

$vehicles = ($vRes && ($vRes['success'] ?? false)) ? $vRes['vehicles'] : [];
$toolTypes = ($lookupRes && ($lookupRes['success'] ?? false)) ? $lookupRes['types'] : [];
$toolTrades = ($lookupRes && ($lookupRes['success'] ?? false)) ? $lookupRes['trades'] : [];
?>

<!-- Lightbox CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/css/lightbox.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lightbox2@2.11.3/dist/js/lightbox.min.js"></script>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Tool & Asset Management</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Real-time inventory tracking and fleet asset valuation.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="edit_tools.php" class="bg-gray-900 dark:bg-slate-800 hover:bg-gray-800 text-white px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-xl flex items-center gap-2">
                <i class="fas fa-list-ul text-indigo-400"></i> Master Registry
            </a>
            <a href="history.php" class="btn-secondary py-3 px-6 shadow-none text-[10px]">
                <i class="fas fa-history text-emerald-500"></i> Stocktake History
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="quickStats">
        <div class="card-base p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xl shadow-sm"><i class="fas fa-tools"></i></div>
            <div>
                <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-0.5">Total Tools</div>
                <div class="text-2xl font-black text-gray-900 dark:text-white" id="stat-total-tools">--</div>
            </div>
        </div>
        <div class="card-base p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shadow-sm"><i class="fas fa-euro-sign"></i></div>
            <div>
                <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-0.5">Global Asset Value</div>
                <div class="text-2xl font-black text-gray-900 dark:text-white" id="stat-total-value">--</div>
            </div>
        </div>
        <div class="card-base p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xl shadow-sm"><i class="fas fa-truck-loading"></i></div>
            <div>
                <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-0.5">Assigned Assets</div>
                <div class="text-2xl font-black text-gray-900 dark:text-white" id="stat-assigned-value">--</div>
            </div>
        </div>
        <div class="card-base p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xl shadow-sm"><i class="fas fa-clipboard-check"></i></div>
            <div>
                <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-0.5">Fleet Stocktake %</div>
                <div class="text-2xl font-black text-gray-900 dark:text-white" id="stat-stocktake-pct">--</div>
            </div>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-8" id="inventoryTabs">
            <button data-tab="overview" class="tab-btn border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-tachometer-alt"></i> Van Overview
            </button>
            <button data-tab="assign" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-exchange-alt"></i> Assign & Move
            </button>
            <button data-tab="inventory" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-boxes"></i> Detailed Audit
            </button>
            <button data-tab="analytics" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-chart-pie"></i> Visual Analytics
            </button>
        </nav>
    </div>

    <div id="tabContents">
        <!-- Tab: Overview -->
        <div id="tab-overview" class="tab-pane">
            <div id="vanCardDeck" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Loaded via AJAX -->
            </div>
        </div>

        <!-- Tab: Assign -->
        <div id="tab-assign" class="tab-pane hidden space-y-8">
            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-plus-circle text-emerald-400"></i> Assign Tool to Van
                    </h3>
                    <button onclick="openModal('addToolModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg active:scale-95">
                        <i class="fas fa-tools mr-1"></i> Add Master Tool
                    </button>
                </div>
                <div class="p-8">
                    <form id="assignToolForm" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Van *</label>
                                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="vehicle_id" id="vehicle_id" required>
                                    <option value="">Select a van</option>
                                    <?php foreach ($vehicles as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."'>".htmlspecialchars($v['license_plate'])." - ".htmlspecialchars($v['make_model'])."</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Tool *</label>
                                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="tool_id" id="tool_id" required>
                                    <option value="">Select a tool</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Quantity *</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="number" name="quantity" value="1" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Condition</label>
                                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="condition" required>
                                    <option value="New">New</option>
                                    <option value="Good" selected>Good</option>
                                    <option value="Fair">Fair</option>
                                    <option value="Poor">Poor</option>
                                    <option value="Broken">Broken</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Price/Value (€)</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="text" name="price" id="price" value="0.00">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Internal Remarks</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="text" name="remarks" placeholder="Optional notes...">
                            </div>
                        </div>
                        <div id="tool_info" class="mb-6"></div>
                        <button class="w-full py-5 bg-indigo-600 dark:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-3" type="submit">
                            <i class="fas fa-check-circle text-emerald-400"></i> Complete Asset Assignment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: Inventory -->
        <div id="tab-inventory" class="tab-pane hidden space-y-8">
            <div class="card-base border-none">
                <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60 flex-wrap gap-4">
                    <h3>
                        <i class="fas fa-boxes text-indigo-300"></i> Van Audit Table
                    </h3>
                    <div class="flex flex-wrap items-center gap-4">
                        <select id="vehicleFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest focus:bg-white focus:text-gray-900 outline-none transition-all">
                            <option value="" class="text-gray-900">Select Vehicle to Audit</option>
                            <?php foreach ($vehicles as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."' class='text-gray-900'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                        </select>
                        <button id="saveInventoryLogButton" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md active:scale-95">
                            <i class="fas fa-clipboard-check mr-1"></i> Verify Current Stock
                        </button>
                    </div>
                </div>
                <div class="p-6">
                    <div id="lastInventoryCheckTime" class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 ml-1"></div>
                    <div class="table-container">
                        <table id="inventoryTable" class="w-full text-sm">
                            <thead class="table-header-row">
                                <tr>
                                    <th class="px-6 py-4 text-left">ID</th>
                                    <th class="px-6 py-4 text-left">Tool Name</th>
                                    <th class="px-6 py-4 text-center">Type</th>
                                    <th class="px-6 py-4 text-center">Qty</th>
                                    <th class="px-6 py-4 text-center">Condition</th>
                                    <th class="px-6 py-4 text-right">Value (€)</th>
                                    <th class="px-6 py-4 text-left">Remarks</th>
                                    <th class="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Analytics -->
        <div id="tab-analytics" class="tab-pane hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="card-base p-8 border-none">
                    <h3 class="text-gray-900 dark:text-white font-black uppercase text-xs tracking-widest mb-8 flex items-center gap-2"><i class="fas fa-chart-bar text-indigo-500"></i> Asset Value by Vehicle</h3>
                    <div class="h-80"><canvas id="vanValueChart"></canvas></div>
                </div>
                <div class="card-base p-8 border-none">
                    <h3 class="text-gray-900 dark:text-white font-black uppercase text-xs tracking-widest mb-8 flex items-center gap-2"><i class="fas fa-chart-pie text-emerald-500"></i> Tools by Category</h3>
                    <div class="h-80"><canvas id="categoryChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="addToolModal" class="fixed inset-0 z-[150] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="card-base w-full max-w-2xl overflow-hidden border-none shadow-2xl">
            <div class="section-header">
                <h3><i class="fas fa-tools text-indigo-400"></i> Create Master Tool</h3>
                <button onclick="closeModal('addToolModal')" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white"><i class="fas fa-times"></i></button>
            </div>
            <form id="addToolForm" class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Tool Name *</label>
                        <input type="text" name="toolName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category *</label>
                        <select name="toolTypeID" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php foreach($toolTypes as $type) echo "<option value='{$type['ToolTypeID']}'>".htmlspecialchars($type['ToolTypeName'])."</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Serial Number</label>
                        <input type="text" name="serialNumber" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Market Value (€)</label>
                        <input type="text" name="value" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" value="0.00">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Applicable Trades</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($toolTrades as $trade): ?>
                            <label class="flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all group">
                                <input type="checkbox" name="toolTradeID[]" value="<?php echo $trade['TradeID']; ?>" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400"><?php echo htmlspecialchars($trade['TradeName']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4 -mx-8 -mb-8">
                    <button type="button" onclick="closeModal('addToolModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                    <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-2">
                        <i class="fas fa-save text-indigo-200"></i> Save Master Tool
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // --- Globals ---
    let inventoryTable;
    let vanValueChart, categoryChart;

    // --- Tab Switching ---
    $('.tab-btn').click(function() {
        const tab = $(this).data('tab');
        $('.tab-btn').removeClass('border-indigo-500 text-indigo-600').addClass('border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200');
        $(this).removeClass('border-transparent text-gray-500').addClass('border-indigo-500 text-indigo-600');
        $('.tab-pane').addClass('hidden');
        $(`#tab-${tab}`).removeClass('hidden');
        if (tab === 'overview') loadVanToolValues();
        if (tab === 'analytics') loadAnalytics();
    });

    // --- Core Functions ---

    function loadStats() {
        $.getJSON('fetch_tool_data.php?action=stats_summary', function(res) {
            if (res.success) {
                const stats = res.stats;
                $('#stat-total-tools').text(stats.total_tools);
                $('#stat-total-value').text('€' + parseFloat(stats.total_value).toLocaleString());
                
                let assignedTotal = 0;
                res.stats.van_breakdown.forEach(v => assignedTotal += parseFloat(v.value));
                $('#stat-assigned-value').text('€' + assignedTotal.toLocaleString());
                
                $('#stat-stocktake-pct').text('100%');
            }
        });
    }

    function loadAnalytics() {
        const isDark = document.documentElement.classList.contains('dark');
        const textColor = isDark ? '#94a3b8' : '#64748b';
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';

        $.getJSON('fetch_tool_data.php?action=stats_summary', function(res) {
            if (res.success) {
                const stats = res.stats;
                
                if (vanValueChart) vanValueChart.destroy();
                vanValueChart = new Chart($('#vanValueChart'), {
                    type: 'bar',
                    data: {
                        labels: stats.van_breakdown.map(v => v.label),
                        datasets: [{
                            label: 'Asset Value (€)',
                            data: stats.van_breakdown.map(v => v.value),
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

                if (categoryChart) categoryChart.destroy();
                categoryChart = new Chart($('#categoryChart'), {
                    type: 'doughnut',
                    data: {
                        labels: stats.category_breakdown.map(c => c.label),
                        datasets: [{
                            data: stats.category_breakdown.map(c => c.count),
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
            }
        });
    }

    function loadTools(selectedToolID = null) {
        $.getJSON('fetch_tools.php', function(data) {
            const dropdown = $('#tool_id');
            dropdown.empty().append('<option value="">Select a tool</option>');
            data.forEach(tool => {
                dropdown.append(`<option value="${tool.ToolID}" ${selectedToolID == tool.ToolID ? 'selected' : ''}>${tool.ToolName}</option>`);
            });
            if (selectedToolID) dropdown.trigger('change');
        });
    }

    function loadVanInventory(vehicleID) {
        if (!vehicleID) { $('#inventoryTableWrapper').hide(); return; }
        $('#inventoryTableWrapper').show();

        if (inventoryTable) inventoryTable.destroy();

        inventoryTable = $('#inventoryTable').DataTable({
            ajax: { url: `fetch_van_inventory.php?vehicle_id=${vehicleID}`, dataSrc: '' },
            columns: [
                { data: 'InventoryID', className: 'px-6 py-4 text-xs font-mono text-gray-400 dark:text-gray-600' },
                { data: 'ToolName', className: 'px-6 py-4 font-bold text-gray-900 dark:text-gray-100' },
                { data: 'ToolTypeName', className: 'px-6 py-4 text-[10px] uppercase font-black text-gray-400 dark:text-gray-500' },
                { data: 'Quantity', className: 'px-6 py-4 text-center font-black text-indigo-600 dark:text-indigo-400' },
                { data: 'Condition', className: 'px-6 py-4 text-center font-bold text-gray-600 dark:text-gray-400' },
                { data: 'Price', className: 'px-6 py-4 text-right font-mono font-bold dark:text-gray-300', render: (d) => `€${parseFloat(d).toFixed(2)}` },
                { data: 'Remarks', className: 'px-6 py-4 text-xs text-gray-500 dark:text-gray-500 italic' },
                { data: 'InventoryID', className: 'px-6 py-4 text-right', render: (d) => `
                    <button onclick="deleteRecord(${d})" class="text-red-400 hover:text-red-600 font-black uppercase text-[10px] tracking-widest transition-colors">
                        <i class="fas fa-trash-alt"></i> Remove
                    </button>` 
                }
            ],
            responsive: true,
            dom: 'rtp',
            pageLength: 25
        });
    }

    function loadVanToolValues() {
        $.getJSON('fetch_van_tool_values.php', function(data) {
            const container = $('#vanCardDeck');
            container.empty();
            if (data.success) {
                data.vans.forEach(van => {
                    const totalValue = parseFloat(van.total_value) || 0;
                    const card = $(`
                        <div class="card-base p-8 flex flex-col items-center text-center group hover:bg-indigo-600 transition-all duration-300 transform hover:-translate-y-2 cursor-pointer border-none shadow-hard" onclick="$('[data-tab=inventory]').click(); $('#vehicleFilter').val(${van.vehicle_id}).trigger('change');">
                            <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl mb-4 group-hover:bg-white/20 group-hover:text-white transition-colors">
                                <i class="fas fa-shuttle-van"></i>
                            </div>
                            <h4 class="text-xl font-black uppercase italic tracking-tighter text-gray-900 dark:text-white group-hover:text-white mb-1">${van.license_plate}</h4>
                            <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest group-hover:text-indigo-200 mb-6">${van.make_model}</p>
                            <div class="w-full h-px bg-gray-100 dark:bg-slate-800 group-hover:bg-white/10 mb-6"></div>
                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-indigo-400 group-hover:text-indigo-200 mb-1">Estimated Asset Value</div>
                            <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 group-hover:text-white mb-6">€${totalValue.toLocaleString()}</div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-gray-300 dark:text-gray-600 group-hover:text-white/50">Last Stocktake: ${van.last_inventory_date || 'Never'}</div>
                        </div>
                    `);
                    container.append(card);
                });
            }
        });
    }

    // ... (rest of Event Listeners remain same logic, but UI will now use global styles)
    $('#tool_id').change(function() {
        const id = $(this).val();
        if (id) {
            $.get(`fetch_tool_info.php?tool_id=${id}`, function(html) {
                $('#tool_info').html(html);
                const price = $('#val').text();
                $('#price').val(price);
            });
        } else { $('#tool_info').empty(); }
    });

    $('#vehicleFilter').change(function() {
        loadVanInventory($(this).val());
        fetchLastInventoryCheck($(this).val());
    });

    function fetchLastInventoryCheck(vid = '') {
        $.getJSON(`fetch_last_inventory_check.php?vehicle_id=${vid}`, function(data) {
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
                    Swal.fire({ icon:'success', title:'Assigned!', text:'Tool added to van.', timer:1500, showConfirmButton:false });
                    $('#assignToolForm')[0].reset();
                    $('#tool_info').empty();
                    $('#tool_id').val(null).trigger('change');
                    loadTools(); loadStats();
                } else { Swal.fire('Error', res.message, 'error'); }
            }
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
                    Swal.fire({ icon:'success', title:'Created!', text:'Tool added to master list.', timer:1500, showConfirmButton:false });
                    closeModal('addToolModal');
                    loadTools(res.toolID); loadStats();
                }
            }
        });
    });

    $('#saveInventoryLogButton').click(function() {
        const vid = $('#vehicleFilter').val();
        if (!vid) return Swal.fire('Select Van', 'Please select a vehicle first.', 'warning');
        
        Swal.fire({ title: 'Confirm Stocktake?', text: "This will record current van contents as verified.", icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, record it!' }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({ url: 'insert_van_inventory_to_log.php', method: 'POST', contentType: 'application/json', data: JSON.stringify({ vehicle_id: vid }), success: function(res) { if (res.success) { Swal.fire('Saved!', 'Inventory check recorded.', 'success'); fetchLastInventoryCheck(vid); loadVanToolValues(); } } });
            }
        });
    });

    window.deleteRecord = function(id) {
        Swal.fire({ title: 'Remove Assignment?', text: "Remove tool from this van's inventory?", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, remove it' }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_inventory.php', { id: id }, function(res) { if (res.success) { Swal.fire('Removed!', 'Tool removed.', 'success'); loadVanInventory($('#vehicleFilter').val()); loadStats(); } });
            }
        });
    };

    window.openModal = function(id) { $(`#${id}`).removeClass('hidden'); };
    window.closeModal = function(id) { $(`#${id}`).addClass('hidden'); };

    // Initial Load
    loadTools();
    loadVanToolValues();
    loadStats();
});
</script>

<style>
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #4f46e5 !important;
        color: white !important;
        border: none !important;
        border-radius: 12px !important;
        font-weight: 900 !important;
        font-size: 10px !important;
        padding: 5px 12px !important;
    }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

</body>
</html>
