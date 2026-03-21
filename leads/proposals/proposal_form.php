<?php
require_once "../../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../../oauth2callback.php');
    exit();
}

require_once __DIR__ . "/../../tracker_data.php"; // For makeApiCall

$pageTitle = "Create Proposal";
include_once "../../header.php";
include_once "../../nav.php";

$leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
$templateId = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$selectedLead = null;
if ($leadId > 0) {
    $leadRes = makeApiCall("/api/leads/{$leadId}");
    if ($leadRes && ($leadRes['success'] ?? false)) {
        $selectedLead = $leadRes['data'];
        $categoryId = $selectedLead['category_id'] ?? $categoryId;
    }
}

// Fetch Category Details
$category = null;
if ($categoryId > 0) {
    $catRes = makeApiCall("/api/leads/categories/{$categoryId}");
    if ($catRes && ($catRes['success'] ?? false)) {
        $category = $catRes['data'];
    }
}

// Fetch Template Content
$template = null;
if ($templateId > 0) {
    $tempRes = makeApiCall("/api/proposals/templates/{$templateId}");
    if ($tempRes && ($tempRes['success'] ?? false)) {
        $template = $tempRes['data'];
    }
} elseif ($categoryId > 0) {
    $tempRes = makeApiCall("/api/proposals/category-default/{$categoryId}");
    if ($tempRes && ($tempRes['success'] ?? false)) {
        $template = $tempRes['data'];
    }
}

// Fetch Taxes
$taxes = [];
$taxRes = makeApiCall("/api/proposals/taxes");
if ($taxRes && ($taxRes['success'] ?? false)) {
    $taxes = $taxRes['data'];
}

// Fetch Existing Proposals for this lead
$existingProposals = [];
if ($leadId > 0) {
    // We might need a specific endpoint for lead proposals, for now fetching all and filtering
    $propRes = makeApiCall("/api/proposals");
    if ($propRes && ($propRes['success'] ?? false)) {
        $existingProposals = array_filter($propRes['data'], function($p) use ($leadId) {
            return $p['lead_id'] == $leadId;
        });
    }
}

$categoryDisplayName = $category['category_name'] ?? 'General Proposal';
$logoUrl = $category['logo_url'] ?? 'https://via.placeholder.com/150';

?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Form Side -->
        <div class="lg:col-span-7 space-y-8">
            <div class="card-base border-none">
                <div class="section-header">
                    <h3><i class="fas fa-file-invoice text-indigo-400 mr-2"></i> <?php echo htmlspecialchars($categoryDisplayName); ?> Proposal</h3>
                    <a href="../leads.php" class="text-[10px] font-black uppercase bg-white/10 px-3 py-1.5 rounded-lg hover:bg-white/20 transition-all text-white">Back to Leads</a>
                </div>
                
                <form id="proposalForm" class="p-8 space-y-8">
                    <input type="hidden" name="lead_id" id="lead_id" value="<?php echo $leadId; ?>">
                    <input type="hidden" name="category_id" id="category_id" value="<?php echo $categoryId; ?>">
                    
                    <!-- Client Selection -->
                    <div class="space-y-4">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Client Information</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800">
                                <p class="text-[10px] font-black uppercase text-indigo-500 mb-1">Lead Name</p>
                                <p class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($selectedLead['client_name'] ?? 'New Client'); ?></p>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800">
                                <p class="text-[10px] font-black uppercase text-indigo-500 mb-1">Email</p>
                                <p class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($selectedLead['client_email'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Editor -->
                    <div class="space-y-4">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Project Overview</label>
                        <div id="editor" class="bg-white dark:bg-slate-950 rounded-2xl min-h-[300px] border border-gray-200 dark:border-slate-800"></div>
                        <textarea name="description" id="projectOverviewHidden" style="display:none;"></textarea>
                    </div>

                    <!-- Line Items -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Quote Items</label>
                            <button type="button" id="add-item" class="text-[10px] font-black uppercase bg-indigo-600 text-white px-4 py-2 rounded-xl hover:bg-indigo-700 transition-all shadow-md active:scale-95">
                                <i class="fas fa-plus mr-1"></i> Add Item
                            </button>
                        </div>
                        
                        <div id="proposal-items" class="space-y-4">
                            <!-- Items added via JS -->
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 p-8 bg-gray-50 dark:bg-slate-950 rounded-3xl border border-gray-100 dark:border-slate-800">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Discount Type</label>
                                <select name="discount_type" id="discount_type" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white">
                                    <option value="fixed">Fixed (€)</option>
                                    <option value="percent">Percentage (%)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Discount Value</label>
                                <input type="number" name="discount" id="discount" value="0" step="0.01" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl font-bold dark:text-white">
                            </div>
                        </div>
                        <div class="flex flex-col justify-end space-y-2 text-right">
                            <div class="text-[10px] font-black uppercase text-gray-400">Sub Total: <span id="sub-total-display" class="text-gray-900 dark:text-white ml-2">€0.00</span></div>
                            <div class="text-[10px] font-black uppercase text-gray-400">Tax: <span id="tax-total-display" class="text-gray-900 dark:text-white ml-2">€0.00</span></div>
                            <div class="text-2xl font-black italic uppercase tracking-tighter text-indigo-600 dark:text-indigo-400">Total: <span id="total-display">€0.00</span></div>
                        </div>
                    </div>

                    <button id="saveProposal" type="button" class="w-full py-5 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-3">
                        <i class="fas fa-save text-emerald-400"></i> Create & Review Proposal
                    </button>
                </form>
            </div>
        </div>

        <!-- Preview Side -->
        <div class="lg:col-span-5">
            <div class="sticky top-8 space-y-8">
                <div class="card-base border-none overflow-hidden">
                    <div class="section-header !bg-gray-100 dark:!bg-slate-800 !text-gray-900 dark:!text-white border-b border-gray-200 dark:border-slate-700">
                        <h3><i class="fas fa-eye text-gray-400 mr-2"></i> Live Preview</h3>
                    </div>
                    <div class="p-8 bg-white dark:bg-white text-gray-900 min-h-[600px] shadow-inner proposal-preview">
                        <!-- Preview content -->
                        <div class="flex justify-between items-start mb-8">
                            <img src="<?php echo $logoUrl; ?>" class="max-h-16">
                            <div class="text-right text-[10px] font-bold uppercase text-gray-500">
                                <p>Proposal #PRO-TEMP</p>
                                <p>Date: <?php echo date('d M Y'); ?></p>
                            </div>
                        </div>
                        
                        <div id="displayQuillContent" class="prose prose-sm max-w-none mb-8">
                            <!-- Live content from editor -->
                        </div>

                        <div id="preview-items-table">
                            <!-- Live table from items -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.taxesData = <?php echo json_encode($taxes); ?>;
    window.templateData = <?php echo json_encode($template); ?>;
    window.selectedLead = <?php echo json_encode($selectedLead); ?>;
    
    // Initialize Quill
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
    
    if (window.templateData && window.templateData.description) {
        quill.root.innerHTML = window.templateData.description;
    }

    quill.on('text-change', function() {
        document.getElementById('displayQuillContent').innerHTML = quill.root.innerHTML;
        document.getElementById('projectOverviewHidden').value = quill.root.innerHTML;
    });
</script>

<script src="modules/calculationModule.js"></script>
<script src="modules/itemManagementModule.js"></script>
<script src="modules/proposalSubmissionModule.js"></script>
<script src="modules/main.js"></script>

<?php include_once "../../footer.php"; ?>
