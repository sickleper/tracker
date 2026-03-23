<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: oauth2callback.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$wo = null;

if ($id) {
    // Fetch task details from API
    $response = makeApiCall("/api/tasks/{$id}");
    if ($response && ($response['success'] ?? false)) {
        $wo = $response['task'];
    }

    // GMAIL OVERRIDE: If loading from Gmail list, use the fresh extracted info
    if (isset($_GET['po_number'])) {
        if (!empty($_GET['po_number'])) $wo['poNumber'] = $_GET['po_number'];
        if (!empty($_GET['property_code'])) $wo['propertyCode'] = $_GET['property_code'];
        if (!empty($_GET['eircode'])) $wo['eircode'] = $_GET['eircode'];
        if (!empty($_GET['heading'])) $wo['task'] = $_GET['heading'];
        if (!empty($_GET['property'])) $wo['location'] = $_GET['property'];
        if (!empty($_GET['contact'])) $wo['contact'] = $_GET['contact'];
        if (!empty($_GET['priority'])) $wo['priority'] = $_GET['priority'];
        if (!empty($_GET['dateBooked'])) $wo['dateBooked'] = $_GET['dateBooked'];
        if (!empty($_GET['nextVisit'])) $wo['nextVisit'] = $_GET['nextVisit'];
    }
} else {
    // Check for GET parameters (Import from Gmail)
    $wo = [
        'poNumber' => $_GET['po_number'] ?? '',
        'task' => $_GET['heading'] ?? '',
        'location' => $_GET['property'] ?? '',
        'clientId' => $_GET['client_id'] ?? '',
        'contact' => $_GET['contact'] ?? '',
        'priority' => $_GET['priority'] ?? 'Medium',
        'openingDate' => $_GET['openingDate'] ?? date('Y-m-d'),
        'dateBooked' => $_GET['dateBooked'] ?? '',
        'nextVisit' => $_GET['nextVisit'] ?? '',
        'propertyCode' => $_GET['property_code'] ?? '',
        'eircode' => $_GET['eircode'] ?? '',
        'invoiceNo' => '',
        'invoiceSent' => 'No',
        'certSent' => 'No',
        'assignedTo' => '',
        'lat_lng' => '',
        'invoiceContact' => '',
        'invoiceAddress' => '',
        'invoiceEmail' => ''
    ];
}

// Get active users for assignment from API
$usersResponse = makeApiCall("/api/users/active");
$users = ($usersResponse && ($usersResponse['success'] ?? false)) ? $usersResponse['users'] : [];

// Fetch Subcontractors for Tagging from API
$subcontractorsResponse = makeApiCall("/api/users/subcontractors");
$subcontractors = ($subcontractorsResponse && ($subcontractorsResponse['success'] ?? false)) ? $subcontractorsResponse['subcontractors'] : [];

$priorityOptions = ['Low', 'Medium', 'High', 'Urgent', 'Emergency'];
$statusOptions = ['Open', 'Pending', 'In Progress', 'On Hold', 'Completed', 'Closed', 'Cancelled'];
$yesNoOptions = ['Yes', 'No', 'Not Required'];
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

$pageTitle = $wo ? "Edit Order #" . ($id ?? '') : "Create New Work Order";

include_once "header.php";
if (!$isEmbedded) {
    include_once "nav.php";
}
?>

<div class="<?php echo $isEmbedded ? 'px-4 py-4 md:px-6 md:py-6' : 'max-w-4xl mx-auto px-4 py-8'; ?>">
    
    <!-- Header -->
    <?php 
        $backUrl = "index.php";
        if ($wo && !empty($wo['clientId'])) $backUrl .= "?client_filter=" . $wo['clientId'];
    ?>
    <?php if (!$isEmbedded): ?>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="heading-brand">
                    <i class="fas <?php echo $id ? 'fa-edit' : 'fa-plus-circle'; ?> text-indigo-600 dark:text-indigo-400 mr-2"></i> <?php echo $pageTitle; ?>
                </h1>
                <p class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Manage core work order parameters and files</p>
            </div>
            <a href="<?php echo $backUrl; ?>" class="btn-secondary py-2 px-4 shadow-none">
                <i class="fas fa-arrow-left"></i> Back to Table
            </a>
        </div>
    <?php endif; ?>

    <!-- Form Section -->
    <div class="card-base">
        <form action="tracker_handler.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $id ? 'save_edit' : 'create'; ?>">
            <?php if ($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
            <input type="hidden" name="lat_lng" id="latLngInput" value="<?php echo htmlspecialchars($wo['lat_lng'] ?? ''); ?>">

            <div class="p-8 space-y-8">
                <!-- Group 1: PO & Priority -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">PO Number *</label>
                        <input type="text" name="poNumber" required value="<?php echo htmlspecialchars($wo['poNumber'] ?? ''); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold dark:text-white outline-none transition-all" placeholder="PO-12345">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Priority Level *</label>
                        <select name="priority" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold dark:text-white outline-none transition-all">
                            <?php foreach ($priorityOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo (strtolower($opt) == strtolower($wo['priority'] ?? 'Medium')) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Group 2: Description -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Task/Job Description *</label>
                    <textarea name="task" required rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-medium dark:text-gray-300 outline-none transition-all leading-relaxed" placeholder="Detailed description of the work..."><?php echo htmlspecialchars($wo['task'] ?? ''); ?></textarea>
                </div>

                <!-- Group 3: Client & Invoicing (Nested Card) -->
                <div class="bg-indigo-50/50 dark:bg-indigo-950/20 p-6 rounded-3xl border border-indigo-100 dark:border-indigo-900/30 space-y-6">
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
                        <i class="fas fa-user-tie"></i> Client & Billing
                    </h4>
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-indigo-400 mb-2">Automated Client Selection</label>
                        <input type="hidden" name="clientId" id="clientIdInput" value="<?php echo $wo['clientId'] ?? ''; ?>">
                        <select id="clientSelect" class="w-full p-4 bg-white dark:bg-slate-900 border border-indigo-100 dark:border-indigo-900/50 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold dark:text-white outline-none transition-all" onchange="fillClientDetails(true)">
                            <option value="">Loading client list...</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">Invoice Contact</label>
                            <input type="text" id="invoiceContact" name="invoiceContact" value="<?php echo htmlspecialchars($wo['invoiceContact'] ?? ($wo['invoice_contact'] ?? '')); ?>" class="w-full p-3 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">Invoice Address</label>
                            <input type="text" id="invoiceAddress" name="invoiceAddress" value="<?php echo htmlspecialchars($wo['invoiceAddress'] ?? ($wo['invoice_address'] ?? '')); ?>" class="w-full p-3 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 mb-2">Invoice Email</label>
                            <input type="email" id="invoiceEmail" name="invoiceEmail" value="<?php echo htmlspecialchars($wo['invoiceEmail'] ?? ($wo['invoice_email'] ?? '')); ?>" class="w-full p-3 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white">
                        </div>
                    </div>
                </div>

                <!-- Group 4: Site Details -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Eircode / Address Identification</label>
                        <div class="flex gap-2">
                            <input type="text" id="eircodeInput" name="eircode" value="<?php echo htmlspecialchars($wo['eircode'] ?? ''); ?>" class="flex-grow p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-black uppercase dark:text-white" placeholder="D24WF60">
                            <button type="button" onclick="lookupEircode()" class="px-6 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 transition-all shadow-lg active:scale-95">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Property Code</label>
                        <input type="text" name="propertyCode" value="<?php echo htmlspecialchars($wo['propertyCode'] ?? ''); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-black uppercase dark:text-white" placeholder="FLAT-01">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Property Name</label>
                        <input type="text" id="propertyName" name="property" value="<?php echo htmlspecialchars($wo['property'] ?? ''); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white" placeholder="Site Name">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Full Location / Address</label>
                        <input type="text" id="locationAddress" name="location" value="<?php echo htmlspecialchars($wo['location'] ?? ''); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-medium dark:text-gray-300" placeholder="Street Address">
                    </div>
                </div>

                <!-- Group 5: Status & Assignment -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Lead Technician</label>
                        <select name="assignedTo" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold dark:text-white outline-none">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $u) {
                                $label = htmlspecialchars($u['name']) . (!empty($u['is_subcontractor']) ? ' (Sub)' : '');
                                echo "<option value='{$u['id']}' " . (($wo['assignedTo'] ?? '') == $u['id'] ? 'selected' : '') . ">{$label}</option>";
                            } ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Workflow Status *</label>
                        <select name="status" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 text-sm font-bold dark:text-white outline-none">
                            <?php foreach ($statusOptions as $opt) echo "<option value='{$opt}' " . (strtolower($opt) == strtolower($wo['status'] ?? 'Open') ? 'selected' : '') . ">{$opt}</option>"; ?>
                        </select>
                    </div>
                </div>

                <!-- Group 6: Dates (Styled Grid) -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 bg-gray-50 dark:bg-slate-900/50 rounded-3xl border border-gray-100 dark:border-slate-800">
                    <?php
                    $today = date('Y-m-d');
                    $dates = [
                        ['label' => 'Opening', 'name' => 'openingDate', 'val' => ($wo['openingDate'] ?? '' && $wo['openingDate'] !== '0000-00-00') ? $wo['openingDate'] : $today, 'cls' => 'text-gray-900 dark:text-white'],
                        ['label' => 'Booked',  'name' => 'dateBooked',  'val' => ($wo['dateBooked']  ?? '' && $wo['dateBooked']  !== '0000-00-00') ? $wo['dateBooked']  : '',      'cls' => 'text-blue-600 dark:text-blue-400'],
                        ['label' => 'Visit',   'name' => 'nextVisit',   'val' => ($wo['nextVisit']   ?? '' && $wo['nextVisit']   !== '0000-00-00') ? $wo['nextVisit']   : '',      'cls' => 'text-orange-600 dark:text-orange-400'],
                        ['label' => 'Closed',  'name' => 'closingDate', 'val' => ($wo['closingDate'] ?? '' && $wo['closingDate'] !== '0000-00-00') ? $wo['closingDate'] : '',      'cls' => 'text-emerald-600 dark:text-emerald-400']
                    ];
                    foreach ($dates as $d): ?>
                        <div class="space-y-1.5">
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 ml-1"><?php echo $d['label']; ?></label>
                            <input type="date" name="<?php echo $d['name']; ?>" value="<?php echo $d['val']; ?>" class="w-full bg-transparent border-none p-0 text-xs font-black uppercase <?php echo $d['cls']; ?> focus:ring-0">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Group 7: Billing Status -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Invoice Status</label>
                        <select name="invoiceSent" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white">
                            <?php foreach ($yesNoOptions as $opt) echo "<option value='{$opt}' " . (($wo['invoiceSent'] ?? 'No') == $opt ? 'selected' : '') . ">{$opt}</option>"; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Invoice Number</label>
                        <input type="text" name="invoiceNo" value="<?php echo htmlspecialchars($wo['invoiceNo'] ?? ''); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white" placeholder="####">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Certificate Sent</label>
                        <select name="certSent" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white">
                            <?php foreach ($yesNoOptions as $opt) echo "<option value='{$opt}' " . (($wo['certSent'] ?? 'No') == $opt ? 'selected' : '') . ">{$opt}</option>"; ?>
                        </select>
                    </div>
                </div>

                <!-- Group 8: Remarks -->
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-2 ml-1">Internal Remarks</label>
                    <textarea name="remarks" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-red-500 text-sm font-bold text-red-600 dark:text-red-400 outline-none transition-all leading-relaxed" placeholder="Confidential notes..."><?php echo htmlspecialchars($wo['remarks'] ?? ''); ?></textarea>
                </div>

                <!-- Group 9: Attachments -->
                <div class="bg-indigo-50 dark:bg-indigo-950/20 p-8 rounded-3xl border-2 border-dashed border-indigo-200 dark:border-indigo-900/50">
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-paperclip"></i> Media & Attachments
                    </h4>

                    <?php if ($id && !empty($wo['poNumber'])): ?>
                        <?php
                        $attachmentsResponse = makeApiCall("/api/tasks/{$id}/attachments");
                        $existingFiles = ($attachmentsResponse && ($attachmentsResponse['success'] ?? false)) ? $attachmentsResponse['attachments'] : [];
                        if (!empty($existingFiles)):
                            ?>
                            <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach($existingFiles as $f): ?>
                                    <div id="doc-<?php echo $f['id']; ?>" class="group relative bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-indigo-100 dark:border-indigo-900/50 flex items-center gap-4 hover:shadow-md transition-all">
                                        <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400"><i class="fas fa-file-pdf"></i></div>
                                        <div class="flex-1 min-w-0">
                                            <a href="<?php echo htmlspecialchars($f['link']); ?>" target="_blank" rel="noopener noreferrer" class="block text-xs font-bold text-gray-900 dark:text-white truncate hover:underline"><?php echo htmlspecialchars($f['name']); ?></a>
                                            <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest"><?php echo $f['date']; ?></span>
                                        </div>
                                        <button type="button" onclick="deleteAttachment(<?php echo $f['id']; ?>)" class="w-8 h-8 rounded-full bg-red-50 dark:bg-red-950/30 text-red-500 hover:bg-red-100 opacity-0 group-hover:opacity-100 transition-opacity"><i class="fas fa-trash-alt text-[10px]"></i></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="relative group">
                        <input type="file" name="attachments[]" multiple accept="image/*,.pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <div class="w-full py-8 text-center bg-white dark:bg-slate-900/50 rounded-2xl border border-indigo-100 dark:border-indigo-900/30 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 transition-all">
                            <i class="fas fa-cloud-upload-alt text-indigo-400 text-3xl mb-2"></i>
                            <p class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Click or drag to upload files</p>
                            <p class="text-[9px] text-gray-400 mt-1 font-bold">Images and PDFs supported</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Footer -->
            <div class="bg-gray-50 dark:bg-slate-900/50 p-8 border-t border-gray-100 dark:border-slate-800 flex flex-col md:flex-row gap-4">
                <button type="submit" class="btn-primary flex-1 py-4 text-sm tracking-widest"><i class="fas fa-save mr-2 text-emerald-400"></i> <?php echo $id ? 'Save Changes' : 'Create Order'; ?></button>
                <a href="<?php echo $backUrl; ?>" class="btn-secondary flex-1 py-4 text-sm tracking-widest text-gray-400">Cancel Entry</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Lead client data cache
    let clientsData = [];

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Fetch clients from API for auto-fill logic
        fetch('tracker_clients.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    clientsData = data.data;
                    const select = document.getElementById('clientSelect');
                    select.innerHTML = '<option value="">Select a Client to Auto-fill...</option>';
                    clientsData.sort((a,b) => (a.name || '').localeCompare(b.name || ''));
                    clientsData.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        select.appendChild(opt);
                    });
                    const savedId = document.getElementById('clientIdInput').value;
                    if (savedId) {
                        select.value = savedId;
                        fillClientDetails();
                    }
                }
            });
    });

    function fillClientDetails(force = false) {
        const clientId = document.getElementById('clientSelect').value;
        document.getElementById('clientIdInput').value = clientId;
        if (!clientId) return;
        const c = clientsData.find(x => x.id == clientId);
        if (c) {
            // Auto-fill Invoice details
            const fields = { invoiceContact: 'name', invoiceAddress: 'address', invoiceEmail: 'invoice_email' };
            Object.keys(fields).forEach(fid => {
                const el = document.getElementById(fid);
                const val = c[fields[fid]] || (fields[fid] === 'invoiceEmail' ? c.email : '');
                if (force || !el.value) el.value = val;
            });

            // Auto-fill PO Prefix if empty
            const poInput = document.querySelector('input[name="poNumber"]');
            if (c.wo_prefix && (force || !poInput.value)) {
                poInput.value = c.wo_prefix;
            }
        }
    }

    function lookupEircode() {
        const eircode = document.getElementById('eircodeInput').value.trim();
        if (!eircode) return Swal.fire({ title: 'Error', text: 'Please enter an Eircode', icon: 'error', theme: getSwalTheme() });
        const btn = document.querySelector('button[onclick="lookupEircode()"]');
        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        fetch(`../query_address.php?eircode=${eircode}`)
            .then(r => r.json())
            .then(data => {
                btn.disabled = false; btn.innerHTML = orig;
                if (data.error) return Swal.fire({ title: 'Error', text: data.error, icon: 'error', theme: getSwalTheme() });
                document.getElementById('locationAddress').value = data.address;
                const latLngInput = document.getElementById('latLngInput');
                if (latLngInput && data.coordinates) {
                    latLngInput.value = `${data.coordinates.lat},${data.coordinates.lng}`;
                }
                Swal.fire({ title: 'Address Found', text: data.address, icon: 'success', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
            }).catch(() => { btn.disabled = false; btn.innerHTML = orig; });
    }

    function deleteAttachment(id) {
        Swal.fire({ title: 'Delete File?', text: "This will permanently remove the attachment.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', theme: getSwalTheme() })
            .then((r) => {
                if (r.isConfirmed) {
                    const fd = new FormData(); fd.append('action', 'delete_attachment'); fd.append('doc_id', id);
                    fetch('tracker_handler.php', { method: 'POST', body: fd })
                        .then(r => r.json()).then(data => {
                            if (data.success) { document.getElementById('doc-'+id).remove(); Swal.fire({ title: 'Deleted!', text: 'File removed.', icon: 'success', theme: getSwalTheme() }); }
                        });
                }
            });
    }
</script>

<?php include_once "footer.php"; ?>
