<?php
require_once __DIR__ . '/../config.php';
$hash = $_GET['hash'] ?? '';
if (empty($hash)) {
    die("Invalid link provided.");
}
$pageTitle = "Assigned Work Orders";
include_once __DIR__ . '/../header.php'; 
?>

<div class="bg-gray-50 dark:bg-slate-950 min-h-screen">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8 text-center">
            <h1 id="user-name" class="text-3xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white mb-2">Your Assigned Jobs</h1>
            <p class="text-gray-500 dark:text-slate-400 font-bold text-xs uppercase tracking-[0.3em]">Live view of your active work orders</p>
        </div>

        <div id="tasks-container" class="space-y-4">
            <!-- Tasks will be loaded here by JavaScript -->
        </div>

        <div id="loader" class="py-20 text-center">
            <div class="inline-block animate-spin w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full mb-4"></div>
            <p class="text-sm font-black uppercase tracking-widest text-gray-400">Loading your assigned jobs...</p>
        </div>
        
        <div id="error-message" class="hidden py-20 text-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
            <p class="text-lg font-bold text-red-600 dark:text-red-400">Could not load tasks.</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">The link may be invalid or expired. Please contact your administrator.</p>
        </div>
    </div>
</div>

<!-- Remark Modal -->
<div id="remark-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
            <div class="bg-indigo-600 p-6 flex justify-between items-center text-white">
                <h3 class="text-lg font-black uppercase italic tracking-wider">Add a Remark</h3>
                <button onclick="closeRemarkModal()" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all">&times;</button>
            </div>
            <div class="p-8">
                <input type="hidden" id="remark-task-id">
                <textarea id="remark-text" rows="5" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl font-medium text-sm dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none" placeholder="Type your update or comment..."></textarea>
                <div class="mt-6 flex justify-end gap-3">
                    <button onclick="closeRemarkModal()" class="px-6 py-3 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-gray-200 transition-all">Cancel</button>
                    <button onclick="submitRemark()" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg">Save Remark</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const userHash = <?php echo json_encode($hash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const laravelApiUrl = '<?php echo $_ENV["LARAVEL_API_URL"]; ?>';

function publicApiHeaders() {
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };
    if (window.trackerTenantSlug) {
        headers['X-Tenant-Slug'] = window.trackerTenantSlug;
    }
    return headers;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    loadTasks();
});

function getStatusColor(status) {
    switch(String(status || '').toLowerCase()) {
        case 'completed': return 'bg-green-100 text-green-700';
        case 'pending': return 'bg-red-100 text-red-700';
        case 'in progress': return 'bg-blue-100 text-blue-700';
        default: return 'bg-gray-100 text-gray-700';
    }
}

function loadTasks() {
    document.getElementById('loader').classList.remove('hidden');
    document.getElementById('error-message').classList.add('hidden');
    document.getElementById('tasks-container').innerHTML = '';

    fetch(`${laravelApiUrl}/api/public/tasks`, {
        method: 'POST',
        headers: publicApiHeaders(),
        body: JSON.stringify({ hash: userHash, search: '' })
    })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loader').classList.add('hidden');
            if (!data.success) {
                throw new Error(data.message || 'Failed to load data.');
            }
            
            document.getElementById('user-name').textContent = `${data.user.name}'s Assigned Jobs`;
            const container = document.getElementById('tasks-container');
            if (data.tasks.length === 0) {
                container.innerHTML = `<div class="text-center py-10 card-base"><p class="text-gray-500">You have no active work orders assigned.</p></div>`;
                return;
            }

            let html = '';
            data.tasks.forEach(task => {
                const isComplete = String(task.status || '').toLowerCase() === 'completed';
                const safeStatus = escapeHtml(task.status || '');
                const safePo = escapeHtml(task.poNumber || 'N/A');
                const safeAssignedUser = escapeHtml(task.assignedUser?.name || '');
                const safeClient = escapeHtml(task.client?.name || '');
                const safeHeading = escapeHtml(task.property || task.location || 'N/A');
                const safeTask = escapeHtml(task.task || '');
                const safeRemarks = escapeHtml(task.remarks || '');
                const safeHash = encodeURIComponent(task.hash || '');
                const safeLocation = task.location ? encodeURIComponent(task.location) : '';
                html += `
                    <div class="card-base p-6 border-l-4 ${isComplete ? 'border-green-500' : 'border-indigo-500'}">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div class="flex-grow">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="px-3 py-1 ${getStatusColor(task.status)} text-[10px] font-black rounded-full uppercase tracking-widest">${safeStatus}</span>
                                    <span class="font-mono text-base font-black text-indigo-600 dark:text-indigo-400">${safePo}</span>
                                ${task.assignedUser ? `<span class="text-xs text-gray-500 dark:text-gray-400">Assigned: ${safeAssignedUser}</span>` : ''}
                                ${task.client ? `<span class="text-xs text-gray-500 dark:text-gray-400">Client: ${safeClient}</span>` : ''}
                                </div>
                                <h4 class="font-bold text-lg text-gray-900 dark:text-white">${safeHeading}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">${safeTask}</p>
                            </div>
                            <div class="flex-shrink-0 flex flex-wrap items-center gap-2 mt-4 md:mt-0 md:justify-end">
                                <a href="task_view.php?h=${safeHash}" class="btn-secondary text-[11px] py-2.5 px-4 flex-1 md:flex-none text-center whitespace-nowrap shadow-none">
                                    <i class="fas fa-eye mr-1.5"></i> Details
                                </a>
                                <button onclick="openRemarkModal(${task.id})" class="btn-secondary text-[11px] py-2.5 px-4 flex-1 md:flex-none text-center whitespace-nowrap shadow-none">
                                    <i class="fas fa-comment-dots mr-1.5"></i> Remarks
                                </button>
                                ${task.location ? `
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=${safeLocation}" target="_blank" rel="noopener noreferrer" class="btn-secondary text-[11px] py-2.5 px-4 flex-1 md:flex-none text-center whitespace-nowrap shadow-none">
                                        <i class="fas fa-map-marker-alt mr-1.5"></i> Map
                                    </a>` : ''}
                                ${!isComplete ? `
                                    <button onclick="markComplete(${task.id})" class="btn-primary text-[11px] py-2.5 px-4 flex-[2] md:flex-none text-center whitespace-nowrap shadow-md">
                                        <i class="fas fa-check-circle mr-1.5"></i> Complete
                                    </button>` : ''}
                            </div>
                        </div>
                        ${task.remarks ? `<div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-800 text-xs text-gray-500 dark:text-slate-400 whitespace-pre-wrap font-mono">${safeRemarks}</div>` : ''}
                    </div>
                `;
            });
            container.innerHTML = html;
        })
        .catch(error => {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('error-message').classList.remove('hidden');
            console.error('Error loading tasks:', error);
        });
}

function openRemarkModal(taskId) {
    document.getElementById('remark-task-id').value = taskId;
    document.getElementById('remark-text').value = '';
    document.getElementById('remark-modal').classList.remove('hidden');
    document.getElementById('remark-text').focus();
}

function closeRemarkModal() {
    document.getElementById('remark-modal').classList.add('hidden');
}

function submitRemark() {
    const taskId = document.getElementById('remark-task-id').value;
    const remark = document.getElementById('remark-text').value;

    if (!remark.trim()) {
        alert('Remark cannot be empty.');
        return;
    }

    fetch(`${laravelApiUrl}/api/public/tasks/${taskId}/remark`, {
        method: 'POST',
        headers: publicApiHeaders(),
        body: JSON.stringify({ hash: userHash, remark: remark })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            closeRemarkModal();
            loadTasks();
        } else {
            alert('Failed to save remark: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to save remark.');
    });
}

function markComplete(taskId) {
    if (!confirm('Are you sure you want to mark this job as complete?')) return;

    fetch(`${laravelApiUrl}/api/public/tasks/${taskId}/complete`, {
        method: 'PATCH',
        headers: publicApiHeaders(),
        body: JSON.stringify({ hash: userHash })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            loadTasks();
        } else {
            alert('Failed to update task: ' + data.message);
        }
    })
    .catch(() => {
        alert('Failed to update task.');
    });
}

</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
