<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$pageTitle = "Lead Management";
include_once "../header.php";
include_once "../nav.php";
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

// Fetch categories for the filter
$categories = [];
$catRes = makeApiCall('/api/leads/categories');
if ($catRes && ($catRes['success'] ?? false)) $categories = $catRes['data'];
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header & Main Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="heading-brand">Lead Management</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Track, convert, and schedule potential projects.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="email_leads.php" class="px-6 py-3 bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-slate-700 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all shadow-sm flex items-center gap-2">
                <i class="fas fa-inbox text-indigo-400"></i> Lead Inbox
            </a>
            <button onclick="openAddLeadModal()" class="px-6 py-3 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-800 dark:hover:bg-indigo-700 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-plus-circle text-emerald-400"></i> Create New Lead
            </button>
        </div>
    </div>

    <!-- View Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="leads.php" class="border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="leads_callout_map.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-map-marked-alt"></i> Callout Map
            </a>
            <a href="leads_booking.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-calendar-alt"></i> Scheduler
            </a>
            <a href="leads_visualize.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-chart-pie"></i> Visualizer
            </a>
            <a href="proposals/proposals_list.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-file-invoice"></i> Proposal Registry
            </a>
        </nav>
    </div>

    <!-- Filters & Search -->
    <div class="card-base p-6 mb-8 border-none">
        <div class="flex flex-col md:flex-row gap-6 items-end">
            <div class="flex-grow w-full">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Search Database</label>
                <div class="relative group">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300 dark:text-gray-600 group-focus-within:text-indigo-500 transition-colors"></i>
                    <input type="text" id="leadSearch" placeholder="Search by name, email, Eircode, or phone..." class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
            </div>
            <div class="w-full md:w-64">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Filter by Brand</label>
                <select id="categoryFilter" class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-800 outline-none transition-all text-sm font-bold dark:text-white">
                    <option value="">All Brands</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-shrink-0">
                <button onclick="refreshLeads()" class="h-[46px] w-[46px] flex items-center justify-center bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="Refresh Data">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-database text-indigo-400"></i> Active Leads (<span id="totalLeadsCount">--</span>)
            </h3>
        </div>
        <div class="table-container">
            <table id="leadsTable" class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Request Date / Details</th>
                        <th class="px-6 py-4 text-left">Client Details</th>
                        <th class="px-6 py-4 text-left">Contact</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-center">Brand</th>
                        <th class="px-6 py-4 text-center">Follow Up</th>
                        <th class="px-6 py-4 text-center">Actions</th>
                        <th class="px-6 py-4 text-center">Reminder</th>
                        <th class="px-6 py-4 text-center">Urgency</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                    <!-- DataTables populates this -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Lead Detail/Edit Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="leadModal">
    <div class="bg-white dark:bg-slate-900 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white">
            <h5 id="leadModalLabel" class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-bullseye text-indigo-400"></i> Lead Details
            </h5>
            <button type="button" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white" onclick="closeLeadModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar" id="leadModalBody">
            <!-- Form will be here -->
        </div>
    </div>
</div>

<!-- Follow Up Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="followUpModal">
    <div class="bg-white dark:bg-slate-900 w-full max-w-2xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <form id="followUpForm" class="flex flex-col overflow-hidden h-full">
            <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white shrink-0">
                <h5 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                    <i class="fas fa-calendar-alt text-indigo-400"></i> Schedule Follow Up
                </h5>
                <button type="button" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white" onclick="closeFollowUpModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-8 space-y-6 overflow-y-auto flex-1 custom-scrollbar">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1 block">Lead Name</span>
                    <h3 id="followUpLeadName" class="text-2xl font-black text-gray-900 dark:text-white truncate italic tracking-tight">--</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Follow Up Date & Time</label>
                        <input type="text" id="followUpDateTime" name="next_follow_up_date" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Send Reminder</label>
                        <select name="send_reminder" id="sendReminderSelect" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                            <option value="no">No Reminder</option>
                            <option value="yes">Yes, Notify Me</option>
                        </select>
                    </div>
                </div>

                <div id="reminderOptions" class="hidden grid grid-cols-2 gap-6 p-6 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-900/50">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-2 ml-1">Remind Before</label>
                        <input type="number" name="remind_time" value="1" min="1" class="w-full p-4 bg-white dark:bg-slate-950 border border-indigo-200 dark:border-indigo-800 rounded-xl outline-none text-sm font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-2 ml-1">Unit</label>
                        <select name="remind_type" class="w-full p-4 bg-white dark:bg-slate-950 border border-indigo-200 dark:border-indigo-800 rounded-xl outline-none text-sm font-bold focus:ring-2 focus:ring-indigo-500 dark:text-white">
                            <option value="hour">Hour(s)</option>
                            <option value="day">Day(s)</option>
                            <option value="minute(s)">Minute(s)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Internal Remarks</label>
                    <textarea name="remark" id="followUpRemark" rows="4" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm leading-relaxed dark:text-gray-300" placeholder="Notes about this follow-up..."></textarea>
                </div>

                <input type="hidden" name="lead_id" id="followUpLeadId">
            </div>

            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex justify-between items-center">
                <button type="button" onclick="clearFollowUp()" class="text-red-500 font-black text-xs uppercase tracking-widest hover:underline flex items-center gap-2 hover:bg-red-50 dark:hover:bg-red-900/30 px-4 py-2 rounded-xl transition-all">
                    <i class="fas fa-trash-alt"></i> Clear Follow Up
                </button>
                <div class="flex gap-3">
                    <button type="button" onclick="closeFollowUpModal()" class="px-6 py-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-gray-300 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-100 dark:hover:bg-slate-700 transition-all">Cancel</button>
                    <button type="submit" class="px-8 py-3 bg-gray-900 dark:bg-indigo-600 text-white rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-800 dark:hover:bg-indigo-700 transition-all shadow-lg flex items-center gap-2">
                        <i class="fas fa-save text-emerald-400"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-0 md:p-4 hidden" id="summary-modal">
    <div class="w-full max-w-2xl h-full md:h-[95vh] flex flex-col relative bg-white dark:bg-slate-900 md:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <button onclick="closeSummary()" class="absolute top-4 right-4 z-[100] w-10 h-10 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center transition-all shadow-lg backdrop-blur-sm">
            <i class="fas fa-times"></i>
        </button>
        <div class="flex-1 w-full h-full overflow-y-auto custom-scrollbar" id="summary-content">
            <!-- Content will be loaded here via AJAX -->
        </div>
    </div>
</div>

<script>
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.leadCategories = <?php echo json_encode($categories); ?>;
</script>
<script src="leads.js?v=<?php echo time(); ?>"></script>

<?php include_once "../footer.php"; ?>
