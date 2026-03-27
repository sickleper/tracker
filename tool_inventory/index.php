<?php 
$pageTitle = "Tool Inventory Dashboard";
require_once '../config.php';
require_once '../tracker_data.php';

$pageCssFiles = [rtrim(trackerAppUrl(), '/') . '/tool_inventory/main.css?v=' . time()];

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
                <i class="fas fa-tachometer-alt"></i> Overview
            </button>
            <button data-tab="assign" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-exchange-alt"></i> Assign
            </button>
            <button data-tab="inventory" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-boxes"></i> Stocktake
            </button>
            <button data-tab="analytics" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-chart-pie"></i> Reports
            </button>
        </nav>
    </div>

    <div id="tabContents">
        <?php include __DIR__ . '/partials/tab_overview.php'; ?>
        <?php include __DIR__ . '/partials/tab_assign.php'; ?>
        <?php include __DIR__ . '/partials/tab_inventory.php'; ?>
        <?php include __DIR__ . '/partials/tab_analytics.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/modal_add_tool.php'; ?>

<script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/tool_inventory/main.js?v=<?php echo time(); ?>"></script>

</body>
</html>
