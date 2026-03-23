<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', '/home/workorders/tmp');
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    exit("<div class='p-12 text-center font-black text-red-500 uppercase tracking-widest'>Unauthorized</div>");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$type = $_GET['type'] ?? 'task'; // Default to task

if (!$id) {
    echo "<div class='p-12 text-center font-black text-red-500 uppercase tracking-widest'>Invalid ID</div>";
    exit();
}

$job = null;
try {
    $endpoint = ($type === 'lead') ? "/api/leads/{$id}" : "/api/tasks/{$id}";
    $apiResponse = makeApiCall($endpoint);

    if ($apiResponse && ($apiResponse['success'] ?? false)) {
        if ($type === 'lead' && isset($apiResponse['data'])) {
            $job = $apiResponse['data'];
            $job['poNumber'] = "LEAD-" . $job['id'];
            $job['task'] = $job['message'] ?? 'No requirements provided.';
            $job['priority'] = 'Medium';
            $job['status'] = ($job['status_id'] == 1) ? 'Pending' : (($job['status_id'] == 2) ? 'Completed' : 'Reschedule');
            $job['location'] = $job['address'] ?? '';
            $job['property'] = $job['client_name'] ?? 'N/A';
            $job['contact'] = $job['mobile'] ?? '';
            $job['remarks'] = '';
            $job['hash'] = ''; 
            if (!empty($job['follow_ups'])) {
                $job['dateBooked'] = $job['follow_ups'][0]['next_follow_up_date'];
            }
        } elseif (isset($apiResponse['task'])) {
            $job = $apiResponse['task'];
            $job['poNumber'] = $job['poNumber'] ?? ($job['po_number'] ?? '');
            $job['task'] = $job['task'] ?? ($job['heading'] ?? '');
            $job['details'] = $job['description'] ?? '';
            $job['openingDate'] = $job['openingDate'] ?? ($job['start_date'] ?? '');
            $job['closingDate'] = $job['closingDate'] ?? ($job['due_date'] ?? '');
            $job['propertyCode'] = $job['propertyCode'] ?? ($job['property_code'] ?? '');
            $job['invoiceNo'] = $job['invoiceNo'] ?? ($job['invoice_no'] ?? '');
            $job['assignedTo'] = $job['assignedTo'] ?? ($job['assigned_to'] ?? '');
            $job['dateBooked'] = $job['dateBooked'] ?? ($job['date_booked'] ?? '');
            $job['nextVisit'] = $job['nextVisit'] ?? ($job['next_visit'] ?? '');
            $job['assignedUserName'] = $job['assignedToName'] ?? ($job['assigned_user']['name'] ?? null);
        }
    } else {
        echo "<div class='p-12 text-center font-black text-red-500 uppercase tracking-widest'>Job not found</div>";
        exit();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

// Fetch files
$attachedFiles = [];
try {
    if (!empty($job['poNumber'])) {
        $apiResponse = makeApiCall('/api/attachments/by-po/' . $job['poNumber']);
        if ($apiResponse && ($apiResponse['success'] ?? false)) {
            $rawAttachments = $apiResponse['attachments'];
            $uniqueMap = [];
            foreach ($rawAttachments as $at) {
                $name = $at['name'] ?? ($at['DocumentName'] ?? 'Unknown');
                if (!isset($uniqueMap[$name])) { $uniqueMap[$name] = $at; }
            }
            $attachedFiles = array_values($uniqueMap);
        }
    }
} catch (Exception $e) {}

$prioColor = 'bg-gray-100 text-gray-600 dark:bg-slate-800 dark:text-gray-400';
$p = strtolower($job['priority'] ?? '');
if($p == 'urgent') $prioColor = 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
elseif($p == 'high') $prioColor = 'bg-orange-100 text-orange-700 dark:bg-amber-900/30 dark:text-amber-400';
elseif($p == 'medium') $prioColor = 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400';
elseif($p == 'low') $prioColor = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';

$mapQuery = '';
$geoLat = $job['latitude'] ?? $job['lat'] ?? null;
$geoLng = $job['longitude'] ?? $job['lng'] ?? null;
if (!empty($geoLat) && !empty($geoLng)) {
    $mapQuery = "{$geoLat},{$geoLng}";
} elseif (!empty($job['location'])) {
    $mapQuery = $job['location'];
} elseif (!empty($job['property'])) {
    $mapQuery = $job['property'];
}
$hasMapLink = $mapQuery !== '';
$mapUrl = $hasMapLink ? 'https://www.google.com/maps?q=' . urlencode($mapQuery) : '';

$isComplete = strtolower($job['status'] ?? '') === 'completed';

// --- AJAX Response Part ---
if (isset($_GET['ajax']) && $_GET['ajax'] == '1'): ?>
    <div class="space-y-6 p-6 md:p-10 bg-gray-50 dark:bg-slate-950 min-h-[400px]">
        <div class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-gray-100 dark:border-slate-800 shadow-soft relative overflow-hidden">
            <div class="absolute top-0 right-0 px-4 py-2 rounded-bl-2xl text-[10px] font-black uppercase tracking-widest <?php echo $isComplete ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400'; ?>">
                <?php echo $job['status']; ?>
            </div>
            <div class="mb-6">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-500 dark:text-indigo-400 mb-2 block">Work Order Ref</span>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter italic uppercase"><?php echo $job['poNumber']; ?></h1>
                <div class="flex items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 rounded-md <?php echo $prioColor; ?> text-[9px] font-black uppercase tracking-widest border border-black/5 dark:border-white/5"><?php echo $job['priority']; ?></span>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2 italic"><i class="fas fa-map-marker-alt text-indigo-500"></i> Location & Client</h2>
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200 leading-relaxed uppercase italic">
                        <span class="text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($job['property']); ?></span><br>
                        <?php echo nl2br(htmlspecialchars($job['location'])); ?>
                    </p>
                </div>
                <div>
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2 italic"><i class="fas fa-align-left text-indigo-500"></i> Requirements</h2>
                    <div class="p-5 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800 text-sm font-bold italic text-gray-600 dark:text-gray-400 leading-relaxed shadow-inner">
                        "<?php echo nl2br(htmlspecialchars($job['task'])); ?>"
                    </div>
                </div>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-3 no-print">
                <?php if($hasMapLink): ?>
                <a href="<?php echo htmlspecialchars($mapUrl); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 py-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                    <i class="fas fa-map-marker-alt text-indigo-500"></i> View on Maps
                </a>
                <?php endif; ?>
                <div class="flex items-center justify-center py-4 bg-gray-50 dark:bg-slate-800/50 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-400 italic">
                    Status: <?php echo $job['status']; ?>
                </div>
            </div>
        </div>
        <?php if (!empty($attachedFiles)): ?>
        <section class="space-y-3">
            <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-2 flex items-center gap-2 italic"><i class="fas fa-paperclip text-indigo-500"></i> Related Documents</h2>
            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($attachedFiles as $f): 
                    $link = $f['link'] ?? ($f['FilePath'] ?? '');
                    $name = $f['name'] ?? ($f['DocumentName'] ?? 'Unknown');
                ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800 hover:shadow-md transition-all group shadow-sm">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <i class="fas fa-file-pdf text-red-500 group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 truncate uppercase tracking-tighter"><?php echo htmlspecialchars($name); ?></span>
                        </div>
                        <i class="fas fa-external-link-alt text-gray-300 dark:text-gray-600 text-[10px]"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        <div class="pt-8 flex justify-center gap-4 no-print">
            <a href="job_summary.php?id=<?php echo $id; ?>&type=<?php echo $type; ?>" target="_blank" rel="noopener noreferrer" class="px-8 py-4 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] hover:bg-black dark:hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 shadow-xl active:scale-95">
                <i class="fas fa-print text-indigo-200"></i> Full View / Print
            </a>
        </div>
    </div>
<?php exit(); endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary - <?php echo $job['poNumber']; ?></title>
    <link rel="stylesheet" href="dist/output.css">
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else { document.documentElement.classList.remove('dark'); }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .shadow-soft { box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="bg-gray-50 dark:bg-slate-950 transition-colors duration-300 p-4 md:p-8">
    <div class="max-w-xl mx-auto space-y-6 pb-12">
        <!-- (Standard Non-AJAX body content - Similar to above) -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-[2rem] border border-gray-100 dark:border-slate-800 shadow-soft relative overflow-hidden">
            <div class="absolute top-0 right-0 px-4 py-2 rounded-bl-2xl text-[10px] font-black uppercase tracking-widest <?php echo $isComplete ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400'; ?>">
                <?php echo $job['status']; ?>
            </div>
            <div class="mb-6">
                <span class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-500 dark:text-indigo-400 mb-2 block">Work Order Ref</span>
                <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter italic uppercase"><?php echo $job['poNumber']; ?></h1>
                <div class="flex items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 rounded-md <?php echo $prioColor; ?> text-[9px] font-black uppercase tracking-widest border border-black/5 dark:border-white/5"><?php echo $job['priority']; ?></span>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2 italic"><i class="fas fa-map-marker-alt text-indigo-500"></i> Location & Client</h2>
                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200 leading-relaxed uppercase italic">
                        <span class="text-indigo-600 dark:text-indigo-400"><?php echo htmlspecialchars($job['property']); ?></span><br>
                        <?php echo nl2br(htmlspecialchars($job['location'])); ?>
                    </p>
                </div>
                <div>
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2 italic"><i class="fas fa-align-left text-indigo-500"></i> Requirements</h2>
                    <div class="p-5 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-100 dark:border-slate-800 text-sm font-bold italic text-gray-600 dark:text-gray-400 leading-relaxed shadow-inner">
                        "<?php echo nl2br(htmlspecialchars($job['task'])); ?>"
                    </div>
                </div>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-3 no-print">
                <?php if($hasMapLink): ?>
                <a href="<?php echo htmlspecialchars($mapUrl); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 py-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                    <i class="fas fa-map-marker-alt text-indigo-500"></i> View on Maps
                </a>
                <?php endif; ?>
                <div class="flex items-center justify-center py-4 bg-gray-50 dark:bg-slate-800/50 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-400 italic">
                    Status: <?php echo $job['status']; ?>
                </div>
            </div>
        </div>
        <?php if (!empty($attachedFiles)): ?>
        <section class="space-y-3">
            <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-2 flex items-center gap-2 italic"><i class="fas fa-paperclip text-indigo-500"></i> Related Documents</h2>
            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($attachedFiles as $f): 
                    $link = $f['link'] ?? ($f['FilePath'] ?? '');
                    $name = $f['name'] ?? ($f['DocumentName'] ?? 'Unknown');
                ?>
                    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800 hover:shadow-md transition-all group shadow-sm">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <i class="fas fa-file-pdf text-red-500 group-hover:scale-110 transition-transform"></i>
                            <span class="text-[11px] font-bold text-gray-700 dark:text-gray-300 truncate uppercase tracking-tighter"><?php echo htmlspecialchars($name); ?></span>
                        </div>
                        <i class="fas fa-external-link-alt text-gray-300 dark:text-gray-600 text-[10px]"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        <div class="pt-8 flex justify-center gap-4 no-print">
            <button onclick="window.print()" class="px-8 py-4 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] hover:bg-black dark:hover:bg-indigo-700 transition-all flex items-center justify-center gap-2 shadow-xl">
                <i class="fas fa-print text-indigo-200"></i> Print PDF
            </button>
        </div>
    </div>
</body>
</html>
