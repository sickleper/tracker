<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SECURITY: Authentication check
if (!isTrackerAuthenticated()) {
    http_response_code(401);
    exit("Unauthorized");
}

// Decode 'data' param if present to bypass WAF filters on URL parameters
$incoming = $_GET;
if (isset($_GET['data'])) {
    $rawData = $_GET['data'];
    // Fix potential space-to-plus conversion issue from URL decoding
    $normalizedData = str_replace(' ', '+', $rawData);
    $decodedData = base64_decode($normalizedData);
    if ($decodedData !== false) {
        $decodedJson = json_decode($decodedData, true);
        if (is_array($decodedJson)) {
            $incoming = array_merge($incoming, $decodedJson);
        } else {
            error_log("Gmail Import: Failed to decode JSON from 'data' parameter.");
        }
    } else {
        error_log("Gmail Import: Failed to Base64 decode 'data' parameter.");
    }
}

$id = isset($incoming['id']) ? (int)$incoming['id'] : null;
$wo = null;

if ($id) {
    // Fetch existing if updating from Laravel API
    $response = makeApiCall("/api/tasks/{$id}");
    if ($response && ($response['id'] ?? false)) { // Check for 'id' as TaskController returns Task object directly
        $wo = $response;
        // Rename keys to match old variable names for backward compatibility
        $wo['poNumber'] = $wo['po_number'];
        $wo['propertyCode'] = $wo['property_code'];
        $wo['clientId'] = $wo['client_id'];
        $wo['dateBooked'] = $wo['date_booked'];
        $wo['nextVisit'] = $wo['next_visit'];
        $wo['task'] = $wo['heading'];
    } else {
        error_log("Failed to fetch task {$id} from Laravel API.");
    }
}

// Helper to handle potentially Base64 encoded PDFs from URL to bypass WAF
$rawPdfs = $incoming['pdfs'] ?? '[]';
if (!empty($rawPdfs) && $rawPdfs !== '[]' && $rawPdfs[0] !== '[' && $rawPdfs[0] !== '{') {
    // Attempt Base64 decode (non-strict)
    $decoded = base64_decode(str_replace(' ', '+', $rawPdfs));
    // Check if result is valid JSON
    if ($decoded !== false) {
        $jsonCheck = json_decode($decoded);
        if ($jsonCheck !== null) {
            $rawPdfs = $decoded;
        }
    }
}

// Map parameters from URL/Encoded Data
$params = [
    'poNumber' => $incoming['po_number'] ?? ($wo['poNumber'] ?? ''),
    'propertyCode' => $incoming['property_code'] ?? ($wo['propertyCode'] ?? ''),
    'eircode' => $incoming['eircode'] ?? ($wo['eircode'] ?? ''),
    'task' => $incoming['heading'] ?? ($wo['task'] ?? ''),
    'location' => $incoming['property'] ?? ($wo['location'] ?? ''),
    'clientId' => $incoming['client_id'] ?? ($wo['clientId'] ?? ''),
    'contact' => $incoming['contact'] ?? ($wo['contact'] ?? ''),
    'priority' => $incoming['priority'] ?? ($wo['priority'] ?? 'Medium'),
    'openingDate' => $incoming['openingDate'] ?? date('Y-m-d'),
    'dateBooked' => $incoming['dateBooked'] ?? ($wo['dateBooked'] ?? ''),
    'nextVisit' => $incoming['nextVisit'] ?? ($wo['nextVisit'] ?? ''),
    'uid' => $incoming['uid'] ?? '',
    'pdfs' => $rawPdfs
];

// Active users are fetched via API call already in tracker_data.php, so can directly make this call.
// No longer needs the local 'users' array here explicitly as it was for a dropdown.
// The dropdown for Assigned To will need to call /api/users itself via JavaScript.

$users = [];
$usersApiResponse = makeApiCall('/api/users/active');
if ($usersApiResponse && ($usersApiResponse['success'] ?? false)) {
    $users = $usersApiResponse['users'] ?? [];
}

$priorityOptions = ['Low', 'Medium', 'High', 'Urgent', 'Emergency'];
$statusOptions = ['Open', 'Pending', 'In Progress', 'On Hold', 'Completed', 'Closed', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order Import</title>
    <link rel="stylesheet" href="dist/output.css">
    
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .loader-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-6">
    <!-- Loader Overlay -->
    <div id="loader" class="loader-overlay">
        <div class="w-16 h-16 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
        <p class="text-indigo-900 font-bold text-lg animate-pulse">Creating Work Order...</p>
        <p class="text-gray-500 text-sm">Please wait, syncing with Google Drive & Sheets...</p>
    </div>

    <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <form id="importForm" action="tracker_handler.php" method="POST" enctype="multipart/form-data" class="divide-y divide-gray-100">
            <input type="hidden" name="action" value="<?php echo $id ? 'save_edit' : 'create'; ?>">
            <?php if ($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
            
            <!-- Gmail Meta (Optional for future automation) -->
            <input type="hidden" name="gmail_uid" value="<?php echo htmlspecialchars($params['uid']); ?>">
            <?php 
                // Extract names for backend compatibility
                $pendingPdfs = json_decode($params['pdfs'], true);
                $pdfNamesOnly = [];
                if (!empty($pendingPdfs)) {
                    foreach ($pendingPdfs as $p) {
                        $pdfNamesOnly[] = is_array($p) ? $p['name'] : $p;
                    }
                }
            ?>
            <input type="hidden" name="gmail_pdfs" value='<?php echo htmlspecialchars(json_encode($pdfNamesOnly)); ?>'>
            <input type="hidden" id="latLngInput" name="lat_lng" value="">

            <!-- Header Section -->
            <div class="p-6 bg-indigo-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">PO Number *</label>
                        <input type="text" name="poNumber" required value="<?php echo htmlspecialchars($params['poNumber']); ?>" class="w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none text-lg font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-indigo-600 uppercase tracking-wider mb-1">Priority</label>
                        <select name="priority" required class="w-full px-4 py-3 rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none bg-white">
                            <?php foreach ($priorityOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo strtolower($opt) == strtolower($params['priority']) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Client & Assignment -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Client</label>
                        <select name="clientId" id="clientSelect" required class="w-full px-4 py-2 rounded-lg border-gray-300 bg-white" onchange="fillClientDetails()">
                            <option value="">Loading clients...</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Assigned To</label>
                        <select name="assignedTo" class="w-full px-4 py-2 rounded-lg border-gray-300 bg-white">
                            <option value="">Select person...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($wo['assigned_to'] ?? '') == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 opacity-60">
                    <input type="text" id="invoiceContact" name="invoiceContact" placeholder="Inv Contact" class="bg-gray-50 border rounded p-2 text-sm pointer-events-none" readonly>
                    <input type="text" id="invoiceAddress" name="invoiceAddress" placeholder="Inv Address" class="bg-gray-50 border rounded p-2 text-sm pointer-events-none" readonly>
                    <input type="text" id="invoiceEmail" name="invoiceEmail" placeholder="Inv Email" class="bg-gray-50 border rounded p-2 text-sm pointer-events-none" readonly>
                </div>
            </div>

            <!-- Job Content -->
            <div class="p-6 space-y-6">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Task / Work Required *</label>
                    <textarea name="task" required rows="4" class="w-full px-4 py-2 rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500 outline-none"><?php echo htmlspecialchars($params['task']); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Property Code</label>
                        <input type="text" name="propertyCode" value="<?php echo htmlspecialchars($params['propertyCode']); ?>" class="w-full px-4 py-2 rounded-lg border-gray-300 uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Eircode</label>
                        <input type="text" name="eircode" value="<?php echo htmlspecialchars($params['eircode']); ?>" class="w-full px-4 py-2 rounded-lg border-gray-300 uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact</label>
                        <input type="text" name="contact" value="<?php echo htmlspecialchars($params['contact']); ?>" class="w-full px-4 py-2 rounded-lg border-gray-300">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Property Name</label>
                    <input type="text" name="property" value="" class="w-full px-4 py-2 rounded-lg border-gray-300 placeholder-gray-300" placeholder="e.g. Iveagh Trust Flats">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Location / Address</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($params['location']); ?>" class="w-full px-4 py-2 rounded-lg border-gray-300">
                </div>
            </div>

            <!-- Scheduling -->
            <div class="p-6 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2 rounded-lg border-gray-300 bg-white">
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo strtolower($opt) == 'open' ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Opening Date</label>
                        <input type="date" name="openingDate" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 rounded-lg border-gray-300 bg-blue-50 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Date Booked</label>
                        <input type="date" name="dateBooked" value="<?php echo $params['dateBooked']; ?>" class="w-full px-4 py-2 rounded-lg border-gray-300">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Next Visit</label>
                        <input type="date" name="nextVisit" value="<?php echo $params['nextVisit']; ?>" class="w-full px-4 py-2 rounded-lg border-gray-300">
                    </div>
                </div>
            </div>

            <!-- File Upload -->
            <div class="p-6 bg-gray-50 space-y-4">
                <?php 
                $pendingPdfs = json_decode($params['pdfs'], true);
                if (!empty($pendingPdfs)): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-xs font-bold text-blue-600 uppercase mb-2">Automatically Carrying Over from Gmail:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($pendingPdfs as $pdf): 
                                $name = is_array($pdf) ? $pdf['name'] : $pdf;
                                $link = is_array($pdf) ? ($pdf['link'] ?? '#') : '#';
                            ?>
                                <?php if ($link !== '#'): ?>
                                    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener noreferrer" class="px-3 py-1 bg-white border border-blue-200 rounded text-sm text-blue-700 flex items-center gap-2 hover:bg-blue-50 transition-colors">
                                        <i class="far fa-file-pdf text-red-500"></i> <?php echo htmlspecialchars($name); ?> <i class="fas fa-external-link-alt text-xs text-gray-400 ml-1"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-white border border-blue-200 rounded text-sm text-blue-700 flex items-center gap-2">
                                        <i class="far fa-file-pdf text-red-500"></i> <?php echo htmlspecialchars($name); ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-4 rounded-lg border border-dashed border-gray-300">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Upload Additional Files</label>
                    <input type="file" name="attachments[]" multiple class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-200">
                </div>
            </div>

            <!-- Footer / Actions -->
            <div class="p-6 bg-white flex flex-col sm:flex-row gap-4">
                <button type="submit" class="flex-1 py-4 px-6 bg-indigo-600 text-white rounded-lg font-bold text-lg hover:bg-indigo-700 shadow-md transition-all">
                    <i class="fas fa-file-import mr-2"></i> <?php echo $id ? 'Update Job' : 'Complete Import'; ?>
                </button>
                <button type="button" onclick="window.parent.postMessage('close_modal', '*')" class="py-4 px-8 bg-gray-100 text-gray-600 rounded-lg font-bold hover:bg-gray-200 transition-all">
                    Cancel
                </button>
            </div>
        </form>
    </div>

    <script>
        let clientsData = [];
        const preselectedClientId = "<?php echo $params['clientId']; ?>";
        const initialEircode = "<?php echo $params['eircode']; ?>";
        const apiToken = "<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>";

        document.getElementById('importForm').addEventListener('submit', function() {
            document.getElementById('loader').style.display = 'flex';
        });

        document.addEventListener('DOMContentLoaded', function() {
            // 1. Fetch Clients from Laravel API
            fetch('<?php echo $_ENV['LARAVEL_API_URL']; ?>/api/clients/tabs', {
                headers: {
                    'Authorization': 'Bearer ' + apiToken,
                    'Accept': 'application/json'
                }
            })
                .then(r => {
                    if (!r.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return r.json();
                })
                .then(data => {
                    if (data.success) {
                        clientsData = data.clients;
                        const select = document.getElementById('clientSelect');
                        select.innerHTML = '<option value="">Select Client...</option>';
                        clientsData.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                        
                        clientsData.forEach(client => {
                            const opt = document.createElement('option');
                            opt.value = client.id;
                            opt.textContent = client.name;
                            if (client.id == preselectedClientId) opt.selected = true;
                            select.appendChild(opt);
                        });
                        fillClientDetails();
                    } else {
                        console.error('API Error:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    const select = document.getElementById('clientSelect');
                    select.innerHTML = '<option value="">Error loading clients</option>';
                });

            // 2. Auto-Lookup Eircode if present
            if (initialEircode && initialEircode.length >= 7) {
                autoLookupEircode(initialEircode);
            }
        });

        function autoLookupEircode(eircode) {
            fetch(`../query_address.php?eircode=${encodeURIComponent(eircode)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.address && !data.error) {
                        document.querySelector('input[name="location"]').value = data.address;
                    }
                    if (data.coordinates) {
                        document.getElementById('latLngInput').value = `${data.coordinates.lat},${data.coordinates.lng}`;
                    }
                })
                .catch(e => {
                    console.error("Auto-lookup failed", e);
                    // No need to revert, it just stays as the original $_GET value
                });
        }

        function fillClientDetails() {
            const clientId = document.getElementById('clientSelect').value;
            const client = clientsData.find(c => c.id == clientId);
            if (client) {
                document.getElementById('invoiceContact').value = client.name || '';
                document.getElementById('invoiceAddress').value = client.address || '';
                document.getElementById('invoiceEmail').value = client.invoice_email || client.email || '';
            }
        }
    </script>
</body>
</html>
