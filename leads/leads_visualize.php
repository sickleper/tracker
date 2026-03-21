<?php
// leads/leads_visualize.php - PORTED TO API
require_once '../config.php';
require_once '../api_helper.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$numWeeks = filter_var($_GET['weeks'] ?? 4, FILTER_VALIDATE_INT);

// 1. Fetch Schedule Data via API
$response = makeApiCall('/api/booking/schedule', ['weeks' => $numWeeks]);

if (!$response || !($response['success'] ?? false)) {
    error_log("API Error in leads_visualize.php: " . print_r($response, true));
    $response = [
        'startDate' => date('Y-m-d'),
        'endDate' => date('Y-m-d'),
        'schedule' => [],
        'holidays' => [],
        'leaves' => [],
        'standardSlots' => [],
        'allowedDayNames' => ['Thursday', 'Friday'],
        'loadError' => $response['message'] ?? 'Failed to fetch schedule from API.'
    ];
}

$startDate = $response['startDate'];
$endDate = $response['endDate'];
$schedule = $response['schedule'];
$holidays = $response['holidays'];
$leavesByDate = $response['leaves'];
$standardSlots = $response['standardSlots'];
$allowedDayNames = $response['allowedDayNames'] ?? ['Thursday', 'Friday'];
$loadError = $response['loadError'] ?? null;

$pageTitle = "Scheduling Master View";
include '../header.php';
include '../nav.php';
?>

<style>
    @media print { .no-print { display: none !important; } }
    .slot-booked { @apply bg-indigo-50 dark:bg-indigo-900/30 border-l-4 border-indigo-600 dark:border-indigo-400; }
    .slot-booked-alt { @apply bg-emerald-50 dark:bg-emerald-900/30 border-l-4 border-emerald-600 dark:border-emerald-400; }
    .slot-holiday { @apply bg-red-50 dark:bg-red-950/30 border-l-4 border-red-600; }
    .slot-half-day-leave { @apply bg-amber-50 dark:bg-amber-950/20 border-l-4 border-amber-600; }
    .slot-empty { @apply bg-gray-50 dark:bg-slate-900 border-l-4 border-gray-200 dark:border-slate-700; }
    .week-map { height: 400px; width: 100%; border-radius: 1.5rem; margin-top: 1rem; position: relative; }
    
    .dark .fc-unthemed td, .dark .fc-unthemed th { border-color: #334155 !important; }
</style>

<script>
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initWeeklyMap(elementId, locations) {
        const mapDiv = document.getElementById(elementId);
        if (!mapDiv) return;
        mapDiv.innerHTML = '';

        const map = L.map(elementId, { center: [53.4106, -6.4426], zoom: 10, tap: false });
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        if (!locations || locations.length === 0) return;
        
        const markers = [];
        locations.forEach(loc => {
            const lat = parseFloat(loc.lat);
            const lng = parseFloat(loc.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            // Simple color toggle based on day index in configured calloutDays
            const dayIndex = window.calloutDays.indexOf(moment(loc.date).day());
            const color = (dayIndex % 2 === 0) ? '#4f46e5' : '#10b981';
            
            const icon = L.divIcon({
                className: 'custom-div-icon',
                html: `<div style="background-color:${color}; width:14px; height:14px; border-radius:50%; border:2px solid white; box-shadow:0 4px 6px rgba(0,0,0,0.3);"></div>`,
                iconSize: [14, 14],
                iconAnchor: [7, 7]
            });

            const marker = L.marker([lat, lng], { icon: icon }).addTo(map);
            marker.bindPopup(`
                <div class="p-2 min-w-[180px] dark:text-slate-900">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest">${escapeHtml(loc.time)}</span>
                        <span class="text-[8px] font-black px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded uppercase tracking-widest">${escapeHtml(loc.category)}</span>
                    </div>
                    <strong class="text-sm block text-gray-900 leading-tight mb-1 uppercase font-black italic italic">${escapeHtml(loc.title)}</strong>
                    <p class="text-[10px] text-gray-500 leading-normal border-t border-gray-100 pt-2 mt-2 italic">${escapeHtml(loc.address)}</p>
                </div>
            `);
            markers.push([lat, lng]);
        });

        if (markers.length > 0) map.fitBounds(markers, { padding: [40, 40] });
        setTimeout(() => map.invalidateSize(), 500);
    }
</script>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php if ($loadError): ?>
        <div class="card-base border-none mb-8 p-6 border-l-4 border-l-red-500">
            <div class="text-red-600 dark:text-red-400 font-black uppercase tracking-widest text-[10px] mb-2">Schedule Load Failed</div>
            <div class="text-sm text-gray-600 dark:text-gray-300"><?php echo htmlspecialchars($loadError); ?></div>
        </div>
    <?php endif; ?>
    
    <!-- Header Section -->
    <header class="flex flex-col lg:flex-row justify-between items-center mb-8 gap-6">
        <div>
            <h1 class="heading-brand">
                <i class="fas fa-calendar-alt text-indigo-600 dark:text-indigo-400 mr-2"></i> Scheduling Master
            </h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Clustering Analysis & Fleet Logistics Optimization</p>
        </div>
        <div class="flex items-center gap-3 no-print bg-white dark:bg-slate-900 p-2 rounded-2xl shadow-soft border border-gray-100 dark:border-slate-800">
            <a href="?weeks=4" class="px-5 py-2 <?php echo $numWeeks == 4 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200/50' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800'; ?> rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">4 Weeks</a>
            <a href="?weeks=8" class="px-5 py-2 <?php echo $numWeeks == 8 ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-200/50' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-800'; ?> rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">8 Weeks</a>
            <div class="w-px h-6 bg-gray-200 dark:bg-slate-700 mx-1"></div>
            <button onclick="window.print()" class="px-5 py-2 bg-gray-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all shadow-xl">
                <i class="fas fa-print mr-2"></i> Print View
            </button>
        </div>
    </header>

    <!-- View Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800 no-print">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="leads_callout_map.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-map-marked-alt"></i> Callout Map
            </a>
            <a href="leads_booking.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-calendar-alt"></i> Scheduler
            </a>
            <a href="leads_visualize.php" class="border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-chart-pie"></i> Visualizer
            </a>
        </nav>
    </div>

    <?php
    for ($w = 0; $w < $numWeeks; $w++):
        $weekStart = date('Y-m-d', strtotime("$startDate +$w weeks"));
        $weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
        $weekLocations = [];
    ?>
    <section class="mb-16">
        <!-- Week Header -->
        <div class="bg-gray-900 text-white px-8 py-5 rounded-t-3xl flex justify-between items-center shadow-xl">
            <h2 class="font-black uppercase tracking-[0.2em] text-xs">
                Timeline: Week <?php echo $w + 1; ?>
                <span class="ml-4 text-indigo-400 font-bold italic lowercase opacity-80"><?php echo date('M jS', strtotime($weekStart)); ?> - <?php echo date('M jS', strtotime($weekEnd)); ?></span>
            </h2>
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Live Schedule</span>
            </div>
        </div>

        <div class="card-base rounded-t-none border-t-0">
            <div class="table-container">
                <table class="w-full text-left border-collapse min-w-[1200px]" style="table-layout: fixed;">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-slate-900/50 border-b border-gray-100 dark:border-slate-800">
                            <th class="px-8 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400 w-48">Day / Date</th>
                            <?php foreach($standardSlots as $slot): ?>
                                <th class="px-2 py-5 text-[10px] font-black uppercase tracking-widest text-gray-400 text-center"><?php echo $slot; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach($allowedDayNames as $dayIndex => $dayName):
                            $currentDate = date('Y-m-d', strtotime("$weekStart $dayName"));
                        ?>
                        <tr class="border-b border-gray-50 dark:border-slate-800 last:border-0">
                            <td class="px-8 py-8 bg-gray-50/30 dark:bg-slate-900/20">
                                <div class="text-sm font-black text-gray-900 dark:text-white uppercase mb-1"><?php echo $dayName; ?></div>
                                <div class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400"><?php echo date('M jS, Y', strtotime($currentDate)); ?></div>
                            </td>
                            
                            <?php
                            foreach($standardSlots as $time):
                                $slotTime = strtotime($time);
                                $isFirstHalf = ($slotTime < strtotime('12:30'));
                                
                                $isUnavailable = false;
                                $reason = '';
                                $isHalf = false;

                                if (in_array($currentDate, $holidays)) { $isUnavailable = true; $reason = 'Holiday'; }
                                elseif (isset($leavesByDate[$currentDate])) {
                                    $l = $leavesByDate[$currentDate];
                                    if ($l === 'full day') { $isUnavailable = true; $reason = 'O.O.O'; }
                                    elseif (is_array($l)) {
                                        if ((in_array('first_half', $l) && $isFirstHalf) || (in_array('second_half', $l) && !$isFirstHalf)) {
                                            $isUnavailable = true; $reason = 'Half Day'; $isHalf = true;
                                        }
                                    }
                                }

                                $bookings = array_filter($schedule[$currentDate] ?? [], function($b) use ($slotTime) {
                                    $bTime = strtotime(date('H:i', strtotime($b['next_follow_up_date'])));
                                    return $bTime >= $slotTime && $bTime < ($slotTime + 5400);
                                });
                            ?>
                                <td class="px-1.5 py-6 align-top">
                                    <?php if ($isUnavailable): ?>
                                        <div class="p-3 rounded-2xl h-24 flex items-center justify-center text-center <?php echo $isHalf ? 'slot-half-day-leave' : 'slot-holiday'; ?>">
                                            <span class="text-[9px] font-black uppercase"><?php echo $reason; ?></span>
                                        </div>
                                    <?php elseif (!empty($bookings)): ?>
                                        <div class="space-y-2">
                                            <?php foreach ($bookings as $b):
                                                if ($b['latlng']) {
                                                    [$lat, $lng] = explode(',', $b['latlng']);
                                                    $weekLocations[] = [
                                                        'lat' => $lat, 'lng' => $lng, 'title' => $b['client_name'], 
                                                        'address' => $b['address'] ?: 'No address', 'day' => $dayName,
                                                        'time' => date('H:i', strtotime($b['next_follow_up_date'])),
                                                        'category' => $b['category_name'] ?: 'General'
                                                    ];
                                                }
                                            ?>
                                                <div class="p-3 rounded-2xl shadow-sm cursor-move group transition-all hover:shadow-md <?php echo ($dayIndex % 2 !== 0) ? 'slot-booked-alt' : 'slot-booked'; ?>"
                                                     draggable="true" ondragstart="handleDragStart(event, '<?php echo $b['id']; ?>')">
                                                    <div class="text-[10px] font-black text-gray-900 dark:text-white leading-tight mb-1 truncate"><?php echo htmlspecialchars($b['client_name']); ?></div>
                                                    <div class="flex items-center gap-1 mb-2">
                                                        <span class="bg-white/50 dark:bg-black/20 text-[8px] font-black px-1.5 py-0.5 rounded text-gray-500 dark:text-gray-300 uppercase"><?php echo htmlspecialchars($b['city'] ?: 'TBD'); ?></span>
                                                        <span class="text-[8px] font-black text-gray-400 dark:text-gray-500 ml-auto"><?php echo date('H:i', strtotime($b['next_follow_up_date'])); ?></span>
                                                    </div>
                                                    <div class="text-[8px] text-gray-400 dark:text-gray-500 italic truncate" title="<?php echo htmlspecialchars($b['address']); ?>"><?php echo htmlspecialchars($b['address']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="slot-empty p-3 rounded-2xl h-24 flex flex-col items-center justify-center opacity-30 hover:opacity-100 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 border-2 border-transparent hover:border-indigo-200 transition-all cursor-pointer group"
                                             ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" ondrop="handleDrop(event, '<?php echo $currentDate; ?>', '<?php echo $time; ?>')">
                                            <i class="fas fa-plus text-gray-300 dark:text-gray-600 group-hover:text-indigo-400 text-xs mb-1"></i>
                                            <span class="text-[8px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-tighter">Available</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-8 bg-gray-50/50 dark:bg-slate-900/30 border-t border-gray-100 dark:border-slate-800">
                <div class="flex flex-col sm:flex-row items-center gap-6 mb-6">
                    <h4 class="text-[10px] font-black uppercase text-gray-400 tracking-[0.2em]"><i class="fas fa-layer-group mr-2"></i> Geographic Clustering</h4>
                    <div class="flex gap-6">
                        <?php foreach($allowedDayNames as $dayIndex => $dayName): ?>
                            <div class="flex items-center gap-2 text-[9px] font-black uppercase text-gray-500 dark:text-gray-400">
                                <div class="w-3 h-3 rounded-md <?php echo ($dayIndex % 2 === 0) ? 'bg-indigo-600' : 'bg-emerald-500'; ?>"></div> 
                                <?php echo $dayName; ?> Hub
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="map-week-<?php echo $w; ?>" class="week-map shadow-inner border border-gray-200 dark:border-slate-800"></div>
            </div>
        </div>
    </section>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof initWeeklyMap === 'function') {
                initWeeklyMap('map-week-<?php echo $w; ?>', <?php echo json_encode($weekLocations); ?>);
            }
        });
    </script>
    <?php endfor; ?>
</div>

<script>
    function handleDragStart(e, id) { e.dataTransfer.setData("lead_id", id); e.target.style.opacity = "0.4"; }
    function handleDragOver(e) { e.preventDefault(); e.target.closest('.slot-empty')?.classList.add('bg-indigo-100', 'border-indigo-300', 'dark:bg-indigo-900/40'); }
    function handleDragLeave(e) { e.target.closest('.slot-empty')?.classList.remove('bg-indigo-100', 'border-indigo-300', 'dark:bg-indigo-900/40'); }

    async function handleDrop(e, date, time) {
        e.preventDefault();
        const id = e.dataTransfer.getData("lead_id");
        if (!id) return;

        Swal.fire({ title: 'Rescheduling...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            data: { action: 'update_booking_slot', lead_id: id, date: date, time: time },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon:'success', title:'Moved!', text:res.message, timer:1500, showConfirmButton:false }).then(() => location.reload());
                } else {
                    Swal.fire('Conflict', res.message, 'warning');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update the booking slot.', 'error');
            }
        });
    }
</script>

<?php include "../footer.php"; ?>
