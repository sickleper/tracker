<?php
require_once "../config.php";
require_once "../tracker_data.php";

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($projectId <= 0) {
    header('Location: projects_list.php');
    exit();
}

$pageTitle = "Project Workspace";
include_once "../header.php";
include_once "../nav.php";

$categories = [];
$catRes = makeApiCall("/api/leads/categories?all=1");
if ($catRes && isset($catRes['data'])) {
    $categories = $catRes['data'];
}
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Navigation / Breadcrumbs -->
    <div class="flex items-center gap-4 mb-8">
        <a href="projects_list.php" class="w-10 h-10 flex items-center justify-center bg-white dark:bg-slate-900 rounded-xl border border-gray-100 dark:border-slate-800 text-gray-400 hover:text-indigo-600 transition-all">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 id="projectHeaderName" class="text-2xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white">Loading Project...</h1>
            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-500 flex items-center gap-2 mt-1">
                <i class="fas fa-project-diagram opacity-50"></i> Project Workspace <span id="projectHeaderCode" class="opacity-50"></span>
            </p>
        </div>
    </div>

    <!-- Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Left Column: Details & Milestones (8 Units) -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Core Project Info Card -->
            <div class="card-base border-none p-8 md:p-10">
                <div class="flex flex-col md:flex-row justify-between gap-8">
                    <div class="flex-grow space-y-6">
                        <div class="flex justify-between items-start">
                            <div id="projectStatusBadgeContainer"></div>
                            <button onclick="openEditProjectModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg active:scale-95">
                                <i class="fas fa-edit mr-1"></i> Edit Project
                            </button>
                        </div>
                        <h2 id="displayProjectName" class="text-3xl font-black text-gray-900 dark:text-white leading-tight"></h2>
                        <p id="displayProjectSummary" class="text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl"></p>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 pt-4 border-t border-gray-50 dark:border-slate-800">
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Start Date</p>
                                <p id="displayStartDate" class="font-bold text-gray-900 dark:text-gray-200 text-sm italic"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Target Deadline</p>
                                <p id="displayDeadline" class="font-bold text-red-500 text-sm italic"></p>
                            </div>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Source Lead</p>
                                <div id="displayLeadLink"></div>
                            </div>
                            <div>
                                <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Signed Proposal</p>
                                <div id="displayProposalLink"></div>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-50 dark:border-slate-800">
                            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">Project Site Location</p>
                            <div class="flex items-center gap-3">
                                <p id="displayLocation" class="font-bold text-gray-700 dark:text-gray-300 text-sm"></p>
                                <div id="displayLatLng" class="text-[10px] font-mono text-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20 px-2 py-0.5 rounded"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="md:w-48 flex flex-col items-center justify-center p-6 bg-gray-50 dark:bg-slate-950 rounded-[2rem] border border-gray-100 dark:border-slate-800">
                        <div class="relative w-24 h-24 flex items-center justify-center mb-4">
                            <svg class="w-full h-full -rotate-90">
                                <circle cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-200 dark:text-slate-800" />
                                <circle id="progressCircle" cx="48" cy="48" r="40" stroke="currentColor" stroke-width="8" fill="transparent" stroke-dasharray="251.2" stroke-dashoffset="251.2" class="text-indigo-600 transition-all duration-1000" />
                            </svg>
                            <span id="progressText" class="absolute text-xl font-black text-gray-900 dark:text-white">0%</span>
                        </div>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400">Total Completion</p>
                    </div>
                </div>
            </div>

            <!-- Milestones Section -->
            <div class="card-base border-none">
                <div class="section-header flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <h3><i class="fas fa-flag-checkered mr-2 text-indigo-400"></i> Project Milestones</h3>
                        <div class="hidden md:flex items-center gap-2 px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-full">
                            <i class="fas fa-info-circle text-indigo-400 text-[10px]"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest text-indigo-300">Click icon box to toggle status</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="syncWithTemplate()" class="px-4 py-2 bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-200 border border-indigo-500/30 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                            <i class="fas fa-sync-alt mr-1"></i> Sync with Template
                        </button>
                        <button onclick="openAddMilestoneModal()" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                            Add Milestone
                        </button>
                    </div>
                </div>
                <div class="p-8">
                    <div id="milestonesContainer" class="space-y-4">
                        <!-- Milestones loaded via JS -->
                    </div>
                </div>
            </div>

            <!-- Email History Section -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3><i class="fas fa-envelope-open-text mr-2 text-indigo-400"></i> Communication History</h3>
                </div>
                <div class="p-8">
                    <div id="emailsContainer" class="space-y-4">
                        <!-- Emails loaded via JS -->
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Activity, Members, Files (4 Units) -->
        <div class="lg:col-span-4 space-y-8">
            
            <!-- Team Members -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3><i class="fas fa-users mr-2 text-indigo-400"></i> Allocated Team</h3>
                </div>
                <div class="p-6">
                    <div id="membersContainer" class="space-y-4"></div>
                    <button onclick="openAddMemberModal()" class="w-full mt-6 py-3 border-2 border-dashed border-gray-200 dark:border-slate-800 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-all">
                        Assign Member
                    </button>
                </div>
            </div>

            <!-- Project Files -->
            <div class="card-base border-none overflow-hidden">
                <div class="section-header">
                    <h3><i class="fas fa-folder-open mr-2 text-indigo-400"></i> Documentation</h3>
                </div>
                <div class="p-6">
                    <div id="filesContainer" class="space-y-2"></div>
                    <div class="mt-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border-2 border-dashed border-indigo-200 dark:border-indigo-800 text-center relative group cursor-pointer">
                        <input type="file" class="absolute inset-0 opacity-0 cursor-pointer">
                        <i class="fas fa-cloud-upload-alt text-indigo-400 text-xl mb-1"></i>
                        <p class="text-[10px] font-black uppercase text-indigo-600">Upload Project File</p>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="card-base border-none">
                <div class="section-header">
                    <h3><i class="fas fa-history mr-2 text-indigo-400"></i> Activity Feed</h3>
                </div>
                <div class="p-6 max-h-[400px] overflow-y-auto custom-scrollbar">
                    <div id="activityContainer" class="space-y-6 relative before:content-[''] before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-gray-100 dark:before:bg-slate-800">
                        <!-- Activity loaded via JS -->
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Project Edit Modal -->
<div id="projectEditModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-8 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-xl">
                    <i class="fas fa-edit text-indigo-200"></i> Edit Project Details
                </h3>
                <button onclick="closeModal('projectEditModal')" class="w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <form id="projectEditForm" class="p-10 space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Project Name *</label>
                        <input type="text" name="project_name" id="editProjectName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category *</label>
                        <select name="category_id" id="editProjectCategory" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Project Code</label>
                        <input type="text" name="project_short_code" id="editProjectShortCode" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Summary</label>
                        <textarea name="project_summary" id="editProjectSummary" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Start Date</label>
                        <input type="date" name="start_date" id="editProjectStartDate" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Deadline</label>
                        <input type="date" name="deadline" id="editProjectDeadline" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Location Coordinates (Lat,Lng)</label>
                        <input type="text" name="project_latlng" id="editProjectLatLng" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Status</label>
                        <select name="status" id="editProjectStatus" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <option value="1">Active</option>
                            <option value="0">Inactive / On Hold</option>
                            <option value="2">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Budget (€)</label>
                        <input type="number" step="0.01" name="project_budget" id="editProjectBudget" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Allocated Hours</label>
                        <input type="number" step="0.5" name="hours_allocated" id="editProjectHours" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Internal Notes</label>
                        <textarea name="notes" id="editProjectNotes" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeModal('projectEditModal')" class="flex-1 py-4 bg-gray-100 dark:bg-slate-800 text-gray-500 rounded-2xl font-black uppercase tracking-widest hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="submit" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl shadow-indigo-200/50">Update Project Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Milestone Modal -->
<div id="milestoneModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <i class="fas fa-flag-checkered text-indigo-200"></i> <span id="milestoneModalTitle">Add Milestone</span>
                </h3>
                <button onclick="closeModal('milestoneModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <form id="milestoneForm" class="p-8 space-y-6">
                <input type="hidden" name="id" id="milestoneId">
                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Milestone Title *</label>
                        <input type="text" name="milestone_title" id="milestoneTitle" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Target Date</label>
                        <input type="date" name="end_date" id="milestoneEndDate" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Allocated Cost (€)</label>
                        <input type="number" step="0.01" name="cost" id="milestoneCost" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Summary / Details</label>
                        <textarea name="summary" id="milestoneSummary" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="button" onclick="closeModal('milestoneModal')" class="flex-1 py-4 bg-gray-100 dark:bg-slate-800 text-gray-500 rounded-2xl font-black uppercase tracking-widest hover:bg-gray-200 transition-all">Cancel</button>
                    <button type="submit" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl">Save Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Project Member Modal -->
<div id="memberModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-[2rem] shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <i class="fas fa-user-plus text-indigo-200"></i> Assign Team Member
                </h3>
                <button onclick="closeModal('memberModal')" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <div class="p-8 space-y-6">
                <div class="space-y-4">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Select Members to Add</label>
                    <div id="membersChecklist" class="max-h-60 overflow-y-auto custom-scrollbar space-y-2 pr-2">
                        <p class="text-center text-gray-400 italic text-xs py-4">Loading members...</p>
                    </div>
                </div>
                <button onclick="saveMember()" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl active:scale-95 flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i> Add Selected to Project Team
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const projectId = <?php echo $projectId; ?>;
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.laravelApiUrl = '<?php echo $_ENV['LARAVEL_API_URL']; ?>';

    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    $(document).ready(function() {
        loadProjectDetails();
    });

    let currentProjectData = null;
    function loadProjectDetails() {
        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/${projectId}`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    currentProjectData = res.data;
                    renderHeader(currentProjectData);
                    renderCoreInfo(currentProjectData);
                    renderMilestones(currentProjectData.milestones);
                    renderMembers(currentProjectData.members);
                    renderFiles(currentProjectData.files);
                    renderActivity(currentProjectData.activities);
                    renderEmails(currentProjectData.email_leads);
                }
            }
        });
    }

    function renderEmails(emails) {
        const container = $('#emailsContainer');
        if (!emails || emails.length === 0) {
            container.html('<div class="text-center py-10 text-gray-400 italic text-xs">No email conversations linked to this project.</div>');
            return;
        }

        let html = '';
        emails.forEach(e => {
            html += `
                <div class="p-5 bg-gray-50 dark:bg-slate-950 border border-gray-100 dark:border-slate-800 rounded-2xl group transition-all hover:bg-white dark:hover:bg-slate-900 hover:shadow-lg">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="text-[9px] font-black text-indigo-500 uppercase tracking-widest">${moment(e.received_at).format('MMM D, YYYY HH:mm')}</span>
                            <h4 class="font-bold text-gray-900 dark:text-white text-sm mt-1">${e.subject}</h4>
                        </div>
                        <button class="px-3 py-1 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-[9px] font-black uppercase tracking-widest hover:text-indigo-600 transition-all">Read Message</button>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 leading-relaxed">
                        ${e.body.replace(/<[^>]*>?/gm, '').substring(0, 200)}...
                    </div>
                </div>`;
        });
        container.html(html);
    }

    function renderHeader(p) {
        $('#projectHeaderName').text(p.project_name);
        $('#projectHeaderCode').text('#' + (p.project_short_code || p.id));
    }

    function renderCoreInfo(p) {
        $('#displayProjectName').text(p.project_name);
        $('#displayProjectSummary').text(p.project_summary || 'No summary provided for this project.');
        $('#displayStartDate').text(p.start_date ? moment(p.start_date).format('Do MMM YYYY') : 'Not Set');
        $('#displayDeadline').text(p.deadline ? moment(p.deadline).format('Do MMM YYYY') : 'Ongoing');
        $('#displayLocation').text(p.location || 'Address not recorded');
        $('#displayLatLng').text(p.project_latlng || 'No coordinates');

        // Render Source Lead Link
        if (p.lead) {
            $('#displayLeadLink').html(`<span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">#${p.lead.id} ${p.lead.client_name}</span>`);
        } else {
            $('#displayLeadLink').html('<span class="text-xs text-gray-400">Direct Entry</span>');
        }

        // Render Proposal Link
        if (p.proposal) {
            const propUrl = `../leads/proposals/proposal_view.php?id=${p.proposal.id}`;
            $('#displayProposalLink').html(`<a href="${propUrl}" class="text-xs font-bold text-emerald-600 hover:underline flex items-center gap-1"><i class="fas fa-file-contract"></i> Accepted Quote</a>`);
        } else {
            $('#displayProposalLink').html('<span class="text-xs text-gray-400">None Linked</span>');
        }

        // Progress
        const progress = p.completion_percent || 0;
        $('#progressText').text(progress + '%');
        const offset = 251.2 - (251.2 * progress / 100);
        
        // Color coding
        let strokeColor = 'text-red-500';
        if (progress >= 75) strokeColor = 'text-emerald-500';
        else if (progress >= 25) strokeColor = 'text-amber-500';
        
        $('#progressCircle')
            .removeClass('text-indigo-600 text-red-500 text-amber-500 text-emerald-500')
            .addClass(strokeColor)
            .css('stroke-dashoffset', offset);
    }

    function renderMilestones(milestones) {
        const container = $('#milestonesContainer');
        if (!milestones || milestones.length === 0) {
            container.html(`
                <div class="text-center py-12 px-6 bg-gray-50 dark:bg-slate-950/50 rounded-3xl border-2 border-dashed border-gray-100 dark:border-slate-800">
                    <div class="w-16 h-16 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-800 flex items-center justify-center mx-auto mb-4 text-indigo-500">
                        <i class="fas fa-flag-checkered text-2xl"></i>
                    </div>
                    <h4 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight mb-2">No Milestones Found</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6 max-w-xs mx-auto italic">This project doesn't have any milestones configured yet.</p>
                    <button onclick="syncWithTemplate()" class="px-6 py-3 bg-indigo-600 hover:bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg active:scale-95 flex items-center gap-2 mx-auto">
                        <i class="fas fa-magic"></i> Generate from Category Template
                    </button>
                </div>
            `);
            return;
        }

        let html = '';
        milestones.forEach(m => {
            const isComplete = m.status === 'complete';
            const statusClass = isComplete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
            const tooltip = isComplete ? 'Click to mark as Incomplete' : 'Click to mark as Complete';
            
            html += `
                <div class="flex items-start gap-6 p-5 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl group transition-all hover:shadow-lg">
                    <div onclick="toggleMilestone(${m.id})" title="${tooltip}" class="w-10 h-10 rounded-xl ${isComplete ? 'bg-emerald-500' : 'bg-gray-100 dark:bg-slate-800'} flex items-center justify-center text-white shrink-0 group-hover:scale-110 transition-transform cursor-pointer shadow-sm">
                        <i class="fas ${isComplete ? 'fa-check' : 'fa-hourglass-half text-gray-400'}"></i>
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-black text-gray-900 dark:text-white uppercase tracking-tight text-sm">${m.milestone_title}</h4>
                            <div class="flex items-center gap-2">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded ${statusClass}">${m.status}</span>
                                <button onclick="editMilestone(${m.id})" class="text-gray-400 hover:text-indigo-500 transition-all p-1"><i class="fas fa-edit"></i></button>
                                <button onclick="deleteMilestone(${m.id})" class="text-gray-400 hover:text-red-500 transition-all p-1"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed mb-3">${m.summary || ''}</p>
                        <div class="flex items-center gap-4 text-[10px] font-bold text-gray-400">
                            <span><i class="fas fa-calendar-day mr-1"></i> ${m.end_date ? moment(m.end_date).format('MMM D') : '--'}</span>
                            <span><i class="fas fa-euro-sign mr-1"></i> ${parseFloat(m.cost).toLocaleString()}</span>
                        </div>
                    </div>
                </div>`;
        });
        container.html(html);
    }

    function toggleMilestone(milestoneId) {
        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/milestones/${milestoneId}/toggle`,
            type: 'PATCH',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    // Reload project details to update Progress Circle, Text and Milestones UI
                    loadProjectDetails();
                }
            }
        });
    }
    function syncWithTemplate() {
        Swal.fire({
            title: 'Sync Milestones?',
            text: 'This will add missing milestones from the category template. Do you also want to clear existing milestones?',
            icon: 'question',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Yes, Clear & Sync',
            denyButtonText: 'Sync (Keep Existing)',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444',
            denyButtonColor: '#6366f1',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed || result.isDenied) {
                const clearExisting = result.isConfirmed ? 1 : 0;
                $.ajax({
                    url: `${window.laravelApiUrl}/api/projects/${projectId}/apply-template`,
                    type: 'POST',
                    data: { clear_existing: clearExisting },
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Synced!', text: res.message, theme: getSwalTheme() });
                            loadProjectDetails();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                        }
                    }
                });
            }
        });
    }

    function renderMembers(members) {
        const container = $('#membersContainer');
        if (!members || members.length === 0) {
            container.html('<p class="text-center text-gray-400 italic text-[10px]">No team members assigned.</p>');
            return;
        }

        let html = '';
        members.forEach(m => {
            const u = m.user;
            if (!u) return;
            html += `
                <div class="flex items-center gap-3 group">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-[10px] font-black">
                        ${u.name.split(' ').map(n => n[0]).join('').toUpperCase()}
                    </div>
                    <div class="flex-grow">
                        <p class="text-xs font-bold text-gray-900 dark:text-gray-100">${u.name}</p>
                        <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest">${u.email}</p>
                    </div>
                    <button onclick="removeMember(${m.id})" class="opacity-0 group-hover:opacity-100 p-2 text-red-400 hover:text-red-600 transition-all text-xs">
                        <i class="fas fa-user-minus"></i>
                    </button>
                </div>`;
        });
        container.html(html);
    }

    function renderFiles(files) {
        const container = $('#filesContainer');
        if (!files || files.length === 0) {
            container.html('<p class="text-center text-gray-400 italic text-[10px]">No project files uploaded.</p>');
            return;
        }

        let html = '';
        files.forEach(f => {
            html += `
                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-950 rounded-xl group transition-all hover:bg-white dark:hover:bg-slate-900 hover:shadow-sm border border-transparent hover:border-gray-100 dark:hover:border-slate-800">
                    <div class="flex items-center gap-3">
                        <i class="far fa-file-pdf text-red-500"></i>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300">${f.filename}</span>
                    </div>
                    <button class="opacity-0 group-hover:opacity-100 p-2 text-indigo-500 hover:text-indigo-700 transition-all"><i class="fas fa-download"></i></button>
                </div>`;
        });
        container.html(html);
    }

    function renderActivity(activities) {
        const container = $('#activityContainer');
        if (!activities || activities.length === 0) {
            container.html('<p class="text-center text-gray-400 italic text-[10px] ml-6">No activity recorded.</p>');
            return;
        }

        let html = '';
        activities.forEach(a => {
            html += `
                <div class="relative pl-8">
                    <div class="absolute left-0 top-1 w-6 h-6 rounded-full bg-white dark:bg-slate-900 border-2 border-indigo-500 z-10 flex items-center justify-center">
                        <div class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>
                    </div>
                    <p class="text-xs font-bold text-gray-900 dark:text-gray-100">${a.activity}</p>
                    <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest mt-1">${moment(a.created_at).fromNow()}</p>
                </div>`;
        });
        container.html(html);
    }

    window.openAddMilestoneModal = function() {
        $('#milestoneModalTitle').text('Add Milestone');
        $('#milestoneForm')[0].reset();
        $('#milestoneId').val('');
        $('#milestoneModal').removeClass('hidden');
    }

    window.editMilestone = function(id) {
        // Find milestone data from current project state
        const milestone = currentProjectData.milestones.find(m => m.id == id);
        if (!milestone) return;

        $('#milestoneModalTitle').text('Edit Milestone');
        $('#milestoneId').val(milestone.id);
        $('#milestoneTitle').val(milestone.milestone_title);
        $('#milestoneEndDate').val(milestone.end_date);
        $('#milestoneCost').val(milestone.cost);
        $('#milestoneSummary').val(milestone.summary);
        $('#milestoneModal').removeClass('hidden');
    }

    window.deleteMilestone = function(id) {
        Swal.fire({
            title: 'Delete Milestone?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.laravelApiUrl}/api/projects/milestones/${id}`,
                    type: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, theme: getSwalTheme() });
                            loadProjectDetails();
                        }
                    }
                });
            }
        });
    }

    $('#milestoneForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#milestoneId').val();
        const isEdit = id !== '';
        const url = isEdit ? `${window.laravelApiUrl}/api/projects/milestones/${id}` : `${window.laravelApiUrl}/api/projects/milestones`;
        const method = isEdit ? 'PATCH' : 'POST';

        $.ajax({
            url: url,
            type: method,
            data: $(this).serialize(),
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Success', text: res.message, theme: getSwalTheme() });
                    closeModal('milestoneModal');
                    loadProjectDetails();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                }
            }
        });
    });

    window.openEditProjectModal = function() {
        if (!currentProjectData) return;
        
        $('#editProjectName').val(currentProjectData.project_name);
        $('#editProjectCategory').val(currentProjectData.category_id);
        $('#editProjectShortCode').val(currentProjectData.project_short_code);
        $('#editProjectSummary').val(currentProjectData.project_summary);
        $('#editProjectLatLng').val(currentProjectData.project_latlng);
        $('#editProjectStatus').val(currentProjectData.status);
        $('#editProjectBudget').val(currentProjectData.project_budget);
        $('#editProjectHours').val(currentProjectData.hours_allocated);
        $('#editProjectNotes').val(currentProjectData.notes);
        
        if (currentProjectData.start_date) {
            $('#editProjectStartDate').val(moment(currentProjectData.start_date).format('YYYY-MM-DD'));
        }
        if (currentProjectData.deadline) {
            $('#editProjectDeadline').val(moment(currentProjectData.deadline).format('YYYY-MM-DD'));
        }
        
        $('#projectEditModal').removeClass('hidden');
    }

    $('#projectEditForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/${projectId}`,
            type: 'PATCH',
            data: $(this).serialize(),
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Updated!', text: res.message, theme: getSwalTheme() });
                    closeModal('projectEditModal');
                    loadProjectDetails();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                }
            }
        });
    });

    window.closeModal = function(id) {
        $(`#${id}`).addClass('hidden');
    }

    window.openAddMemberModal = function() {
        $('#memberModal').removeClass('hidden');
        $('#membersChecklist').html('<p class="text-center text-gray-400 italic text-xs py-4">Loading members...</p>');
        
        // Get already assigned member IDs
        const assignedIds = currentProjectData.members ? currentProjectData.members.map(m => m.user_id) : [];

        $.ajax({
            url: `${window.laravelApiUrl}/api/users?team_only=1`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    let html = '';
                    const availableUsers = res.users.filter(u => !assignedIds.includes(u.id));
                    
                    if (availableUsers.length === 0) {
                        html = '<p class="text-center text-gray-400 italic text-xs py-4">All team members are already assigned.</p>';
                    } else {
                        availableUsers.forEach(u => {
                            html += `
                                <label class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-950 rounded-xl cursor-pointer hover:bg-white dark:hover:bg-slate-900 border border-transparent hover:border-gray-100 dark:hover:border-slate-800 transition-all group">
                                    <input type="checkbox" name="user_ids[]" value="${u.id}" class="w-5 h-5 rounded-lg border-gray-300 dark:border-slate-800 text-indigo-600 focus:ring-indigo-500 bg-white dark:bg-slate-950">
                                    <div class="flex-grow">
                                        <p class="text-xs font-bold text-gray-900 dark:text-gray-100">${u.name}</p>
                                        <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest">${u.email}</p>
                                    </div>
                                </label>`;
                        });
                    }
                    $('#membersChecklist').html(html);
                }
            }
        });
    }

    window.saveMember = function() {
        const selectedIds = [];
        $('#membersChecklist input[name="user_ids[]"]:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            Swal.fire({ icon: 'error', title: 'Selection Required', text: 'Please select at least one member to add.', theme: getSwalTheme() });
            return;
        }

        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/members`,
            type: 'POST',
            data: { project_id: projectId, user_ids: selectedIds },
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Added!', text: res.message, timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                    closeModal('memberModal');
                    loadProjectDetails();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Failed to add members.', theme: getSwalTheme() });
            }
        });
    }

    window.removeMember = function(memberId) {
        Swal.fire({
            title: 'Remove Member?',
            text: "This user will no longer be assigned to this project.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Remove',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.laravelApiUrl}/api/projects/members/${memberId}`,
                    type: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Removed!', text: res.message, timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                            loadProjectDetails();
                        }
                    }
                });
            }
        });
    }
</script>

<?php include_once "../footer.php"; ?>
