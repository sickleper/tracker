<?php 
$pageTitle = "Email Leads Inbox";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Email Leads Inbox</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Processed incoming leads from multiple domains.</p>
        </div>
        <div class="flex flex-wrap gap-3 items-center">
            <select id="categoryFilter" class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl px-6 py-3 text-xs font-black uppercase tracking-widest focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm">
                <option value="">All Categories</option>
                <?php 
                $catRes = makeApiCall('/api/leads/categories', ['email_sync_only' => 1]);
                if ($catRes && ($catRes['success'] ?? false)) {
                    foreach ($catRes['data'] as $cat) {
                        echo "<option value='{$cat['id']}'>".htmlspecialchars($cat['category_name'])."</option>";
                    }
                }
                ?>
            </select>
            <select id="statusFilter" class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl px-6 py-3 text-xs font-black uppercase tracking-widest focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm">
                <option value="">All Statuses</option>
                <option value="new">New</option>
                <option value="reviewing">Reviewing</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="archived">Archived</option>
            </select>
            <input type="text" id="searchFilter" placeholder="Search sender, subject, body" class="min-w-[250px] bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl px-6 py-3 text-xs font-black tracking-wider focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm">
            <button id="clearFiltersBtn" class="bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 text-gray-600 dark:text-gray-300 px-6 py-3 rounded-2xl font-black uppercase text-xs tracking-widest transition-all shadow-sm hover:bg-gray-50 dark:hover:bg-slate-800">
                Clear Filters
            </button>
            <button id="fetchEmailsBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-2xl font-black uppercase text-xs tracking-widest transition-all shadow-xl flex items-center gap-2 active:scale-95">
                <i class="fas fa-sync-alt"></i> Sync All Inboxes
            </button>
        </div>
    </div>

    <!-- View Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="email_leads.php" class="border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-inbox"></i> Lead Inbox
            </a>
            <a href="leads_callout_map.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-map-marked-alt"></i> Callout Map
            </a>
            <a href="leads_booking.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-calendar-alt"></i> Scheduler
            </a>
            <a href="leads_visualize.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-chart-pie"></i> Visualizer
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8" id="emailLeadSummary">
        <div class="rounded-3xl border border-blue-100 dark:border-blue-900/30 bg-blue-50 dark:bg-blue-950/20 p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-blue-500 dark:text-blue-300">Filtered Queue</div>
            <div class="mt-3 text-3xl font-black text-blue-900 dark:text-blue-100" id="summaryTotal">0</div>
            <div class="mt-1 text-xs font-bold text-blue-700 dark:text-blue-300">emails on this page</div>
        </div>
        <div class="rounded-3xl border border-amber-100 dark:border-amber-900/30 bg-amber-50 dark:bg-amber-950/20 p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-500 dark:text-amber-300">Needs Review</div>
            <div class="mt-3 text-3xl font-black text-amber-900 dark:text-amber-100" id="summaryReview">0</div>
            <div class="mt-1 text-xs font-bold text-amber-700 dark:text-amber-300">new or reviewing</div>
        </div>
        <div class="rounded-3xl border border-emerald-100 dark:border-emerald-900/30 bg-emerald-50 dark:bg-emerald-950/20 p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-500 dark:text-emerald-300">Converted</div>
            <div class="mt-3 text-3xl font-black text-emerald-900 dark:text-emerald-100" id="summaryConverted">0</div>
            <div class="mt-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">linked to leads</div>
        </div>
        <div class="rounded-3xl border border-rose-100 dark:border-rose-900/30 bg-rose-50 dark:bg-rose-950/20 p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-rose-500 dark:text-rose-300">Aging</div>
            <div class="mt-3 text-3xl font-black text-rose-900 dark:text-rose-100" id="summaryAging">0</div>
            <div class="mt-1 text-xs font-bold text-rose-700 dark:text-rose-300">older than 24h</div>
        </div>
    </div>

    <div id="bulkActionsBar" class="hidden mb-6 rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="text-sm font-bold text-slate-700 dark:text-slate-200">
                <span id="selectedCount">0</span> emails selected
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="bulkReviewBtn" class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-amber-500 hover:text-white transition-all">Bulk Review</button>
                <button id="bulkRejectBtn" class="px-4 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-red-600 hover:text-white transition-all">Bulk Reject</button>
                <button id="bulkArchiveBtn" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-slate-900 hover:text-white transition-all">Bulk Archive</button>
                <button id="clearSelectionBtn" class="px-4 py-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-gray-300 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-50 dark:hover:bg-slate-800 transition-all">Clear Selection</button>
            </div>
        </div>
    </div>

    <div class="card-base mb-8">
        <div class="table-container">
            <table id="emailLeadsTable" class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-center w-12">
                            <input type="checkbox" id="selectAllLeads" class="rounded border-gray-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500">
                        </th>
                        <th class="px-6 py-4 text-left">Category</th>
                        <th class="px-6 py-4 text-left">Received</th>
                        <th class="px-6 py-4 text-left">Contact</th>
                        <th class="px-6 py-4 text-left">Thread</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20" id="emailLeadsBody">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
        <div id="paginationContainer" class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex justify-between items-center"></div>
    </div>
</div>

<!-- Email Content Modal -->
<div id="emailContentModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-sm">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-gray-900 p-6 flex items-center justify-between text-white border-b border-white/10">
                <h3 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3">
                    <i class="fas fa-envelope-open-text text-indigo-400"></i> <span id="modalSubject">Email Details</span>
                </h3>
                <button onclick="closeModal('emailContentModal')" class="w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <div class="p-8 overflow-y-auto flex-1 custom-scrollbar">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 bg-gray-50 dark:bg-slate-950 p-6 rounded-2xl border border-gray-100 dark:border-slate-800">
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">From</div>
                        <div class="font-bold text-gray-900 dark:text-white" id="modalFrom"></div>
                    </div>
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Received At</div>
                        <div class="font-bold text-gray-900 dark:text-white" id="modalDate"></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Category</div>
                        <div class="font-bold text-sm text-gray-900 dark:text-white" id="modalCategory"></div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Status</div>
                        <div class="font-bold text-sm text-gray-900 dark:text-white" id="modalStatus"></div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Thread</div>
                        <div class="font-bold text-sm text-gray-900 dark:text-white" id="modalThread"></div>
                    </div>
                    <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Lead Link</div>
                        <div class="font-bold text-sm text-gray-900 dark:text-white" id="modalLeadLink"></div>
                    </div>
                </div>
                <div id="modalParentThreadWrap" class="hidden rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4 mb-8">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Replying To</div>
                    <div class="font-bold text-sm text-gray-900 dark:text-white" id="modalParentThread"></div>
                </div>
                <div class="prose max-w-none text-gray-700 dark:text-gray-300 dark:prose-invert" id="modalBody"></div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-950 p-6 border-t border-gray-100 dark:border-slate-800 flex flex-wrap justify-end gap-3">
                <button onclick="updateStatus('rejected')" id="rejectBtn" class="px-6 py-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all">Reject</button>
                <button onclick="updateStatus('archived')" class="px-6 py-3 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-900 hover:text-white transition-all">Archive</button>
                <button onclick="updateStatus('reviewing')" class="px-6 py-3 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-amber-500 hover:text-white transition-all">Mark Reviewing</button>
                <button onclick="extractWithAI()" id="extractBtn" class="px-6 py-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all flex items-center gap-2 shadow-sm">
                    <i class="fas fa-magic"></i> Extract with AI
                </button>
                <button onclick="generateAIReply()" id="replyBtn" class="px-6 py-3 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-600 hover:text-white transition-all flex items-center gap-2 shadow-sm">
                    <i class="fas fa-comment-dots"></i> AI Draft Reply
                </button>
                <button onclick="closeModal('emailContentModal')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- AI Reply Draft Modal -->
<div id="replyDraftModal" class="fixed inset-0 z-[110] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-3xl max-h-[85vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-emerald-600 p-6 flex items-center justify-between text-white">
                <h3 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3">
                    <i class="fas fa-robot text-emerald-200"></i> AI Crafted Reply
                </h3>
                <button onclick="closeModal('replyDraftModal')" class="text-white opacity-50 hover:opacity-100">&times;</button>
            </div>
            <div class="p-8 overflow-y-auto flex-1 custom-scrollbar">
                <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-900/50 rounded-xl text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-widest">
                    ⚠️ Review carefully. This is an AI draft based on real availability.
                </div>
                <div id="replyDraftContent" class="p-6 bg-gray-50 dark:bg-slate-950 border border-gray-100 dark:border-slate-800 rounded-2xl text-gray-800 dark:text-gray-200 leading-relaxed font-medium min-h-[300px] whitespace-pre-wrap outline-none focus:ring-2 focus:ring-emerald-500 transition-all" contenteditable="true"></div>
            </div>
            <div class="bg-gray-50 dark:bg-slate-950 p-6 border-t border-gray-100 dark:border-slate-800 flex justify-end gap-3">
                <button id="copyReplyBtn" class="px-6 py-3 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-700 dark:text-gray-300 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-slate-700 transition-all flex items-center gap-2">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button onclick="sendAIReply()" id="sendAIReplyBtn" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all flex items-center gap-2 shadow-lg">
                    <i class="fas fa-paper-plane"></i> Send Now
                </button>
                <button onclick="closeModal('replyDraftModal')" class="px-6 py-3 text-[10px] font-black uppercase tracking-widest text-gray-500">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- AI Extraction Confirmation Modal -->
<div id="extractionModal" class="fixed inset-0 z-[110] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-2xl rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-gray-900 p-6 flex items-center justify-between text-white border-b border-white/10">
                <h3 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3 text-white">
                    <i class="fas fa-robot text-indigo-400"></i> AI Extraction
                </h3>
                <button onclick="closeModal('extractionModal')" class="text-white/50 hover:text-white">&times;</button>
            </div>
            <form id="conversionForm" class="p-8 space-y-6">
                <input type="hidden" name="action" value="convert_email_lead">
                <input type="hidden" name="id" id="convertEmailId">
                <input type="hidden" name="category_id" id="convertCategoryId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Client Name</label>
                        <input type="text" name="client_name" id="ext_name" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Mobile Number</label>
                        <input type="text" name="mobile" id="ext_mobile" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address</label>
                    <input type="email" name="client_email" id="ext_email" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Address</label>
                    <input type="text" name="address" id="ext_address" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Requirements</label>
                    <textarea name="message" id="ext_message" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-medium dark:text-gray-300 outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
                <div id="convertDuplicateWarning" class="hidden rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-300">Duplicate Match Warning</div>
                    <div class="mt-2 text-sm font-medium text-amber-900 dark:text-amber-100" id="convertDuplicateWarningText"></div>
                </div>
                
                <button type="submit" class="w-full py-4 bg-emerald-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-check-double"></i> Confirm & Create Database Lead
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let currentLeads = [];
let currentEmailId = null;
let selectedLeadIds = new Set();

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function renderEmailPreview(body) {
    const raw = String(body || '').trim();
    if (!raw) {
        return '<p class="text-sm text-gray-500 dark:text-gray-400">No email body available.</p>';
    }

    const looksLikeHtml = /<\/?[a-z][\s\S]*>/i.test(raw) || /&nbsp;|&amp;|&#\d+;|&#x[0-9a-f]+;/i.test(raw);
    if (!looksLikeHtml) {
        return escapeHtml(raw).replace(/\n/g, '<br>');
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(raw, 'text/html');

    doc.querySelectorAll('script, style, iframe, object, embed, link, meta, base, form').forEach(el => el.remove());
    doc.querySelectorAll('*').forEach(el => {
        [...el.attributes].forEach(attr => {
            const name = attr.name.toLowerCase();
            const value = String(attr.value || '');
            if (name.startsWith('on')) {
                el.removeAttribute(attr.name);
                return;
            }
            if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value)) {
                el.removeAttribute(attr.name);
                return;
            }
            if (name === 'style') {
                el.removeAttribute(attr.name);
            }
        });
    });

    const html = (doc.body && doc.body.innerHTML ? doc.body.innerHTML : '').trim();
    if (html) {
        return html;
    }

    return escapeHtml(doc.body?.textContent || raw).replace(/\n/g, '<br>');
}

// Function to determine SweetAlert2 theme based on dark mode
function getSwalTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
}

function getStatusBadge(lead) {
    const status = String(lead.status || 'new').toLowerCase();
    if (status === 'new') {
        return '<span class="px-2 py-1 bg-blue-100 dark:bg-indigo-900/30 text-blue-700 dark:text-indigo-400 text-[9px] font-black rounded uppercase tracking-widest border border-indigo-100/50">New</span>';
    }
    if (status === 'reviewing') {
        return '<span class="px-2 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[9px] font-black rounded uppercase tracking-widest border border-amber-100/50">Reviewing</span>';
    }
    if (status === 'rejected') {
        return '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-[9px] font-black rounded uppercase tracking-widest border border-red-100/50">Rejected</span>';
    }
    if (status === 'archived') {
        return '<span class="px-2 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[9px] font-black rounded uppercase tracking-widest border border-slate-200 dark:border-slate-700">Archived</span>';
    }
    return '<span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[9px] font-black rounded uppercase tracking-widest border border-emerald-100/50">Approved</span>';
}

function getAgeBadge(lead) {
    const ageHours = Number(lead.age_hours || 0);
    if (ageHours >= 48) {
        return '<span class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-[9px] font-black rounded uppercase tracking-widest border border-red-100/50">48h+</span>';
    }
    if (ageHours >= 24) {
        return '<span class="px-2 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[9px] font-black rounded uppercase tracking-widest border border-amber-100/50">24h+</span>';
    }
    return '<span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[9px] font-black rounded uppercase tracking-widest border border-emerald-100/50">Fresh</span>';
}

function updateSummary(leads) {
    const total = leads.length;
    const review = leads.filter(lead => ['new', 'reviewing'].includes(String(lead.status || 'new').toLowerCase())).length;
    const converted = leads.filter(lead => !!lead.is_converted).length;
    const aging = leads.filter(lead => Number(lead.age_hours || 0) >= 24).length;

    $('#summaryTotal').text(total);
    $('#summaryReview').text(review);
    $('#summaryConverted').text(converted);
    $('#summaryAging').text(aging);
}

function syncBulkBar() {
    const count = selectedLeadIds.size;
    $('#selectedCount').text(count);
    $('#bulkActionsBar').toggleClass('hidden', count === 0);
    $('#selectAllLeads').prop('checked', count > 0 && currentLeads.length > 0 && currentLeads.every(lead => selectedLeadIds.has(String(lead.id))));
}

function clearSelection() {
    selectedLeadIds.clear();
    $('.lead-select-row').prop('checked', false);
    syncBulkBar();
}

function quickStatusUpdate(id, status, event) {
    if (event) {
        event.stopPropagation();
    }

    $.ajax({
        url: 'leads_handler.php',
        method: 'POST',
        data: { action: 'update_email_lead_status', id, status },
        success: function(res) {
            if (res.success) {
                loadEmailLeads();
            } else {
                Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to update status.', theme: getSwalTheme() });
            }
        },
        error: function() {
            Swal.fire({ icon:'error', title:'Error', text: 'Failed to update status.', theme: getSwalTheme() });
        }
    });
}

function bulkStatusUpdate(status) {
    const ids = Array.from(selectedLeadIds);
    if (!ids.length) return;

    Swal.fire({
        title: 'Applying Bulk Action...',
        text: `Updating ${ids.length} emails`,
        allowOutsideClick: false,
        theme: getSwalTheme(),
        didOpen: () => {
            Swal.showLoading();
            const requests = ids.map(id => $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: { action: 'update_email_lead_status', id, status }
            }));

            Promise.allSettled(requests).then(results => {
                Swal.close();
                const failed = results.filter(r => r.status !== 'fulfilled' || !r.value?.success).length;
                clearSelection();
                loadEmailLeads();
                if (failed) {
                    Swal.fire({ icon:'warning', title:'Partial Update', text: `${failed} email(s) failed to update.`, theme: getSwalTheme() });
                }
            });
        }
    });
}

function loadEmailLeads(page = 1) {
    $('#emailLeadsBody').html('<tr><td colspan="7" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Emails...</p></div></td></tr>');
    
    const categoryId = $('#categoryFilter').val();
    const status = $('#statusFilter').val();
    const search = $('#searchFilter').val();
    $.getJSON(`leads_handler.php?action=list_email_leads&page=${page}&category_id=${encodeURIComponent(categoryId || '')}&status=${encodeURIComponent(status || '')}&search=${encodeURIComponent(search || '')}`, function(res) {
        if (res.success) {
            currentLeads = res.data.data;
            updateSummary(currentLeads);
            let html = '';
            
            if (currentLeads.length === 0) {
                html = '<tr><td colspan="7" class="p-20 text-center text-gray-400 italic">No email leads found for the current filters.</td></tr>';
            } else {
                currentLeads.forEach(lead => {
                    const safeCategoryName = escapeHtml(lead.category_name || '');
                    const safeFromName = escapeHtml(lead.from_name || '');
                    const safeFromEmail = escapeHtml(lead.from_email || '');
                    const safeSubject = escapeHtml(lead.subject || '');
                    const safePreview = escapeHtml(lead.body_preview || '');
                    const safeDomain = escapeHtml(lead.from_domain || '');
                    const statusBadge = getStatusBadge(lead);
                    const ageBadge = getAgeBadge(lead);
                    const threadBits = [];
                    if (lead.has_thread) threadBits.push(`<span class="px-2 py-1 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[9px] font-black rounded uppercase tracking-widest">Thread ${escapeHtml(String(lead.reply_count || 0))}</span>`);
                    if (lead.parent_subject) threadBits.push(`<span class="px-2 py-1 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-300 text-[9px] font-black rounded uppercase tracking-widest">Reply</span>`);
                    if (lead.is_converted) threadBits.push('<span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[9px] font-black rounded uppercase tracking-widest">Linked Lead</span>');
                    if (lead.has_duplicate_match) threadBits.push(`<span class="px-2 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-[9px] font-black rounded uppercase tracking-widest">Match Lead #${escapeHtml(String(lead.matched_lead_id || ''))}</span>`);

                    html += `
                        <tr class="table-row-hover cursor-pointer" onclick="viewEmail(${lead.id})">
                            <td class="px-6 py-4 text-center" onclick="event.stopPropagation()">
                                <input type="checkbox" class="lead-select-row rounded border-gray-300 dark:border-slate-700 text-indigo-600 focus:ring-indigo-500" data-id="${lead.id}" ${selectedLeadIds.has(String(lead.id)) ? 'checked' : ''}>
                            </td>
                            <td class="px-6 py-4 font-black text-gray-900 dark:text-indigo-400 italic uppercase text-[10px]">${safeCategoryName}</td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                                <div>${moment(lead.received_at).format('DD MMM, HH:mm')}</div>
                                <div class="mt-2">${ageBadge}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-900 dark:text-gray-100 leading-tight">${safeFromName}</div>
                                <div class="text-[10px] text-gray-400 font-medium">${safeFromEmail}</div>
                                <div class="text-[10px] text-gray-400 font-medium uppercase tracking-widest mt-1">${safeDomain}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-gray-700 dark:text-gray-200 truncate max-w-xs" title="${safeSubject}">${safeSubject}</div>
                                <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-1 max-w-md truncate" title="${safePreview}">${safePreview}</div>
                                <div class="flex flex-wrap gap-1 mt-2">${threadBits.join('')}</div>
                            </td>
                            <td class="px-6 py-4 text-center">${statusBadge}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="quickStatusUpdate(${lead.id}, 'reviewing', event)" class="px-3 py-2 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-amber-500 hover:text-white transition-all">Review</button>
                                    <button onclick="quickStatusUpdate(${lead.id}, 'rejected', event)" class="px-3 py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-red-600 hover:text-white transition-all">Reject</button>
                                    <button onclick="quickStatusUpdate(${lead.id}, 'archived', event)" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-xl font-black uppercase text-[9px] tracking-widest hover:bg-slate-900 hover:text-white transition-all">Archive</button>
                                    <button onclick="viewEmail(${lead.id}); event.stopPropagation();" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">Open</button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            $('#emailLeadsBody').html(html);
            syncBulkBar();
            renderPagination(res.data);
        } else {
            $('#emailLeadsBody').html('<tr><td colspan="7" class="p-20 text-center text-red-500 italic">Failed to load email leads.</td></tr>');
            Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to load email leads.', theme: getSwalTheme() });
        }
    }).fail(function() {
        updateSummary([]);
        clearSelection();
        $('#emailLeadsBody').html('<tr><td colspan="7" class="p-20 text-center text-red-500 italic">Failed to load email leads.</td></tr>');
        Swal.fire({ icon:'error', title:'Error', text: 'Failed to load email leads.', theme: getSwalTheme() });
    });
}

function renderPagination(data) {
    let html = `
        <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">
            Showing ${data.from || 0} to ${data.to || 0} of ${data.total} leads
        </div>
        <div class="flex gap-2">
    `;
    
    if (data.prev_page_url) {
        html += `<button onclick="loadEmailLeads(${data.current_page - 1})" class="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-slate-700 transition-all shadow-sm">Prev</button>`;
    }
    
    if (data.next_page_url) {
        html += `<button onclick="loadEmailLeads(${data.current_page + 1})" class="px-4 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-slate-700 transition-all shadow-sm">Next</button>`;
    }
    
    html += '</div>';
    $('#paginationContainer').html(html);
}

function viewEmail(id) {
    const lead = currentLeads.find(l => l.id == id);
    if (!lead) return;
    
    currentEmailId = id;
    $('#modalSubject').text(lead.subject);
    $('#modalFrom').text(`${lead.from_name} <${lead.from_email}>`);
    $('#modalDate').text(moment(lead.received_at).format('LLLL'));
    $('#modalCategory').text(lead.category_name || 'Uncategorised');
    $('#modalStatus').text(lead.status_label || 'New');
    $('#modalThread').text(lead.has_thread ? `${lead.reply_count || 0} replies linked` : 'Standalone email');
    if (lead.parent_subject) {
        $('#modalParentThread').text(lead.parent_subject);
        $('#modalParentThreadWrap').removeClass('hidden');
    } else {
        $('#modalParentThread').text('');
        $('#modalParentThreadWrap').addClass('hidden');
    }
    if (lead.is_converted) {
        $('#modalLeadLink').html(`<a href="leads.php?lead_id=${lead.lead_id}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Lead #${lead.lead_id}</a>`);
    } else if (lead.has_duplicate_match) {
        $('#modalLeadLink').html(`<span class="text-amber-600 dark:text-amber-300">Possible duplicate: Lead #${lead.matched_lead_id} ${escapeHtml(lead.matched_lead_name || '')}</span>`);
    } else {
        $('#modalLeadLink').html('<span class="text-gray-400">Not converted</span>');
    }
    $('#modalBody').html(renderEmailPreview(lead.body || ''));
    
    $('#emailContentModal').removeClass('hidden');
}

function updateStatus(status) {
    if (!currentEmailId) return;
    
    $.ajax({
        url: 'leads_handler.php',
        method: 'POST',
        data: { action: 'update_email_lead_status', id: currentEmailId, status: status },
        success: function(res) {
            if (res.success) {
                closeModal('emailContentModal');
                loadEmailLeads();
            } else {
                Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to update status.', theme: getSwalTheme() });
            }
        },
        error: function() {
            Swal.fire({ icon:'error', title:'Error', text: 'Failed to update status.', theme: getSwalTheme() });
        }
    });
}

function extractWithAI() {
    if (!currentEmailId) return;
    const lead = currentLeads.find(l => l.id == currentEmailId);
    
    Swal.fire({
        title: 'AI Extraction',
        text: 'Analyzing email content...',
        allowOutsideClick: false,
        theme: getSwalTheme(),
        didOpen: () => {
            Swal.showLoading();
            $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: { action: 'extract_email_lead', id: currentEmailId },
                success: function(res) {
                    Swal.close();
                    if (res.success) {
                        const data = res.data;
                        $('#convertEmailId').val(currentEmailId);
                        $('#convertCategoryId').val(lead.project_category_id);
                        $('#ext_name').val(data.client_name || '');
                        $('#ext_email').val(data.client_email || lead.from_email);
                        $('#ext_mobile').val(data.client_mobile || '');
                        $('#ext_address').val(data.client_address || '');
                        $('#ext_message').val(data.message || '');
                        if (lead.has_duplicate_match) {
                            $('#convertDuplicateWarningText').html(`Existing lead match found: <a href="leads.php?lead_id=${lead.matched_lead_id}" class="text-amber-700 dark:text-amber-300 underline">Lead #${escapeHtml(String(lead.matched_lead_id))} ${escapeHtml(lead.matched_lead_name || '')}</a>. Saving will link this email to that lead instead of creating a duplicate.`);
                            $('#convertDuplicateWarning').removeClass('hidden');
                        } else {
                            $('#convertDuplicateWarning').addClass('hidden');
                            $('#convertDuplicateWarningText').html('');
                        }
                        $('#extractionModal').removeClass('hidden');
                    } else {
                        Swal.fire({ icon:'error', title:'Error', text: res.message || 'AI failed to parse email.', theme: getSwalTheme() });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon:'error', title:'Error', text: 'AI extraction request failed.', theme: getSwalTheme() });
                }
            });
        }
    });
}

function generateAIReply() {
    if (!currentEmailId) return;
    const lead = currentLeads.find(l => l.id == currentEmailId);
    
    Swal.fire({
        title: 'AI Drafting...',
        text: 'Checking availability and style guides...',
        allowOutsideClick: false,
        theme: getSwalTheme(),
        didOpen: () => {
            Swal.showLoading();
            $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: { action: 'generate_ai_reply', email_id: currentEmailId, lead_id: lead.lead_id },
                success: function(res) {
                    Swal.close();
                    if (res.success) {
                        $('#replyDraftContent').text(res.reply || '');
                        $('#replyDraftModal').removeClass('hidden');
                    } else {
                        Swal.fire({ icon:'error', title:'Drafting Failed', text: res.message || 'AI failed to generate a reply.', theme: getSwalTheme() });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon:'error', title:'Drafting Failed', text: 'AI reply request failed.', theme: getSwalTheme() });
                }
            });
        }
    });
}

function sendAIReply() {
    if (!currentEmailId) return;
    const lead = currentLeads.find(l => l.id == currentEmailId);
    const content = $('#replyDraftContent').text();
    
    Swal.fire({
        title: 'Sending Reply...',
        text: 'Dispatching email and syncing Sent folder...',
        allowOutsideClick: false,
        theme: getSwalTheme(),
        didOpen: () => {
            Swal.showLoading();
            $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: { action: 'send_email', email_id: currentEmailId, recipient: lead.from_email, subject: 'Re: ' + lead.subject, message: content },
                success: function(res) {
                    Swal.close();
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Sent!', text: 'Reply dispatched.', timer: 2000, showConfirmButton: false, theme: getSwalTheme() });
                        closeModal('replyDraftModal');
                        closeModal('emailContentModal');
                        loadEmailLeads();
                    } else {
                        Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to send.', theme: getSwalTheme() });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({ icon:'error', title:'Error', text: 'Failed to send.', theme: getSwalTheme() });
                }
            });
        }
    });
}

function closeModal(id) { $(`#${id}`).addClass('hidden'); }

$(document).ready(function() {
    loadEmailLeads();
    $('#copyReplyBtn').click(function() {
        const text = $('#replyDraftContent').text();
        navigator.clipboard.writeText(text).then(() => {
            Swal.fire({ icon: 'success', title: 'Copied!', text: 'Draft copied to clipboard.', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
        });
    });
    
    $('#categoryFilter').change(() => loadEmailLeads(1));
    $('#statusFilter').change(() => loadEmailLeads(1));
    $('#selectAllLeads').on('change', function() {
        if (this.checked) {
            currentLeads.forEach(lead => selectedLeadIds.add(String(lead.id)));
            $('.lead-select-row').prop('checked', true);
        } else {
            currentLeads.forEach(lead => selectedLeadIds.delete(String(lead.id)));
            $('.lead-select-row').prop('checked', false);
        }
        syncBulkBar();
    });
    $(document).on('change', '.lead-select-row', function() {
        const id = String($(this).data('id'));
        if (this.checked) {
            selectedLeadIds.add(id);
        } else {
            selectedLeadIds.delete(id);
        }
        syncBulkBar();
    });
    $('#bulkReviewBtn').on('click', () => bulkStatusUpdate('reviewing'));
    $('#bulkRejectBtn').on('click', () => bulkStatusUpdate('rejected'));
    $('#bulkArchiveBtn').on('click', () => bulkStatusUpdate('archived'));
    $('#clearSelectionBtn').on('click', clearSelection);
    $('#clearFiltersBtn').on('click', function() {
        $('#categoryFilter').val('');
        $('#statusFilter').val('');
        $('#searchFilter').val('');
        loadEmailLeads(1);
    });
    $('#searchFilter').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadEmailLeads(1);
        }
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal('emailContentModal');
            closeModal('replyDraftModal');
            closeModal('extractionModal');
        }
    });
    
    $('#conversionForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).addClass('opacity-50');
        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    const title = res.deduped ? 'Existing Lead Linked' : 'Lead Created!';
                    const text = res.deduped ? (res.message || 'Matched to an existing lead.') : 'Added to database.';
                    Swal.fire({ icon: 'success', title, text, timer: 2200, showConfirmButton: false, theme: getSwalTheme() });
                    closeModal('extractionModal'); closeModal('emailContentModal'); loadEmailLeads();
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: res.message, theme: getSwalTheme() });
                    btn.prop('disabled', false).removeClass('opacity-50');
                }
            },
            error: function() {
                Swal.fire({ icon:'error', title:'Error', text: 'Failed to create lead.', theme: getSwalTheme() });
                btn.prop('disabled', false).removeClass('opacity-50');
            }
        });
    });

    $('#fetchEmailsBtn').click(function() {
        const btn = $(this);
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin mr-2"></i> Syncing...');
        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            data: { action: 'fetch_email_leads', category_id: $('#categoryFilter').val() },
            success: function(res) {
                btn.prop('disabled', false).html(originalHtml);
                if (res.success) {
                    Swal.fire({ title: 'Sync Complete', icon: 'success', theme: getSwalTheme() });
                    loadEmailLeads(1);
                } else { Swal.fire({ icon:'error', title:'Sync Failed', text: res.message, theme: getSwalTheme() }); }
            },
            error: () => { btn.prop('disabled', false).html(originalHtml); Swal.fire({ icon:'error', title:'Error', text: 'API connection failed.', theme: getSwalTheme() }); }
        });
    });
});
</script>

<?php include '../footer.php'; ?>
