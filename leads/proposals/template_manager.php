<?php
require_once "../../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../../oauth2callback.php');
    exit();
}

// RESTRICT: Only system administrators can access template manager
$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    header('Location: ../../admin/index.php');
    exit();
}

$pageTitle = "Proposal Template Manager";
include_once "../../header.php";
include_once "../../nav.php";

$categories = [];
$catRes = makeApiCall("/api/leads/categories");
if ($catRes && ($catRes['success'] ?? false)) {
    $categories = $catRes['data'];
}
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white flex items-center gap-3">
                Template Manager
                <i class="fas fa-info-circle text-indigo-500 text-xl cursor-help hover:scale-110 transition-transform" onclick="showTemplateInfo()"></i>
            </h1>
            <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mt-1">Create and manage proposal templates for categories</p>
        </div>
        <button onclick="openAddTemplateModal()" class="px-6 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest transition-all active:scale-95 shadow-xl flex items-center gap-3 self-start md:self-auto">
            <i class="fas fa-plus-circle text-lg"></i> Add New Template
        </button>
    </div>

    <div class="card-base border-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800">
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Template Name</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Category</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400">Items Count</th>
                        <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="templatesListBody" class="divide-y divide-gray-50 dark:divide-slate-800">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Template Modal -->
<div id="templateModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-3xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 px-8 py-6 flex justify-between items-center">
                <h3 class="text-xl font-black italic uppercase tracking-tighter text-white" id="modalTitle">Add New Template</h3>
                <button onclick="closeModal()" class="text-white/50 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="templateForm" class="p-8 space-y-8">
                <input type="hidden" name="template_id" id="editTemplateId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Template Name</label>
                        <input type="text" name="name" id="templateName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-100 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Category</label>
                        <select name="category_id" id="templateCategoryId" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-100 dark:border-slate-800 rounded-2xl font-bold dark:text-white focus:ring-2 focus:ring-indigo-500 transition-all outline-none">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Description / Overview Template</label>
                    <div id="editor" class="bg-white dark:bg-slate-950 rounded-2xl min-h-[200px] border border-gray-200 dark:border-slate-800"></div>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Default Quote Items</label>
                        <button type="button" id="add-item" class="text-[10px] font-black uppercase bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition-all shadow-md">
                            <i class="fas fa-plus mr-1"></i> Add Item
                        </button>
                    </div>
                    
                    <div id="template-items" class="space-y-4">
                        <!-- Items added via JS -->
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button type="button" onclick="closeModal()" class="flex-1 py-4 bg-gray-100 dark:bg-slate-800 text-gray-500 font-black uppercase tracking-widest rounded-2xl hover:bg-gray-200 dark:hover:bg-slate-700 transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-emerald-600 text-white font-black uppercase tracking-widest rounded-2xl hover:bg-emerald-700 transition-all shadow-lg active:scale-95">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
    window.laravelApiUrl = '<?php echo $_ENV['LARAVEL_API_URL']; ?>';
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeJsString(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['clean']
            ]
        }
    });

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    $(document).ready(function() {
        loadTemplatesList();

        $('#add-item').on('click', function() {
            addItemRow();
        });

        $('#templateForm').submit(function(e) {
            e.preventDefault();
            saveTemplate();
        });
    });

    function loadTemplatesList() {
        $('#templatesListBody').html('<tr><td colspan="4" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Templates...</p></div></td></tr>');
        $.getJSON('../leads_handler.php?action=get_templates', function(res) {
            if (res.success) {
                let html = '';
                res.data.forEach(temp => {
                    const safeName = escapeHtml(temp.name);
                    const safeCategory = escapeHtml(temp.category_name || 'General');
                    const safeNameJs = safeJsString(temp.name);
                    html += `
                        <tr class="table-row-hover group">
                            <td class="px-6 py-5 font-bold text-gray-900 dark:text-gray-100">${safeName}</td>
                            <td class="px-6 py-5 text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest">${safeCategory}</td>
                            <td class="px-6 py-5 text-gray-500 dark:text-gray-400 font-mono text-xs">${temp.items ? temp.items.length : 0} items</td>
                            <td class="px-6 py-5 text-right flex items-center justify-end gap-4">
                                <button onclick="editTemplate(${temp.id})" class="text-indigo-600 dark:text-indigo-400 font-black uppercase text-[10px] tracking-widest hover:underline">Edit</button>
                                <button onclick="deleteTemplate(${temp.id}, '${safeNameJs}')" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all active:scale-95">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>`;
                });
                $('#templatesListBody').html(html || '<tr><td colspan="4" class="p-10 text-center text-gray-400 uppercase font-bold text-xs">No templates found</td></tr>');
            }
        }).fail(function() {
            $('#templatesListBody').html('<tr><td colspan="4" class="p-10 text-center text-red-500 font-bold text-xs uppercase">Failed to load templates</td></tr>');
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load templates.', theme: getSwalTheme() });
        });
    }

    function deleteTemplate(id, name) {
        Swal.fire({
            title: 'Delete Template?',
            text: `Permanently remove "${name}"? This cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Delete It',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.laravelApiUrl}/api/proposals/templates/${id}`,
                    type: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + window.apiToken },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Deleted!', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                            loadTemplatesList();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Delete failed', theme: getSwalTheme() });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON ? xhr.responseJSON.message : 'API Error', theme: getSwalTheme() });
                    }
                });
            }
        });
    }

    function openAddTemplateModal() {
        $('#editTemplateId').val('');
        $('#templateForm')[0].reset();
        quill.root.innerHTML = '';
        $('#template-items').empty();
        $('#modalTitle').text('Add New Template');
        $('#templateModal').removeClass('hidden');
    }

    function closeModal() {
        $('#templateModal').addClass('hidden');
    }

    function addItemRow(item = {}) {
        const id = Date.now();
        const html = `
            <div class="template-item-row p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800 grid grid-cols-1 md:grid-cols-4 gap-4 items-start" id="item-${id}">
                <div class="md:col-span-2">
                    <input type="text" placeholder="Item Name" class="item-name w-full p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg text-sm font-bold dark:text-white" value="${escapeHtml(item.item_name || '')}">
                    <textarea placeholder="Description" class="item-summary w-full mt-2 p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg text-xs dark:text-gray-400">${escapeHtml(item.item_summary || '')}</textarea>
                </div>
                <div>
                    <input type="number" placeholder="Qty" class="item-qty w-full p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg text-sm font-bold dark:text-white" value="${escapeHtml(item.quantity || 1)}">
                </div>
                <div class="relative">
                    <input type="number" step="0.01" placeholder="Price" class="item-price w-full p-2 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg text-sm font-bold dark:text-white" value="${escapeHtml(item.unit_price || 0)}">
                    <button type="button" onclick="$('#item-${id}').remove()" class="absolute -right-2 -top-2 w-6 h-6 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center shadow-lg"><i class="fas fa-times"></i></button>
                </div>
            </div>`;
        $('#template-items').append(html);
    }

    function saveTemplate() {
        const items = [];
        $('.template-item-row').each(function() {
            items.push({
                item_name: $(this).find('.item-name').val(),
                quantity: $(this).find('.item-qty').val(),
                unit_price: $(this).find('.item-price').val(),
                item_summary: $(this).find('.item-summary').val()
            });
        });

        const data = {
            name: $('#templateName').val(),
            category_id: $('#templateCategoryId').val(),
            description: quill.root.innerHTML,
            items: items
        };

        const id = $('#editTemplateId').val();
        const url = id ? `${window.laravelApiUrl}/api/proposals/templates/${id}` : `${window.laravelApiUrl}/api/proposals/templates`;
        const method = id ? 'PATCH' : 'POST';

        Swal.fire({ title: 'Saving Template...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });

        $.ajax({
            url: url,
            type: method,
            headers: { 'Authorization': 'Bearer ' + window.apiToken, 'Content-Type': 'application/json' },
            data: JSON.stringify(data),
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                    closeModal();
                    loadTemplatesList();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to save', theme: getSwalTheme() });
                }
            },
            error: function(xhr) {
                Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON ? xhr.responseJSON.message : 'API Error', theme: getSwalTheme() });
            }
        });
    }

    function editTemplate(id) {
        Swal.fire({ title: 'Loading...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });
        $.getJSON('../leads_handler.php?action=get_templates', function(res) {
            if (res.success) {
                const temp = res.data.find(t => t.id == id);
                if (temp) {
                    $('#editTemplateId').val(temp.id);
                    $('#templateName').val(temp.name);
                    $('#templateCategoryId').val(temp.category_id);
                    quill.root.innerHTML = temp.description || '';
                    $('#template-items').empty();
                    if (temp.items) {
                        temp.items.forEach(item => addItemRow(item));
                    }
                    $('#modalTitle').text('Edit Template');
                    Swal.close();
                    $('#templateModal').removeClass('hidden');
                }
            }
        }).fail(function() {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load template details.', theme: getSwalTheme() });
        });
    }

    window.showTemplateInfo = function() {
        Swal.fire({
            title: '<span class="text-xl font-black italic uppercase italic tracking-tighter">Template System Guide</span>',
            html: `
                <div class="text-left space-y-4 text-sm font-medium text-gray-600 dark:text-gray-400 leading-relaxed">
                    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                        <h4 class="font-black uppercase tracking-widest text-[10px] text-indigo-600 dark:text-indigo-400 mb-2">How it Works</h4>
                        <p>Proposal templates allow you to pre-define the <b>Scope of Work</b> and <b>Standard Pricing</b> for each brand or service category.</p>
                    </div>

                    <div class="space-y-3 px-2">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-magic text-indigo-500 mt-1"></i>
                            <p><b>Auto-Fill:</b> When you create a new proposal, selecting a category will automatically load the linked template's description and items.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fas fa-edit text-indigo-500 mt-1"></i>
                            <p><b>Fully Editable:</b> Once loaded, you can still customize the description and line items specifically for that lead.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i class="fas fa-link text-indigo-500 mt-1"></i>
                            <p><b>Linking:</b> Link templates to categories in the <a href="../../admin/" class="text-indigo-600 font-bold hover:underline">Admin Panel</a> under the "Categories & Sync" tab.</p>
                        </div>
                    </div>

                    <p class="text-[10px] font-black uppercase text-gray-400 text-center pt-4 tracking-widest italic">Save time and maintain consistency across all brands.</p>
                </div>
            `,
            confirmButtonText: 'GOT IT!',
            confirmButtonColor: '#4f46e5',
            padding: '2rem',
            customClass: {
                popup: 'rounded-3xl border-none',
                confirmButton: 'rounded-xl px-8 py-3 font-black uppercase tracking-widest text-xs'
            }
        });
    }
</script>

<?php include_once "../../footer.php"; ?>
