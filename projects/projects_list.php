<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

require_once "../tracker_data.php";

$pageTitle = "Project Registry";
include_once "../header.php";
include_once "../nav.php";

$categories = [];
$catRes = makeApiCall("/api/leads/categories"); // Default API behavior is enabled only
if ($catRes && ($catRes['success'] ?? false)) {
    $categories = $catRes['data'];
}

$clients = [];
$clientRes = makeApiCall("/api/clients/full-list?all=1"); // Get ALL clients for new projects
if ($clientRes && ($clientRes['success'] ?? false)) {
    $clients = $clientRes['data'];
}
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white flex items-center gap-3">
                Project Registry
                <span id="projectCount" class="bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-black px-3 py-1 rounded-full border border-indigo-200/50">0</span>
            </h1>
            <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mt-1">Manage full-scale projects and detailed operations</p>
        </div>
        <button onclick="openAddProjectModal()" class="px-6 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest transition-all active:scale-95 shadow-xl flex items-center gap-3 self-start md:self-auto">
            <i class="fas fa-plus-circle text-lg text-indigo-200"></i> Initialize New Project
        </button>
    </div>

    <!-- Quick Stats -->
    <div id="statsContainer" class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Stats loaded via JS -->
    </div>

    <div class="card-base border-none overflow-hidden">
        <div class="p-6 bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800 flex flex-wrap gap-4 items-center">
            <div class="relative flex-grow max-w-md">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" id="projectSearch" placeholder="Search projects, codes, or clients..." class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-xs font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
            </div>
            <select id="categoryFilter" class="px-4 py-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="projectsTable">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800">
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Project Name / Client</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Code</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Category</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400 text-center">Progress</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Timeline</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="projectsListBody" class="divide-y divide-gray-50 dark:divide-slate-800">
                    <!-- Projects loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Project Modal -->
<div id="projectModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-8 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-xl">
                    <i class="fas fa-project-diagram text-indigo-200 text-2xl"></i> <span id="modalTitle">Initialize Project</span>
                </h3>
                <button onclick="closeModal()" class="w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <form id="projectForm" class="p-8 md:p-12 space-y-10 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <input type="hidden" name="id" id="editProjectId">
                
                <div class="space-y-6">
                    <h4 class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500 border-b border-gray-100 dark:border-slate-800 pb-2">1. Core Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Name *</label>
                            <input type="text" name="project_name" id="projectName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none" placeholder="e.g. Roof Inspection - Smith">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Client *</label>
                            <select name="client_id" id="projectClientId" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                                <option value="">Select a Client</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Code / PO</label>
                            <input type="text" name="project_short_code" id="projectShortCode" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none uppercase tracking-widest">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Category</label>
                            <select name="category_id" id="projectCategoryId" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <h4 class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500 border-b border-gray-100 dark:border-slate-800 pb-2">2. Scope & Timeline</h4>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Summary</label>
                        <textarea name="project_summary" id="projectSummary" rows="4" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-medium text-sm dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none leading-relaxed"></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Start Date</label>
                            <input type="date" name="start_date" id="startDate" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Deadline (Optional)</label>
                            <input type="date" name="deadline" id="deadline" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <h4 class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500 border-b border-gray-100 dark:border-slate-800 pb-2">3. Logistics & Financials</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Location Coordinates (Lat,Lng)</label>
                            <input type="text" name="project_latlng" id="projectLatLng" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none" placeholder="e.g. 53.3498,-6.2603">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Status</label>
                            <select name="status" id="projectStatus" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                                <option value="1">Active</option>
                                <option value="0">Inactive / On Hold</option>
                                <option value="2">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Budget (€)</label>
                            <input type="number" step="0.01" name="project_budget" id="projectBudget" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Allocated Hours</label>
                            <input type="number" step="0.5" name="hours_allocated" id="projectHoursAllocated" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Internal Notes</label>
                        <textarea name="notes" id="projectNotes" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl font-medium text-sm dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none leading-relaxed"></textarea>
                    </div>
                </div>

                <div class="pt-6 flex gap-4">
                    <button type="button" onclick="closeModal()" class="flex-1 py-5 bg-gray-100 dark:bg-slate-800 text-gray-500 font-black uppercase tracking-widest rounded-3xl hover:bg-gray-200 dark:hover:bg-slate-700 transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-5 bg-indigo-600 text-white font-black uppercase tracking-widest rounded-3xl hover:bg-emerald-600 transition-all shadow-2xl shadow-indigo-200 active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save text-indigo-200"></i> Save Project Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.laravelApiUrl = '<?php echo $_ENV['LARAVEL_API_URL']; ?>';

    function getSelectedCategoryFilter() {
        return $('#categoryFilter').val() || '';
    }

    function getProjectSearchTerm() {
        return ($('#projectSearch').val() || '').toLowerCase();
    }

    function applyProjectSearchFilter() {
        const val = getProjectSearchTerm();
        let visibleCount = 0;
        $('#projectsListBody tr').each(function() {
            const $row = $(this);
            if ($row.data('empty') === true) {
                $row.show();
                return;
            }
            const isVisible = $row.text().toLowerCase().indexOf(val) > -1;
            $row.toggle(isVisible);
            if (isVisible) {
                visibleCount++;
            }
        });
        $('#projectCount').text(visibleCount);
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function getStatColorClasses(color) {
        const map = {
            indigo: {
                bg: 'bg-indigo-50 dark:bg-indigo-900/20',
                text: 'text-indigo-600 dark:text-indigo-400'
            },
            emerald: {
                bg: 'bg-emerald-50 dark:bg-emerald-900/20',
                text: 'text-emerald-600 dark:text-emerald-400'
            },
            blue: {
                bg: 'bg-blue-50 dark:bg-blue-900/20',
                text: 'text-blue-600 dark:text-blue-400'
            },
            rose: {
                bg: 'bg-rose-50 dark:bg-rose-900/20',
                text: 'text-rose-600 dark:text-rose-400'
            }
        };

        return map[color] || map.indigo;
    }

    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    $(document).ready(function() {
        loadProjects(getSelectedCategoryFilter());

        $('#projectSearch').on('keyup', function() {
            applyProjectSearchFilter();
        });

        $('#categoryFilter').on('change', function() {
            const val = $(this).val();
            loadProjects(val);
        });

        $('#projectForm').on('submit', function(e) {
            e.preventDefault();
            saveProject();
        });
    });

    function loadProjects(categoryId = '') {
        const body = $('#projectsListBody');
        body.html('<tr><td colspan="6" class="p-20 text-center"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i></td></tr>');
        
        $.ajax({
            url: `${window.laravelApiUrl}/api/projects`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    let projects = res.data;
                    if (categoryId) {
                        projects = projects.filter(p => p.category_id == categoryId);
                    }
                    
                    $('#projectCount').text(projects.length);
                    renderStats(projects);

                    let html = '';
                    if (projects.length === 0) {
                        html = '<tr data-empty="true"><td colspan="6" class="p-20 text-center text-gray-400 italic font-medium uppercase text-xs tracking-widest">No projects matching criteria</td></tr>';
                    } else {
                        projects.forEach(p => {
                            const progress = p.completion_percent || 0;
                            
                            let statusColor = 'bg-red-500';
                            let badgeColor = 'bg-red-50 text-red-600 border-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800/50';
                            
                            if (progress >= 75) {
                                statusColor = 'bg-emerald-500';
                                badgeColor = 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800/50';
                            } else if (progress >= 25) {
                                statusColor = 'bg-amber-500';
                                badgeColor = 'bg-amber-50 text-amber-600 border-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800/50';
                            }
                            
                            const clientName = escapeHtml(p.client ? p.client.name : 'Unknown Client');
                            const catName = escapeHtml(p.category ? p.category.category_name : 'General');
                            const start = p.start_date ? moment(p.start_date).format('DD MMM') : '--';
                            const end = p.deadline ? moment(p.deadline).format('DD MMM YYYY') : 'Ongoing';
                            const safeProjectName = escapeHtml(p.project_name || 'Untitled Project');
                            const safeShortCode = escapeHtml(p.project_short_code || '--');

                            html += `
                                <tr class="table-row-hover group cursor-pointer" onclick="window.location.href='view.php?id=${p.id}'">
                                    <td class="px-6 py-6">
                                        <a href="view.php?id=${p.id}" class="block group-hover:translate-x-1 transition-transform">
                                            <div class="font-black text-gray-900 dark:text-gray-100 text-sm italic uppercase tracking-tight">${safeProjectName}</div>
                                            <div class="text-[10px] text-gray-400 font-bold uppercase mt-1 tracking-widest">${clientName}</div>
                                        </a>
                                    </td>
                                    <td class="px-6 py-6 font-mono text-[10px] font-black text-indigo-500 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-lg inline-block mt-4 ml-6">${safeShortCode}</td>
                                    <td class="px-6 py-6"><span class="px-3 py-1 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-400 text-[9px] font-black rounded-lg uppercase tracking-widest">${catName}</span></td>
                                    <td class="px-6 py-6 text-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <div class="flex items-center justify-center mb-1">
                                                <span class="px-2 py-0.5 ${badgeColor} text-[10px] font-black rounded-full border">${progress}%</span>
                                            </div>
                                            <div class="w-24 bg-gray-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden shadow-inner mx-auto">
                                                <div class="${statusColor} h-full transition-all duration-700 ease-out" style="width: ${progress}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="text-[10px] font-black text-gray-900 dark:text-gray-300 uppercase">${start} &rarr; ${end}</div>
                                    </td>
                                    <td class="px-6 py-6 text-right space-x-2" onclick="event.stopPropagation()">
                                        <a href="view.php?id=${p.id}" class="p-3 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 rounded-xl transition-all inline-block" title="View Workspace"><i class="fas fa-eye"></i></a>
                                        <button onclick="editProject(${p.id})" class="p-3 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-xl transition-all" title="Edit Properties"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteProject(${p.id}, ${JSON.stringify(p.project_name || 'Untitled Project')})" class="p-3 text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all" title="Delete Project"><i class="fas fa-trash-alt"></i></button>
                                    </td>
                                </tr>`;
                        });
                    }
                    body.html(html);
                    applyProjectSearchFilter();
                } else {
                    $('#projectCount').text('0');
                    $('#statsContainer').empty();
                    body.html('<tr data-empty="true"><td colspan="6" class="p-20 text-center text-red-500 italic font-medium uppercase text-xs tracking-widest">Failed to load projects</td></tr>');
                    Swal.fire({ icon: 'error', title: 'Load Failed', text: res.message || 'Could not load projects.', theme: getSwalTheme() });
                }
            },
            error: function(xhr) {
                $('#projectCount').text('0');
                $('#statsContainer').empty();
                body.html('<tr data-empty="true"><td colspan="6" class="p-20 text-center text-red-500 italic font-medium uppercase text-xs tracking-widest">Failed to load projects</td></tr>');
                Swal.fire({ icon: 'error', title: 'API Error', text: xhr.responseJSON?.message || 'Could not load projects.', theme: getSwalTheme() });
            }
        });
    }

    function renderStats(projects) {
        const total = projects.length;
        const completed = projects.filter(p => p.completion_percent === 100).length;
        const active = total - completed;
        const avgProgress = total > 0 ? Math.round(projects.reduce((acc, p) => acc + (p.completion_percent || 0), 0) / total) : 0;

        const stats = [
            { label: 'Active Projects', value: active, icon: 'fa-project-diagram', color: 'indigo' },
            { label: 'Completed', value: completed, icon: 'fa-check-double', color: 'emerald' },
            { label: 'Avg. Progress', value: avgProgress + '%', icon: 'fa-chart-line', color: 'blue' },
            { label: 'Portfolio Health', value: 'Excellent', icon: 'fa-heartbeat', color: 'rose' }
        ];

        let html = '';
        stats.forEach(s => {
            const colorClasses = getStatColorClasses(s.color);
            html += `
                <div class="card-base p-6 border-none flex items-center gap-5">
                    <div class="w-12 h-12 rounded-2xl ${colorClasses.bg} ${colorClasses.text} flex items-center justify-center text-xl shadow-sm">
                        <i class="fas ${s.icon}"></i>
                    </div>
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-400 mb-1">${s.label}</p>
                        <p class="text-xl font-black text-gray-900 dark:text-white italic uppercase">${s.value}</p>
                    </div>
                </div>`;
        });
        $('#statsContainer').html(html);
    }

    function openAddProjectModal() {
        $('#editProjectId').val('');
        $('#projectForm')[0].reset();
        $('#modalTitle').text('Initialize Project');
        $('#projectModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    }

    function closeModal() {
        $('#projectModal').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    }

    function saveProject() {
        const id = $('#editProjectId').val();
        const url = id ? `${window.laravelApiUrl}/api/projects/${id}` : `${window.laravelApiUrl}/api/projects`;
        const method = id ? 'PATCH' : 'POST';
        
        const data = {};
        $('#projectForm').serializeArray().forEach(item => {
            data[item.name] = item.value === '' ? null : item.value;
        });

        const deadline = data.deadline;
        const startDate = data.start_date;
        if (startDate && deadline && deadline < startDate) {
            Swal.fire({ icon: 'error', title: 'Invalid Timeline', text: 'Deadline cannot be earlier than the start date.', theme: getSwalTheme() });
            return;
        }

        if (data.project_latlng) {
            const latLngPattern = /^\s*-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?\s*$/;
            if (!latLngPattern.test(data.project_latlng)) {
                Swal.fire({ icon: 'error', title: 'Invalid Coordinates', text: 'Use latitude and longitude in the format 53.3498,-6.2603.', theme: getSwalTheme() });
                return;
            }
        }

        const submitButton = $('#projectForm button[type="submit"]');
        submitButton.prop('disabled', true).addClass('opacity-60 cursor-not-allowed');

        Swal.fire({ title: 'Synchronizing...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });

        $.ajax({
            url: url,
            type: method,
            headers: { 'Authorization': 'Bearer ' + window.apiToken, 'Content-Type': 'application/json' },
            data: JSON.stringify(data),
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Success', text: res.message, timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                    closeModal();
                    loadProjects(getSelectedCategoryFilter());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                }
            },
            error: (xhr) => Swal.fire({ icon: 'error', title: 'API Error', text: xhr.responseJSON?.message || 'Connection failed', theme: getSwalTheme() }),
            complete: function() {
                submitButton.prop('disabled', false).removeClass('opacity-60 cursor-not-allowed');
            }
        });
    }

    function editProject(id) {
        Swal.fire({ title: 'Retrieving...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });
        $.ajax({
            url: `${window.laravelApiUrl}/api/projects/${id}`,
            type: 'GET',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            success: function(res) {
                if (res.success) {
                    const p = res.data;
                    $('#editProjectId').val(p.id);
                    $('#projectName').val(p.project_name);
                    $('#projectClientId').val(p.client_id);
                    $('#projectShortCode').val(p.project_short_code);
                    $('#projectCategoryId').val(p.category_id);
                    $('#projectSummary').val(p.project_summary);
                    $('#startDate').val(p.start_date ? p.start_date.substring(0, 10) : '');
                    $('#deadline').val(p.deadline ? p.deadline.substring(0, 10) : '');
                    $('#projectLatLng').val(p.project_latlng);
                    $('#projectStatus').val(p.status);
                    $('#projectBudget').val(p.project_budget);
                    $('#projectHoursAllocated').val(p.hours_allocated);
                    $('#projectNotes').val(p.notes);
                    
                    $('#modalTitle').text('Refine Project Properties');
                    Swal.close();
                    $('#projectModal').removeClass('hidden');
                    $('body').addClass('overflow-hidden');
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Project could not be loaded.', theme: getSwalTheme() });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'API Error', text: xhr.responseJSON?.message || 'Project could not be loaded.', theme: getSwalTheme() });
            }
        });
    }

    function deleteProject(id, name) {
        Swal.fire({
            title: 'Expunge Project?',
            text: `Permanently delete "${name}" and all associated data?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Delete It',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.laravelApiUrl}/api/projects/${id}`,
                    type: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Expunged', text: 'Project removed from registry.', theme: getSwalTheme() });
                            loadProjects(getSelectedCategoryFilter());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'API Error', text: xhr.responseJSON?.message || 'Project could not be deleted.', theme: getSwalTheme() });
                    }
                });
            }
        });
    }
</script>

<?php include_once "../footer.php"; ?>
