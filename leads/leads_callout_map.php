<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$pageTitle = "Callout Map";
include_once "../header.php";
include_once "../nav.php";
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

// Fetch categories for the modals (leads.js needs them)
$categories = [];
$catRes = makeApiCall('/api/leads/categories');
if ($catRes && ($catRes['success'] ?? false)) $categories = $catRes['data'];
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Page Header & Filters -->
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col lg:flex-row items-center justify-between gap-6 mb-8">
        <div>
            <h1 class="heading-brand">
                <i class="fas fa-map-marked-alt text-indigo-600 dark:text-indigo-400"></i>
                Callout Map
            </h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Geographic distribution of scheduled lead visits.</p>
        </div>
        
        <div class="flex flex-wrap items-center justify-center gap-3 bg-white dark:bg-slate-900 p-2 rounded-3xl shadow-soft border border-gray-100 dark:border-slate-800">
            <button onclick="loadMapData('last')" id="btn-last" class="px-5 py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-indigo-600 hover:text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all">
                Last Week
            </button>
            <button onclick="loadMapData('this')" id="btn-this" class="px-5 py-2.5 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all shadow-lg shadow-indigo-200/50">
                This Week
            </button>
            <button onclick="loadMapData('next')" id="btn-next" class="px-5 py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-indigo-600 hover:text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all">
                Next Week
            </button>
            <div class="w-px h-6 bg-gray-200 dark:bg-slate-700 mx-1"></div>
            <a href="leads_callout_printable.php" class="px-5 py-2.5 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-600 hover:text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all flex items-center">
                <i class="fas fa-print mr-2 text-xs"></i> Print List
            </a>
        </div>
    </div>

    <!-- View Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="leads_callout_map.php" class="border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
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

    <div class="card-base h-[700px] relative border-none">
        <div id="map" class="w-full h-full z-0 bg-gray-50 dark:bg-slate-950"></div>
        
        <!-- Loading Overlay -->
        <div id="mapLoading" class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 z-[1000] flex items-center justify-center backdrop-blur-sm">
            <div class="flex flex-col items-center gap-3">
                <i class="fas fa-circle-notch fa-spin text-4xl text-indigo-600 dark:text-indigo-400"></i>
                <span class="text-[10px] font-black uppercase tracking-widest text-indigo-400">Syncing Map Data...</span>
            </div>
        </div>
    </div>
</div>

<!-- Lead Detail/Edit Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-4 hidden" id="leadModal">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 p-6 flex items-center justify-between text-white">
            <h5 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3" id="leadModalLabel">
                <i class="fas fa-bullseye text-indigo-400"></i> Lead Details
            </h5>
            <button type="button" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white" onclick="closeLeadModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar" id="leadModalBody"></div>
    </div>
</div>

<!-- Summary Modal -->
<div class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] flex items-center justify-center p-0 md:p-4 hidden" id="summary-modal">
    <div class="w-full max-w-2xl h-full md:h-[95vh] flex flex-col relative bg-white md:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <button onclick="closeSummary()" class="absolute top-4 right-4 z-[100] w-10 h-10 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center transition-all shadow-lg backdrop-blur-sm">
            <i class="fas fa-times"></i>
        </button>
        <div class="flex-1 w-full h-full overflow-hidden">
            <iframe id="summary-frame" src="" class="w-full h-full border-none shadow-inner"></iframe>
        </div>
    </div>
</div>

<script>
    window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
    window.leadCategories = <?php echo json_encode($categories); ?>;
    let map = null;
    let markers = [];
    let currentPeriod = 'this';

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function escapeJsString(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
    }

    function initMap() {
        if (map !== null) return;
        if (typeof L === 'undefined') { setTimeout(initMap, 500); return; }

        try {
            map = L.map('map', { center: [53.3498, -6.2603], zoom: 10, tap: false });
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            loadMapData('this');
        } catch (e) { console.error("Leaflet initialization failed:", e); }
    }

    function loadMapData(period) {
        currentPeriod = period;
        updateButtons();
        $('#mapLoading').removeClass('hidden');

        markers.forEach(m => map.removeLayer(m));
        markers = [];

        $.post('leads_handler.php', { action: 'map_data', period: period }, function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.success && res.data && res.data.length > 0) {
                const bounds = [];
                res.data.forEach(lead => {
                    if (!lead.lat || !lead.lng) return;
                    const safeStatus = escapeHtml(lead.status || '');
                    const safeName = escapeHtml(lead.name || '');
                    const safeCategory = escapeHtml(lead.cat || '');
                    const safeAddress = escapeHtml(lead.address || '');
                    const safeRemark = escapeHtml(lead.remark || '');
                    const safeMobile = String(lead.mobile || '').replace(/[^\d+]/g, '');
                    const safeLeadId = Number(lead.id) || 0;
                    const safeEditId = escapeJsString(safeLeadId);
                    const iconHtml = `<div class="w-10 h-10 bg-indigo-600 rounded-2xl border-2 border-white shadow-xl flex items-center justify-center text-white font-black text-xs transform -rotate-45 hover:rotate-0 hover:scale-110 transition-all"><i class="fas fa-bullseye rotate-45"></i></div>`;
                    const customIcon = L.divIcon({ html: iconHtml, className: 'bg-transparent', iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40] });
                    const marker = L.marker([lead.lat, lead.lng], { icon: customIcon })
                        .bindPopup(`
                            <div class="p-2 min-w-[260px] dark:text-slate-900">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="text-[9px] font-black uppercase tracking-widest text-indigo-500">
                                        ${moment(lead.date).format('dddd, Do MMMM')} @ ${moment(lead.date).format('HH:mm')}
                                    </div>
                                    <span class="px-1.5 py-0.5 bg-gray-100 text-[8px] font-black rounded uppercase">${safeStatus}</span>
                                </div>
                                
                                <h3 class="font-black text-gray-900 text-base mb-1 uppercase italic tracking-tighter leading-tight">${safeName}</h3>
                                
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-600 text-[9px] font-black rounded-lg border border-indigo-100 uppercase tracking-widest">${safeCategory}</span>
                                    <span class="text-[10px] font-bold text-gray-400">#${safeLeadId}</span>
                                </div>

                                <div class="space-y-2 mb-4">
                                    <p class="text-xs text-gray-600 leading-relaxed flex items-start gap-2">
                                        <i class="fas fa-map-marker-alt mt-0.5 text-indigo-400 flex-shrink-0"></i>
                                        <span>${safeAddress}</span>
                                    </p>
                                    ${lead.remark ? `
                                        <div class="bg-gray-50 p-2 rounded-xl border border-gray-100 italic text-[11px] text-gray-500 leading-snug">
                                            ${safeRemark.length > 100 ? safeRemark.substring(0, 100) + '...' : safeRemark}
                                        </div>
                                    ` : ''}
                                </div>

                                <div class="flex gap-2 border-t border-gray-100 pt-3">
                                    <a href="tel:${safeMobile}" class="flex-1 text-center py-2.5 bg-emerald-600 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-md flex items-center justify-center gap-2">
                                        <i class="fas fa-phone-alt"></i> Call
                                    </a>
                                    <button onclick="openEditLeadModal('${safeEditId}')" class="flex-1 text-center py-2.5 bg-gray-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-md flex items-center justify-center gap-2">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                            </div>
                        `);
                    marker.addTo(map);
                    markers.push(marker);
                    bounds.push([lead.lat, lead.lng]);
                });
                if (bounds.length > 0) map.fitBounds(bounds, { padding: [50, 50] });
                setTimeout(() => map.invalidateSize(), 500);
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'No scheduled visits for this period.', showConfirmButton: false, timer: 3000, theme: getSwalTheme() });
            }
            $('#mapLoading').addClass('hidden');
        }).fail(function() {
            $('#mapLoading').addClass('hidden');
            Swal.fire({ icon: 'error', title: 'Map Load Failed', text: 'Unable to load callout map data.', theme: getSwalTheme() });
        });
    }

    function updateButtons() {
        ['last', 'this', 'next'].forEach(p => {
            const btn = document.getElementById('btn-' + p);
            if (!btn) return;
            if (p === currentPeriod) {
                btn.className = "px-5 py-2.5 bg-indigo-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all shadow-lg shadow-indigo-200/50";
            } else {
                btn.className = "px-5 py-2.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-indigo-600 hover:text-white rounded-2xl font-black text-[10px] uppercase tracking-wider transition-all";
            }
        });
    }

    $(document).ready(initMap);
</script>

<script src="leads.js?v=<?php echo time(); ?>"></script>

<?php include_once "../footer.php"; ?>
