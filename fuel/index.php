<?php 
$pageTitle = "Fuel & Mileage Log";
require_once '../config.php';
require_once '../tracker_data.php';
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/daily_checks_repository.php';

$pageCssFiles = [rtrim(trackerAppUrl(), '/') . '/fuel/main.css?v=' . time()];

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// FEATURE GATE: Redirect if module is disabled
if (!featureEnabled('module_fuel_enabled')) {
    header('Location: ../index.php?error=feature_disabled');
    exit();
}

// Fetch vehicles and drivers once for the page
$vRes = makeApiCall('/api/fuel/vehicles');
$uRes = makeApiCall('/api/users', ['team_only' => 1]); // Fetch all non-client users for the registry
$driversOnlyRes = makeApiCall('/api/users', ['drivers_only' => 1, 'team_only' => 1]); // For dropdowns

$fuelVehicles = $vRes['vehicles'] ?? [];

$fuelPageData = [
    'drivers' => $uRes['users'] ?? [],
    'currentUserName' => $_SESSION['user_name'] ?? 'User',
    'vehicleReminderEndpoint' => rtrim(trackerAppUrl(), '/') . '/api/vehicle_reminders.php',
];

include '../header.php';
include '../nav.php';
?>
<script>
    window.fuelMainData = <?php echo json_encode($fuelPageData); ?>;
</script>

<!-- Main Content -->
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <h1 class="heading-brand">Fuel & Fleet Management</h1>
        <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Logs, maintenance, drivers, and fleet health in one place.</p>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-8" aria-label="Tabs" id="dashboardTabs">
            <button data-tab="logs" class="tab-btn border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-gas-pump"></i> Logs
            </button>
            <button data-tab="maintenance" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-tools"></i> Maintenance
            </button>
            <button data-tab="fleet" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-car"></i> Fleet
            </button>
            <button data-tab="reports" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-chart-pie"></i> Reports
            </button>
        </nav>
    </div>

    <!-- Tab Contents -->
    <div id="tabContents">
        <?php include __DIR__ . '/partials/tab_logs.php'; ?>
        <?php include __DIR__ . '/partials/tab_maintenance.php'; ?>
        <?php include __DIR__ . '/partials/tab_fleet.php'; ?>
        <?php include __DIR__ . '/partials/tab_reports.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/modal_edit_driver.php'; ?>
<?php include __DIR__ . '/partials/modal_edit_vehicle.php'; ?>
<?php include __DIR__ . '/partials/modal_edit_fuel_log.php'; ?>

    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/core.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/reports.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/defects.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/fleet.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/daily_checks.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/js/forms.js?v=<?php echo time(); ?>"></script>
    <script src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/fuel/main.js?v=<?php echo time(); ?>"></script>


</body>
</html>
