<?php
require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
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

$email = $_SESSION['email'] ?? '';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="heading-brand">
                <i class="fas fa-ticket-alt text-indigo-600 dark:text-indigo-400 mr-2"></i> Manage Tickets
            </h1>
            <p class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Track internal tasks, feature requests, and system updates</p>
        </div>
        <div class="flex gap-3">
            <button onclick="openCreateTicketModal()" class="px-6 py-3 bg-gray-900 text-white rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-plus-circle text-emerald-400"></i> Create New Ticket
            </button>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8" aria-label="Tabs">
            <button onclick="filterTickets('active')" id="tab-active" class="border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-dot-circle text-emerald-500"></i> Active Queue
            </button>
            <button onclick="filterTickets('closed')" id="tab-closed" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-check-circle text-red-400"></i> Resolved / Closed
            </button>
            <button onclick="filterTickets('archived')" id="tab-archived" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-all">
                <i class="fas fa-archive text-gray-400"></i> Archived History
            </button>
        </nav>
    </div>

    <!-- Ticket List Table -->
    <div id="ticket-table-container">
        <div class="flex justify-center p-20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500"></i></div>
    </div>
</div>

<!-- Ticket Details Modal -->
<div id="ticketModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <span id="ticketModalLabel">Ticket Details</span>
                </h3>
                <button onclick="closeModal('ticketModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <div class="modal-body p-8 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="createTicketModal">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 p-6 flex items-center justify-between text-white">
            <h2 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-plus-circle text-emerald-400"></i> Submit New Support Ticket
            </h2>
            <button type="button" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white" onclick="closeModal('createTicketModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="createTicketForm" class="p-8 space-y-6 overflow-y-auto flex-1 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Group Selection -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Group / Category</label>
                    <select name="category_id" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        <option value="">Select Group...</option>
                        <?php if (is_array($formGroups)): ?>
                            <?php foreach($formGroups as $g): ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['group_name']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Type Selection -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Ticket Type</label>
                    <select name="label_id" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        <option value="">Select Type...</option>
                        <?php if (is_array($formTypes)): ?>
                            <?php foreach($formTypes as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['type']); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <!-- Priority Selection -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Assign Urgency</label>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <?php 
                    $priors = [
                        'Low' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400', 
                        'Medium' => 'bg-gray-50 text-gray-600 dark:bg-slate-800 dark:text-gray-400', 
                        'High' => 'bg-orange-50 text-orange-600 dark:bg-amber-900/20 dark:text-amber-400', 
                        'Urgent' => 'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400'
                    ];
                    foreach($priors as $p => $class): ?>
                        <label class="cursor-pointer group">
                            <input type="radio" name="priority" value="<?php echo strtolower($p); ?>" <?php echo $p === 'Medium' ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="p-4 text-center rounded-2xl border border-gray-200 dark:border-slate-800 <?php echo $class; ?> font-black text-[10px] uppercase tracking-widest peer-checked:ring-2 peer-checked:ring-indigo-500 peer-checked:bg-white dark:peer-checked:bg-slate-800 transition-all shadow-sm">
                                <?php echo $p; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Subject -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Subject / Title</label>
                <input type="text" name="title" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" placeholder="Briefly describe the request...">
            </div>

            <!-- Message -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Full Description</label>
                <textarea name="message" rows="6" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm leading-relaxed dark:text-gray-300" placeholder="Provide as much detail as possible..."></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-600 transition-all active:scale-[0.98] shadow-2xl flex items-center justify-center gap-3">
                    <i class="fas fa-paper-plane text-emerald-400"></i> Dispatch Ticket
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    var userEmail = '<?php echo $email ?>';
</script>
<script src="tickets.js?v=<?php echo time(); ?>"></script>
<script src="show_ticket.js?v=<?php echo time(); ?>"></script>

<script>
    $(document).ready(function() {
        filterTickets('active');
    });
</script>

<?php include_once "../footer.php"; ?>
