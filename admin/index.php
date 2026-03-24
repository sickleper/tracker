<?php 
$pageTitle = "System Administration";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$isSuperAdmin = isTrackerSuperAdmin();
$isAdminUser = isTrackerAdminUser();
$canUseTenantAdminTools = function_exists('trackerCanUseTenantAdminTools') && trackerCanUseTenantAdminTools();
$canUseSharedMaintenanceTools = function_exists('trackerIsPrimaryApp') && trackerIsPrimaryApp();

if (!$isAdminUser) {
    header('Location: ../index.php');
    exit();
}

include '../header.php';
include '../nav.php';
?>

<div class="admin-shell">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">System Administration</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage users, project categories, and global settings.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <?php if ($isSuperAdmin): ?>
            <a href="profile.php" class="admin-action admin-action-outline-sky">
                <i class="fas fa-id-card"></i> Profile
            </a>
            <a href="api_docs.php" class="admin-action admin-action-outline-info">
                <i class="fas fa-book"></i> API Docs
            </a>
            <a href="client_portals.php" class="admin-action admin-action-outline-info">
                <i class="fas fa-user-shield"></i> Client Portals
            </a>
            <?php if ($canUseTenantAdminTools): ?>
            <a href="tenants.php" class="admin-action admin-action-outline-violet">
                <i class="fas fa-building"></i> Tenants
            </a>
            <?php endif; ?>
            <a href="deploy.php" class="admin-action admin-action-outline-success">
                <i class="fas fa-rocket"></i> Deploy
            </a>
            <?php endif; ?>
            <button id="mainAddBtn" onclick="openAddModal()" class="admin-action admin-action-dark">
                <i class="fas fa-plus-circle text-emerald-400"></i> <span id="addBtnText">Add New Category</span>
            </button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800 overflow-x-auto">
        <nav class="flex -mb-px space-x-8 min-w-max">
            <button data-tab="categories" class="admin-tab-btn border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-project-diagram"></i> Categories & Sync
            </button>
            <button data-tab="users" class="admin-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-users"></i> User Management
            </button>
            <?php if ($isAdminUser): ?>
            <button data-tab="global-settings" class="admin-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-cogs"></i> Global Settings
            </button>
            <?php endif; ?>
            <button data-tab="leave-types" class="admin-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-calendar-alt"></i> Leave Types
            </button>
            <?php if ($canUseSharedMaintenanceTools): ?>
            <button data-tab="maintenance" class="admin-tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-tools"></i> Maintenance
            </button>
            <?php endif; ?>
        </nav>
    </div>

    <div id="tabContents">
        <!-- Tab: Categories -->
        <div id="tab-categories" class="admin-tab-pane space-y-8">
            <div class="admin-panel">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-list-ul text-indigo-400 mr-2"></i> Project Categories
                    </h3>
                    <div class="flex gap-3">
                        <a href="../leads/proposals/template_manager.php" class="admin-action admin-action-sm bg-white/10 text-white shadow-none hover:bg-white/20">
                            <i class="fas fa-file-invoice"></i> Manage Templates
                        </a>
                    </div>
                </div>
                <div class="table-container">
                    <table class="w-full text-sm" id="categoriesTable">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4 text-left">Category Name</th>
                                <th class="px-6 py-4 text-left">Logo</th>
                                <th class="px-6 py-4 text-left">Website</th>
                                <th class="px-6 py-4 text-center">System Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="admin-table-body" id="categoriesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: User Management -->
        <div id="tab-users" class="admin-tab-pane hidden space-y-8">
            <div class="admin-panel">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-users text-indigo-400 mr-2"></i> System Users
                    </h3>
                </div>
                <div class="table-container">
                    <table class="w-full text-sm" id="usersTable">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4 text-left">User</th>
                                <th class="px-6 py-4 text-left">Roles</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="admin-table-body" id="usersBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Global User Settings (Moved from Global Settings) -->
            <form id="userGlobalSettingsForm" class="space-y-6">
                <div id="userSettingsContainer" class="space-y-6">
                    <!-- Loaded via loadGlobalSettings -->
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="admin-action admin-action-primary admin-action-lg">
                        <i class="fas fa-save"></i> Save Callout Configuration
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab: Global Settings -->
        <?php if ($isAdminUser): ?>
        <div id="tab-global-settings" class="admin-tab-pane hidden">
            <?php if ($isSuperAdmin): ?>
            <div class="admin-panel admin-panel-body-lg space-y-6 mb-8">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 flex items-center gap-2">
                            <i class="fas fa-plug text-gray-300"></i> App Connection
                        </h4>
                        <p class="mt-2 text-sm font-semibold text-gray-500 dark:text-slate-400">
                            Local bootstrap settings stored on this tracker server. These are used before Laravel runtime settings load.
                        </p>
                    </div>
                    <div class="admin-chip border-amber-200 bg-amber-50 text-[10px] font-black uppercase tracking-widest text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
                        Superadmin Only
                    </div>
                </div>
                <form id="appBootstrapForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="admin-label">App Name</label>
                            <input type="text" name="app_name" id="bootstrapAppName" class="admin-input" placeholder="Tracker Dublin">
                        </div>
                        <div>
                            <label class="admin-label">Default Tenant</label>
                            <input type="text" name="default_tenant" id="bootstrapDefaultTenant" class="admin-input" placeholder="tenant-slug">
                        </div>
                        <div>
                            <label class="admin-label">Tracker App URL</label>
                            <input type="url" name="app_url" id="bootstrapAppUrl" class="admin-input" placeholder="https://tracker.example.com">
                        </div>
                        <div>
                            <label class="admin-label">Laravel API URL</label>
                            <input type="url" name="laravel_api_url" id="bootstrapLaravelApiUrl" class="admin-input" placeholder="https://api.example.com">
                        </div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-950 px-4 py-4">
                        <input type="hidden" name="is_primary_app" value="0">
                        <label class="flex items-start gap-3 text-sm font-semibold text-gray-700 dark:text-slate-300">
                            <input type="checkbox" name="is_primary_app" id="bootstrapIsPrimaryApp" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="block text-[11px] font-black uppercase tracking-widest text-gray-500 dark:text-slate-400">Primary App</span>
                                <span class="block mt-1">Enable only on the shared admin app. Tenant apps should leave this off and lock to one tenant slug.</span>
                            </span>
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs font-bold text-gray-500 dark:text-slate-400">
                        <div class="admin-chip">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Current Runtime API URL</span>
                            <span id="bootstrapRuntimeApiUrl" class="break-all"><?php echo htmlspecialchars($laravelApiUrl ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="admin-chip">
                            <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Local Storage File</span>
                            <span class="break-all"><?php echo htmlspecialchars(TRACKER_BOOTSTRAP_CONFIG_PATH, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="admin-action admin-action-dark admin-action-lg">
                            <i class="fas fa-save"></i> Save App Connection
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <div id="globalSettingsSubTabs" class="mb-6 flex flex-wrap gap-2">
                <!-- Sub-tabs loaded via AJAX -->
            </div>
            <form id="globalSettingsForm" class="space-y-8">
                <div id="settingsContainer">
                    <!-- Tab Panes loaded via AJAX -->
                </div>
                <div class="pt-8 flex justify-end">
                    <button type="submit" class="admin-action admin-action-primary admin-action-lg">
                        <i class="fas fa-save text-indigo-200"></i> Update Global Configuration
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Tab: Leave Types -->
        <div id="tab-leave-types" class="admin-tab-pane hidden">
            <div class="admin-panel">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-calendar-alt text-indigo-400 mr-2"></i> Manage Leave Types
                    </h3>
                </div>
                <div class="table-container">
                    <table class="w-full text-sm">
                        <thead class="table-header-row">
                            <tr>
                                <th class="px-6 py-4 text-left">Leave Type</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-center">Statutory</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveTypesBody" class="admin-table-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: Maintenance -->
        <?php if ($canUseSharedMaintenanceTools): ?>
        <div id="tab-maintenance" class="admin-tab-pane hidden">
            <div class="card-base border-none p-6 space-y-4">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-4">System Maintenance Tools</h3>
                <div class="rounded-2xl border border-indigo-200 dark:border-indigo-900/40 bg-indigo-50 dark:bg-indigo-950/20 p-4">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-500">Tenant Context</p>
                            <p class="mt-2 text-sm font-bold text-gray-700 dark:text-slate-200">
                                Maintenance calls are locked to the current runtime tenant for this app and cannot be redirected by request data.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-bold text-gray-600 dark:text-slate-300">
                            <div class="rounded-xl border border-white/70 dark:border-slate-800 bg-white dark:bg-slate-950 px-4 py-3">
                                <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Tenant Slug</span>
                                <span id="maintenanceTenantSlug" class="break-all"><?php echo htmlspecialchars(trackerTenantSlug() ?: 'Not resolved', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="rounded-xl border border-white/70 dark:border-slate-800 bg-white dark:bg-slate-950 px-4 py-3">
                                <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Tenant ID</span>
                                <span id="maintenanceTenantId"><?php echo htmlspecialchars((string) ($_SESSION['tenant_id'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <button id="clearCacheBtn" class="admin-action admin-action-primary rounded-xl shadow-md">
                        <i class="fas fa-broom"></i> Clear Application Cache (cache:clear)
                    </button>
                    <button id="clearRouteCacheBtn" class="admin-action bg-blue-600 text-white hover:bg-blue-700 rounded-xl shadow-md">
                        <i class="fas fa-route"></i> Clear Route Cache
                    </button>
                    <button id="clearConfigCacheBtn" class="admin-action bg-purple-600 text-white hover:bg-purple-700 rounded-xl shadow-md">
                        <i class="fas fa-cogs"></i> Clear Config Cache
                    </button>
                    <button id="optimizeClearBtn" class="admin-action admin-action-success rounded-xl shadow-md">
                        <i class="fas fa-magic"></i> Clear All Compiled (optimize:clear)
                    </button>
                    <button id="clearLogsBtn" class="admin-action bg-red-600 text-white hover:bg-red-700 rounded-xl shadow-md">
                        <i class="fas fa-eraser"></i> Clear Application Logs
                    </button>
                    <button id="clearSessionsBtn" class="admin-action bg-orange-600 text-white hover:bg-orange-700 rounded-xl shadow-md">
                        <i class="fas fa-user-clock"></i> Clear Expired Sessions (>7 Days)
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <i class="fas fa-project-diagram text-indigo-200"></i> <span id="modalTitle">Category Settings</span>
                </h3>
                <button onclick="closeModal('categoryModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <form id="categoryForm" class="p-8 space-y-8 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <input type="hidden" name="id" id="editCatId">
                <input type="hidden" id="formAction" value="update_category">
                
                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-4 pb-2 border-b border-gray-100 dark:border-slate-800">1. Basic Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category Name *</label>
                            <input type="text" name="category_name" id="editCatName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Official Website</label>
                            <input type="url" name="website" id="editCatWebsite" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category Email</label>
                            <input type="email" name="email" id="editCatEmail" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                        </div>
                    </div>
                    <div class="mt-6">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Office Address</label>
                        <textarea name="address" id="editCatAddress" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                    </div>
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-4 pb-2 border-b border-gray-100 dark:border-slate-800">2. Proposal Settings</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Default Template</label>
                            <select name="default_proposal_template_id" id="editCatTemplate" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Logo URL</label>
                            <input type="text" name="logo_url" id="editCatLogo" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <div class="mt-3 flex items-center gap-4 rounded-2xl border border-dashed border-gray-200 dark:border-slate-800 bg-gray-50/80 dark:bg-slate-950/60 p-4">
                                <div id="editCatLogoPreviewBox" class="w-16 h-16 rounded-2xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 flex items-center justify-center overflow-hidden shrink-0">
                                    <img id="editCatLogoPreview" src="" alt="Category logo preview" class="hidden w-full h-full object-contain">
                                    <i id="editCatLogoPreviewPlaceholder" class="fas fa-image text-gray-300 dark:text-slate-700 text-xl"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Logo Preview</p>
                                    <p id="editCatLogoPreviewText" class="mt-1 text-xs font-bold text-gray-500 dark:text-slate-400 truncate">No logo selected</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category Brochure (PDF URL)</label>
                            <input type="text" name="brochure_url" id="editCatBrochure" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" placeholder="Link to PDF brochure">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <i class="fas fa-user-plus text-indigo-200"></i> <span id="userModalTitle">Manage User</span>
                </h3>
                <button onclick="closeModal('userModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <form id="userForm" class="p-8 space-y-8 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <input type="hidden" name="id" id="editUserId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Full Name *</label>
                        <input type="text" name="name" id="editUserName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address *</label>
                        <input type="email" name="email" id="editUserEmail" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Mobile Number</label>
                        <input type="text" name="mobile" id="editUserMobile" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Account Status</label>
                        <select name="status" id="editUserStatus" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Password</label>
                        <input type="password" name="password" id="editUserPassword" minlength="8" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Set a new password">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Confirm Password</label>
                        <input type="password" name="password_confirm" id="editUserPasswordConfirm" minlength="8" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Repeat the new password">
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-4 text-xs text-amber-900 dark:text-amber-100">
                    Leave password fields blank when editing a user if you do not want to change their password.
                </div>

                <div>
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-4 pb-2 border-b border-gray-100 dark:border-slate-800">Assign Roles</h4>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_member" id="editUserIsMember" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Worker</span>
                        </div>
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_driver" id="editUserIsDriver" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Driver</span>
                        </div>
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_callout_driver" id="editUserIsCalloutDriver" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Callout</span>
                        </div>
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_office" id="editUserIsOffice" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Office</span>
                        </div>
                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_subcontractor" id="editUserIsSubcontractor" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Sub</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl">Save User Details</button>
            </form>
        </div>
    </div>
</div>

<!-- Milestone Builder Modal -->
<div id="milestoneBuilderModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <div class="flex flex-col">
                    <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                        <i class="fas fa-list-ol text-indigo-200"></i> Milestone Builder
                    </h3>
                    <p id="milestoneBuilderCatName" class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-200/60 mt-0.5 ml-8"></p>
                </div>
                <button onclick="closeModal('milestoneBuilderModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            
            <div class="p-8 flex flex-col gap-8 max-h-[85vh]">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-widest text-gray-400">Template Milestones</h4>
                        <p class="text-[9px] font-medium text-gray-400 mt-1 uppercase">Define the steps automatically added to new projects in this category.</p>
                    </div>
                    <button onclick="addBuilderRow()" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all flex items-center gap-2">
                        <i class="fas fa-plus"></i> Add Step
                    </button>
                </div>

                <div class="flex-grow overflow-y-auto custom-scrollbar pr-2">
                    <form id="milestoneBuilderForm">
                        <input type="hidden" name="category_id" id="builderCatId">
                        <div id="milestoneRowsContainer" class="space-y-3">
                            <!-- Rows loaded via JS -->
                        </div>
                    </form>
                </div>

                <div class="pt-6 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                    <button onclick="closeModal('milestoneBuilderModal')" class="flex-1 py-4 bg-gray-100 dark:bg-slate-800 text-gray-500 rounded-2xl font-black uppercase tracking-widest hover:bg-gray-200 transition-all text-xs">Cancel</button>
                    <button onclick="saveMilestoneTemplate()" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl text-xs flex items-center justify-center gap-3">
                        <i class="fas fa-save text-indigo-200"></i> Save Template Configuration
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    window.laravelApiUrl = <?php echo json_encode($laravelApiUrl ?? ''); ?>;
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.appBootstrapConfig = {};
    window.allClients = [];
    window.isTrackerSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    window.officeSettingsAccessMap = {
        users: null,
        'Business Rules': null,
        localization: null,
        fleet: null,
        finances: null,
        general: null,
        apis: [
            'gmail_workorder_lookback_days',
            'booking_service_radius_km',
            'booking_recommended_max_marginal_cost',
            'booking_near_base_max_distance_km'
        ],
        twilio: [
            'whatsapp_join_number',
            'whatsapp_admin_number',
            'whatsapp_admin_alerts_enabled'
        ]
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeJsString(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    function accessBadgeHtml(level) {
        if (level === 'superadmin') {
            return '<span class="ml-2 inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">Superadmin</span>';
        }
        return '<span class="ml-2 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[8px] font-black uppercase tracking-widest text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300">Office</span>';
    }

    function getSettingAccessLevel(group, key) {
        const map = window.officeSettingsAccessMap || {};
        if (!Object.prototype.hasOwnProperty.call(map, group)) {
            return 'superadmin';
        }

        const allowedKeys = map[group];
        if (allowedKeys === null) {
            return 'office';
        }

        return allowedKeys.includes(key) ? 'office' : 'superadmin';
    }

    function getGroupAccessLevel(group, settings) {
        if (!Array.isArray(settings) || settings.length === 0) {
            return 'office';
        }

        const levels = settings.map(setting => getSettingAccessLevel(group, setting.key));
        return levels.every(level => level === 'office') ? 'office' : 'superadmin';
    }

    function updateCategoryLogoPreview(value) {
        const logoUrl = String(value || '').trim();
        const preview = $('#editCatLogoPreview');
        const placeholder = $('#editCatLogoPreviewPlaceholder');
        const previewText = $('#editCatLogoPreviewText');

        if (!logoUrl) {
            preview.attr('src', '').addClass('hidden');
            placeholder.removeClass('hidden');
            previewText.text('No logo selected');
            return;
        }

        preview.attr('src', logoUrl).removeClass('hidden');
        placeholder.addClass('hidden');
        previewText.text(logoUrl);
    }

    console.log("Admin Panel Script Initialized");

    // --- User Functions ---
    window.loadUsers = function() {
        $('#usersBody').html('<tr><td colspan="4" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Users...</p></div></td></tr>');
        $.ajax({
            url: `${window.laravelApiUrl}/api/users?all=1`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    let html = '';
                    res.users.forEach(u => {
                        let roles = [];
                        if (u.client_details_count > 0) roles.push('<span class="px-2 py-0.5 bg-amber-50 text-amber-600 text-[8px] font-black rounded uppercase border border-amber-100">Client</span>');
                        if (u.is_member) roles.push('<span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[8px] font-black rounded uppercase border border-indigo-100">Worker</span>');
                        if (u.is_driver) roles.push('<span class="px-2 py-0.5 bg-blue-50 text-blue-600 text-[8px] font-black rounded uppercase border border-blue-100">Driver</span>');
                        if (u.is_callout_driver) roles.push('<span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[8px] font-black rounded uppercase border border-emerald-100">Callout</span>');
                        if (u.is_office) roles.push('<span class="px-2 py-0.5 bg-purple-50 text-purple-600 text-[8px] font-black rounded uppercase border border-purple-100">Office</span>');
                        if (u.is_subcontractor) roles.push('<span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[8px] font-black rounded uppercase border border-rose-100">Sub</span>');
                        
                        const statusColor = u.status === 'active' ? 'text-emerald-500' : 'text-red-400';
                        const safeName = escapeHtml(u.name);
                        const safeEmail = escapeHtml(u.email);
                        const safeStatus = escapeHtml(u.status);
                        const safeNameJs = safeJsString(u.name);

                        html += `
                            <tr class="table-row-hover">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900 dark:text-gray-100">${safeName}</div>
                                    <div class="text-[10px] text-gray-400 font-medium">${safeEmail}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">${roles.join('') || '<span class="text-gray-300 italic text-[9px]">No roles</span>'}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-[10px] font-black uppercase tracking-widest ${statusColor}">${safeStatus}</span>
                                </td>
                                <td class="px-6 py-4 text-right flex items-center justify-end gap-3">
                                    <a href="../profile.php?user_id=${u.id}" class="text-sky-600 dark:text-sky-400 font-black uppercase text-[10px] tracking-widest hover:underline transition-all">Profile</a>
                                    <button onclick="editUser(${u.id})" class="text-indigo-600 dark:text-indigo-400 font-black uppercase text-[10px] tracking-widest hover:underline transition-all">Edit</button>
                                    <button onclick="deleteUser(${u.id}, '${safeNameJs}')" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all active:scale-95">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                    $('#usersBody').html(html || '<tr><td colspan="4" class="p-12 text-center text-gray-400">No users found.</td></tr>');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to load users.';
                $('#usersBody').html(`<tr><td colspan="4" class="p-12 text-center text-red-500 font-bold">${escapeHtml(msg)}</td></tr>`);
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'User Load Failed', text: msg });
            }
        });
    }

    window.deleteUser = function(id, name) {
        Swal.fire({
            ...getSwalConfig(),
            title: 'Delete User?',
            text: `Permanently remove user "${name}"? This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete User'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.laravelApiUrl}/api/users/${id}`,
                    type: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ ...getSwalConfig(), icon:'success', title:'Deleted!', timer:1500, showConfirmButton:false });
                            loadUsers();
                        } else {
                            Swal.fire({ ...getSwalConfig(), icon:'error', title:'Cannot Delete', text: res.message || 'Operation failed.' });
                        }
                    },
                    error: (xhr) => {
                        const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Network error';
                        Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error', text: msg });
                    }
                });
            }
        });
    }

    window.openUserModal = function() {
        $('#userForm')[0].reset();
        $('#editUserId').val('');
        $('#userModalTitle').text('Add New System User');
        $('#userModal').removeClass('hidden');
    }

    window.editUser = function(id) {
        $.ajax({
            url: `${window.laravelApiUrl}/api/users/${id}`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    const u = res.user;
                    $('#editUserId').val(u.id);
                    $('#editUserName').val(u.name);
                    $('#editUserEmail').val(u.email);
                    $('#editUserMobile').val(u.mobile);
                    $('#editUserStatus').val(u.status);
                    $('#editUserPassword').val('');
                    $('#editUserPasswordConfirm').val('');
                    $('#editUserIsMember').prop('checked', !!u.is_member);
                    $('#editUserIsDriver').prop('checked', !!u.is_driver);
                    $('#editUserIsCalloutDriver').prop('checked', !!u.is_callout_driver);
                    $('#editUserIsOffice').prop('checked', !!u.is_office);
                    $('#editUserIsSubcontractor').prop('checked', !!u.is_subcontractor);
                    $('#userModalTitle').text('Edit User: ' + u.name);
                    $('#userModal').removeClass('hidden');
                }
            },
            error: function(xhr) {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to load user details.' });
            }
        });
    }

    $('#userForm').submit(function(e) {
        e.preventDefault();
        const id = $('#editUserId').val();
        const method = id ? 'PATCH' : 'POST';
        const url = id ? `${window.laravelApiUrl}/api/users/${id}` : `${window.laravelApiUrl}/api/users/create`;
        const password = $('#editUserPassword').val();
        const passwordConfirm = $('#editUserPasswordConfirm').val();

        if (password || passwordConfirm) {
            if (password.length < 8) {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Password Too Short', text: 'Passwords must be at least 8 characters.' });
                return;
            }

            if (password !== passwordConfirm) {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Passwords Do Not Match', text: 'Confirm the same password before saving.' });
                return;
            }
        }
        
        const data = {
            name: $('#editUserName').val(),
            email: $('#editUserEmail').val(),
            mobile: $('#editUserMobile').val(),
            status: $('#editUserStatus').val(),
            is_member: $('#editUserIsMember').is(':checked') ? 1 : 0,
            is_driver: $('#editUserIsDriver').is(':checked') ? 1 : 0,
            is_callout_driver: $('#editUserIsCalloutDriver').is(':checked') ? 1 : 0,
            is_office: $('#editUserIsOffice').is(':checked') ? 1 : 0,
            is_subcontractor: $('#editUserIsSubcontractor').is(':checked') ? 1 : 0
        };

        if (password) {
            data.password = password;
        }

        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({
            url: url,
            type: method,
            data: JSON.stringify(data),
            contentType: 'application/json',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'User Saved', timer: 1500, showConfirmButton: false });
                    $('#userModal').addClass('hidden');
                    loadUsers();
                } else {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Save failed' });
                }
            },
            error: function(xhr) {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to save user.' });
            },
            complete: () => submitBtn.prop('disabled', false).html('Save User Details')
        });
    });

    // --- Category Functions ---
    window.loadCategories = function() {
        $('#categoriesBody').html('<tr><td colspan="5" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Categories...</p></div></td></tr>');
        $.getJSON('../leads/leads_handler.php?action=get_categories_full&all=1', function(res) {
            if (res.success) {
                let html = '';
                res.data.forEach(cat => {
                    const enabledChecked = cat.is_enabled ? 'checked' : '';
                    const safeCategoryName = escapeHtml(cat.category_name);
                    const safeEmail = escapeHtml(cat.email || 'No email configured');
                    const safeWebsite = escapeHtml(cat.website || '--');
                    const safeLogoUrl = escapeHtml(cat.logo_url || '');
                    const safeCategoryNameJs = safeJsString(cat.category_name);
                    html += `
                        <tr class="table-row-hover">
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-gray-100">${safeCategoryName}</div>
                                <div class="text-[10px] text-gray-400 font-medium">${safeEmail}</div>
                            </td>
                            <td class="px-6 py-4">
                                ${cat.logo_url
                                    ? `<img src="${safeLogoUrl}" alt="${safeCategoryName} logo" class="w-12 h-12 object-contain rounded-xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1">`
                                    : `<div class="w-12 h-12 rounded-xl border border-dashed border-gray-200 dark:border-slate-800 bg-gray-50 dark:bg-slate-900 flex items-center justify-center text-gray-300 dark:text-slate-700"><i class="fas fa-image"></i></div>`
                                }
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">${safeWebsite}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" onchange="toggleCategoryStatus(${cat.id}, this.checked)" ${enabledChecked} class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                    <span class="px-2 py-0.5 ${cat.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400'} text-[8px] font-black rounded uppercase">
                                        ${cat.is_enabled ? 'ENABLED' : 'DISABLED'}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right flex items-center justify-end gap-3">
                                <button onclick="openMilestoneBuilder(${cat.id}, '${safeCategoryNameJs}')" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-indigo-600 hover:text-white transition-all">Milestones</button>
                                <button onclick="editCategory(${cat.id})" class="text-indigo-600 dark:text-indigo-400 font-black uppercase text-[10px] tracking-widest hover:underline transition-all">Configure</button>
                                <button onclick="deleteCategory(${cat.id}, '${safeCategoryNameJs}')" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all active:scale-95">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                $('#categoriesBody').html(html);
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to load categories.';
            $('#categoriesBody').html(`<tr><td colspan="5" class="p-12 text-center text-red-500 font-bold">${escapeHtml(msg)}</td></tr>`);
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Category Load Failed', text: msg });
        });
    }

    window.toggleCategoryStatus = function(id, isEnabled) {
        $.post('../leads/leads_handler.php', { 
            action: 'update_category', 
            id: id, 
            is_enabled: isEnabled ? 1 : 0,
            is_email_sync_enabled: isEnabled ? 1 : 0 // Keep sync in line with enabled status
        }, function(res) {
            if (res.success) {
                Swal.fire({ ...getSwalConfig(), toast:true, position:'top-end', icon:'success', title:'Status Updated', timer:1500, showConfirmButton:false });
                loadCategories();
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Update failed' });
            }
        }, 'json').fail(function() {
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to update category status.' });
        });
    }

    window.deleteCategory = function(id, name, force = false) {
        const title = force ? 'Force Delete Category?' : 'Delete Category?';
        const text = force 
            ? `WARNING: This will also delete all associated milestone templates. Permanently remove "${name}"?`
            : `Permanently remove "${name}"? This will fail if there are linked leads or projects.`;
        
        Swal.fire({
            ...getSwalConfig(),
            title: title,
            text: text,
            icon: force ? 'error' : 'warning',
            showCancelButton: true,
            confirmButtonText: force ? 'Yes, Force Delete' : 'Yes, Delete',
            confirmButtonColor: force ? '#ef4444' : '#6366f1'
        }).then((result) => {
            if (result.isConfirmed) {
                const params = { action: 'delete_category', id: id };
                if (force) params.force = 1;

                $.post('../leads/leads_handler.php', params, function(res) {
                    if (res.success) {
                        Swal.fire({ ...getSwalConfig(), icon:'success', title:'Deleted!', timer:1500, showConfirmButton:false });
                        loadCategories();
                    } else if (res.can_force) {
                        // Show secondary confirmation for force delete
                        Swal.fire({
                            ...getSwalConfig(),
                            icon: 'warning',
                            title: 'Dependencies Found',
                            text: res.message,
                            showCancelButton: true,
                            confirmButtonText: 'Force Delete',
                            cancelButtonText: 'Cancel'
                        }).then((forceResult) => {
                            if (forceResult.isConfirmed) {
                                deleteCategory(id, name, true);
                            }
                        });
                    } else {
                        Swal.fire({ ...getSwalConfig(), icon:'error', title:'Cannot Delete', text: res.message || 'Operation failed.' });
                    }
                }, 'json').fail(function() {
                    Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error', text:'Failed to delete category.' });
                });
            }
        });
    }

    window.openAddModal = function() {
        $('#categoryForm')[0].reset();
        $('#editCatId').val('');
        $('#formAction').val('create_category');
        $('#modalTitle').text('Add New Category');
        updateCategoryLogoPreview('');
        loadTemplates();
        $('#categoryModal').removeClass('hidden');
    }

    window.editCategory = function(id) {
        loadTemplates().then(() => {
            $.getJSON(`../leads/leads_handler.php?action=get_category_details&id=${id}`, function(res) {
                if (res.success) {
                    const cat = res.data;
                    $('#editCatId').val(cat.id);
                    $('#formAction').val('update_category');
                    $('#modalTitle').text('Edit Category Settings');
                    $('#editCatName').val(cat.category_name);
                    $('#editCatWebsite').val(cat.website);
                    $('#editCatAddress').val(cat.address);
                    $('#editCatEmail').val(cat.email);
                    $('#editCatLogo').val(cat.logo_url);
                    $('#editCatBrochure').val(cat.brochure_url);
                    $('#editCatTemplate').val(cat.default_proposal_template_id);
                    updateCategoryLogoPreview(cat.logo_url);
                    $('#categoryModal').removeClass('hidden');
                }
            }).fail(function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to load category details.' });
            });
        });
    }

    $(document).on('input', '#editCatLogo', function() {
        updateCategoryLogoPreview(this.value);
    });

    $(document).on('error', '#editCatLogoPreview', function() {
        $(this).attr('src', '').addClass('hidden');
        $('#editCatLogoPreviewPlaceholder').removeClass('hidden');
        $('#editCatLogoPreviewText').text('Unable to load logo preview');
    });

    $(document).on('load', '#editCatLogoPreview', function() {
        if ($(this).attr('src')) {
            $(this).removeClass('hidden');
            $('#editCatLogoPreviewPlaceholder').addClass('hidden');
        }
    });

    function loadTemplates() {
        return $.getJSON('../leads/leads_handler.php?action=get_templates', function(res) {
            if (res.success) {
                let html = '<option value="">No Default Template</option>';
                res.data.forEach(temp => html += `<option value="${temp.id}">${escapeHtml(temp.name)}</option>`);
                $('#editCatTemplate').html(html);
            }
        }).fail(function() {
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to load proposal templates.' });
        });
    }

    window.loadAppBootstrapSettings = function() {
        $.getJSON('app_bootstrap_handler.php', function(res) {
            if (!res.success) {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Bootstrap Load Failed', text: res.message || 'Unable to load app connection settings.' });
                return;
            }

            const data = res.data || {};
            window.appBootstrapConfig = data;
            $('#bootstrapAppName').val(data.app_name || '');
            $('#bootstrapAppUrl').val(data.app_url || '');
            $('#bootstrapLaravelApiUrl').val(data.laravel_api_url || '');
            $('#bootstrapDefaultTenant').val(data.default_tenant || data.default_tenant_slug || '');
            $('#bootstrapIsPrimaryApp').prop('checked', String(data.is_primary_app || '') === '1' || String(data.is_primary_app || '').toLowerCase() === 'true');
            $('#bootstrapRuntimeApiUrl').text(data.laravel_api_url || window.laravelApiUrl || '');
        }).fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Unable to load app connection settings.';
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Bootstrap Load Failed', text: message });
        });
    }

    $('#appBootstrapForm').submit(function(e) {
        e.preventDefault();

        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        const previousApiUrl = window.laravelApiUrl || '';

        submitBtn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({
            url: 'app_bootstrap_handler.php',
            method: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (!res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Save Failed', text: res.message || 'Unable to save app connection settings.' });
                    return;
                }

                const data = res.data || {};
                window.appBootstrapConfig = data;
                window.laravelApiUrl = data.laravel_api_url || '';
                $('#bootstrapRuntimeApiUrl').text(window.laravelApiUrl || '');

                const apiUrlChanged = previousApiUrl !== (window.laravelApiUrl || '');
                Swal.fire({
                    ...getSwalConfig(),
                    icon: 'success',
                    title: 'App Connection Saved',
                    text: apiUrlChanged ? 'Bootstrap updated. Reloading to apply the new API base URL.' : 'Bootstrap settings updated.',
                    timer: apiUrlChanged ? 1400 : 1800,
                    showConfirmButton: false
                });

                if (apiUrlChanged) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 900);
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Unable to save app connection settings.';
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Save Failed', text: message });
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });

    // --- Global Settings Functions ---
    window.loadGlobalSettings = function() {
        const container = $('#settingsContainer');
        const tabsContainer = $('#globalSettingsSubTabs');
        const settingsIntroHtml = window.isTrackerSuperAdmin
            ? '<div class="mb-6 rounded-2xl border border-indigo-200 dark:border-indigo-900/40 bg-indigo-50 dark:bg-indigo-950/20 px-5 py-4 text-sm font-semibold text-indigo-900 dark:text-indigo-100">Access labels are shown on each settings group and field. <span class="font-black">Office</span> settings are safe for operational admins. <span class="font-black">Superadmin</span> settings are restricted to platform-level control.</div>'
            : '<div class="mb-6 rounded-2xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 px-5 py-4 text-sm font-semibold text-emerald-900 dark:text-emerald-100">You are viewing the office-safe settings subset for this app.</div>';
        container.html(settingsIntroHtml + '<div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Global Settings...</p></div>');
        tabsContainer.empty();
        
        // Ensure we have clients for the pickers
        $.ajax({
            url: `${window.laravelApiUrl}/api/clients/full-list?all=1`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(cRes) {
                if (cRes.success) {
                    window.allClients = cRes.data;
                    
                    $.getJSON('../leads/leads_handler.php?action=get_global_settings', function(res) {
                        if (res.success) {
                            container.html(settingsIntroHtml);
                            tabsContainer.empty();

                            if (!res.data.apis) {
                                res.data.apis = [];
                            }
                            if (!res.data.general) {
                                res.data.general = [];
                            }
                            if (!res.data.users) {
                                res.data.users = [];
                            }

                            let gmailLookbackSetting = null;
                            Object.keys(res.data).forEach(groupKey => {
                                if (!Array.isArray(res.data[groupKey])) {
                                    return;
                                }
                                const remaining = [];
                                res.data[groupKey].forEach(setting => {
                                    if ((setting.key || '') === 'gmail_workorder_lookback_days') {
                                        gmailLookbackSetting = {
                                            ...setting,
                                            group: 'general',
                                            description: setting.description || 'Gmail Work Order Lookback Days'
                                        };
                                        return;
                                    }
                                    remaining.push(setting);
                                });
                                res.data[groupKey] = remaining;
                            });

                            if (!gmailLookbackSetting) {
                                gmailLookbackSetting = {
                                    key: 'gmail_workorder_lookback_days',
                                    value: '2',
                                    group: 'general',
                                    description: 'Gmail Work Order Lookback Days'
                                };
                            }

                            const hasGmailLookbackSetting = res.data.general.some(setting => setting.key === 'gmail_workorder_lookback_days');
                            if (!hasGmailLookbackSetting) {
                                res.data.general.unshift(gmailLookbackSetting);
                            }

                            const hasSettingKey = (key) => Object.values(res.data).some(groupItems =>
                                Array.isArray(groupItems) && groupItems.some(setting => setting.key === key)
                            );

                            const ensureSetting = (group, key, value, description) => {
                                if (!res.data[group]) {
                                    res.data[group] = [];
                                }
                                if (!hasSettingKey(key)) {
                                    res.data[group].push({
                                        key,
                                        value,
                                        group,
                                        description
                                    });
                                }
                            };

                            ensureSetting('apis', 'booking_service_radius_km', '60', 'Booking Service Radius (km)');
                            ensureSetting('apis', 'booking_recommended_max_marginal_cost', '10', 'Booking Recommended Max Marginal Cost');
                            ensureSetting('apis', 'booking_near_base_max_distance_km', '20', 'Booking Near Base Max Distance (km)');
                            ensureSetting('twilio', 'whatsapp_join_number', '', 'WhatsApp Join Number');
                            ensureSetting('twilio', 'whatsapp_admin_number', '', 'Administrative WhatsApp Number');
                            ensureSetting('twilio', 'whatsapp_admin_alerts_enabled', '1', 'Enable Admin WhatsApp Alerts (1=on, 0=off)');
                            ensureSetting('general', 'vehicle_document_reminder_days', '30', 'Vehicle Document Reminder Window (days)');
                            ensureSetting('general', 'vehicle_service_reminder_days', '30', 'Vehicle Service Reminder Window (days)');
                            
                            const groups = Object.keys(res.data);
                            const userSettingsContainer = $('#userSettingsContainer');
                            userSettingsContainer.empty();

                            groups.forEach((group, index) => {
                                if (group === 'users') {
                                    // Handle User Specific Global Settings
                                    let userHtml = `
                                        <div class="card-base p-8 space-y-8 border-none">
                                            <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-6 flex items-center gap-2 border-b border-gray-100 dark:border-slate-800 pb-4">
                                                <i class="fas fa-calendar-alt text-gray-300"></i> Lead Callout Configuration
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">`;
                                    
                                    res.data[group].forEach(setting => {
                                        if (setting.key === 'callout_days') {
                                            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                            const activeDays = (setting.value || '').split(',').map(Number);
                                            
                                            userHtml += `
                                                <div class="col-span-1 md:col-span-2">
                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 ml-1">
                                                        ${escapeHtml(setting.description)}
                                                    </label>
                                                    <div class="flex flex-wrap gap-2">`;
                                            
                                            days.forEach((dayName, dayIdx) => {
                                                const isChecked = activeDays.includes(dayIdx);
                                                userHtml += `
                                                    <label class="flex-1 min-w-[80px] cursor-pointer group">
                                                        <input type="checkbox" name="callout_days_check" value="${dayIdx}" ${isChecked ? 'checked' : ''} class="hidden peer callout-day-cb">
                                                        <div class="p-3 text-center border border-gray-200 dark:border-slate-800 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all 
                                                                    peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600
                                                                    hover:bg-gray-50 dark:hover:bg-slate-800 peer-checked:hover:bg-indigo-700">
                                                            ${dayName.substring(0, 3)}
                                                        </div>
                                                    </label>`;
                                            });
                                            
                                            userHtml += `</div><input type="hidden" name="callout_days" id="user_callout_days_hidden" value="${escapeHtml(setting.value || '')}"></div>`;
                                        } else {
                                            userHtml += `
                                                <div>
                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">
                                                        ${escapeHtml(setting.description || setting.key)}
                                                    </label>
                                                    <input type="text" name="${escapeHtml(setting.key)}" value="${escapeHtml(setting.value || '')}" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                                </div>`;
                                        }
                                    });
                                    userHtml += `</div></div>`;
                                    userSettingsContainer.append(userHtml);
                                    return; // Don't add to main settings tabs
                                }

                                const groupId = `gs-group-${group.replace(/[^a-z0-9]/gi, '-')}`;
                                const groupTitle = group.charAt(0).toUpperCase() + group.slice(1);
                                const groupAccessLevel = getGroupAccessLevel(group, res.data[group]);
                                const groupBadge = accessBadgeHtml(groupAccessLevel);
                                
                                // Create Sub-tab button
                                tabsContainer.append(`
                                    <button type="button" data-gs-tab="${groupId}" class="${GLOBAL_SETTINGS_TAB_BASE_CLASS} ${GLOBAL_SETTINGS_TAB_INACTIVE_CLASS}">
                                        ${escapeHtml(groupTitle)}
                                    </button>
                                `);
                                
                                // Create tab pane
                                let groupHtml = `
                                    <div id="${groupId}" class="gs-tab-pane hidden card-base p-8 space-y-8 animate-fade-in">
                                        <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-6 flex items-center gap-2 border-b border-gray-100 dark:border-slate-800 pb-4">
                                            <i class="fas fa-folder text-gray-300"></i> ${escapeHtml(groupTitle)} Configuration ${groupBadge}
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">`;
                                
                                res.data[group].forEach(setting => {
                                    const accessLevel = getSettingAccessLevel(group, setting.key);
                                    const settingBadge = accessBadgeHtml(accessLevel);
                                    if (setting.key === 'callout_days') {
                                        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                        const activeDays = (setting.value || '').split(',').map(Number);
                                        
                                        let dayPickerHtml = `
                                            <div class="col-span-1 md:col-span-2">
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 ml-1">
                                                    ${escapeHtml(setting.description)} ${settingBadge}
                                                </label>
                                                <div class="flex flex-wrap gap-2">`;
                                        
                                        days.forEach((dayName, dayIdx) => {
                                            const isChecked = activeDays.includes(dayIdx);
                                            dayPickerHtml += `
                                                <label class="flex-1 min-w-[80px] cursor-pointer group">
                                                    <input type="checkbox" name="callout_days_check" value="${dayIdx}" ${isChecked ? 'checked' : ''} class="hidden peer callout-day-cb">
                                                    <div class="p-3 text-center border border-gray-200 dark:border-slate-800 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all 
                                                                peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600
                                                                hover:bg-gray-50 dark:hover:bg-slate-800 peer-checked:hover:bg-indigo-700">
                                                        ${dayName.substring(0, 3)}
                                                    </div>
                                                </label>`;
                                        });
                                        
                                        dayPickerHtml += `</div><input type="hidden" name="callout_days" id="callout_days_hidden" value="${escapeHtml(setting.value || '')}"></div>`;
                                        groupHtml += dayPickerHtml;
                                    } 
                                    else if (['tracker_notify_names', 'tracker_client_names', 'tracker_sub_names'].includes(setting.key)) {
                                        const activeNames = (setting.value || '').split(',').map(s => s.trim());
                                        let clientPickerHtml = `
                                            <div class="col-span-1 md:col-span-2">
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 ml-1">
                                                    ${escapeHtml(setting.description)} ${settingBadge}
                                                </label>
                                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">`;
                                        
                                        window.allClients.forEach(client => {
                                            const isChecked = activeNames.includes(client.name);
                                            clientPickerHtml += `
                                                <label class="cursor-pointer group">
                                                    <input type="checkbox" name="${escapeHtml(setting.key)}_check" value="${escapeHtml(client.name)}" ${isChecked ? 'checked' : ''} class="hidden peer client-name-cb" data-key="${escapeHtml(setting.key)}">
                                                    <div class="p-3 text-center border border-gray-200 dark:border-slate-800 rounded-2xl text-[9px] font-black uppercase tracking-wider transition-all 
                                                                peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600
                                                                hover:bg-gray-50 dark:hover:bg-slate-800 peer-checked:hover:bg-indigo-700 h-full flex items-center justify-center">
                                                        ${escapeHtml(client.name)}
                                                    </div>
                                                </label>`;
                                        });
                                        
                                        clientPickerHtml += `</div><input type="hidden" name="${escapeHtml(setting.key)}" id="${escapeHtml(setting.key)}_hidden" value="${escapeHtml(setting.value || '')}"></div>`;
                                        groupHtml += clientPickerHtml;
                                    }
                                    else if (setting.key === 'whatsapp_admin_alerts_enabled') {
                                        const enabled = String(setting.value ?? '1') === '1';
                                        groupHtml += `
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">
                                                    ${escapeHtml(setting.description || 'Admin WhatsApp Alerts')} ${settingBadge}
                                                </label>
                                                <select name="${escapeHtml(setting.key)}" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                                    <option value="1" ${enabled ? 'selected' : ''}>On</option>
                                                    <option value="0" ${!enabled ? 'selected' : ''}>Off</option>
                                                </select>
                                            </div>`;
                                    }
                                    else {
                                        const numericSettings = new Set([
                                            'gmail_workorder_lookback_days',
                                            'booking_service_radius_km',
                                            'booking_recommended_max_marginal_cost',
                                            'booking_near_base_max_distance_km'
                                        ]);
                                        const isNumberSetting = numericSettings.has(setting.key);
                                        const numericAttributes = setting.key === 'gmail_workorder_lookback_days'
                                            ? 'min="1" max="30" step="1"'
                                            : (isNumberSetting ? 'step="0.1"' : '');
                                        const helperText = setting.key === 'gmail_workorder_lookback_days'
                                            ? '<p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">Controls how many days of Gmail messages the Work Orders inbox scans. Allowed range: 1 to 30 days.</p>'
                                            : '';
                                        groupHtml += `
                                            <div>
                                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">
                                                    ${escapeHtml(setting.description || setting.key)} ${settingBadge}
                                                </label>
                                                <input type="${isNumberSetting ? 'number' : 'text'}" name="${escapeHtml(setting.key)}" value="${escapeHtml(setting.value || '')}" ${numericAttributes} class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                                                ${helperText}
                                            </div>`;
                                    }
                                });
                                
                                groupHtml += `</div></div>`;
                                container.append(groupHtml);
                            });

                            // Attach sub-tab switching listener
                            $('.gs-tab-btn').off('click').on('click', function() {
                                activateGlobalSettingsTab($(this).data('gs-tab'));
                            });
                            activateGlobalSettingsTab(preservedGlobalSettingsTabId);

                            // Attach listener for the name picker checkboxes
                            $(document).off('change', '.client-name-cb').on('change', '.client-name-cb', function() {
                                const key = $(this).data('key');
                                const checked = [];
                                $(`.client-name-cb[data-key="${key}"]:checked`).each(function() {
                                    checked.push($(this).val());
                                });
                                $(`#${key}_hidden`).val(checked.join(','));
                            });
                        }
                    }).fail(function() {
                        container.html('<div class="p-12 text-center text-red-500 font-bold">Failed to load global settings.</div>');
                        Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Settings Load Failed', text: 'Failed to load global settings.' });
                    });
                }
            }
        }).fail(function() {
            container.html('<div class="p-12 text-center text-red-500 font-bold">Failed to load client data.</div>');
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Settings Load Failed', text: 'Failed to load client data.' });
        });
    }

    const GLOBAL_SETTINGS_TAB_BASE_CLASS = 'gs-tab-btn px-5 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all';
    const GLOBAL_SETTINGS_TAB_ACTIVE_CLASS = 'bg-indigo-600 text-white shadow-lg';
    const GLOBAL_SETTINGS_TAB_INACTIVE_CLASS = 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700';
    let preservedGlobalSettingsTabId = null;

    function activateGlobalSettingsTab(tabId) {
        const buttons = $('.gs-tab-btn');
        if (!buttons.length) return;
        const desiredId = tabId && buttons.filter(`[data-gs-tab="${tabId}"]`).length ? tabId : buttons.first().data('gs-tab');
        if (!desiredId) return;
        preservedGlobalSettingsTabId = desiredId;
        buttons.each(function() {
            $(this)
                .removeClass(GLOBAL_SETTINGS_TAB_ACTIVE_CLASS)
                .removeClass(GLOBAL_SETTINGS_TAB_INACTIVE_CLASS)
                .addClass(GLOBAL_SETTINGS_TAB_INACTIVE_CLASS);
        });
        const activeButton = buttons.filter(`[data-gs-tab="${desiredId}"]`);
        activeButton
            .removeClass(GLOBAL_SETTINGS_TAB_INACTIVE_CLASS)
            .addClass(GLOBAL_SETTINGS_TAB_ACTIVE_CLASS);
        $('.gs-tab-pane').addClass('hidden');
        $(`#${desiredId}`).removeClass('hidden');
    }

    $('#globalSettingsForm').submit(function(e) {
        e.preventDefault();
        
        // Sync all hidden fields before submit
        const calloutDays = [];
        $('.callout-day-cb:checked').each(function() { calloutDays.push($(this).val()); });
        $('#callout_days_hidden').val(calloutDays.join(','));

        ['tracker_notify_names', 'tracker_client_names', 'tracker_sub_names'].forEach(key => {
            const checked = [];
            $(`.client-name-cb[data-key="${key}"]:checked`).each(function() { checked.push($(this).val()); });
            $(`#${key}_hidden`).val(checked.join(','));
        });

        const settingsData = {};
        $(this).serializeArray().forEach(item => {
            if (!item.name.endsWith('_check')) {
                settingsData[item.name] = item.value;
            }
        });
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');
        
        $.ajax({
            url: '../leads/leads_handler.php',
            method: 'POST',
            data: { action: 'update_global_settings', ...settingsData },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Settings Saved', text: 'Global configuration updated.', timer: 2000, showConfirmButton: false });
                    loadGlobalSettings();
                } else {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Save failed' });
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to save global settings.' });
            },
            complete: () => submitBtn.prop('disabled', false).html(originalText)
        });
    });

    $('#userGlobalSettingsForm').submit(function(e) {
        e.preventDefault();
        
        // Sync checkboxes for callout days
        const calloutDays = [];
        $(this).find('.callout-day-cb:checked').each(function() { calloutDays.push($(this).val()); });
        $('#user_callout_days_hidden').val(calloutDays.join(','));

        const settingsData = {};
        $(this).serializeArray().forEach(item => {
            if (!item.name.endsWith('_check')) {
                settingsData[item.name] = item.value;
            }
        });
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');
        
        $.ajax({
            url: '../leads/leads_handler.php',
            method: 'POST',
            data: { action: 'update_global_settings', ...settingsData },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Config Updated', text: 'User-specific settings saved.', timer: 1500, showConfirmButton: false });
                    loadGlobalSettings();
                } else {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Save failed' });
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to save user-specific settings.' });
            },
            complete: () => submitBtn.prop('disabled', false).html(originalText)
        });
    });

    // --- Leave Type Functions ---
    window.loadLeaveTypes = function() {
        $('#leaveTypesBody').html('<tr><td colspan="4" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Leave Types...</p></div></td></tr>');
        $.getJSON('../holidays/fetch_leave_types.php', function(res) {
            if (res.success) {
                let html = '';
                res.data.forEach(lt => {
                    const disabled = lt.is_statutory ? 'disabled' : '';
                    const checked = lt.is_enabled ? 'checked' : '';
                    html += `
                        <tr class="table-row-hover">
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">${escapeHtml(lt.type_name)}</td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-1 ${lt.is_enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-400'} text-[9px] font-black rounded-lg uppercase">${lt.is_enabled ? 'ACTIVE' : 'OFF'}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                ${lt.is_statutory ? '<span class="px-2 py-1 bg-blue-100 text-blue-700 text-[9px] font-black rounded-lg uppercase">Yes</span>' : '<span class="text-gray-400 text-xs">No</span>'}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" onchange="toggleLeaveType(${lt.id}, this.checked)" ${checked} ${disabled} class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </td>
                        </tr>`;
                });
                $('#leaveTypesBody').html(html);
            }
        }).fail(function() {
            $('#leaveTypesBody').html('<tr><td colspan="4" class="p-12 text-center text-red-500 font-bold">Failed to load leave types.</td></tr>');
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Leave Type Load Failed', text: 'Failed to load leave types.' });
        });
    }

    window.toggleLeaveType = function(id, isEnabled) {
        $.post('../holidays/update_leave_type_status.php', { id: id, is_enabled: isEnabled ? 1 : 0 }, function(res) {
            if (res.success) {
                Swal.fire({ ...getSwalConfig(), toast:true, position:'top-end', icon:'success', title:'Status Updated', timer:1500, showConfirmButton:false });
                loadLeaveTypes();
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Update failed' });
            }
        }, 'json').fail(function() {
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to update leave type status.' });
        });
    }

    // --- Maintenance Functions ---
    function runMaintenanceAction(action, successTitle) {
        $.post('../admin/maintenance_handler.php', { action }, function(res) {
            if (res.success) {
                Swal.fire({ ...getSwalConfig(), icon: 'success', title: successTitle, text: res.message });
                if (res.tenant_slug) {
                    $('#maintenanceTenantSlug').text(res.tenant_slug);
                }
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Maintenance Failed', text: res.message || 'Request failed.' });
            }
        }, 'json').fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Request failed.';
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Maintenance Failed', text: message });
        });
    }

    window.clearCache = function() {
        runMaintenanceAction('clear_cache', 'Cache Cleared!');
    }
    window.clearLogs = function() {
        runMaintenanceAction('clear_logs', 'Logs Cleared!');
    }
    window.clearSessions = function() {
        runMaintenanceAction('clear_sessions', 'Sessions Cleared!');
    }
    window.clearRouteCache = function() {
        runMaintenanceAction('clear_route_cache', 'Route Cache Cleared!');
    }
    window.clearConfigCache = function() {
        runMaintenanceAction('clear_config_cache', 'Config Cache Cleared!');
    }
    window.optimizeClear = function() {
        runMaintenanceAction('optimize_clear', 'All Compiled Cleared!');
    }

    $('#clearCacheBtn').on('click', clearCache);
    $('#clearLogsBtn').on('click', clearLogs);
    $('#clearRouteCacheBtn').on('click', clearRouteCache);
    $('#clearConfigCacheBtn').on('click', clearConfigCache);
    $('#optimizeClearBtn').on('click', optimizeClear);
    $('#clearSessionsBtn').on('click', clearSessions);

    window.closeModal = function(id) { $(`#${id}`).addClass('hidden'); }

    window.openMilestoneBuilder = function(id, name) {
        $('#builderCatId').val(id);
        $('#milestoneBuilderCatName').text(name);
        $('#milestoneRowsContainer').empty();
        $('#milestoneBuilderModal').removeClass('hidden');

        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/categories/${id}/milestones`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success && res.data.length > 0) {
                    res.data.forEach(m => addBuilderRow(m));
                } else {
                    addBuilderRow(); // Add one empty row
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to load milestone template.' });
                addBuilderRow();
            }
        });
    }

    window.addBuilderRow = function(data = null) {
        const count = $('#milestoneRowsContainer .milestone-row').length;
        const html = `
            <div class="milestone-row flex items-center gap-4 p-4 bg-gray-50 dark:bg-slate-950/50 border border-gray-100 dark:border-slate-800/50 rounded-2xl group transition-all">
                <input type="hidden" name="milestones[${count}][id]" value="${data ? data.id : ''}">
                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 text-indigo-500 font-black text-[10px]">
                    ${count + 1}
                </div>
                <div class="flex-grow">
                    <input type="text" name="milestones[${count}][name]" value="${escapeHtml(data ? data.status_name : '')}" placeholder="Step Name (e.g. Scaffolding Erected)" class="w-full bg-transparent border-none p-0 font-bold text-sm dark:text-white outline-none focus:ring-0">
                </div>
                <div class="w-20">
                    <input type="number" name="milestones[${count}][sort]" value="${escapeHtml(data ? data.sort_order : count + 1)}" class="w-full bg-transparent border-none p-0 text-center font-bold text-xs text-gray-400 outline-none focus:ring-0">
                </div>
                <button type="button" onclick="removeBuilderRow(this)" class="p-2 text-gray-300 hover:text-red-500 transition-all opacity-0 group-hover:opacity-100">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>`;
        $('#milestoneRowsContainer').append(html);
    }

    window.removeBuilderRow = function(btn) {
        $(btn).closest('.milestone-row').remove();
    }

    window.saveMilestoneTemplate = function() {
        const formData = $('#milestoneBuilderForm').serialize();
        const submitBtn = $('#milestoneBuilderModal button[onclick="saveMilestoneTemplate()"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');

        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/categories/milestones`,
            type: 'POST',
            data: formData,
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Template Saved!', text: res.message, timer: 1500, showConfirmButton: false });
                    closeModal('milestoneBuilderModal');
                } else {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message });
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to save milestone template.' });
            },
            complete: () => submitBtn.prop('disabled', false).html('<i class="fas fa-save text-indigo-200"></i> Save Template Configuration')
        });
    }

    // --- UI Helpers ---
    window.getSwalTheme = function() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    window.getSwalConfig = function() {
        const isDark = $('html').hasClass('dark');
        return {
            background: isDark ? '#0f172a' : '#ffffff',
            color: isDark ? '#f8fafc' : '#1e293b',
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#ef4444',
            theme: getSwalTheme()
        };
    }

    // --- Tab Management ---
    window.activateTab = function(tab) {
        $('.admin-tab-btn').removeClass('border-indigo-500 text-indigo-600 dark:text-indigo-400').addClass('border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400');
        $(`.admin-tab-btn[data-tab="${tab}"]`).removeClass('border-transparent text-gray-500').addClass('border-indigo-500 text-indigo-600 dark:text-indigo-400');
        $('.admin-tab-pane').addClass('hidden');
        $(`#tab-${tab}`).removeClass('hidden');

        // Update Add Button
        if (tab === 'users') {
            $('#addBtnText').text('Add New User');
            $('#mainAddBtn').attr('onclick', 'openUserModal()');
        } else {
            $('#addBtnText').text('Add New Category');
            $('#mainAddBtn').attr('onclick', 'openAddModal()');
        }

        if (tab === 'global-settings') {
            loadAppBootstrapSettings();
            loadGlobalSettings();
        }
        if (tab === 'categories') loadCategories();
        if (tab === 'leave-types') loadLeaveTypes();
        if (tab === 'users') {
            loadUsers();
            loadGlobalSettings();
        }
    }

    $('.admin-tab-btn').click(function() {
        const tab = $(this).data('tab');
        window.history.pushState(null, null, `?tab=${tab}`);
        activateTab(tab);
    });

    // Handle initial tab from URL or default to categories
    const urlParams = new URLSearchParams(window.location.search);
    const requestedTab = urlParams.get('tab') || 'categories';
    if (requestedTab === 'client-portals') {
        window.location.replace('client_portals.php');
        return;
    }
    const initialTab = requestedTab;
    activateTab(initialTab);
});
</script>

<?php include '../footer.php'; ?>
