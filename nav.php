<?php
// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$isTickets = (strpos($_SERVER['REQUEST_URI'], '/tickets/') !== false);
$isLeads = (strpos($_SERVER['REQUEST_URI'], '/leads/') !== false);
$isHolidays = (strpos($_SERVER['REQUEST_URI'], '/holidays/') !== false);
$isTimesheets = (strpos($_SERVER['REQUEST_URI'], '/timesheets/') !== false);
$isFuel = (strpos($_SERVER['REQUEST_URI'], '/fuel/') !== false);
$isToolInventory = (strpos($_SERVER['REQUEST_URI'], '/tool_inventory/') !== false);
$isAdmin = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);
$isBackup = (strpos($_SERVER['REQUEST_URI'], '/backup/') !== false);

// Robust Base path calculation based on script's location relative to DOCUMENT_ROOT
$docRoot = $_SERVER['DOCUMENT_ROOT'];
$scriptPath = $_SERVER['SCRIPT_FILENAME'];
$relativePathFromRoot = str_replace($docRoot, '', $scriptPath);
$depth = substr_count(trim($relativePathFromRoot, '/'), '/');
$base = str_repeat('../', $depth);

if (!function_exists('featureEnabled')) {
    function featureEnabled(string $key, bool $default = false): bool
    {
        $value = gs($key, $default ? '1' : '0');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

$moduleFuelEnabled = featureEnabled('module_fuel_enabled');
$moduleToolInventoryEnabled = featureEnabled('module_tool_inventory_enabled');
$moduleHolidaysEnabled = featureEnabled('module_holidays_enabled');
$moduleTimesheetsEnabled = featureEnabled('module_timesheets_enabled');
$moduleTicketsEnabled = featureEnabled('module_tickets_enabled');
?>
<!-- User/System Top Bar -->
<div class="bg-gray-900/50 dark:bg-black/20 backdrop-blur-sm border-b border-white/5 py-2 no-print">
    <div class="max-w-full mx-auto px-4 flex justify-between items-center text-white/70">
        <div class="flex items-center gap-4">
            <a href="<?php echo $base; ?>index.php" class="flex items-center gap-2 no-underline text-white group">
                <div class="bg-white text-indigo-700 p-1 rounded-lg group-hover:scale-110 transition-transform text-xs">
                    <i class="fas fa-tasks"></i>
                </div>
                <span class="font-black italic uppercase tracking-tighter text-sm">Tracker</span>
            </a>
        </div>
        
            <div class="flex items-center gap-6">
            <!-- Theme Toggle -->
            <button onclick="toggleTheme()" class="hover:text-white transition-all outline-none focus:ring-0 text-xs flex items-center gap-2" title="Toggle Dark Mode">
                <i class="fas fa-moon dark:hidden"></i>
                <i class="fas fa-sun hidden dark:inline"></i>
                <span class="hidden sm:inline font-bold uppercase tracking-widest text-[9px]">Appearance</span>
            </button>

            <a href="<?php echo $base; ?>profile.php" class="hidden sm:flex items-center gap-2 hover:text-white transition-all text-xs font-bold uppercase tracking-widest" title="My Profile">
                <i class="fas fa-id-badge"></i>
                <span>Profile</span>
            </a>

            <!-- User Info -->
            <div class="hidden sm:flex items-center gap-2 text-[9px] font-black uppercase tracking-widest">
                <i class="fas fa-user-circle opacity-50"></i>
                <span>Logged in: <span class="text-white"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span></span>
                <?php 
                $isSuperAdmin = isTrackerSuperAdmin();
                
                if ($isSuperAdmin): 
                ?>
                    <span class="ml-1 px-1.5 py-0.5 bg-emerald-500 text-white rounded text-[8px] font-black italic tracking-tighter shadow-sm">SuperAdmin</span>
                <?php endif; ?>
            </div>

            <!-- Logout -->
            <a href="<?php echo $base; ?>logout.php" class="text-red-400 hover:text-red-500 font-black uppercase tracking-widest text-[9px] flex items-center gap-2 transition-colors">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<nav style="background-color: var(--nav-bg);" class="text-white shadow-lg sticky top-0 z-50 transition-colors duration-300">
    <div class="max-w-full mx-auto px-4">
        <div class="flex items-center justify-between h-14">
            <!-- Left: Links -->
            <div class="flex items-center gap-4 lg:gap-8">
                <!-- Desktop Menu -->
                <div class="hidden xl:flex items-center gap-2">
                    <a href="<?php echo $base; ?>index.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo (!$isTickets && !$isLeads && !$isHolidays && !$isBackup && !$isFuel && !$isToolInventory && $currentPage == 'index.php') ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-home mr-1"></i> Dashboard
                    </a>
                    
                    <?php if ($moduleTicketsEnabled): ?>
                    <a href="<?php echo $base; ?>tickets/tickets.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isTickets) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-ticket-alt mr-1"></i> Tickets
                    </a>
                    <?php endif; ?>

                    <a href="<?php echo $base; ?>projects/projects_list.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects/') !== false) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-project-diagram mr-1"></i> Projects
                    </a>

                    <div class="relative group">
                        <button class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/proposals/') !== false) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all flex items-center gap-1">
                            <i class="fas fa-file-invoice-dollar mr-1"></i> Proposals <i class="fas fa-chevron-down text-[8px] opacity-50 group-hover:rotate-180 transition-transform"></i>
                        </button>
                        <div class="absolute left-0 w-64 mt-0 origin-top-left bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-800 divide-y divide-gray-50 dark:divide-slate-800 overflow-hidden opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                            <?php $propBase = $base . 'leads/proposals/'; ?>
                            <a href="<?php echo $propBase; ?>proposals_list.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-list-ul mr-2 opacity-50"></i> Proposals Registry
                            </a>
                    <?php if (isTrackerSuperAdmin()): ?>
                            <a href="<?php echo $propBase; ?>template_manager.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-layer-group mr-2 opacity-50"></i> Template Manager
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Leads Dropdown -->
                    <div class="relative group">
                        <button class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isLeads) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all flex items-center gap-1">
                            <i class="fas fa-bullseye mr-1"></i> Leads <i class="fas fa-chevron-down text-[8px] opacity-50 group-hover:rotate-180 transition-transform"></i>
                        </button>
                        <div class="absolute left-0 w-64 mt-0 origin-top-left bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-800 divide-y divide-gray-50 dark:divide-slate-800 overflow-hidden opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                            <?php $leadBase = $base . 'leads/'; ?>
                            <a href="<?php echo $leadBase; ?>leads.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-list-ul mr-2 opacity-50"></i> Manage Leads
                            </a>
                            <a href="<?php echo $leadBase; ?>email_leads.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-inbox mr-2 opacity-50"></i> Email Leads Inbox
                            </a>
                            <a href="<?php echo $leadBase; ?>leads_callout_map.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-map-marked-alt mr-2 opacity-50"></i> Callout Map
                            </a>
                            <a href="<?php echo $leadBase; ?>leads_callout_printable.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-print mr-2 opacity-50"></i> Printable List
                            </a>
                            <a href="<?php echo $leadBase; ?>leads_booking.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-calendar-plus mr-2 opacity-50"></i> Schedule Console
                            </a>
                            <a href="<?php echo $leadBase; ?>leads_visualize.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                <i class="fas fa-chart-line mr-2 opacity-50"></i> Visualize Schedule
                            </a>
                            <a href="<?php echo $base; ?>public/iframe_code.txt" target="_blank" rel="noopener noreferrer" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                                <i class="fas fa-code mr-2 opacity-50"></i> Public Booking Code
                            </a>
                        </div>
                    </div>

                    <?php if ($moduleFuelEnabled): ?>
                    <a href="<?php echo $base; ?>fuel/index.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isFuel) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-gas-pump mr-1"></i> Fuel & Vehicles
                    </a>
                    <?php endif; ?>

                    <?php if ($moduleToolInventoryEnabled): ?>
                    <a href="<?php echo $base; ?>tool_inventory/index.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isToolInventory) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-tools mr-1"></i> Tool Inventory
                    </a>
                    <?php endif; ?>

                    <?php if ($moduleHolidaysEnabled): ?>
                    <a href="<?php echo $base; ?>holidays/book_holiday.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isHolidays) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-umbrella-beach mr-1"></i> Holidays
                    </a>
                    <?php endif; ?>

                    <?php if ($moduleTimesheetsEnabled): ?>
                    <a href="<?php echo $base; ?>timesheets/index.php" class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isTimesheets) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all">
                        <i class="fas fa-stopwatch mr-1"></i> Timesheets
                    </a>
                    <?php endif; ?>

                    <?php if (isTrackerAdminUser()): ?>
                        <!-- Admin Dropdown -->
                        <div class="relative group">
                            <button class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isAdmin || $isBackup) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all flex items-center gap-1">
                                <i class="fas fa-user-shield mr-1"></i> Admin <i class="fas fa-chevron-down text-[8px] opacity-50 group-hover:rotate-180 transition-transform"></i>
                            </button>
                            <div class="absolute left-0 w-56 mt-0 origin-top-left bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-800 divide-y divide-gray-50 dark:divide-slate-800 overflow-hidden opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <a href="<?php echo $base; ?>admin/index.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-cog mr-2 opacity-50"></i> System Settings
                                </a>
                                <?php if (isTrackerSuperAdmin()): ?>
                                <a href="<?php echo $base; ?>admin/profile.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors">
                                    <i class="fas fa-id-card mr-2 opacity-50"></i> Admin Profile
                                </a>
                                <a href="<?php echo $base; ?>admin/client_portals.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-user-shield mr-2 opacity-50"></i> Client Portals
                                </a>
                                <a href="<?php echo $base; ?>admin/tenants.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-building mr-2 opacity-50"></i> Tenant Manager
                                </a>
                                <a href="<?php echo $base; ?>admin/deploy.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                                    <i class="fas fa-rocket mr-2 opacity-50"></i> Deploy Tracker
                                </a>
                                <a href="<?php echo $base; ?>work_order_workflow.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-clipboard-list mr-2 opacity-50"></i> Work Order Workflow
                                </a>
                                <a href="<?php echo $base; ?>projects/workflow.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-project-diagram mr-2 opacity-50"></i> Project Workflow
                                </a>
                                <a href="<?php echo $base; ?>backup/backup_ui.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-database mr-2 opacity-50"></i> Database Backup
                                </a>
                                <a href="<?php echo $base; ?>admin/twilio.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-phone-volume mr-2 opacity-50"></i> Twilio Routing
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Mobile Toggle -->
            <div class="flex items-center gap-4">
                <div class="relative" id="reminderBellGroup">
                    <button id="notificationBell" type="button" aria-haspopup="true" aria-expanded="false" class="relative text-white hover:text-indigo-200 transition-all p-2 rounded-full bg-white/10 hover:bg-white/20">
                        <i class="fas fa-bell"></i>
                        <span id="notificationCount" class="absolute -top-1 -right-1 text-[9px] bg-amber-400 text-slate-900 font-black rounded-full px-2 hidden">0</span>
                    </button>
                    <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-[320px] sm:w-[600px] bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-800 overflow-hidden z-[100]">
                        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-800/50">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-lg bg-indigo-500 text-white flex items-center justify-center text-xs shadow-lg shadow-indigo-500/20">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div>
                                    <span class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 leading-none">Notifications</span>
                                    <span id="notificationPanelCount" class="text-[9px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">0 Active Reminders</span>
                                </div>
                            </div>
                            <button onclick="dismissAllReminders(event)" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition-colors flex items-center gap-1.5">
                                <i class="fas fa-check-double"></i> Dismiss All
                            </button>
                        </div>
                        <div id="notificationItems" class="max-h-[400px] overflow-y-auto px-2 py-2 divide-y divide-gray-100 dark:divide-slate-800 text-[10px] font-bold">
                            <p class="text-center text-gray-400 py-12">
                                <i class="fas fa-circle-notch fa-spin text-xl mb-3 block text-indigo-400"></i>
                                <span class="uppercase tracking-widest text-[9px]">Syncing reminders…</span>
                            </p>
                        </div>
                        <a href="<?php echo $base; ?>leads/leads.php" class="block text-center text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300 bg-gray-50 dark:bg-slate-950/50 py-3 hover:text-indigo-400 transition-colors border-t border-gray-100 dark:border-slate-800">
                            <i class="fas fa-external-link-alt mr-2 opacity-50"></i> View All Leads
                        </a>
                    </div>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="xl:hidden p-2 rounded-lg bg-indigo-800 text-white focus:outline-none focus:ring-2 focus:ring-white">
                    <i class="fas fa-bars text-xl" id="mobile-menu-icon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Panel -->
    <div id="mobile-menu" class="hidden xl:hidden bg-indigo-800 dark:bg-slate-900 border-t border-indigo-600 dark:border-slate-800 animate-slide-down">
    <div class="px-4 pt-2 pb-6 space-y-1">
            <a href="<?php echo $base; ?>index.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo (!$isTickets && !$isLeads && !$isHolidays && !$isBackup && !$isFuel && !$isToolInventory && $currentPage == 'index.php') ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-home mr-2"></i> Dashboard
            </a>
            
            <?php if ($moduleTicketsEnabled): ?>
            <a href="<?php echo $base; ?>tickets/tickets.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isTickets) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-ticket-alt mr-2"></i> Tickets
            </a>
            <?php endif; ?>

            <a href="<?php echo $base; ?>projects/projects_list.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects/') !== false) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-project-diagram mr-2"></i> Projects
            </a>

            <a href="<?php echo $base; ?>leads/proposals/proposals_list.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/proposals/') !== false) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Proposals
            </a>
            <?php if (isTrackerSuperAdmin()): ?>
                <a href="<?php echo $base; ?>leads/proposals/template_manager.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">
                    <i class="fas fa-layer-group mr-2 opacity-50"></i> Template Manager
                </a>
            <?php endif; ?>

            <!-- Mobile Leads Submenu -->
            <div class="space-y-1 pt-2">
                <div class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-300 dark:text-indigo-400">Lead Management</div>
                <?php $leadBase = $base . 'leads/'; ?>
                <a href="<?php echo $leadBase; ?>leads.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Manage Leads</a>
                <a href="<?php echo $leadBase; ?>email_leads.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Email Leads Inbox</a>
                <a href="<?php echo $leadBase; ?>leads_callout_map.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Callout Map</a>
                <a href="<?php echo $leadBase; ?>leads_callout_printable.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Printable List</a>
                <a href="<?php echo $leadBase; ?>leads_booking.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Schedule Console</a>
                <a href="<?php echo $leadBase; ?>leads_visualize.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Visualize Schedule</a>
            </div>

            <?php if ($moduleFuelEnabled): ?>
            <a href="<?php echo $base; ?>fuel/index.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isFuel) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-gas-pump mr-2"></i> Fuel & Vehicles
            </a>
            <?php endif; ?>

            <?php if ($moduleToolInventoryEnabled): ?>
            <a href="<?php echo $base; ?>tool_inventory/index.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isToolInventory) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-tools mr-2"></i> Tool Inventory
            </a>
            <?php endif; ?>

            <a href="<?php echo $base; ?>profile.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($currentPage == 'profile.php' && !$isAdmin) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-id-badge mr-2"></i> Profile
            </a>

            <?php if ($moduleHolidaysEnabled): ?>
            <a href="<?php echo $base; ?>holidays/book_holiday.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isHolidays) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-umbrella-beach mr-2"></i> Holidays
            </a>
            <?php endif; ?>

            <?php if ($moduleTimesheetsEnabled): ?>
            <a href="<?php echo $base; ?>timesheets/index.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isTimesheets) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-stopwatch mr-2"></i> Timesheets
            </a>
            <?php endif; ?>

            <?php if (isTrackerAdminUser()): ?>
                <!-- Mobile Admin Section -->
                <div class="space-y-1 pt-2">
                    <div class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-300 dark:text-indigo-400">Administration</div>
                    <a href="<?php echo $base; ?>admin/index.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">System Settings</a>
                    <?php if (isTrackerSuperAdmin()): ?>
                    <a href="<?php echo $base; ?>admin/profile.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-sky-200 hover:text-white dark:hover:bg-slate-800">Admin Profile</a>
                    <a href="<?php echo $base; ?>admin/client_portals.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Client Portals</a>
                    <a href="<?php echo $base; ?>admin/tenants.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Tenant Manager</a>
                    <a href="<?php echo $base; ?>admin/deploy.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-emerald-300 hover:text-white dark:hover:bg-slate-800">Deploy Tracker</a>
                    <a href="<?php echo $base; ?>work_order_workflow.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Work Order Workflow</a>
                    <a href="<?php echo $base; ?>projects/workflow.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Project Workflow</a>
                    <a href="<?php echo $base; ?>backup/backup_ui.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Database Backup</a>
                    <a href="<?php echo $base; ?>admin/twilio.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Twilio Routing</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="pt-4 border-t border-indigo-600 dark:border-slate-800 mt-4">
                <a href="<?php echo $base; ?>logout.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest text-red-300 hover:bg-red-600 hover:text-white transition-all">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
    </div>
</div>

<script>
(function() {
    const escapeHtml = (value) => {
        if (typeof value !== 'string') return '';
        return value.replace(/[&<>"']/g, (char) => {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char] || char;
        });
    };
    const bell = document.getElementById('notificationBell');
    const panel = document.getElementById('notificationDropdown');
    const countBadge = document.getElementById('notificationCount');
    const panelCount = document.getElementById('notificationPanelCount');
    const items = document.getElementById('notificationItems');
    if (!bell || !panel || !countBadge || !items || !window.laravelApiUrl) return;

    let dropdownOpen = false;
    let cachedReminders = { general: [], vehicle: [] };
    const reminderStorageKey = 'tracker_reminder_seen_ids';
    let seenReminderKeys = new Set();
    try {
        const saved = JSON.parse(localStorage.getItem(reminderStorageKey) || '[]');
        if (Array.isArray(saved)) {
            saved.forEach(key => { if (key) seenReminderKeys.add(key); });
        }
    } catch (error) {
        seenReminderKeys = new Set();
    }
    const getDismissedVehicleAlerts = () => JSON.parse(localStorage.getItem('dismissed_vehicle_alerts') || '[]');
    const saveDismissedVehicleAlert = (id) => {
        const dismissed = getDismissedVehicleAlerts();
        if (!dismissed.includes(id)) {
            dismissed.push(id);
            localStorage.setItem('dismissed_vehicle_alerts', JSON.stringify(dismissed));
        }
    };

    window.dismissSingleReminder = async function(event, type, id, extra = '') {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (type === 'general') {
            try {
                const response = await fetch(`${window.laravelApiUrl.replace(/\/$/, '')}/api/leads/${id}/followup`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + window.apiToken,
                    },
                });
                if (!response.ok) throw new Error('Delete failed');
            } catch (error) {
                console.error('Dismiss general reminder failed', error);
                return;
            }
        } else if (type === 'vehicle') {
            saveDismissedVehicleAlert(id);
        }

        refreshReminders();
    };

    window.dismissAllReminders = async function(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        // 1. Dismiss all general
        const generalPromises = cachedReminders.general.map(r => 
            fetch(`${window.laravelApiUrl.replace(/\/$/, '')}/api/leads/${r.lead_id}/followup`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + window.apiToken,
                },
            })
        );

        // 2. Dismiss all vehicle
        cachedReminders.vehicle.forEach(r => {
            const alertId = `${r.vehicle_id}_${r.doc_type}_${r.date}`;
            saveDismissedVehicleAlert(alertId);
        });

        if (generalPromises.length > 0) {
            await Promise.allSettled(generalPromises);
        }
        
        refreshReminders();
    };

    function toggleDropdown(state) {
        dropdownOpen = typeof state === 'boolean' ? state : !dropdownOpen;
        panel.classList.toggle('hidden', !dropdownOpen);
        bell.setAttribute('aria-expanded', dropdownOpen ? 'true' : 'false');
    }

    document.addEventListener('click', (event) => {
        if (bell.contains(event.target) || panel.contains(event.target)) {
            return;
        }
        if (dropdownOpen) {
            toggleDropdown(false);
        }
    });

    bell.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleDropdown();
    });

    const vehicleReminderEndpoint = '<?php echo $base; ?>api/vehicle_reminders.php';

    async function fetchGeneralReminders() {
        if (!window.apiToken || !window.laravelApiUrl) {
            return [];
        }

        const endpoint = window.laravelApiUrl.replace(/\/$/, '') + '/api/reminders?limit=10';
        try {
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + window.apiToken,
                },
                cache: 'no-store',
            });
            const payload = await response.json();
            if (!payload.success) {
                console.warn('General reminders API returned false', payload.message);
                return [];
            }
            return payload.data ?? [];
        } catch (error) {
            console.error('General reminder fetch failed', error);
            return [];
        }
    }

    async function fetchVehicleReminders() {
        if (!vehicleReminderEndpoint) {
            return [];
        }

        try {
        const response = await fetch(vehicleReminderEndpoint, { 
            cache: 'no-store',
            credentials: 'same-origin'
        });
        if (!response.ok) {
            return [];
        }
        const payload = await response.json();
        const rawReminders = Array.isArray(payload.reminders) ? payload.reminders : [];
        
        // Filter out dismissed alerts
        const dismissed = getDismissedVehicleAlerts();
        return rawReminders.filter(r => {
            const alertId = `${r.vehicle_id}_${r.doc_type}_${r.date}`;
            return !dismissed.includes(alertId);
        });
        } catch (error) {
            console.error('Vehicle reminder fetch failed', error);
            return [];
        }
    }

    async function refreshReminders() {
        const [generalReminders, vehicleReminders] = await Promise.all([
            fetchGeneralReminders(),
            fetchVehicleReminders(),
        ]);
        cachedReminders = { general: generalReminders, vehicle: vehicleReminders };
        renderReminders(generalReminders, vehicleReminders);
    }

    function renderReminders(reminders, vehicleReminders) {
        const total = (Array.isArray(reminders) ? reminders.length : 0) + (Array.isArray(vehicleReminders) ? vehicleReminders.length : 0);
        if (total === 0) {
            items.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <div class="w-12 h-12 rounded-full bg-gray-50 dark:bg-slate-800/50 flex items-center justify-center mb-3">
                        <i class="fas fa-check text-xl opacity-20"></i>
                    </div>
                    <p class="uppercase tracking-[0.2em] text-[9px] font-black">All Caught Up</p>
                    <p class="text-[8px] font-bold mt-1 opacity-50">No pending reminders at this time.</p>
                </div>
            `;
            countBadge.classList.add('hidden');
            panelCount.textContent = '0 Active Reminders';
            return;
        }

        countBadge.textContent = total;
        countBadge.classList.remove('hidden');
        panelCount.textContent = `${total} Active Reminder${total === 1 ? '' : 's'}`;

        const generalHtml = Array.isArray(reminders) ? reminders.map(reminder => {
            const reminderTime = reminder.reminder_at || reminder.next_follow_up_date;
            const label = reminderTime ? moment(reminderTime).format('ddd HH:mm') : 'Scheduled';
            const leadLabel = reminder.lead_name || reminder.lead_email || 'Lead';
            const isOverdue = reminderTime && moment(reminderTime).isBefore(moment());
            const colorClass = isOverdue ? 'text-red-500' : 'text-indigo-600 dark:text-indigo-400';
            const relative = reminderTime ? moment(reminderTime).fromNow() : '';
            const leadUrl = '<?php echo $base; ?>leads/leads.php?id=' + encodeURIComponent(reminder.lead_id);
            return `
                <div class="notification-item group relative bg-white dark:bg-slate-900 transition-all">
                    <a href="${leadUrl}" class="notification-link block px-4 py-4 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex justify-between items-center gap-2 mb-1.5">
                            <span class="text-[10px] font-black uppercase tracking-[0.1em] ${colorClass} flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                ${label}
                            </span>
                            <span class="text-[8px] font-black uppercase tracking-widest text-gray-400">${relative}</span>
                        </div>
                        <p class="text-xs font-black text-gray-900 dark:text-white truncate pr-8">${escapeHtml(leadLabel)}</p>
                        <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 truncate">${escapeHtml(reminder.lead_email || 'No contact email')}</p>
                    </a>
                    <button type="button" onclick="dismissSingleReminder(event, 'general', '${reminder.lead_id}')" class="notification-dismiss-btn absolute top-2 right-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-full transition-all opacity-0 group-hover:opacity-100" title="Dismiss reminder">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                </div>
            `;
        }).join('') : '';

        const vehicleHtml = renderVehicleReminderItems(vehicleReminders);

        items.innerHTML = `${generalHtml}${vehicleHtml}`;
        trackReminderNotifications(reminders);
    }

    function renderVehicleReminderItems(reminders) {
        if (!Array.isArray(reminders) || reminders.length === 0) {
            return '';
        }

        const heading = `<div class="px-4 pb-2 pt-6 text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500 flex items-center gap-2">
            <i class="fas fa-car-side"></i> Fleet Compliance
        </div>`;
        const body = reminders.map(rem => {
        const docColor = ['Expired', 'Overdue'].includes(rem.status) ? 'text-rose-500' : 'text-amber-500';
            const days = Number(rem.days_until ?? 0);
            const relative = days < 0 ? `${Math.abs(days)}d Overdue` : `in ${days}d`;
            const alertId = `${rem.vehicle_id}_${rem.doc_type}_${rem.date}`;
            const targetUrl = '<?php echo $base; ?>fuel/index.php?vehicle_id=' + encodeURIComponent(rem.vehicle_id ?? '');
            return `
                <div class="notification-item group relative bg-white dark:bg-slate-900 transition-all">
                    <a href="${targetUrl}" class="notification-link block px-4 py-4 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                        <div class="flex justify-between gap-2 items-center mb-1.5">
                            <span class="text-[10px] font-black text-gray-900 dark:text-white bg-gray-100 dark:bg-slate-800 px-2 py-0.5 rounded-md font-mono tracking-tighter">${escapeHtml(rem.license_plate)}</span>
                            <span class="${docColor} text-[9px] font-black uppercase tracking-[0.1em]">${escapeHtml(rem.doc_type)}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">${escapeHtml(rem.status)} • ${escapeHtml(rem.date)}</p>
                            <span class="text-[9px] font-black uppercase tracking-widest ${docColor}">${escapeHtml(relative)}</span>
                        </div>
                    </a>
                    <button type="button" onclick="dismissSingleReminder(event, 'vehicle', '${alertId}')" class="notification-dismiss-btn absolute top-2 right-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-full transition-all opacity-0 group-hover:opacity-100" title="Dismiss reminder">
                        <i class="fas fa-times text-[10px]"></i>
                    </button>
                </div>
            `;
        }).join('');
        return heading + body;
    }

    refreshReminders();
    setInterval(refreshReminders, 60000);

    function reminderLookupKey(reminder) {
        const leadId = reminder.lead_id ?? reminder.id ?? (reminder.lead_email ? reminder.lead_email.replace(/[^a-z0-9]/gi, '') : '');
        if (!leadId) return '';
        const when = reminder.reminder_at ?? reminder.next_follow_up_date ?? '';
        return `${leadId}:${when}`;
    }

    function trackReminderNotifications(reminders) {
        if (!Array.isArray(reminders) || reminders.length === 0) return;
        reminders.forEach(reminder => {
            const key = reminderLookupKey(reminder);
            if (!key || seenReminderKeys.has(key)) return;
            seenReminderKeys.add(key);
        });
        persistSeenReminders();
    }

    function persistSeenReminders() {
        const entries = Array.from(seenReminderKeys);
        if (entries.length > 120) {
            seenReminderKeys = new Set(entries.slice(-120));
        }
        localStorage.setItem(reminderStorageKey, JSON.stringify(Array.from(seenReminderKeys)));
    }

})();
</script>
    </div>
</nav>

<script>
$(document).ready(function() {
    $('#mobile-menu-btn').click(function() {
        $('#mobile-menu').toggleClass('hidden');
        const isHidden = $('#mobile-menu').hasClass('hidden');
        $('#mobile-menu-icon').toggleClass('fa-bars', isHidden).toggleClass('fa-times', !isHidden);
    });
});

function toggleTheme() {
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}
</script>

<style>
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-down {
    animation: slideDown 0.3s ease-out forwards;
}
.notification-item {
    border-radius: 1.25rem;
    border: 1px solid rgba(15, 23, 42, 0.05);
    margin: 0.3rem 0;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}
.notification-item:hover {
    border-color: rgba(79, 70, 229, 0.3);
    box-shadow: 0 20px 45px -30px rgba(79, 70, 229, 0.7);
}
.notification-item .notification-link {
    display: block;
    position: relative;
    padding-right: 3.5rem;
    min-height: 64px;
}
.notification-dismiss-btn {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
}
@media (max-width: 640px) {
    #notificationDropdown {
        width: calc(100vw - 1.5rem);
    }
    .notification-item {
        border-radius: 1rem;
    }
}
</style>
