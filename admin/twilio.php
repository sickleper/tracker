<?php
$pageTitle = "Twilio Routing";
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

if (!isTrackerSuperAdmin()) {
    header('Location: ../index.php');
    exit();
}

include '../header.php';
include '../nav.php';
?>

<div class="admin-shell">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="heading-brand">Twilio Routing</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage inbound number routing and inspect recent call logs.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="loadTwilioData()" class="admin-action admin-action-primary">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <section class="xl:col-span-2 admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-phone text-indigo-400 mr-2"></i> Twilio Numbers</h3>
            </div>
            <div class="table-container">
                <table class="w-full text-sm">
                    <thead class="table-header-row">
                        <tr>
                            <th class="px-6 py-4 text-left">Number</th>
                            <th class="px-6 py-4 text-left">Friendly Name</th>
                            <th class="px-6 py-4 text-left">Assigned Users</th>
                            <th class="px-6 py-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="twilioNumbersBody" class="admin-table-body">
                        <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px]">Loading numbers...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-route text-indigo-400 mr-2"></i> Assignment Editor</h3>
            </div>
            <div class="admin-panel-body space-y-5">
                <div>
                    <label class="admin-label">Twilio Number</label>
                    <select id="phone_number" class="admin-select">
                        <option value="">Select a number</option>
                    </select>
                </div>
                <div>
                <label class="admin-label">Staff Phone Numbers</label>
                <select id="user_ids" multiple class="admin-select min-h-[220px]"></select>
                <p class="mt-2 text-[10px] text-gray-400 font-bold uppercase tracking-widest">Only staff with configured mobile numbers can be routed. Hold Ctrl/Cmd to select multiple users.</p>
                </div>
                <button onclick="saveAssignments()" class="admin-action admin-action-primary admin-action-lg w-full text-xs">
                    <i class="fas fa-save text-indigo-200"></i> Save Routing
                </button>
            </div>
        </section>
    </div>

    <section class="mt-8 admin-panel">
        <div class="section-header">
            <h3><i class="fas fa-phone-volume text-indigo-400 mr-2"></i> Recent Call Logs</h3>
        </div>
        <div class="table-container">
            <table class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Time</th>
                        <th class="px-6 py-4 text-left">Caller</th>
                        <th class="px-6 py-4 text-left">Twilio Number</th>
                        <th class="px-6 py-4 text-left">Forwarded To</th>
                        <th class="px-6 py-4 text-left">Status</th>
                    </tr>
                </thead>
                <tbody id="twilioLogsBody" class="admin-table-body">
                    <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px]">Loading logs...</td></tr>
                </tbody>
            </table>
            </div>
    </section>
</div>

<script>
window.laravelApiUrl = '<?php echo $_ENV['LARAVEL_API_URL'] ?? ''; ?>';
window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
window.twilioState = { numbers: [], users: [] };

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function twilioHeaders() {
    return {
        'Authorization': 'Bearer ' + window.apiToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
    };
}

function renderNumbers() {
    const body = document.getElementById('twilioNumbersBody');
    const select = document.getElementById('phone_number');
    select.innerHTML = '<option value="">Select a number</option>';

    if (!window.twilioState.numbers.length) {
        body.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px]">No Twilio numbers found.</td></tr>';
        return;
    }

    body.innerHTML = window.twilioState.numbers.map((number) => {
        const safePhoneNumber = escapeHtml(number.phone_number || '');
        const safeFriendlyName = escapeHtml(number.friendly_name || '');
        const assignmentValues = (number.assignments || []).map((row) => escapeHtml(row.friendly_name || row.user_auth_id)).join(', ');
        const assignments = assignmentValues || '<span class="text-gray-300 italic">Unassigned</span>';
        const encodedPhoneNumber = JSON.stringify(String(number.phone_number || ''));
        return `
            <tr class="table-row-hover">
                <td class="px-6 py-4 font-black text-gray-900 dark:text-white">${safePhoneNumber}</td>
                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">${safeFriendlyName}</td>
                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">${assignments}</td>
                <td class="px-6 py-4">
                    <button onclick='editNumber(${encodedPhoneNumber})' class="admin-action admin-action-dark admin-action-sm rounded-xl shadow-none">
                        Edit Routing
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    window.twilioState.numbers.forEach((number) => {
        if (number.phone_number) {
            const option = document.createElement('option');
            option.value = number.phone_number;
            option.textContent = `${number.phone_number} ${number.friendly_name ? '- ' + number.friendly_name : ''}`;
            select.appendChild(option);
        }
    });
}

function renderUsers() {
    const select = document.getElementById('user_ids');
    select.innerHTML = '';
    const staffWithPhone = window.twilioState.users.filter((user) => user.mobile && user.mobile.trim() !== '');
    const entries = staffWithPhone.length ? staffWithPhone : window.twilioState.users;
    entries.forEach((user) => {
        const option = document.createElement('option');
        option.value = user.id;
        const labelParts = [escapeHtml(user.name)];
        if (user.mobile) {
            labelParts.push(escapeHtml(user.mobile));
            option.dataset.mobile = user.mobile;
        } else {
            option.dataset.mobile = '';
            option.classList.add('text-gray-400');
        }
        option.textContent = labelParts.join(' - ');
        option.disabled = !user.mobile;
        select.appendChild(option);
    });
}

function renderLogs(logs) {
    const body = document.getElementById('twilioLogsBody');
    if (!logs.length) {
        body.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px]">No call logs found.</td></tr>';
        return;
    }

    body.innerHTML = logs.map((log) => `
        <tr class="table-row-hover">
            <td class="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400">${escapeHtml(log.event_timestamp || '')}</td>
            <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">${escapeHtml(log.caller_number || '')}</td>
            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">${escapeHtml(log.twilio_number || '')}</td>
            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">${log.redirected_number ? escapeHtml(log.redirected_number) : '<span class="text-gray-300 italic">Not Routed</span>'}</td>
            <td class="px-6 py-4"><span class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">${escapeHtml(log.call_status || '')}</span></td>
        </tr>
    `).join('');
}

async function loadTwilioData() {
    try {
        const [numbersRes, logsRes] = await Promise.all([
            fetch(`${window.laravelApiUrl}/api/twilio/numbers`, { headers: twilioHeaders() }),
            fetch(`${window.laravelApiUrl}/api/twilio/call-logs?limit=100`, { headers: twilioHeaders() })
        ]);

        const numbersData = await numbersRes.json();
        const logsData = await logsRes.json();

        if (!numbersData.success) {
            throw new Error(numbersData.message || 'Failed to load Twilio numbers.');
        }

        window.twilioState.numbers = numbersData.data.numbers || [];
        window.twilioState.users = numbersData.data.users || [];
        renderNumbers();
        renderUsers();
        renderLogs(logsData.data || []);

        if (!window.twilioState.users.length) {
            await fetchStaffUsers();
        }
    } catch (error) {
        Swal.fire('Error', error.message || 'Failed to load Twilio data.', 'error');
        await fetchStaffUsers();
    }
}

async function fetchStaffUsers() {
    try {
        const response = await fetch(`${window.laravelApiUrl}/api/users?team_only=1`, {
            headers: twilioHeaders()
        });
        const data = await response.json();
        if (response.ok && data.success) {
            window.twilioState.users = data.users || [];
            renderUsers();
        }
    } catch (error) {
        console.error('Failed to load staff list for Twilio assignment editor.', error);
    }
}

function editNumber(phoneNumber) {
    const number = window.twilioState.numbers.find((row) => row.phone_number === phoneNumber);
    if (!number) return;

    document.getElementById('phone_number').value = phoneNumber;
    const selected = new Set((number.assignments || []).map((row) => String(row.user_auth_id)));
    Array.from(document.getElementById('user_ids').options).forEach((option) => {
        option.selected = selected.has(option.value);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function saveAssignments() {
    const phoneNumber = document.getElementById('phone_number').value;
    const selectedUsers = Array.from(document.getElementById('user_ids').selectedOptions).map((option) => Number(option.value));

    if (!phoneNumber) {
        Swal.fire('Error', 'Select a Twilio number first.', 'error');
        return;
    }

    try {
        const response = await fetch(`${window.laravelApiUrl}/api/twilio/assignments`, {
            method: 'POST',
            headers: twilioHeaders(),
            body: JSON.stringify({
                phone_number: phoneNumber,
                user_ids: selectedUsers
            })
        });
        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Failed to update routing.');
        }
        Swal.fire('Saved', data.message || 'Routing updated.', 'success');
        await loadTwilioData();
        editNumber(phoneNumber);
    } catch (error) {
        Swal.fire('Error', error.message || 'Failed to update routing.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadTwilioData);
</script>
