<?php 
$pageTitle = "Fuel & Mileage Log";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// Fetch vehicles and drivers once for the page
$vRes = makeApiCall('/api/fuel/vehicles');
$uRes = makeApiCall('/api/users', ['team_only' => 1]); // Fetch all non-client users for the registry
$driversOnlyRes = makeApiCall('/api/users', ['drivers_only' => 1, 'team_only' => 1]); // For dropdowns

include '../header.php';
include '../nav.php';
?>

<!-- Main Content -->
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-8">
        <h1 class="heading-brand">Fuel & Fleet Management</h1>
        <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Track efficiency, maintenance, and vehicle logistics.</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-8" aria-label="Tabs" id="dashboardTabs">
            <button data-tab="logs" class="tab-btn border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-gas-pump"></i> Fuel Logs
            </button>
            <button data-tab="maintenance" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-tools"></i> Maintenance
            </button>
            <button data-tab="fleet" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-car"></i> Fleet Management
            </button>
            <button data-tab="reports" class="tab-btn border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-chart-pie"></i> Analytics
            </button>
        </nav>
    </div>

    <!-- Tab Contents -->
    <div id="tabContents">
        
        <!-- Tab: Logs -->
        <div id="tab-logs" class="tab-pane space-y-8">
            <!-- Add Fuel Log Form -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-plus-circle text-emerald-400"></i> Add Fuel and Mileage Log
                    </h3>
                </div>
                <div class="p-8">
                    <form id="addFuelLogForm" action="insert_fuel_log_secure.php" method="POST" onsubmit="return validateMileage()" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1 flex items-center gap-2">
                                    Vehicle *
                                    <i class="fas fa-info-circle text-indigo-400 cursor-help" onclick="showAutopopulateInfo()"></i>
                                </label>
                                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="vehicle_id" id="vehicle_id_add" required>
                                    <option value="">Select Vehicle</option>
                                    <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Driver *</label>
                                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="user_id" id="user_id" required>
                                    <option value="">Select Driver</option>
                                    <?php if ($driversOnlyRes && ($driversOnlyRes['success'] ?? false)) foreach ($driversOnlyRes['users'] as $u) echo "<option value='".htmlspecialchars($u['id'])."'>".htmlspecialchars($u['name'])."</option>"; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Date *</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="date" name="date" id="date" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Start Mileage</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="number" step="0.01" id="start_mileage" name="start_mileage" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Finish Mileage</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="number" step="0.01" name="finish_mileage" id="finish_mileage" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Fuel Amount (L)</label>
                                <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="number" step="0.01" name="fuel_amount" id="fuel_amount" required>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row gap-8 items-end">
                            <div class="flex-grow w-full">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Mileage/Receipt Image</label>
                                <div class="relative group">
                                    <input type="file" accept="image/*" name="image_file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                    <div class="w-full py-6 bg-indigo-50 dark:bg-indigo-900/20 border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 rounded-2xl text-center group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/30 transition-all">
                                        <i class="fas fa-camera text-indigo-400 text-2xl mb-1"></i>
                                        <p class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">Click to upload photo</p>
                                    </div>
                                </div>
                            </div>
                            <button class="w-full md:w-64 py-5 bg-indigo-600 dark:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-3" type="submit">
                                <i class="fas fa-check-circle text-emerald-400"></i> Add Log Entry
                            </button>
                        </div>
                        <div id="error-message" class="mt-4 text-[10px] font-black text-red-500 uppercase tracking-widest ml-1"></div>
                    </form>
                </div>
            </div>

            <!-- Fuel History -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-history text-indigo-400"></i> Fuel Log History
                    </h3>
                    <div class="flex items-center gap-4">
                        <select id="vehicleSelect" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest focus:bg-white focus:text-gray-900 outline-none transition-all">
                            <option value="" class="text-gray-900">All Vehicles</option>
                            <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."' class='text-gray-900'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="w-full text-sm" id="logTable2">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4 text-left">Date</th>
                                <th class="px-6 py-4 text-left">Driver</th>
                                <th class="px-6 py-4 text-left">Plate</th>
                                <th class="px-6 py-4 text-right">Start Mi</th>
                                <th class="px-6 py-4 text-right">Finish Mi</th>
                                <th class="px-6 py-4 text-right">Fuel (L)</th>
                                <th class="px-6 py-4 text-center">Receipt</th>
                                <th class="px-6 py-4 text-right">Km Traveled</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
                    </table>
                </div>
                <div id="logCountsDiv" class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800"></div>
            </div>
        </div>

        <!-- Tab: Maintenance -->
        <div id="tab-maintenance" class="tab-pane hidden space-y-8">
            <!-- Service Schedule -->
            <div class="card-base border-none">
                <div class="section-header !bg-amber-600 dark:!bg-amber-900/40">
                    <h3>
                        <i class="fas fa-tools text-amber-200"></i> Service Schedule
                    </h3>
                </div>
                <div class="p-6">
                    <?php include 'service_intervals.php' ?>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Defects -->
                <div class="lg:col-span-5">
                    <div class="card-base h-full border-none">
                        <div class="section-header !bg-red-700 dark:!bg-red-950/60">
                            <h3>
                                <i class="fas fa-exclamation-triangle text-red-300"></i> Report Defect
                            </h3>
                        </div>
                        <div class="p-8">
                            <form action="save_defect.php" method="post" id="defectForm" class="space-y-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Vehicle *</label>
                                    <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-red-500 outline-none transition-all text-sm font-bold dark:text-white" name="vehicle_id" required>
                                        <option value="">Select Vehicle</option>
                                        <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Details *</label>
                                    <textarea class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-red-500 outline-none transition-all text-sm dark:text-gray-300 font-medium leading-relaxed" name="defect_details" rows="4" placeholder="Describe the issue..." required></textarea>
                                </div>
                                <button type="submit" class="w-full py-4 bg-red-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-red-700 transition-all active:scale-95 shadow-xl flex items-center justify-center gap-3">
                                    <i class="fas fa-paper-plane text-red-200"></i> Submit Defect Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-7">
                    <div class="card-base h-full border-none">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-list text-gray-400 dark:text-gray-500"></i> Active Defect Logs
                            </h3>
                        </div>
                        <div class="p-6">
                            <div id="defectsRow" class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Fleet -->
        <div id="tab-fleet" class="tab-pane hidden space-y-8">
            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-car text-indigo-400"></i> Fleet Overview & Documents
                    </h3>
                </div>
                <div class="p-6">
                    <?php include 'vehicles_list.php' ?>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Add Vehicle -->
                <div class="card-base border-none">
                    <div class="section-header">
                        <h3><i class="fas fa-plus-circle text-indigo-400 mr-2"></i> Register New Vehicle</h3>
                    </div>
                    <div class="p-8">
                        <form id="addVehicleForm" action="add_vehicle.php" method="post" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">License Plate</label>
                                    <input required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" type="text" name="license_plate" placeholder="151-D-12345">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Make & Model</label>
                                    <input required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" type="text" name="make_model" placeholder="Ford Transit">
                                </div>
                            </div>
                            <button class="w-full py-4 bg-indigo-600 dark:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-2" type="submit">
                                <i class="fas fa-save text-emerald-400"></i> Save to Fleet
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Add Driver -->
                <div class="card-base border-none">
                    <div class="section-header">
                        <h3><i class="fas fa-user-plus text-emerald-400 mr-2"></i> Register New Driver</h3>
                    </div>
                    <div class="p-8">
                        <form id="addUserForm" action="add_user.php" method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Full Name</label>
                                    <input type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" name="name" required placeholder="John Smith">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address</label>
                                    <input type="email" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" name="email" required placeholder="email@example.com">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Phone Number</label>
                                <input type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" name="mobile" placeholder="087 123 4567">
                            </div>
                            <button type="submit" class="w-full py-4 bg-indigo-600 dark:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-2">
                                <i class="fas fa-id-card text-indigo-200"></i> Add Driver System
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Drivers List -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-users text-indigo-400"></i> Active Driver Registry
                    </h3>
                </div>
                <div class="table-container">
                    <table class="w-full text-sm">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4 text-left">ID</th>
                                <th class="px-6 py-4 text-left">Name</th>
                                <th class="px-6 py-4 text-left">Email</th>
                                <th class="px-6 py-4 text-center">Driver Status</th>
                                <th class="px-6 py-4 text-center">Callout</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                            <?php if ($uRes && ($uRes['success'] ?? false)): foreach ($uRes['users'] as $user): ?>
                                <tr class="table-row-hover transition-colors">
                                    <td class="px-6 py-4 text-gray-400 dark:text-gray-600 font-mono text-xs"><?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-medium"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" value="" class="sr-only peer" <?php echo (!empty($user['is_driver'])) ? 'checked' : ''; ?> onchange="toggleDriverStatus(<?php echo $user['id']; ?>, this.checked)">
                                                <div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                            </label>
                                            <span class="px-2 py-0.5 <?php echo (!empty($user['is_driver'])) ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300' : 'bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500'; ?> text-[8px] font-black rounded uppercase tracking-widest border border-emerald-200/50">
                                                <?php echo (!empty($user['is_driver'])) ? 'ENABLED' : 'DISABLED'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" value="" class="sr-only peer" <?php echo (!empty($user['is_callout_driver'])) ? 'checked' : ''; ?> onchange="toggleCalloutDriver(<?php echo $user['id']; ?>, this.checked)">
                                                <div class="w-9 h-5 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                            </label>
                                            <?php if (!empty($user['is_callout_driver'])): ?>
                                                <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-[8px] font-black rounded uppercase tracking-widest border border-indigo-200/50">Primary</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500 text-[8px] font-black rounded uppercase tracking-widest">Regular</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-3">
                                            <button type="button" onclick="editDriver(<?php echo $user['id']; ?>)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button type="button" onclick="deleteDriver(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>')" class="text-red-400 hover:text-red-600 font-black uppercase text-[10px] tracking-widest transition-colors">
                                                <i class="fas fa-trash-alt"></i> Del
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">No drivers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit Driver Modal -->
        <div id="editDriverModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
            <div class="flex min-h-screen items-center justify-center p-4">
                <form id="editDriverForm" action="update_user.php" method="post" class="card-base w-full max-w-lg overflow-hidden border-none shadow-2xl">
                    <div class="bg-gray-900 p-6 flex items-center justify-between text-white">
                        <h5 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3"><i class="fas fa-user-edit text-indigo-400"></i> Edit Driver Account</h5>
                        <button type="button" onclick="closeModal('editDriverModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="p-8 space-y-6">
                        <input type="hidden" name="id" id="edit_driver_id">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Full Name</label>
                            <input required type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_driver_name" name="name">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address</label>
                            <input required type="email" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_driver_email" name="email">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Phone Number</label>
                            <input type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_driver_mobile" name="mobile">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Fleet Callout Availability</label>
                            <select name="is_callout_driver" id="edit_is_callout_driver" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="0">Regular Fleet Driver</option>
                                <option value="1">Primary Callout Specialist</option>
                            </select>
                            <p class="mt-3 text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-tight italic leading-relaxed">Only Specialists' leave will trigger schedule blackouts in the lead booking console.</p>
                        </div>
                    </div>
                    <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                        <button type="button" onclick="closeModal('editDriverModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                        <button type="submit" class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2">
                            <i class="fas fa-save text-indigo-200"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab: Analytics -->
        <div id="tab-reports" class="tab-pane hidden space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Row 1: Charts -->
                <div class="card-base h-full border-none p-0">
                    <div class="section-header"><h3><i class="fas fa-tachometer-alt text-indigo-400"></i> Efficiency: Km per Litre</h3></div>
                    <div class="p-8 h-80"><canvas id="mplChart"></canvas></div>
                </div>
                <div class="card-base h-full border-none p-0">
                    <div class="section-header flex items-center justify-between">
                        <h3><i class="fas fa-wave-square text-red-400"></i> Fuel Anomaly Detection</h3>
                        <div class="flex gap-2">
                            <select id="monthFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-3 py-1 text-[10px] font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900"></select>
                            <select id="vehicleFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-3 py-1 text-[10px] font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900">
                                <option value="" class="text-gray-900">All Vehicles</option>
                                <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."' class='text-gray-900'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                            </select>
                        </div>
                    </div>
                    <div class="p-8 h-80"><canvas id="anomaliesChart"></canvas></div>
                </div>
            </div>

            <!-- Row 2: Detailed Table -->
            <div class="card-base border-none">
                <div class="section-header flex items-center justify-between">
                    <h3><i class="fas fa-chart-line text-indigo-300 mr-2"></i> Monthly Fleet Performance</h3>
                    <select id="vehicleSelectReport" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900">
                        <option value="" class="text-gray-900">Filter Registration</option>
                        <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."' class='text-gray-900'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                    </select>
                </div>
                <div class="table-container">
                    <table id="resultsTable" class="w-full text-sm">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4">Driver</th>
                                <th class="px-6 py-4">Registration</th>
                                <th class="px-6 py-4 text-right">Total Km</th>
                                <th class="px-6 py-4 text-right">Total Liters</th>
                                <th class="px-6 py-4 text-center">Period</th>
                                <th class="px-6 py-4 text-right">KPL</th>
                                <th class="px-6 py-4 text-right">MPG</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tailwind Modals -->
<div id="editVehicleModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <form id="editVehicleForm" action="update_vehicle.php" method="post" class="card-base w-full max-w-lg overflow-hidden border-none shadow-2xl">
            <div class="section-header">
                <h3><i class="fas fa-car mr-2 text-indigo-400"></i> Modify Vehicle Core</h3>
                <button type="button" onclick="closeModal('editVehicleModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8 space-y-6">
                <input type="hidden" name="vehicle_id" id="vehicle_update_id">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">License Plate</label>
                    <input required type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_license_plate" name="license_plate">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Make & Model</label>
                    <input required type="text" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_make_model" name="make_model">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Default Assigned Driver</label>
                    <select required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" id="edit_user_id" name="user_id">
                        <option value="">Select Driver</option>
                        <?php if ($driversOnlyRes && ($driversOnlyRes['success'] ?? false)) foreach ($driversOnlyRes['users'] as $u) echo "<option value='".htmlspecialchars($u['id'])."'>".htmlspecialchars($u['name'])."</option>"; ?>
                    </select>
                </div>
            </div>
            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                <button type="button" onclick="closeModal('editVehicleModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                <button type="submit" class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg active:scale-95">Save Update</button>
            </div>
        </form>
    </div>
</div>

<div id="editFuelLogModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <form id="updatelog" action="update_fuel_log.php" method="post" enctype="multipart/form-data" class="card-base w-full max-w-2xl overflow-hidden border-none shadow-2xl">
            <div class="section-header !bg-emerald-600 dark:!bg-emerald-900/40">
                <h3><i class="fas fa-gas-pump mr-2 text-emerald-200"></i> Refine Fuel Entry</h3>
                <button type="button" onclick="closeModal('editFuelLogModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8">
                <input type="hidden" id="log_id" name="log_id" required>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Vehicle</label>
                        <select class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white" name="vehicle_id" id="editFuelLogModal_vehicle_id">
                            <?php if ($vRes && ($vRes['success'] ?? false)) foreach ($vRes['vehicles'] as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."'>".htmlspecialchars($v['license_plate'])."</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Driver</label>
                        <select class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white" name="user_id" id="editFuelLogModal_user_id">
                            <?php if ($driversOnlyRes && ($driversOnlyRes['success'] ?? false)) foreach ($driversOnlyRes['users'] as $u) echo "<option value='".htmlspecialchars($u['id'])."'>".htmlspecialchars($u['name'])."</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Date</label><input class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all" type="date" name="date" required></div>
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Start Mi</label><input class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all" type="text" name="start_mileage" required></div>
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Finish Mi</label><input class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all" type="text" name="finish_mileage" required></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <div><label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Fuel (L)</label><input class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 transition-all" type="text" name="fuel_amount" required></div>
                    <div class="pb-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">New Odo/Receipt Photo</label>
                        <input type="file" class="text-[10px] text-gray-500 font-bold uppercase cursor-pointer" name="image_file">
                    </div>
                </div>
                <div class="mt-8 p-6 bg-gray-50 dark:bg-slate-950 rounded-2xl text-center border border-gray-100 dark:border-slate-800">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4">Current Verification Attachment</label>
                    <img id="current_image" class="mx-auto max-h-48 rounded-xl shadow-lg border border-black/5 dark:border-white/5 transition-all" src="uploads/1200px-Jeep_Odometer.jpg">
                </div>
            </div>
            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                <button type="button" onclick="closeModal('editFuelLogModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                <button type="submit" class="flex-1 py-4 bg-emerald-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-lg active:scale-95 flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle text-emerald-200"></i> Update Verified Entry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
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
        const drivers = <?php echo json_encode($uRes['users'] ?? []); ?>;
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
    table.dataTable.no-footer { border-bottom: 1px solid #f3f4f6 !important; }
    .dark table.dataTable.no-footer { border-bottom: 1px solid #1e293b !important; }
</style>

</body>
</html>
