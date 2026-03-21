<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: oauth2callback.php');
    exit();
}

$pageTitle = "Gmail Lead Inbox";
include_once "header.php";
include_once "nav.php";

$gmailLookbackDays = (int) ($GLOBALS['gmail_workorder_lookback_days'] ?? 2);
if ($gmailLookbackDays < 1) {
    $gmailLookbackDays = 1;
} elseif ($gmailLookbackDays > 30) {
    $gmailLookbackDays = 30;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Header & Main Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="heading-brand">
                <i class="fab fa-google text-red-500 mr-2"></i> Gmail Work Orders
            </h1>
            <p class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">
                <i class="far fa-clock"></i> Last checked:
                <span id="last-checked-time" class="text-indigo-600 dark:text-indigo-400"><?php echo date('g:i:s a'); ?></span>
                <span class="ml-2 opacity-50">(Scanning last <?php echo $gmailLookbackDays; ?> day<?php echo $gmailLookbackDays === 1 ? '' : 's'; ?>, auto-refresh every 30m)</span>
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <button onclick="fetchEmails()" class="btn-secondary py-2 px-4 shadow-none">
                <i class="fas fa-sync-alt"></i> Refresh List
            </button>
            <button onclick="clearCache()" class="btn-secondary dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/50 py-2 px-4 shadow-none">
                <i class="fas fa-trash-alt"></i> Clear AI Cache
            </button>
            <a href="index.php" class="btn-primary bg-slate-900 hover:bg-slate-800 py-2 px-4 shadow-none">
                <i class="fas fa-arrow-left"></i> Back to Tracker
            </a>
        </div>
    </div>

    <div class="mb-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/20 p-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-300">Rules Filter Active</div>
                <p class="mt-2 text-sm font-medium text-indigo-900 dark:text-indigo-100">
                    This list only shows emails from the last <?php echo $gmailLookbackDays; ?> day<?php echo $gmailLookbackDays === 1 ? '' : 's'; ?> that match a client sender/domain rule and produce a work order number from the subject.
                </p>
            </div>
            <div id="rules-match-count" class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-500 dark:text-indigo-300">
                Checking mailbox...
            </div>
        </div>
    </div>

    <div id="email-content-area" class="min-h-[400px]">
        <!-- Loader -->
        <div id="email-loader" class="hidden py-20 text-center">
            <div class="inline-block animate-spin w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full mb-4"></div>
            <p class="text-sm font-black uppercase tracking-widest text-gray-400">AI is reading emails...</p>
        </div>

        <!-- Error Message Area -->
        <div id="error-message-area" class="hidden card-base bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900/50 p-6 mb-8 text-center" role="alert">
            <i class="fas fa-exclamation-circle text-red-500 text-3xl mb-3"></i>
            <h3 class="text-lg font-black text-red-700 dark:text-red-400 uppercase tracking-tighter">Connection Error</h3>
            <p id="error-message-text" class="text-sm text-red-600 dark:text-red-300 font-medium mt-1"></p>
        </div>

        <!-- Email List -->
        <div id="grouped-emails-list" class="space-y-12"></div>
    </div>
</div>

<!-- Import Modal -->
<div id="import-modal" class="hidden fixed inset-0 z-[150] overflow-y-auto bg-black/60 backdrop-blur-sm">
    <div class="flex items-center justify-center min-h-screen p-0 md:p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-5xl h-full md:h-[90vh] flex flex-col rounded-none md:rounded-3xl shadow-2xl overflow-hidden" onclick="event.stopPropagation()">
            <div class="bg-indigo-600 p-6 flex justify-between items-center text-white">
                <h3 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3">
                    <i class="fas fa-file-import text-indigo-200"></i> Import Work Order
                </h3>
                <button type="button" onclick="closeImportModal(false)" class="w-10 h-10 flex items-center justify-center bg-black/10 hover:bg-black/20 rounded-full transition-all">&times;</button>
            </div>
            <div class="flex-1 w-full bg-gray-100 dark:bg-slate-950 overflow-hidden">
                <iframe id="import-frame" src="" class="w-full h-full border-none"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
    // --- DOM refs ---
    const emailLoader       = document.getElementById('email-loader');
    const errorMessageArea  = document.getElementById('error-message-area');
    const errorMessageText  = document.getElementById('error-message-text');
    const groupedEmailsList = document.getElementById('grouped-emails-list');
    const lastCheckedTime   = document.getElementById('last-checked-time');
    const rulesMatchCount   = document.getElementById('rules-match-count');

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
    }

    function safeB64(str) {
        return btoa(unescape(encodeURIComponent(str)));
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        const text = String(value);
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function hasValue(v) {
        return v !== null && v !== undefined && v !== '' && v !== false;
    }

    async function fetchEmails() {
        emailLoader.classList.remove('hidden');
        errorMessageArea.classList.add('hidden');
        groupedEmailsList.innerHTML = '';

        try {
            const response = await fetch('ajax_gmail_orders.php');
            if (!response.ok) throw new Error(`Server returned ${response.status} ${response.statusText}`);
            const data = await response.json();

            if (data.success) {
                renderEmails(data.emails, data.count || 0);
                lastCheckedTime.textContent = new Date().toLocaleTimeString('en-GB', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            } else {
                showError(data.error || 'Unknown error occurred.');
            }
        } catch (error) {
            showError('Network error: ' + error.message);
        } finally {
            emailLoader.classList.add('hidden');
        }
    }

    function startAutoRefresh() {
        fetchEmails();
        setInterval(fetchEmails, 30 * 60 * 1000);
    }

    function showError(msg) {
        errorMessageText.textContent = msg;
        errorMessageArea.classList.remove('hidden');
        if (rulesMatchCount) {
            rulesMatchCount.textContent = 'Mailbox check failed';
        }
    }

    function renderEmails(emails, count) {
        if (rulesMatchCount) {
            rulesMatchCount.textContent = `${count || 0} rule-matched email${(count || 0) !== 1 ? 's' : ''}`;
        }

        if (!emails || emails.length === 0) {
            groupedEmailsList.innerHTML = `
                <div class="card-base p-20 text-center text-gray-400">
                    <i class="fas fa-filter-circle-xmark text-6xl mb-4 opacity-20"></i>
                    <p class="font-black uppercase tracking-[0.2em] text-xs">No Work Orders Matched</p>
                    <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">No emails currently match the configured sender/domain and subject work-order rules.</p>
                </div>`;
            return;
        }

        const grouped = {};
        emails.forEach(email => {
            const clientName = email.client_name || email.client_company || 'Unknown Client';
            if (!grouped[clientName]) grouped[clientName] = [];
            grouped[clientName].push(email);
        });

        let html = '';
        for (const clientName in grouped) {
            const count = grouped[clientName].length;
            html += `
                <div class="client-group">
                    <div class="flex items-center justify-between mb-6 border-b border-indigo-100 dark:border-indigo-900/50 pb-4">
                        <h2 class="text-xl font-black italic uppercase tracking-tighter text-indigo-900 dark:text-indigo-400 flex items-center gap-3">
                            <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-sm"><i class="fas fa-building"></i></div>
                            ${escapeHtml(clientName)}
                        </h2>
                        <span class="px-4 py-1 bg-indigo-600 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-lg shadow-indigo-200/50">
                            ${count} Active Order${count !== 1 ? 's' : ''}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        ${grouped[clientName].map(renderEmailCard).join('')}
                    </div>
                </div>`;
        }
        groupedEmailsList.innerHTML = html;
    }

    function renderEmailCard(email) {
        const ai = email.ai_data || {};
        const matchedRule = email.matched_rule || {};
        const po = email.po || ai.job_code || '';
        const exists = !!email.po_exists;
        const poDisplay = po || 'PO REQUIRED';

        let contactDisplay = email.client_phone || '';
        if (!contactDisplay) {
            const c = ai.job_contact;
            contactDisplay = Array.isArray(c) ? [(c.name || ''), (c.phone || '')].filter(Boolean).join(' ') : (c || '');
        }

        const location = ai.job_location || 'Location Pending';
        const desc     = ai.job_description || (email.message ? email.message.substring(0, 150) + '…' : '');
        const pdfList  = Array.isArray(email.pdfs) ? email.pdfs : [];
        const matchedRuleSummary = matchedRule.summary || 'client portal rules';
        const matchedRuleDetail = [
            matchedRule.sender ? `Sender: ${matchedRule.sender}` : null,
            matchedRule.subject_pattern ? `Subject: ${matchedRule.subject_pattern}` : null,
            matchedRule.pdf_profile ? `PDF: ${matchedRule.pdf_profile}` : null
        ].filter(Boolean).join(' | ');
        
        const pdfsHtml = pdfList.map(pdf => {
            const isNew = (pdf.source === 'email' || !pdf.source);
            const cls   = isNew ? 'bg-red-50 text-red-700 border-red-100' : 'bg-blue-50 text-blue-700 border-blue-100';
            return `
                <a href="${escapeHtml(pdf.link)}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2 px-3 py-1.5 rounded-xl border ${cls} text-[10px] font-black uppercase tracking-widest hover:scale-105 transition-transform">
                    <i class="fas fa-file-pdf"></i> ${escapeHtml(pdf.name)}
                </a>`;
        }).join('');

        const importParams = {
            po_number: po, property_code: ai.job_code || '', eircode: ai.eircode || '', heading: desc,
            property: location, client_id: email.client_id, contact: contactDisplay, priority: ai.priority || 'Medium',
            openingDate: email.date ? new Date(email.date).toISOString().slice(0, 10) : new Date().toISOString().slice(0, 10),
            dateBooked: ai.date_booked || '', nextVisit: ai.next_visit || '', uid: email.id, pdfs: JSON.stringify(pdfList)
        };
        
        const dataB64 = encodeURIComponent(safeB64(JSON.stringify(importParams)));
        const importUrl = `gmail_import_modal.php?data=${dataB64}`;
        const updateUrl = exists ? `gmail_import_modal.php?id=${email.existing_task_id}&data=${dataB64}` : importUrl;

        const timeStr = email.date ? new Date(email.date).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '';

        return `
            <div class="card-base border-l-4 ${exists ? 'border-amber-400' : 'border-emerald-500'} group">
                <div class="flex flex-col lg:flex-row">
                    <div class="flex-grow p-6 lg:p-8 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-900 dark:text-white text-[10px] font-black rounded uppercase tracking-widest">${escapeHtml(poDisplay)}</span>
                                ${exists ? `<span class="px-2 py-1 bg-amber-100 text-amber-700 text-[9px] font-black rounded uppercase tracking-widest">RECORD EXISTS</span>` : `<span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[9px] font-black rounded uppercase tracking-widest animate-pulse">NEW JOB</span>`}
                            </div>
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">${timeStr}</span>
                        </div>

                        <h3 class="text-xl font-black text-gray-900 dark:text-white italic tracking-tight leading-tight">${escapeHtml(email.subject)}</h3>

                        <div class="rounded-2xl border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50 dark:bg-indigo-950/20 px-4 py-3">
                            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.2em] text-indigo-600 dark:text-indigo-300">
                                <i class="fas fa-filter"></i>
                                <span>Matched By ${escapeHtml(matchedRuleSummary)}</span>
                            </div>
                            ${matchedRuleDetail ? `<div class="mt-2 text-xs font-medium text-indigo-900 dark:text-indigo-100">${escapeHtml(matchedRuleDetail)}</div>` : ''}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm font-medium">
                            <div class="space-y-2">
                                <p class="flex items-start gap-3"><i class="fas fa-map-marker-alt text-red-400 mt-1"></i> <span class="text-gray-700 dark:text-gray-300">${escapeHtml(location)}</span></p>
                                ${hasValue(ai.job_code) ? `<p class="flex items-center gap-3 font-mono text-[11px] text-indigo-600 dark:text-indigo-400"><i class="fas fa-key opacity-50"></i> <span>CODE: ${escapeHtml(ai.job_code)}</span></p>` : ''}
                            </div>
                            <div class="space-y-2">
                                ${hasValue(contactDisplay) ? `<p class="flex items-center gap-3 text-emerald-600 dark:text-emerald-400"><i class="fas fa-phone-alt opacity-50"></i> <span>${escapeHtml(contactDisplay)}</span></p>` : ''}
                                ${hasValue(ai.eircode) ? `<p class="flex items-center gap-3 font-mono text-xs text-gray-500 uppercase"><i class="fas fa-map opacity-50"></i> <span>${escapeHtml(ai.eircode)}</span></p>` : ''}
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-slate-900/50 p-4 rounded-2xl border border-gray-100 dark:border-slate-800 text-sm text-gray-600 dark:text-gray-400 italic">
                            "${escapeHtml(desc)}"
                        </div>

                        ${pdfList.length > 0 ? `<div class="flex flex-wrap gap-2 pt-2">${pdfsHtml}</div>` : ''}
                    </div>

                    <div class="w-full lg:w-48 bg-gray-50 dark:bg-slate-900/30 p-6 lg:border-l border-gray-100 dark:border-slate-800 flex flex-row lg:flex-col gap-3">
                        <button onclick='openImportModal(event, "${escapeHtml(updateUrl)}")' class="flex-1 py-3 ${exists ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-700'} text-white rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all shadow-lg active:scale-95">
                            <i class="fas ${exists ? 'fa-edit' : 'fa-file-import'} mb-1 block text-sm"></i> ${exists ? 'Update' : 'Import'}
                        </button>
                        <a href="${escapeHtml(email.gmail_link)}" target="_blank" rel="noopener noreferrer" class="flex-1 py-3 bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl border border-gray-200 dark:border-slate-700 font-black uppercase text-[10px] tracking-widest text-center hover:bg-gray-100 dark:hover:bg-slate-700 transition-all">
                            <i class="fab fa-google mb-1 block text-sm"></i> View
                        </a>
                    </div>
                </div>
            </div>`;
    }

    function clearCache() {
        Swal.fire({
            title: 'Reset AI Memory?',
            text: "This forces the system to re-analyze all emails. Use this if current data extraction looks incorrect.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Yes, Reset Now',
            theme: getSwalTheme()
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            try {
                const response = await fetch('clear_gmail_cache.php');
                const data = await response.json();
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Cache Cleared', timer: 1500, showConfirmButton: false, theme: getSwalTheme() }).then(() => fetchEmails());
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to reach server.', theme: getSwalTheme() });
            }
        });
    }

    function openImportModal(event, url) {
        if (event) { event.preventDefault(); event.stopPropagation(); }
        document.getElementById('import-frame').src = url;
        document.getElementById('import-modal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        return false;
    }

    function closeImportModal(shouldReload = false) {
        document.getElementById('import-modal').classList.add('hidden');
        document.getElementById('import-frame').src = '';
        document.body.classList.remove('overflow-hidden');
        if (shouldReload) fetchEmails();
    }

    window.addEventListener('message', function (event) {
        if (event.data === 'order_saved' || event.data.type === 'order_saved') {
            Swal.fire({ icon: 'success', title: 'Import Successful', timer: 2000, showConfirmButton: false, theme: getSwalTheme() }).then(() => closeImportModal(true));
        }
    });

    document.addEventListener('DOMContentLoaded', startAutoRefresh);
</script>

<?php include_once "footer.php"; ?>
