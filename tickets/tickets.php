<?php
require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// FEATURE GATE: Redirect if module is disabled
if (!featureEnabled('module_tickets_enabled')) {
    header('Location: ../index.php?error=feature_disabled');
    exit();
}

$pageTitle = "Tickets - Management";
include_once "../header.php";
include_once "../nav.php";

// Fetch groups and types for the form
$formGroups = [];
$groupRes = makeApiCall('/api/ticket-groups');
if ($groupRes && ($groupRes['success'] ?? false)) $formGroups = $groupRes['data'];

$formTypes = [];
$typeRes = makeApiCall('/api/ticket-types');
if ($typeRes && ($typeRes['success'] ?? false)) $formTypes = $typeRes['data'];

// Fetch Priorities
$priorities = ['Low', 'Medium', 'High', 'Urgent', 'Emergency'];
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Header & Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand italic">Helpdesk & Support</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage technical issues, maintenance requests, and internal tickets.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button onclick="openCreateTicketModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-[0.2em] transition-all shadow-xl active:scale-95 flex items-center gap-3">
                <i class="fas fa-plus-circle text-indigo-200"></i> New Support Ticket
            </button>
        </div>
    </div>

    <!-- Stats & Filters Container -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
        
        <!-- Left: Quick Filters -->
        <div class="lg:col-span-3 space-y-6">
            <div class="card-base p-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-6 flex items-center gap-2">
                    <i class="fas fa-filter text-indigo-500"></i> Filter Overview
                </h3>
                    <div class="space-y-2">
                        <button id="tab-active" onclick="filterTickets('active')" data-filter="active" class="ticket-filter-btn w-full flex items-center justify-between p-4 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest bg-indigo-50 text-indigo-700 border-2 border-indigo-100 shadow-sm">
                            <span>Active Tickets</span>
                            <i class="fas fa-chevron-right text-[8px] opacity-50"></i>
                        </button>
                        <button id="tab-all" onclick="filterTickets('all')" data-filter="all" class="ticket-filter-btn w-full flex items-center justify-between p-4 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest text-gray-500 hover:bg-gray-50 border-2 border-transparent">
                            <span>All History</span>
                            <i class="fas fa-history text-[8px] opacity-50"></i>
                        </button>
                        <button id="tab-closed" onclick="filterTickets('closed')" data-filter="closed" class="ticket-filter-btn w-full flex items-center justify-between p-4 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest text-gray-500 hover:bg-gray-50 border-2 border-transparent">
                            <span>Resolved</span>
                            <i class="fas fa-check-circle text-[8px] opacity-50"></i>
                        </button>
                </div>
            </div>

            <!-- Stats Mini Card -->
            <div class="card-base p-6 bg-slate-900 border-none text-white overflow-hidden relative group">
                <div class="relative z-10">
                    <div class="text-[9px] font-black uppercase tracking-[0.3em] text-indigo-400 mb-4">SLA Performance</div>
                    <div class="flex items-end gap-3 mb-2">
                        <span class="text-4xl font-black italic tracking-tighter">98%</span>
                        <span class="text-[10px] font-bold text-emerald-400 mb-1.5 uppercase tracking-widest">+2.4%</span>
                    </div>
                    <p class="text-[10px] text-gray-400 font-medium leading-relaxed">Average resolution time currently under 4.5 hours per request.</p>
                </div>
                <i class="fas fa-bolt absolute -bottom-4 -right-4 text-8xl text-white/5 group-hover:rotate-12 transition-transform duration-500"></i>
            </div>
        </div>

        <!-- Right: Main Ticket Registry -->
        <div class="lg:col-span-9">
            <div id="ticket-table-container" class="card-base border-none overflow-hidden">
                <div class="p-12 text-center">
                    <i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500 mb-4"></i>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Synchronizing registry data...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div id="createTicketModal" class="fixed inset-0 z-[150] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="card-base w-full max-w-3xl overflow-hidden border-none shadow-2xl">
            <div class="section-header !bg-indigo-700">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-white">Raise New Ticket</h3>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-indigo-200">System Support & Technical Request</p>
                    </div>
                </div>
                <button onclick="closeCreateTicketModal()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="createTicketForm" class="p-8 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Subject / Title *</label>
                        <input type="text" name="subject" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" placeholder="Brief summary of the issue...">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Ticket Type *</label>
                        <select name="type_id" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="">Select Category</option>
                            <?php foreach ($formTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project / Group</label>
                        <select name="group_id" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="">General Support (No Group)</option>
                            <?php foreach ($formGroups as $group): ?>
                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Priority Level</label>
                        <select name="priority" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php foreach ($priorities as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo $p === 'Medium' ? 'selected' : ''; ?>><?php echo $p; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Detailed Description *</label>
                    <textarea name="description" rows="5" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" placeholder="Provide as much detail as possible to help us resolve this quickly..."></textarea>
                </div>

                <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4 -mx-8 -mb-8">
                    <button type="button" onclick="closeCreateTicketModal()" class="flex-1 py-4 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Discard</button>
                    <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-[0.2em] hover:bg-emerald-600 transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-3">
                        <i class="fas fa-paper-plane text-indigo-200"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Show Ticket Placeholder (Requires show_ticket.php logic) -->
<div id="ticketDetailModal" class="fixed inset-0 z-[160] hidden overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div id="ticketDetailContent" class="w-full max-w-5xl">
            <!-- Loaded via show_ticket.js -->
        </div>
    </div>
</div>

<script src="tickets.js?v=<?php echo time(); ?>"></script>
<script src="show_ticket.js?v=<?php echo time(); ?>"></script>

<script>
    $(document).ready(function() {
        filterTickets('active');
    });
</script>

<?php include_once "../footer.php"; ?>
