<?php
require_once __DIR__ . '/../config.php';

if (!isTrackerAuthenticated()) {
    header("Location: ../oauth2callback.php");
    exit();
}

$pageTitle = "Monthly Timesheet Report";
include "../header.php";
require_once __DIR__ . '/../tracker_data.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: ../oauth2callback.php");
    exit();
}

// Fetch users via API (members only, no subcontractors)
$users = [];
$usersRes = makeApiCall('/api/users', ['members_only' => 1]); 
if ($usersRes && ($usersRes['success'] ?? false)) {
    // Filter out subcontractors manually if the API didn't
    $users = array_filter($usersRes['users'] ?? [], function($u) {
        return empty($u['is_subcontractor']);
    });
}

// Check current user status (Active Clock In)
$currentStatus = null;
$today = date('Y-m-d');
$attendancesRes = makeApiCall('/api/attendances', [
    'user_id' => $user_id,
    'start_date' => $today . ' 00:00:00',
    'end_date' => $today . ' 23:59:59'
]);

if ($attendancesRes && ($attendancesRes['success'] ?? false)) {
    $todayAttendances = $attendancesRes['data'] ?? [];
    foreach ($todayAttendances as $att) {
        if (empty($att['clock_out_time']) && ($att['type'] ?? 'attendance') === 'attendance') {
            $currentStatus = 'clocked_in';
            break;
        }
    }
}
?>

<!-- Calendar Specific CSS -->
<style>
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); border-top: 1px solid rgba(0,0,0,0.05); border-left: 1px solid rgba(0,0,0,0.05); }
    .dark .calendar-grid { border-top-color: rgba(255,255,255,0.05); border-left-color: rgba(255,255,255,0.05); }
    .calendar-day { min-height: 120px; transition: all 0.2s ease; border-right: 1px solid rgba(0,0,0,0.05); border-bottom: 1px solid rgba(0,0,0,0.05); }
    .dark .calendar-day { border-right-color: rgba(255,255,255,0.05); border-bottom-color: rgba(255,255,255,0.05); }
    .calendar-day:hover { background-color: rgba(79, 70, 229, 0.03); }
    .day-num { font-size: 10px; font-weight: 900; margin-bottom: 4px; display: block; }
    .entry-pill { font-size: 8px; font-weight: 900; text-transform: uppercase; padding: 2px 4px; border-radius: 4px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-width: 1px; cursor: pointer; }
    @media print { .no-print { display: none !important; } body { background: white !important; } .card-base { border: 1px solid #eee !important; box-shadow: none !important; } }
</style>

<div class="bg-gray-50 dark:bg-slate-950 text-gray-900 dark:text-gray-100 font-sans min-h-screen pb-12 transition-colors duration-300">
    <?php include "../nav.php"; ?>

    <div class="max-w-full mx-auto px-4 md:px-8 py-8 mt-4">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 no-print">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200 dark:shadow-none">
                    <i class="fas fa-calendar-alt text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="heading-brand">Monthly Timesheet</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-[10px] font-black uppercase tracking-[0.2em] mt-1">Attendance & Leave Calendar</p>
                </div>
            </div>
            <div class="flex gap-3">
                <?php if ($currentStatus === 'clocked_in'): ?>
                    <button onclick="clockOut()" class="py-3 px-6 bg-red-600 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-red-700 transition-all active:scale-95 shadow-lg flex items-center gap-2">
                        <i class="fas fa-stopwatch"></i> Clock Out
                    </button>
                <?php else: ?>
                    <button onclick="showClockInModal()" class="py-3 px-6 bg-emerald-600 text-white rounded-2xl font-black uppercase text-xs tracking-widest hover:bg-emerald-700 transition-all active:scale-95 shadow-lg flex items-center gap-2">
                        <i class="fas fa-play"></i> Clock In
                    </button>
                <?php endif; ?>
                <button onclick="fetchAttendances()" class="btn-secondary py-3 px-4 shadow-none rounded-2xl bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-800">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <div class="lg:col-span-3 space-y-6 no-print">
                <section class="card-base border-none overflow-hidden">
                    <div class="bg-gray-900 dark:bg-black px-6 py-4 flex items-center justify-between">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-white italic"><i class="fas fa-filter text-indigo-400 mr-2"></i> Selection</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 block ml-1">Team Member</label>
                            <select id="filter-user" onchange="fetchAttendances()" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                <option value="all">Team Overview (List)</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo ($u['id'] == $user_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 block ml-1">Select Month</label>
                            <input type="text" id="filter-month" value="<?php echo date('Y-m'); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white cursor-pointer" placeholder="Select Month">
                        </div>
                    </div>
                </section>

                <div class="card-base p-8 border-none transition-all hover:shadow-md">
                    <div class="space-y-6">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block italic">Working Hours</span>
                            <span id="stat-total-hours" class="text-4xl font-black text-gray-900 dark:text-white italic tracking-tighter">0.00</span>
                        </div>
                        <div class="pt-4 border-t border-gray-50 dark:border-slate-800">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block italic">Approved Leave</span>
                            <span id="stat-leave-days" class="text-2xl font-black text-amber-500 italic tracking-tighter">0 Days</span>
                        </div>
                    </div>
                </div>
                <button onclick="window.print()" class="w-full py-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-900 dark:text-gray-300 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-50 transition-all flex items-center justify-center gap-2 shadow-sm">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <div class="lg:col-span-9">
                <section class="card-base border-none overflow-hidden min-h-[650px]">
                    <div class="bg-gray-900 dark:bg-black px-8 py-5 flex items-center justify-between no-print">
                        <h3 class="text-[11px] font-black uppercase tracking-[0.2em] text-white italic" id="view-title">Monthly View</h3>
                        <div class="flex gap-2" id="view-toggle-container">
                            <button onclick="changeView('calendar')" id="btn-view-calendar" class="view-btn px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Calendar</button>
                            <button onclick="changeView('report')" id="btn-view-report" class="view-btn px-3 py-1.5 bg-white/10 text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition-all">Daily List</button>
                        </div>
                    </div>
                    <div id="timesheet-view-container" class="p-0"></div>
                </section>
            </div>
        </div>
    </div>
</div>

<!-- Clock In Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden no-print" id="clockInModal">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-gray-900 px-8 py-6 flex items-center justify-between text-white">
                <h3 class="text-white font-black uppercase italic tracking-wider">🏁 Start Your Shift</h3>
                <button onclick="closeModal('clockInModal')" class="w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8 space-y-6">
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Working From</label>
                    <select id="work-from-type" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white">
                        <option value="office">Office</option>
                        <option value="home">Home</option>
                        <option value="other">Other / Site</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Location / Note</label>
                    <input type="text" id="working-from-note" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white" placeholder="e.g. Site Visit">
                </div>
                <button onclick="clockIn()" class="w-full py-5 bg-emerald-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-700 transition-all active:scale-95 shadow-xl">Confirm Clock In</button>
            </div>
        </div>
    </div>
</div>

<!-- Manual Attendance Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden no-print" id="manualAttendanceModal">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 px-8 py-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider" id="manualModalTitle">Attendance Record</h3>
                <button onclick="closeModal('manualAttendanceModal')" class="w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white"><i class="fas fa-times"></i></button>
            </div>
            <form id="attendanceForm" class="p-8 space-y-6">
                <input type="hidden" id="att-id" name="id">
                <input type="hidden" id="att-user-id" name="user_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Clock In</label>
                        <input type="datetime-local" id="att-clock-in" name="clock_in_time" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Clock Out</label>
                        <input type="datetime-local" id="att-clock-out" name="clock_out_time" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Work Type</label>
                        <select id="att-work-type" name="work_from_type" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none"><option value="office">Office</option><option value="home">Home</option><option value="other">Other / Site</option></select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 block ml-1">Note</label>
                        <input type="text" id="att-working-from" name="working_from" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white">
                    </div>
                </div>
                <div class="flex gap-3 pt-4">
                    <button type="button" id="att-delete-btn" onclick="deleteAttendance()" class="hidden px-6 bg-red-50 text-red-600 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-red-600 hover:text-white transition-all">Delete</button>
                    <button type="submit" class="flex-grow py-5 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-700 shadow-xl transition-all">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Content Ends -->

<script>
    const currentUserId = <?php echo $user_id; ?>;
    let currentData = [];
    let activeView = 'calendar';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).ready(function() {
        const monthInput = document.getElementById('filter-month');
        if (monthInput && typeof flatpickr !== 'undefined' && typeof monthSelectPlugin === 'function') {
            if (monthInput._flatpickr) monthInput._flatpickr.destroy();

            flatpickr(monthInput, {
                dateFormat: "Y-m",
                clickOpens: true,
                plugins: [
                    monthSelectPlugin({
                        shorthand: true,
                        dateFormat: "Y-m",
                        altFormat: "F Y"
                    })
                ],
                onChange: (selectedDates, dateStr) => { 
                    fetchAttendances(); 
                }
            });
        }
        fetchAttendances();
    });

    function changeView(v) {
        activeView = v;
        $('.view-btn').removeClass('bg-indigo-600').addClass('bg-white/10');
        $(`#btn-view-${v}`).addClass('bg-indigo-600').removeClass('bg-white/10');
        render();
    }

    function fetchAttendances() {
        const userId = $('#filter-user').val();
        const selectedMonth = $('#filter-month').val(); // YYYY-MM
        const start = moment(selectedMonth, 'YYYY-MM').startOf('month').format('YYYY-MM-DD 00:00:00');
        const end = moment(selectedMonth, 'YYYY-MM').endOf('month').format('YYYY-MM-DD 23:59:59');

        $('#timesheet-view-container').html('<div class="flex flex-col items-center justify-center py-32"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Records...</p></div>');

        $.getJSON('../handlers/api_proxy.php?endpoint=/api/attendances', {
            user_id: userId,
            start_date: start,
            end_date: end
        }, function(res) {
            if (res.success) {
                currentData = res.data;
                render();
            } else {
                Swal.fire('Error', res.message || 'Failed to fetch data', 'error');
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Failed to fetch attendance data.';
            $('#timesheet-view-container').html(`<div class="p-12 text-center text-red-500 font-bold">${escapeHtml(msg)}</div>`);
            Swal.fire('Error', msg, 'error');
        });
    }

    function render() {
        const userId = $('#filter-user').val();
        if (userId === 'all') {
            $('#view-toggle-container').hide();
            renderListView();
        } else {
            $('#view-toggle-container').show();
            if (activeView === 'calendar') renderCalendarGrid();
            else renderReportList();
        }
    }

    function renderCalendarGrid() {
        const start = moment($('#filter-month').val(), 'YYYY-MM').startOf('month');
        const daysInMonth = start.daysInMonth();
        const firstDay = start.day();
        let workMins = 0, leaveDays = 0;
        let html = `<div class="calendar-grid border-b border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-black/20">${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `<div class="py-4 text-center text-[10px] font-black uppercase tracking-widest text-gray-400">${d}</div>`).join('')}</div><div class="calendar-grid bg-white dark:bg-slate-900/20">`;
        for (let i = 0; i < firstDay; i++) html += `<div class="calendar-day bg-gray-50/20 dark:bg-slate-900/10"></div>`;
        for (let d = 1; d <= daysInMonth; d++) {
            const date = start.clone().date(d);
            const dateKey = date.format('YYYY-MM-DD');
            const entries = currentData.filter(e => moment(e.clock_in_time).isSame(date, 'day'));
            let entryHtml = '';
            entries.forEach(e => {
                let color = 'bg-blue-50 text-blue-600 border-blue-100 dark:bg-blue-900/20 dark:text-blue-400';
                let text = '', action = '';
                if (e.type === 'leave') { 
                    color = 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-900/30'; 
                    leaveDays += (e.duration === 'half day' ? 0.5 : 1); 
                    text = (e.duration === 'half day' ? 'Half Day' : 'Full Day'); 
                }
                else if (e.type === 'holiday') { color = 'bg-purple-50 text-purple-600 border-purple-100'; text = 'Holiday'; }
                else {
                    const cIn = moment(e.clock_in_time), cOut = e.clock_out_time ? moment(e.clock_out_time) : null;
                    action = `onclick="openEditModal(${e.id}, event); event.stopPropagation();"`;
                    if (cOut) { workMins += cOut.diff(cIn, 'minutes'); text = `${cIn.format('HH:mm')}-${cOut.format('HH:mm')}`; }
                    else { color = 'bg-emerald-50 text-emerald-600 border-emerald-100 animate-pulse'; text = `${cIn.format('HH:mm')}-Active`; }
                }
                entryHtml += `<div class="entry-pill border ${color} hover:brightness-95" ${action}>${escapeHtml(text)}</div>`;
            });
            html += `<div class="calendar-day p-2 relative ${date.day()===0||date.day()===6?'bg-gray-50/30 dark:bg-slate-900/10':''} cursor-cell" onclick="openAddModal('${dateKey}')"><span class="day-num ${date.isSame(moment(),'day')?'bg-indigo-600 text-white w-5 h-5 rounded-full flex items-center justify-center':'text-gray-400'}">${d}</span><div class="mt-1 flex flex-col gap-0.5">${entryHtml}</div></div>`;
        }
        $('#timesheet-view-container').html(html + '</div>');
        updateStats(workMins, leaveDays);
        $('#view-title').text(start.format('MMMM YYYY') + ' Calendar');
    }

    function renderReportList() {
        const start = moment($('#filter-month').val(), 'YYYY-MM').startOf('month');
        let workMins = 0, leaveDays = 0;
        let html = `<div class="overflow-x-auto"><table class="w-full text-sm text-left border-collapse"><thead><tr class="bg-gray-50/50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800"><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400">Status</th><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400">Date</th><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400">In</th><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400">Out</th><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400">Location</th><th class="px-8 py-5 text-[10px] font-black uppercase text-gray-400 text-right">Hours</th></tr></thead><tbody class="divide-y divide-gray-50 dark:divide-slate-800">`;
        for (let d = 1; d <= start.daysInMonth(); d++) {
            const date = start.clone().date(d);
            const entries = currentData.filter(e => moment(e.clock_in_time).isSame(date, 'day'));
            if (entries.length === 0) { html += `<tr class="${date.day()===0||date.day()===6?'bg-gray-50/30 dark:bg-slate-900/10':''} opacity-40"><td class="px-8 py-4"><span class="text-[8px] font-black uppercase text-gray-300">--</span></td><td class="px-8 py-4 font-bold text-gray-400 text-xs italic">${date.format('ddd, DD MMM')}</td><td colspan="3" class="px-8 py-4 text-gray-300 italic text-[10px]">No activity</td><td class="px-8 py-4 text-right text-gray-300">-</td></tr>`; }
            else {
                entries.forEach((e, idx) => {
                    const cIn = moment(e.clock_in_time), cOut = e.clock_out_time ? moment(e.clock_out_time) : null;
                    let dur = '-';
                    if (e.type === 'attendance' && cOut) { const m = cOut.diff(cIn, 'minutes'); workMins += m; dur = `${Math.floor(m / 60)}h ${m % 60}m`; }
                    else if (e.type === 'leave') { leaveDays += (e.duration === 'half day' ? 0.5 : 1); dur = (e.duration === 'half day' ? '4h' : '8h'); }
                    else if (e.type === 'holiday') dur = 'OFF';
                    let sClass = e.type === 'leave' ? 'bg-amber-50 text-amber-600' : (e.type === 'holiday' ? 'bg-purple-50 text-purple-600' : (cOut ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600'));
                    html += `<tr class="${date.day()===0||date.day()===6?'bg-gray-50/30 dark:bg-slate-900/10':''} hover:bg-indigo-50/30 transition-colors" onclick="${e.type==='attendance'?'openEditModal('+e.id+', event)':''}"><td class="px-8 py-4"><span class="px-2 py-0.5 rounded text-[8px] font-black uppercase border ${sClass}">${e.type}</span></td><td class="px-8 py-4 font-bold text-gray-900 dark:text-gray-200 text-xs italic">${idx === 0 ? date.format('ddd, DD MMM') : ''}</td><td class="px-8 py-4 text-gray-600 font-bold text-xs">${e.type === 'holiday' ? '--' : cIn.format('HH:mm')}</td><td class="px-8 py-4 text-gray-600 font-bold text-xs">${(cOut && e.type !== 'holiday') ? cOut.format('HH:mm') : '--'}</td><td class="px-8 py-4 text-[10px] text-gray-400">${e.working_from || ''}</td><td class="px-8 py-4 text-right font-black text-indigo-600 dark:text-indigo-400 italic text-sm">${dur}</td></tr>`;
                });
            }
        }
        $('#timesheet-view-container').html(html + '</tbody></table></div>');
        updateStats(workMins, leaveDays);
        $('#view-title').text(start.format('MMMM YYYY') + ' Report');
    }

    function renderListView() {
        // Sort by date descending
        const sortedData = [...currentData].sort((a, b) => moment(b.clock_in_time).unix() - moment(a.clock_in_time).unix());

        let html = `<div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead class="bg-gray-900 text-white"><tr class="border-b dark:border-slate-800"><th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest">Member</th><th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-center">Date</th><th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-center">Status</th><th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-right">Duration</th></tr></thead><tbody class="divide-y divide-gray-50 dark:divide-slate-800">`;
        sortedData.forEach(row => {
            const cIn = moment(row.clock_in_time);
            const cOut = row.clock_out_time ? moment(row.clock_out_time) : null;
            let dur = '-';
            if (row.type === 'attendance' && cOut) dur = `${Math.floor(cOut.diff(cIn,'minutes')/60)}h ${cOut.diff(cIn,'minutes')%60}m`;
            else if (row.type === 'leave') dur = (row.duration === 'half day' ? '4h' : '8h');
            html += `<tr class="hover:bg-indigo-50/30 transition-colors bg-white dark:bg-slate-900/40"><td class="px-8 py-6 font-bold text-gray-900 dark:text-gray-200 text-xs italic">${escapeHtml(row.user?.name || 'Unknown')}</td><td class="px-8 py-6 text-gray-600 font-bold text-xs text-center">${cIn.format('DD MMM, HH:mm')}</td><td class="px-8 py-6 text-center"><span class="px-2 py-0.5 rounded text-[8px] font-black uppercase border bg-gray-50 dark:bg-slate-800">${escapeHtml(row.type)}</span></td><td class="px-8 py-6 text-right font-black text-indigo-600 italic text-sm">${escapeHtml(dur)}</td></tr>`;
        });
        $('#timesheet-view-container').html(html + '</tbody></table></div>');
        $('#view-title').text('Recent Team Activity');
    }

    function updateStats(mins, leaves) { $('#stat-total-hours').text((mins / 60).toFixed(2)); $('#stat-leave-days').text(leaves + ' Days'); }
    function showClockInModal() { $('#clockInModal').removeClass('hidden'); }
    function closeModal(id) { $('#' + id).addClass('hidden'); }

    window.openAddModal = function(date) {
        const userId = $('#filter-user').val();
        if (userId === 'all') {
            Swal.fire({ icon: 'info', title: 'User Required', text: 'Please select a specific team member to add a record.', timer: 2000, showConfirmButton: false });
            return;
        }
        $('#attendanceForm')[0].reset();
        $('#att-id').val('');
        $('#att-user-id').val(userId);
        $('#att-delete-btn').addClass('hidden');
        $('#manualModalTitle').text('Add Manual Record');
        if (date) { $('#att-clock-in').val(date + 'T09:00'); $('#att-clock-out').val(date + 'T17:30'); }
        $('#manualAttendanceModal').removeClass('hidden');
    }

    window.openEditModal = function(id, event) {
        if (event) event.stopPropagation();
        const entry = currentData.find(e => e.id == id);
        if (!entry) return;
        $('#att-id').val(entry.id);
        $('#att-user-id').val(entry.user_id);
        $('#att-clock-in').val(moment(entry.clock_in_time).format('YYYY-MM-DDTHH:mm'));
        $('#att-clock-out').val(entry.clock_out_time ? moment(entry.clock_out_time).format('YYYY-MM-DDTHH:mm') : '');
        $('#att-work-type').val(entry.work_from_type || 'other');
        $('#att-working-from').val(entry.working_from || '');
        $('#att-delete-btn').removeClass('hidden');
        $('#manualModalTitle').text('Edit Attendance Record');
        $('#manualAttendanceModal').removeClass('hidden');
    }

    $('#attendanceForm').submit(function(e) {
        e.preventDefault();
        const id = $('#att-id').val();
        const data = { user_id: $('#att-user-id').val(), clock_in_time: $('#att-clock-in').val(), clock_out_time: $('#att-clock-out').val(), work_from_type: $('#att-work-type').val(), working_from: $('#att-working-from').val() };
        $.ajax({
            url: `../handlers/api_proxy.php?endpoint=${id ? '/api/attendances/'+id : '/api/attendances'}`,
            type: id ? 'PATCH' : 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(res) {
                if (res.success) { Swal.fire({ icon: 'success', title: 'Record Saved', timer: 1500, showConfirmButton: false }); $('#manualAttendanceModal').addClass('hidden'); fetchAttendances(); }
                else { Swal.fire('Error', res.message || 'Save failed', 'error'); }
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Failed to save attendance record.', 'error');
            }
        });
    });

    window.deleteAttendance = function() {
        const id = $('#att-id').val();
        Swal.fire({ title: 'Delete record?', text: "This action cannot be undone.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Yes, Delete' }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `../handlers/api_proxy.php?endpoint=/api/attendances/${id}`,
                    type: 'DELETE',
                    success: function(res) {
                        if (res.success) {
                            $('#manualAttendanceModal').addClass('hidden');
                            fetchAttendances();
                        } else {
                            Swal.fire('Error', res.message || 'Failed to delete attendance record.', 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', xhr.responseJSON?.message || 'Failed to delete attendance record.', 'error');
                    }
                });
            }
        });
    }

    function clockIn() {
        const type = $('#work-from-type').val(), note = $('#working-from-note').val();
        if (navigator.geolocation) { navigator.geolocation.getCurrentPosition(pos => submitClockIn(type, note, pos.coords.latitude, pos.coords.longitude), err => submitClockIn(type, note)); }
        else { submitClockIn(type, note); }
    }

    function submitClockIn(type, note, lat = null, lng = null) {
        const data = { user_id: currentUserId, work_from_type: type, working_from: note, latitude: lat, longitude: lng };
        $.ajax({
            url: '../handlers/api_proxy.php?endpoint=/api/attendances/clock-in',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(res) {
                if (res.success) { location.reload(); } else { Swal.fire('Error', res.message || 'Failed', 'error'); }
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Failed to clock in.', 'error');
            }
        });
    }

    function clockOut() {
        Swal.fire({ title: 'Finish shift?', icon: 'question', showCancelButton: true, confirmButtonText: 'Clock Out' }).then((result) => {
            if (result.isConfirmed) { 
                $.ajax({
                    url: '../handlers/api_proxy.php?endpoint=/api/attendances/clock-out',
                    type: 'POST',
                    data: JSON.stringify({ user_id: currentUserId }),
                    contentType: 'application/json',
                    success: function(res) {
                        if (res.success) location.reload();
                        else Swal.fire('Error', res.message || 'Failed to clock out', 'error');
                    },
                    error: function() {
                        Swal.fire('Error', 'Server connection failed', 'error');
                    }
                });
            }
        });
    }
</script>

<?php include "../footer.php"; ?>
</body>
</html>
