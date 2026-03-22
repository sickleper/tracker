<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    header("Location: ../oauth2callback.php");
    exit();
}

$pageTitle = "Holiday & Leave Management";
include "../header.php";

$isAdmin = isTrackerAdminUser();
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$role_id = (int) ($_SESSION['role_id'] ?? 0);
$isOffice = !empty($_SESSION['is_office']);

// Fetch users via API (team only)
$users = [];
$usersRes = makeApiCall('/api/users', ['team_only' => 1]);
if ($usersRes && ($usersRes['success'] ?? false)) {
    $users = $usersRes['users'] ?? [];
}
$canBookForOthers = count($users) > 1;

if ($user_id <= 0 && !empty($_SESSION['email'])) {
    foreach ($users as $user) {
        if (($user['email'] ?? '') === $_SESSION['email']) {
            $user_id = (int) $user['id'];
            $_SESSION['user_id'] = $user_id;
            break;
        }
    }
}

$currentUserName = $_SESSION['user_name'] ?? 'User';
if ($currentUserName === 'User' && $user_id > 0) {
    foreach ($users as $user) {
        if ((int) ($user['id'] ?? 0) === $user_id) {
            $currentUserName = $user['name'] ?? $currentUserName;
            break;
        }
    }
}

// Fetch leave types via API
$leave_types = [];
$typesRes = makeApiCall('/api/leave-types'); // This now only returns enabled types
if ($typesRes && ($typesRes['success'] ?? false)) {
    $leave_types = $typesRes['data'];
}

// Fetch all public holidays via API
$holidays = [];
$holRes = makeApiCall('/api/holidays');
if ($holRes && ($holRes['success'] ?? false)) {
    $holidays = $holRes['data'];
}

// Fetch leave summary via API (replaces initial leave balances)
$summaryRes = makeApiCall('/api/leaves/summary');
$summaryData = ($summaryRes && ($summaryRes['success'] ?? false)) ? $summaryRes['data'] : [];
?>

<link href='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.css' rel='stylesheet' />

<style>
    .status-pending { color: #f59e0b; font-weight: 900; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
    .status-approved { color: #10b981; font-weight: 900; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
    .status-rejected { color: #ef4444; font-weight: 900; text-transform: uppercase; font-size: 10px; letter-spacing: 0.05em; }
    
    #full-calendar { 
        @apply bg-white dark:bg-slate-900 rounded-2xl p-4;
        min-height: 500px; 
    }
    
    .fc-title { font-size: 11px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .fc-day-grid-event { padding: 2px 4px; border-radius: 6px; margin-bottom: 1px; border: none !important; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .fc-toolbar h2 { @apply text-lg font-black text-gray-800 dark:text-white italic uppercase tracking-tight; }
    .fc-button { @apply bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-200 px-4 py-2 rounded-lg font-bold text-xs hover:bg-gray-50 dark:hover:bg-slate-700 transition-all !important; box-shadow: none !important; background-image: none !important; }
    .fc-state-active { @apply bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700 !important; }
    .fc-day-header { @apply text-[10px] font-black uppercase tracking-widest text-gray-400 py-3 !important; }
    .fc-day-number { @apply p-2 font-bold text-gray-400 !important; }
    .fc-today { background: rgba(79, 70, 229, 0.05) !important; }
    .dark .fc-unthemed td, .dark .fc-unthemed th, .dark .fc-unthemed thead, .dark .fc-unthemed tbody, .dark .fc-unthemed .fc-divider, .dark .fc-unthemed .fc-row, .dark .fc-unthemed .fc-content, .dark .fc-unthemed .fc-popover, .dark .fc-unthemed .fc-list-view, .dark .fc-unthemed .fc-list-heading td {
        border-color: #334155 !important;
    }
    #summary-panel.collapsed .summary-panel-body { display: none; }
    #summary-panel.collapsed .summary-toggle-icon { transform: rotate(-90deg); }
    .summary-scroll { max-height: 320px; overflow-y: auto; }
</style>

<!-- Admin Debug: Email: <?php echo $_SESSION['email'] ?? 'N/A'; ?>, SuperAdmin: <?php echo $superAdminEmail; ?>, Role: <?php echo $role_id ?? 'N/A'; ?>, Office: <?php echo $isOffice ? 'Yes' : 'No'; ?>, isAdmin: <?php echo $isAdmin ? 'Yes' : 'No'; ?>, CanBookForOthers: <?php echo $canBookForOthers ? 'Yes' : 'No'; ?> -->
<body class="bg-gray-50 dark:bg-slate-950 text-gray-900 dark:text-gray-100 font-sans">
<?php include "../nav.php"; ?>

<div class="max-w-full mx-auto px-4 md:px-8 py-8 mt-4">
    <!-- Header Section -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="heading-brand">
                <span>🏖️</span> Holiday & Leave Management
            </h1>
            <p class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Book your time off and view team availability</p>
        </div>
        <div class="flex gap-3">
            <button onclick="fetchSummary(); fetchMyLeaves(); $('#full-calendar').fullCalendar('refetchEvents');" class="btn-secondary py-2 px-4 shadow-none">
                <i class="fas fa-sync-alt"></i> Refresh Data
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8">
        
        <!-- Leave Summary Section -->
        <section id="summary-panel" class="card-base collapsed">
            <div class="section-header">
                <h3>
                    <i class="fas fa-chart-pie text-emerald-400"></i> Team Entitlements
                </h3>
                <div class="flex items-center gap-3">
                    <select id="summary-year" class="bg-white/10 border border-white/20 text-white text-[10px] font-black uppercase rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 outline-none transition-all cursor-pointer">
                        <?php 
                        $curY = date('Y');
                        for($y=$curY-1; $y<=$curY+1; $y++) {
                            echo "<option value='$y' ".($y==$curY ? 'selected':'')." class='text-gray-900'>$y Tracker</option>";
                        }
                        ?>
                    </select>
                    <button type="button" id="toggle-summary-panel" class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white transition-all hover:bg-white/20">
                        <i class="fas fa-chevron-down summary-toggle-icon transition-transform"></i>
                        <span id="summary-toggle-label">Show</span>
                    </button>
                </div>
            </div>
            
            <div class="summary-panel-body border-t border-gray-100 dark:border-slate-800">
                <div class="flex items-center justify-between px-6 py-4 bg-gray-50 dark:bg-slate-900/40 border-b border-gray-100 dark:border-slate-800">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Compact yearly balance overview</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Scroll inside this panel</p>
                </div>
            <div class="table-container summary-scroll">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800 text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <tr>
                            <th class="px-6 py-4">Leave Type</th>
                            <th class="px-6 py-4 text-center">Entitlement</th>
                            <th class="px-6 py-4 text-center">Taken</th>
                            <th class="px-6 py-4 text-center">Pending</th>
                            <th class="px-6 py-4 text-center text-indigo-600 dark:text-indigo-400">Remaining</th>
                        </tr>
                    </thead>
                    <tbody id="summary-table-body" class="divide-y divide-gray-50 dark:divide-slate-800">
                        <!-- Dynamic content via JS -->
                    </tbody>
                </table>
            </div>
            </div>
        </section>

        <!-- Main Workspace: Booking & Calendar -->
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
            
            <!-- Left: Booking Form -->
            <div class="xl:col-span-4 space-y-8">
                <section class="card-base sticky top-24">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-calendar-plus text-indigo-400"></i> Request Time Off
                        </h3>
                    </div>
                    
                    <form id="book-leave-form" class="p-8 space-y-6">
                        <!-- User Selection (Visible for Admin) -->
                        <div id="user-selection-container" class="<?php echo $canBookForOthers ? '' : 'hidden'; ?>">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Book for Member</label>
                            <select name="user_id" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                <option value="<?php echo $user_id; ?>">Myself (<?php echo htmlspecialchars($currentUserName); ?>)</option>
                                <?php foreach($users as $u): ?>
                                    <?php if($u['id'] != $user_id): ?>
                                        <option value="<?php echo $u['id']; ?>">
                                            <?php echo htmlspecialchars($u['name']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Leave Category</label>
                            <select name="leave_type_id" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                <option value="">Select Leave Type...</option>
                                <?php foreach($leave_types as $lt): ?>
                                    <option value="<?php echo $lt['id']; ?>"><?php echo htmlspecialchars($lt['type_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Date(s)</label>
                            <div class="relative group">
                                <input type="text" id="leave_date" name="leave_date" required placeholder="Choose your dates..." class="w-full pl-12 pr-4 py-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white cursor-pointer">
                                <i class="fas fa-calendar-alt absolute left-4 top-1/2 -translate-y-1/2 text-indigo-400 group-focus-within:text-indigo-600 transition-colors"></i>
                            </div>
                            <p class="mt-2 ml-1 text-[10px] font-bold uppercase tracking-widest text-gray-400">Weekends and public holidays are excluded automatically.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Duration</label>
                                <select name="duration" id="duration" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                    <option value="full day">Full Day</option>
                                    <option value="half day">Half Day</option>
                                </select>
                            </div>
                            <div id="half-day-options" class="hidden">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Shift</label>
                                <select name="half_day_type" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                    <option value="morning">Morning</option>
                                    <option value="afternoon">Afternoon</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Reason / Note</label>
                            <textarea name="reason" rows="3" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-medium leading-relaxed dark:text-gray-300" placeholder="Brief explanation..."></textarea>
                        </div>

                        <div id="leave-form-feedback" class="hidden rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-700"></div>

                        <button type="submit" id="submit-leave-button" class="w-full py-5 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-600 transition-all active:scale-[0.98] shadow-2xl flex items-center justify-center gap-3">
                            <i class="fas fa-paper-plane text-emerald-400"></i> Send Request
                        </button>
                    </form>
                </section>
            </div>

            <!-- Right: Calendar & My Requests -->
            <div class="xl:col-span-8 space-y-8">
                <!-- Calendar Card -->
                <section class="card-base p-6">
                    <div id="full-calendar" class="custom-scrollbar"></div>
                </section>

                <!-- All Recent Requests Card -->
                <section class="card-base">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-history text-indigo-400"></i> Request Timeline
                        </h3>
                        <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black text-white uppercase tracking-wider border border-white/20">
                            Verified Log
                        </span>
                    </div>
                    
                    <div class="table-container">
                        <table class="w-full text-sm text-left">
                            <thead>
                                <tr class="table-header-row">
                                    <th class="px-8 py-4">Status</th>
                                    <th class="px-8 py-4 text-left">Team Member</th>
                                    <th class="px-8 py-4 text-left">Date Range</th>
                                    <th class="px-8 py-4 text-left">Type</th>
                                    <th class="px-8 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="my-leaves-table" class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Approving/Rejecting (Admin Only) -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="approveModal">
    <div class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
        <div class="section-header bg-gray-900">
            <h3>🛡️ Manage Request</h3>
            <button onclick="closeModal('approveModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
        </div>
        <div class="p-8">
            <div id="approveModalContent" class="mb-8"></div>
            <div class="space-y-4">
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Admin Resolution Note (Optional)</label>
                <textarea id="admin-note" rows="2" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-medium dark:text-white" placeholder="Reason for approval or rejection..."></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4 mt-8">
                <button onclick="updateStatus('approved')" class="py-4 bg-emerald-600 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-emerald-700 transition-all active:scale-95 shadow-lg shadow-emerald-200/50">Approve</button>
                <button onclick="updateStatus('rejected')" class="py-4 bg-red-600 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition-all active:scale-95 shadow-lg shadow-red-200/50">Reject</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Viewing Details -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="viewDetailsModal">
    <div class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
        <div class="section-header bg-indigo-600">
            <h3>📖 Leave Details</h3>
            <button onclick="closeModal('viewDetailsModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
        </div>
        <div class="p-8">
            <div id="viewDetailsContent"></div>
            <div class="mt-8">
                <button onclick="closeModal('viewDetailsModal')" class="w-full py-4 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-gray-200 dark:hover:bg-slate-700 transition-all active:scale-95">Close Window</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src='https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.10.2/fullcalendar.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    const currentUserId = <?php echo $user_id; ?>;
    const isHolidayAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    const canBookForOthers = <?php echo $canBookForOthers ? 'true' : 'false'; ?>;
    const canApproveLeaves = canBookForOthers || isHolidayAdmin;
    const publicHolidays = <?php echo json_encode($holidays); ?>;
    let selectedLeaveId = null;

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    $(document).ready(function() {
        if (!currentUserId) {
            $('#book-leave-form').html('<div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm font-bold text-red-700">Your user session is incomplete. Refresh the page or sign in again.</div>');
        }

        $('#toggle-summary-panel').on('click', function() {
            const panel = $('#summary-panel');
            panel.toggleClass('collapsed');
            const isCollapsed = panel.hasClass('collapsed');
            $('#summary-toggle-label').text(isCollapsed ? 'Show' : 'Hide');
        });

        flatpickr("#leave_date", {
            mode: "range",
            dateFormat: "Y-m-d",
            minDate: "today",
            disable: [ (date) => (date.getDay() === 0 || date.getDay() === 6), ...publicHolidays.map(h => h.date) ],
            locale: { firstDayOfWeek: 1 }
        });

        $('#duration').change(function() {
            if ($(this).val() === 'half day') $('#half-day-options').removeClass('hidden');
            else $('#half-day-options').addClass('hidden');
        });

        initCalendar();
        fetchSummary();
        fetchMyLeaves();

        $('#summary-year').change(fetchSummary);

        $('#book-leave-form').submit(function(e) {
            e.preventDefault();
            if (!currentUserId) {
                Swal.fire({ icon: 'error', title: 'Session Error', text: 'Your account could not be resolved. Please sign in again.', theme: getSwalTheme() });
                return;
            }

            const dateValue = ($('#leave_date').val() || '').trim();
            const duration = $('#duration').val();
            const reason = ($('textarea[name="reason"]').val() || '').trim();
            const selectedUserId = Number($('select[name="user_id"]').val() || currentUserId);
            const feedback = $('#leave-form-feedback');
            feedback.addClass('hidden').text('');

            if (!dateValue) {
                Swal.fire({ icon: 'error', title: 'Missing Dates', text: 'Select at least one leave date.', theme: getSwalTheme() });
                return;
            }

            if (!reason) {
                Swal.fire({ icon: 'error', title: 'Missing Reason', text: 'Add a short reason or note for this request.', theme: getSwalTheme() });
                return;
            }

            if (!canBookForOthers && selectedUserId !== currentUserId) {
                Swal.fire({ icon: 'error', title: 'Not Allowed', text: 'You can only book leave for yourself.', theme: getSwalTheme() });
                return;
            }

            if (duration === 'half day' && dateValue.includes(' to ')) {
                Swal.fire({ icon: 'error', title: 'Half Day Limit', text: 'Half-day requests must use a single date, not a date range.', theme: getSwalTheme() });
                return;
            }

            const submitButton = $('#submit-leave-button');
            submitButton.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');
            const formData = $(this).serialize();
            Swal.fire({ title: 'Submitting...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), theme: getSwalTheme() });
            $.post('process_book_holiday.php', formData, function(res) {
                if (res.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Requested!', text: res.message, timer: 2000, showConfirmButton: false, theme: getSwalTheme() });
                    $('#book-leave-form')[0].reset();
                    $('#half-day-options').addClass('hidden');
                    feedback.addClass('hidden').text('');
                    fetchSummary(); fetchMyLeaves();
                    $('#full-calendar').fullCalendar('refetchEvents');
                } else {
                    feedback.removeClass('hidden').text(res.message || 'Failed to submit leave request.');
                    Swal.fire({ icon: 'error', title: 'Error!', text: res.message, theme: getSwalTheme() });
                }
            }, 'json').fail(function(xhr) {
                const message = xhr.responseJSON?.message || 'The leave request could not be submitted.';
                feedback.removeClass('hidden').text(message);
                Swal.fire({ icon: 'error', title: 'Request Failed', text: message, theme: getSwalTheme() });
            }).always(function() {
                submitButton.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
            });
        });
    });

    function initCalendar() {
        $('#full-calendar').fullCalendar({
            header: { left: 'prev,next today', center: 'title', right: 'month,agendaWeek,agendaDay' },
            firstDay: 1,
            events: function(start, end, timezone, callback) {
                const events = [];
                publicHolidays.forEach(h => {
                    events.push({ title: '🇮🇪 ' + h.occassion, start: h.date, allDay: true, backgroundColor: '#fef3c7', textColor: '#92400e', className: 'font-bold' });
                });
                $.getJSON('fetch_all_leaves.php', { year: 'all' }, function(lRes) {
                     if(lRes.success || lRes.status === 'success') {
                         lRes.data.forEach(l => {
                             if (l.status === 'rejected') return;
                             const statusColor = l.status === 'approved' ? '#10b981' : '#f59e0b';
                             events.push({ id: l.id, title: l.user_name + ' (' + l.type_name + ')', start: l.leave_date, backgroundColor: statusColor, textColor: '#fff', description: l.reason, status: l.status, user_id: l.user_id });
                         });
                         callback(events);
                     }
                });
            },
            eventClick: function(event) { if (canApproveLeaves && event.user_id) showApproveModal(event); }
        });
    }

    function fetchSummary() {
        const year = $('#summary-year').val();
        $.getJSON('fetch_leave_summary.php', { year: year }, function(res) {
            if (res.success || res.status === 'success') {
                const grouped = {};
                res.data.forEach(row => {
                    if (!grouped[row.user_id]) grouped[row.user_id] = { name: row.user_name, items: [] };
                    grouped[row.user_id].items.push(row);
                });

                let html = '';
                Object.values(grouped).forEach(user => {
                    let totalAnnualLeaveRemaining = 0; // Initialize a new total for Annual Leave
                    html += `<tr class="bg-gray-100/50 dark:bg-slate-900/80"><td colspan="5" class="px-6 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-400"><i class="fas fa-user-ninja mr-2"></i> ${user.name}</td></tr>`;
                    user.items.forEach(row => {
                        // Only add Annual Leave to this new total
                        if (row.type_name === 'Annual Leave') {
                            totalAnnualLeaveRemaining += row.balance;
                        }

                        let balanceClass = 'text-emerald-600 font-black'; // Default good balance
                        if (row.balance <= 0) {
                            balanceClass = 'text-red-600 font-black'; // Zero or negative
                        } else if (row.balance < 3) {
                            balanceClass = 'text-orange-500 font-black'; // Low balance
                        }
                        html += `
                            <tr class="hover:bg-indigo-50/20 dark:hover:bg-indigo-900/10 transition-colors">
                                <td class="px-6 py-4"><span class="px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 text-[9px] font-black rounded uppercase tracking-widest">${row.type_name}</span></td>
                                <td class="px-6 py-4 text-center font-bold text-gray-500 dark:text-gray-400">
                                    <button onclick="editQuota(${row.user_id}, ${row.leave_type_id}, '${row.user_name}', '${row.type_name}', ${row.entitlement})" class="hover:text-indigo-600 transition-colors group flex items-center justify-center gap-1 mx-auto">
                                        <span class="${row.is_custom ? 'text-indigo-600 underline decoration-dotted' : ''}">${row.entitlement}d</span>
                                        <i class="fas fa-edit text-[8px] opacity-0 group-hover:opacity-100"></i>
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-center font-bold text-emerald-600">${row.days_taken}d</td>
                                <td class="px-6 py-4 text-center font-bold text-amber-500">${row.days_pending}d</td>
                                <td class="px-6 py-4 text-center text-base ${balanceClass}">${row.balance}d</td>
                            </tr>`;
                    });
                    
                    html += `<tr class="bg-indigo-50/20 dark:bg-indigo-900/20 font-black">
                                <td class="px-6 py-3 text-right uppercase text-[10px] tracking-widest text-indigo-700 dark:text-indigo-300">Total Annual Leave Remaining</td>
                                <td colspan="4" class="px-6 py-3 text-center text-base text-indigo-700 dark:text-indigo-300">${totalAnnualLeaveRemaining}d</td>
                            </tr>`;

                });
                $('#summary-table-body').html(html || '<tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No entitlement data found.</td></tr>');
            }
        });
    }

    function fetchMyLeaves() {
        $.getJSON('fetch_my_leaves.php', { user_id: canApproveLeaves ? 'all' : currentUserId, year: 'all' }, function(res) {
            if (res.success || res.status === 'success') {
                let html = '';
                res.data.forEach(l => {
                    const statusClass = 'status-' + l.status;
                    let dateDisplay = moment(l.start_date).format('DD MMM YYYY');
                    if (l.start_date !== l.end_date) dateDisplay = moment(l.start_date).format('DD MMM') + ' - ' + moment(l.end_date).format('DD MMM YYYY');
                    const canDelete = canApproveLeaves || (l.user_id === currentUserId && l.status === 'pending');
                    html += `
                        <tr class="hover:bg-gray-50 dark:hover:bg-indigo-900/10 transition-colors">
                            <td class="px-8 py-4"><span class="${statusClass}">${l.status}</span></td>
                            <td class="px-8 py-4"><div class="flex flex-col"><span class="font-bold text-gray-900 dark:text-gray-200">${l.user_name}</span><span class="text-[9px] text-gray-400 font-bold uppercase tracking-widest">${l.days_count} day(s) requested</span></div></td>
                            <td class="px-8 py-4 font-bold text-gray-600 dark:text-gray-400">${dateDisplay}</td>
                            <td class="px-8 py-4 text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase">${l.type_name} <span class="text-gray-400 font-medium lowercase italic ml-1">(${l.duration})</span></td>
                            <td class="px-8 py-4 text-right flex items-center justify-end gap-2">
                                <button onclick="viewLeaveDetails(${JSON.stringify(l).replace(/"/g, '&quot;')})" class="p-2 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition-all shadow-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                ${canApproveLeaves && l.status === 'pending' ? `<button onclick="showApproveModalFromTable(${JSON.stringify(l).replace(/"/g, '&quot;')})" class="p-2 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white rounded-xl transition-all shadow-sm" title="Approve/Reject"><i class="fas fa-check-circle"></i></button>` : ''}
                                ${canDelete ? `<button onclick="deleteLeave(${l.id})" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all" title="Delete Request"><i class="fas fa-trash-alt"></i></button>` : ''}
                            </td>
                        </tr>`;
                });
                $('#my-leaves-table').html(html || '<tr><td colspan="5" class="p-8 text-center text-gray-400 italic">No recent requests.</td></tr>');
            }
        });
    }

    function deleteLeave(id) {
        Swal.fire({ title: 'Cancel Request?', text: "This will remove the leave request.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, remove it', theme: getSwalTheme() }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_leave.php', { leave_id: id }, function(res) {
                    if (res.status === 'success') { Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, theme: getSwalTheme() }); fetchSummary(); fetchMyLeaves(); $('#full-calendar').fullCalendar('refetchEvents'); }
                    else { Swal.fire({ icon: 'error', title: 'Error!', text: res.message, theme: getSwalTheme() }); }
                }, 'json');
            }
        });
    }

    function showApproveModal(event) {
        selectedLeaveId = event.id;
        let html = `
            <div class="p-6 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800">
                <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1 ml-1">Team Member</p>
                <p class="text-xl font-black text-gray-900 dark:text-white italic tracking-tight">${event.title}</p>
                <div class="mt-6 grid grid-cols-2 gap-6">
                    <div><p class="text-[9px] text-gray-400 font-black uppercase tracking-widest ml-1">Requested Date</p><p class="text-sm font-bold text-gray-700 dark:text-gray-300">${moment(event.start).format('DD MMM YYYY')}</p></div>
                    <div><p class="text-[9px] text-gray-400 font-black uppercase tracking-widest ml-1">Current Status</p><p class="status-${event.status}">${event.status}</p></div>
                </div>
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-slate-800">
                    <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest ml-1">Employee Reason</p>
                    <p class="text-sm italic text-gray-600 dark:text-gray-400 leading-relaxed">"${event.description || 'No reason provided'}"</p>
                </div>
            </div>`;
        $('#approveModalContent').html(html); $('#admin-note').val(''); $('#approveModal').removeClass('hidden');
    }

    function updateStatus(status) {
        const note = $('#admin-note').val();
        Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading(), theme: getSwalTheme() });
        $.post('update_leave_status.php', { leave_id: selectedLeaveId, status: status, approve_reason: status === 'approved' ? note : null, reject_reason: status === 'rejected' ? note : null }, function(res) {
            if (res.status === 'success') { Swal.fire({ icon: 'success', title: 'Updated!', text: `Request ${status}.`, timer: 1500, showConfirmButton: false, theme: getSwalTheme() }); closeModal('approveModal'); fetchSummary(); fetchMyLeaves(); $('#full-calendar').fullCalendar('refetchEvents'); }
            else { Swal.fire({ icon: 'error', title: 'Error!', text: res.message, theme: getSwalTheme() }); }
        }, 'json');
    }

    function closeModal(id) { $('#' + id).addClass('hidden'); }

    function viewLeaveDetails(l) {
        let dateDisplay = moment(l.start_date).format('DD MMM YYYY');
        if (l.start_date !== l.end_date) dateDisplay = moment(l.start_date).format('DD MMM') + ' - ' + moment(l.end_date).format('DD MMM YYYY');
        
        const statusColors = {
            'pending': 'text-amber-500 bg-amber-50 dark:bg-amber-950/20',
            'approved': 'text-emerald-500 bg-emerald-50 dark:bg-emerald-950/20',
            'rejected': 'text-red-500 bg-red-50 dark:bg-red-950/20'
        };
        const statusColorClass = statusColors[l.status] || 'text-gray-500 bg-gray-50';

        let html = `
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Team Member</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white italic">${l.user_name}</p>
                    </div>
                    <div class="px-4 py-2 rounded-xl border border-current/20 ${statusColorClass} text-[10px] font-black uppercase tracking-widest">
                        ${l.status}
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 pt-6 border-t border-gray-100 dark:border-slate-800">
                    <div>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Date Range</p>
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">${dateDisplay}</p>
                    </div>
                    <div>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Duration</p>
                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">${l.days_count} day(s) (${l.duration})</p>
                    </div>
                </div>

                <div class="pt-6 border-t border-gray-100 dark:border-slate-800">
                    <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Leave Category</p>
                    <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wide">${l.type_name}</p>
                </div>

                <div class="p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800">
                    <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-2">Employee Note</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed italic">"${l.reason || 'No reason provided.'}"</p>
                </div>

                ${l.admin_note ? `
                <div class="p-4 bg-indigo-50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-100 dark:border-indigo-900/50">
                    <p class="text-[9px] text-indigo-400 font-black uppercase tracking-widest mb-2">Admin Resolution Note</p>
                    <p class="text-sm text-indigo-900 dark:text-indigo-200 leading-relaxed font-medium">"${l.admin_note}"</p>
                </div>` : ''}
            </div>
        `;
        $('#viewDetailsContent').html(html);
        $('#viewDetailsModal').removeClass('hidden');
    }

    function editQuota(userId, typeId, userName, typeName, current) {
        Swal.fire({ title: 'Update Entitlement', text: `Set ${typeName} days for ${userName}`, input: 'number', inputValue: current, showCancelButton: true, confirmButtonText: 'Update', showLoaderOnConfirm: true, theme: getSwalTheme(), preConfirm: (newVal) => { return $.post('update_quota.php', { user_id: userId, leave_type_id: typeId, no_of_leaves: newVal }).then(res => { if (res.status !== 'success') throw new Error(res.message); return res; }); }, allowOutsideClick: () => !Swal.isLoading() }).then((result) => { if (result.isConfirmed) { Swal.fire({ icon: 'success', title: 'Updated!', text: 'Entitlement updated.', timer: 1500, showConfirmButton: false, theme: getSwalTheme() }); fetchSummary(); } }).catch(err => Swal.fire({ icon: 'error', title: 'Error!', text: err.message, theme: getSwalTheme() }));
    }

    function showApproveModalFromTable(l) { showApproveModal({ id: l.id, title: l.user_name + ' (' + l.type_name + ')', start: l.leave_date, status: l.status, description: l.reason, user_id: 1 }); }
</script>

<?php include "../footer.php"; ?>
</body>
</html>
