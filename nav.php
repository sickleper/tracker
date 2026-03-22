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
                $isSuperAdmin = false;
                $superAdminEmail = trackerSuperAdminEmail();
                if (($_SESSION['email'] ?? '') === $superAdminEmail) {
                    $isSuperAdmin = true;
                } else {
                    // Fallback to role check if session email doesn't match
                    if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) {
                        $isSuperAdmin = true;
                    }
                }
                
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
                            <?php if (trackerSuperAdminEmail() !== '' && ($_SESSION['email'] ?? '') === trackerSuperAdminEmail()): ?>
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

                    <?php 
                    $superAdminEmail = trackerSuperAdminEmail();
                    if (($_SESSION['email'] ?? '') === $superAdminEmail): 
                    ?>
                        <!-- Admin Dropdown -->
                        <div class="relative group">
                            <button class="px-3 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest <?php echo ($isAdmin || $isBackup) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600'; ?> transition-all flex items-center gap-1">
                                <i class="fas fa-user-shield mr-1"></i> Admin <i class="fas fa-chevron-down text-[8px] opacity-50 group-hover:rotate-180 transition-transform"></i>
                            </button>
                            <div class="absolute left-0 w-56 mt-0 origin-top-left bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-100 dark:border-slate-800 divide-y divide-gray-50 dark:divide-slate-800 overflow-hidden opacity-0 scale-95 pointer-events-none group-hover:opacity-100 group-hover:scale-100 group-hover:pointer-events-auto transition-all duration-200 z-50">
                                <a href="<?php echo $base; ?>admin/index.php" class="block px-4 py-3 text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                    <i class="fas fa-cog mr-2 opacity-50"></i> System Settings
                                </a>
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
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Mobile Toggle -->
            <div class="flex items-center gap-4">
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
            
            <a href="<?php echo $base; ?>tickets/tickets.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo ($isTickets) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-ticket-alt mr-2"></i> Tickets
            </a>

            <a href="<?php echo $base; ?>projects/projects_list.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/projects/') !== false) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-project-diagram mr-2"></i> Projects
            </a>

            <a href="<?php echo $base; ?>leads/proposals/proposals_list.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest <?php echo (strpos($_SERVER['REQUEST_URI'], '/proposals/') !== false) ? 'bg-white text-indigo-700 dark:bg-indigo-600' : 'text-indigo-100 hover:bg-indigo-700 dark:hover:bg-slate-800'; ?>">
                <i class="fas fa-file-invoice-dollar mr-2"></i> Proposals
            </a>
            <?php if (trackerSuperAdminEmail() !== '' && ($_SESSION['email'] ?? '') === trackerSuperAdminEmail()): ?>
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

            <?php 
            $superAdminEmail = trackerSuperAdminEmail();
            if (($_SESSION['email'] ?? '') === $superAdminEmail): 
            ?>
                <!-- Mobile Admin Section -->
                <div class="space-y-1 pt-2">
                    <div class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-300 dark:text-indigo-400">Administration</div>
                    <a href="<?php echo $base; ?>admin/index.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">System Settings</a>
                    <a href="<?php echo $base; ?>admin/profile.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-sky-200 hover:text-white dark:hover:bg-slate-800">Admin Profile</a>
                    <a href="<?php echo $base; ?>admin/client_portals.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Client Portals</a>
                    <a href="<?php echo $base; ?>admin/tenants.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Tenant Manager</a>
                    <a href="<?php echo $base; ?>admin/deploy.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-emerald-300 hover:text-white dark:hover:bg-slate-800">Deploy Tracker</a>
                    <a href="<?php echo $base; ?>work_order_workflow.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Work Order Workflow</a>
                    <a href="<?php echo $base; ?>projects/workflow.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Project Workflow</a>
                    <a href="<?php echo $base; ?>backup/backup_ui.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Database Backup</a>
                    <a href="<?php echo $base; ?>admin/twilio.php" class="block px-8 py-2 text-sm font-bold uppercase tracking-widest text-indigo-100 hover:text-white dark:hover:bg-slate-800">Twilio Routing</a>
                </div>
            <?php endif; ?>

            <div class="pt-4 border-t border-indigo-600 dark:border-slate-800 mt-4">
                <a href="<?php echo $base; ?>logout.php" class="block px-4 py-3 rounded-xl text-base font-black uppercase tracking-widest text-red-300 hover:bg-red-600 hover:text-white transition-all">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
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
</style>
