<?php
require_once '../config.php';
require_once "../tracker_data.php"; // For makeApiCall

$api_token = getTrackerApiToken();

if (!$api_token) {
    http_response_code(401);
    echo "<div class='card-base p-8 text-center text-red-500 font-bold'>Authentication error: API token not found. Please log in.</div>";
    exit;
}

// Fetch tickets from new API
$statusFilter = $_GET['status_filter'] ?? 'active';
$apiResponse = makeApiCall('/api/tickets', ['status_filter' => $statusFilter]);
if (!$apiResponse || !($apiResponse['success'] ?? false)) {
    echo "<div class='card-base p-8 text-center text-red-500 font-bold'>Error: Failed to fetch tickets from API. " . ($apiResponse['message'] ?? '') . "</div>";
    exit;
}
$tickets = $apiResponse['data'];

// Fetch groups from new API
$groupResponse = makeApiCall('/api/ticket-groups');
$groupsMap = [];
if ($groupResponse && ($groupResponse['success'] ?? false)) {
    foreach ($groupResponse['data'] as $group) {
        $groupsMap[$group['id']] = $group['group_name'];
    }
}

function displayTodoList($tickets, $groupsMap) {
    // Start table construction
    $html = '<div class="card-base mb-8">';
    $html .= '<div class="section-header"><h3><i class="fas fa-ticket-alt text-indigo-400"></i> Support Queue</h3></div>';
    $html .= '<div class="table-container">';
    $html .= '<table id="ticketsDataTable" class="w-full text-sm text-left border-collapse">';
    $html .= '<thead><tr class="table-header-row"><th class="px-6 py-4">Group</th><th class="px-6 py-4">View</th><th class="px-6 py-4">Subject</th><th class="px-6 py-4">Type</th><th class="px-6 py-4">Status</th><th class="px-6 py-4">Priority</th><th class="px-6 py-4">Age</th><th class="px-6 py-4">Requester</th><th class="px-6 py-4 text-center">Action</th></tr></thead>';
    $html .= '<tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">';

    foreach ($tickets as $todo) {
        $updatedAt = new DateTime($todo['updated_at']);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($updatedAt);
        $daysAgo = $interval->days;

        $groupname = $groupsMap[$todo['group_id'] ?? ''] ?? 'General';

        $priorityClasses = match (strtolower($todo['priority'])) {
            'low' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-100 dark:border-blue-900/50',
            'medium' => 'bg-gray-50 dark:bg-slate-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-slate-700',
            'high' => 'bg-orange-50 dark:bg-amber-900/20 text-orange-700 dark:text-amber-400 border-orange-200 dark:border-amber-900/50',
            'urgent' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-900/50',
            default => 'bg-gray-50 text-gray-500 border-gray-100',
        };

        $statusOptions = [
            'open'     => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/50',
            'pending'  => 'bg-yellow-100 dark:bg-amber-900/30 text-yellow-700 dark:text-amber-400 border-yellow-200 dark:border-amber-900/50',
            'resolved' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-900/50',
            'closed'   => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-900/50',
            'archived' => 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-slate-700'
        ];

        $statusClass = $statusOptions[strtolower($todo['status'])] ?? 'bg-gray-100 text-gray-600 border-gray-200';
        $rowClass = ($daysAgo < 5) ? 'table-row-hover' : 'hover:bg-gray-50/50 dark:hover:bg-slate-800/30';

        $html .= "<tr class='{$rowClass} transition-colors'>";
        $html .= "<td class='px-6 py-4'><span class='group-badge px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 text-[10px] font-black rounded-lg border border-gray-200 dark:border-slate-700 uppercase tracking-wider cursor-pointer hover:border-indigo-400 hover:text-indigo-600 transition-all' data-ticket-id='" . $todo['id'] . "' data-current-group-id='" . htmlspecialchars($todo['group_id'] ?? '') . "' data-current-group-name='" . htmlspecialchars($groupname) . "'>{$groupname}</span></td>";
        $html .= "<td class='px-6 py-4'><button id='" . htmlspecialchars($todo['id']) . "' class='ticketbtn p-2 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white rounded-xl transition-all shadow-sm active:scale-95'><i class='fas fa-eye'></i></button></td>";
        $html .= "<td class='px-6 py-4 font-bold text-gray-900 dark:text-white'>" . htmlspecialchars($todo['subject']) . "</td>";

        // Safe access to type data
        $typeId = $todo['type']['id'] ?? '';
        $typeName = $todo['type']['type'] ?? 'Unknown';
        $html .= "<td class='px-6 py-4'><span class='type-badge px-2 py-1 bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 text-[10px] font-black rounded-lg border border-indigo-100 dark:border-indigo-900/50 uppercase tracking-wider cursor-pointer hover:border-indigo-400 transition-all' data-ticket-id='" . $todo['id'] . "' data-current-type-id='" . htmlspecialchars($typeId) . "'>" . htmlspecialchars($typeName) . "</span></td>";

        $html .= "<td class='px-6 py-4'><span class='status-badge px-2 py-1 {$statusClass} text-[10px] font-black rounded-lg border uppercase tracking-wider cursor-pointer hover:brightness-95 transition-all' data-ticket-id='" . $todo['id'] . "' data-current-status='" . htmlspecialchars($todo['status']) . "'>" . htmlspecialchars($todo['status']) . "</span></td>";
        $html .= "<td class='px-6 py-4'><span class='priority-badge px-2 py-1 {$priorityClasses} text-[10px] font-black rounded-lg border uppercase tracking-wider cursor-pointer hover:brightness-95 transition-all' data-ticket-id='" . $todo['id'] . "' data-current-priority='" . htmlspecialchars($todo['priority']) . "'>" . htmlspecialchars($todo['priority']) . "</span></td>";
        $html .= "<td class='px-6 py-4'><span class='text-xs font-mono font-bold text-gray-400 dark:text-gray-500'>{$daysAgo}d</span></td>";

        // Safe access to requester name
        $requesterName = $todo['requester']['name'] ?? 'Unknown';
        $html .= "<td class='px-6 py-4 font-medium text-gray-600 dark:text-gray-400 text-xs italic'>@" . htmlspecialchars($requesterName) . "</td>";

        $html .= "<td class='px-6 py-4 text-center'><button onclick='deleteTicket({$todo['id']})' class='p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all active:scale-95'><i class='fas fa-trash-alt'></i></button></td>";
        $html .= "</tr>";
    }

    $html .= '</tbody></table></div></div>';
    echo $html;
}

displayTodoList($tickets, $groupsMap);
?>

<script>
    window.apiToken = '<?php echo htmlspecialchars($api_token, ENT_QUOTES, 'UTF-8'); ?>';
    window.allTicketGroups = <?php echo json_encode($groupsMap); ?>;
    
    if (typeof initTicketsTable === 'function') {
        setTimeout(function() {
            initTicketsTable();
        }, 100);
    }
</script>

<!-- Tailwind Ticket Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="ticketModal">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 p-6 flex items-center justify-between text-white border-b border-white/10">
            <h5 id="ticketModalLabel" class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-ticket-alt text-indigo-400"></i> Ticket Details
            </h5>
            <button type="button" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white" onclick="closeModal('ticketModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar modal-body">
            <!-- Ticket details populated via JS -->
        </div>
        <div class="bg-gray-50 dark:bg-slate-950 p-6 border-t border-gray-100 dark:border-slate-800 flex justify-end">
            <button type="button" class="px-8 py-3 bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-slate-700 transition-all active:scale-95 shadow-sm" onclick="closeModal('ticketModal')">Close View</button>
        </div>
    </div>
</div>
