<?php
// trackers/index.php
require_once 'config.php';

// SECURITY: Redirect to login if no valid API token is available
if (!isTrackerAuthenticated()) {
    header('Location: oauth2callback.php');
    exit();
}

require_once 'tracker_data.php';

$viewMode = (isset($_GET['view']) && $_GET['view'] === 'mobile') || (isset($_COOKIE['view_mode']) && $_COOKIE['view_mode'] === 'mobile') ? 'mobile' : 'desktop';

include 'header.php';
include 'nav.php';
?>

<div class="bg-gray-50 dark:bg-slate-950 min-h-screen transition-colors duration-300">
<div class="max-w-full mx-auto px-4 md:px-8 py-8">
    
    <!-- Branding Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200 dark:shadow-none">
                <i class="fas fa-tasks text-white text-xl"></i>
            </div>
            <div>
                <h1 class="heading-brand">Work Order Tracker</h1>
                <p class="text-gray-500 dark:text-gray-400 font-bold text-[10px] uppercase tracking-[0.2em] mt-1">Real-time status monitoring & management</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="flex bg-white dark:bg-slate-900 p-1 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm">
                <button onclick="setViewMode('desktop')" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $viewMode === 'desktop' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-400 hover:text-gray-600'; ?>">
                    <i class="fas fa-desktop mr-1.5"></i> Desktop
                </button>
                <button onclick="setViewMode('mobile')" class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $viewMode === 'mobile' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-400 hover:text-gray-600'; ?>">
                    <i class="fas fa-mobile-alt mr-1.5"></i> Mobile
                </button>
            </div>
            <div class="h-8 w-px bg-gray-200 dark:bg-slate-800 hidden md:block"></div>
            <div class="flex flex-wrap gap-2 flex-grow md:flex-grow-0">
                <?php if (trackerSuperAdminEmail() !== '' && ($_SESSION['email'] ?? '') === trackerSuperAdminEmail()): ?>
                    <a href="gmail_orders.php" class="btn-secondary dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/50 py-2 px-4 shadow-none text-sm flex-1 md:flex-none" title="Gmail Work Orders"><i class="fab fa-google"></i> <span class="hidden lg:inline">Gmail</span></a>
                <?php endif; ?>
                <button onclick="toggleStats()" class="btn-primary py-2 px-4 shadow-none text-sm flex-1 md:flex-none" title="Toggle Stats"><i class="fas fa-chart-pie"></i> <span class="hidden lg:inline">Stats</span></button>
                <a href="<?php echo $_ENV['LARAVEL_API_URL']; ?>/api/xero/auth" target="_blank" rel="noopener noreferrer" class="flex-1 md:flex-none px-3 md:px-4 py-2 <?php echo $xeroConnected ? 'bg-green-500 text-white hover:bg-green-600 animate-pulse-green' : 'bg-blue-100 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 border border-transparent dark:border-blue-900/50'; ?> rounded-2xl font-black uppercase tracking-widest text-[10px] flex items-center justify-center gap-2" title="<?php echo $xeroConnected ? 'Xero Connected' : 'Connect to Xero'; ?>"><i class="fas fa-file-invoice"></i> <span class="hidden lg:inline"><?php echo $xeroConnected ? 'Xero Connected' : 'Connect to Xero'; ?></span></a>
                <button onclick="showJoinQR()" class="btn-secondary py-2 px-4 shadow-none text-sm flex-1 md:flex-none" title="Join WhatsApp Tracker"><i class="fas fa-qrcode"></i></button>
                <button onclick="openNewOrderModal()" class="btn-primary bg-blue-600 hover:bg-blue-700 py-2 px-4 shadow-none text-sm flex-1 md:flex-none"><i class="fas fa-plus"></i> <span class="hidden md:inline">New Order</span></button>
                <button onclick="openHelp()" class="btn-success py-2 px-4 shadow-none text-sm flex-1 md:flex-none" title="User Guide"><i class="fas fa-question-circle"></i> <span class="hidden lg:inline">Guide</span></button>
            </div>
        </div>
    </div>

    <!-- Quick Date Filters Row -->
    <div class="flex flex-wrap items-center justify-center gap-2 mb-4">
        <a href="?<?php 
            $todayQs = $_GET; 
            $todayQs['date_filter'] = ($dateFilter === 'today' ? '' : 'today'); 
            echo http_build_query($todayQs); 
        ?>" class="px-6 py-2.5 <?php echo ($dateFilter === 'today') ? 'bg-amber-500 text-white border-amber-600 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:bg-amber-50 hover:text-amber-600'; ?> rounded-full font-bold text-xs uppercase flex items-center gap-2 border transition-all">
            <i class="fas fa-calendar-day"></i> Added Today
        </a>
        <a href="?<?php 
            $threeDaysQs = $_GET; 
            $threeDaysQs['date_filter'] = ($dateFilter === '3days' ? '' : '3days'); 
            echo http_build_query($threeDaysQs); 
        ?>" class="px-6 py-2.5 <?php echo ($dateFilter === '3days') ? 'bg-indigo-600 text-white border-indigo-700 shadow-md' : 'bg-white text-gray-600 border-gray-200 hover:bg-indigo-50 hover:text-indigo-600'; ?> rounded-full font-bold text-xs uppercase flex items-center gap-2 border transition-all">
            <i class="fas fa-history"></i> Last 3 Days
        </a>
        <?php if ($dateFilter !== ''): ?>
            <a href="index.php<?php echo isset($_GET['view']) ? '?view='.$_GET['view'] : ''; ?>" class="text-[10px] font-bold text-red-500 uppercase hover:underline ml-2">
                <i class="fas fa-times"></i> Clear Date Filter
            </a>
        <?php endif; ?>

    </div>

    <!-- Dashboard -->
    <div id="dashboard-stats" class="hidden grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-4 mb-4 md:mb-6">
        <?php 
            renderProgressCircle($stats['completed'], $stats['total'], 'Completed', 'text-green-500', 'fas fa-check-circle', getQueryUrl(['status_filter' => ($statusFilter === 'completed' ? null : 'completed')])); 
            renderProgressCircle($stats['in_progress'], $stats['total'], 'In Progress', 'text-blue-500', 'fas fa-clock', getQueryUrl(['status_filter' => ($statusFilter === 'in_progress' ? null : 'in_progress')])); 
            renderProgressCircle($stats['urgent'], $stats['total'], 'Urgent/High', 'text-red-500', 'fas fa-exclamation-triangle', getQueryUrl(['priority_filter' => ($priorityFilter === 'urgent_high' ? null : 'urgent_high')])); 
            renderProgressCircle($stats['invoiced'], $stats['total'], 'Invoiced', 'text-yellow-500', 'fas fa-file-invoice-dollar', getQueryUrl(['invoice_filter' => ($invoiceFilter === 'yes' ? null : 'yes')])); 
        ?>
    </div>

    <!-- Client Tabs Filter -->
    <div class="card-base p-3 mb-4 flex flex-wrap items-center justify-between gap-4 text-center">
        <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full no-scrollbar" id="client-tabs-container">
            <?php 
                $qs = $_GET; 
                $qs['client_filter'] = ''; 
            ?>
            <a href="?<?php echo http_build_query($qs); ?>" data-client-id="" class="tab-link whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-bold uppercase border <?php echo empty($clientFilter) ? 'bg-indigo-600 text-white border-indigo-700 shadow-sm' : 'bg-white dark:bg-slate-900 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800'; ?>">All Clients</a>
            <?php foreach ($clientTabs as $client): 
                $qs['client_filter'] = $client['id'];
            ?>
                <a href="?<?php echo http_build_query($qs); ?>" data-client-id="<?php echo $client['id']; ?>" class="tab-link whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-bold uppercase border <?php echo ($clientFilter == $client['id']) ? 'bg-indigo-600 text-white border-indigo-700 shadow-sm' : 'bg-white dark:bg-slate-900 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-slate-800 hover:bg-gray-50 dark:hover:bg-slate-800'; ?> flex items-center gap-1">
                    <?php if ($client['notify']): ?>
                        <i class="fas fa-bell text-[8px] <?php echo ($clientFilter == $client['id']) ? 'text-indigo-200' : 'text-indigo-500 dark:text-indigo-400'; ?>"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($client['name']); ?>
                </a>
            <?php endforeach; ?>

            <?php if (!empty($assignedSubcontractorTabs)): ?>
                <div class="h-4 w-px bg-gray-200 dark:bg-slate-800 mx-1"></div>
                <?php foreach ($assignedSubcontractorTabs as $sub): 
                    $qs['client_filter'] = $sub['id'];
                ?>
                    <a href="?<?php echo http_build_query($qs); ?>" data-client-id="<?php echo $sub['id']; ?>" class="tab-link whitespace-nowrap px-3 py-1.5 rounded-full text-[10px] font-bold uppercase border <?php echo ($clientFilter == $sub['id']) ? 'bg-rose-600 text-white border-rose-700 shadow-sm' : 'bg-white dark:bg-slate-900 text-rose-500 dark:text-rose-400 border-rose-100 dark:border-rose-900/30 hover:bg-rose-50 dark:hover:bg-rose-900/10'; ?> flex items-center gap-1">
                        <i class="fas fa-hard-hat text-[8px] <?php echo ($clientFilter == $sub['id']) ? 'text-rose-200' : 'text-rose-500'; ?>"></i>
                        <?php echo htmlspecialchars($sub['name']); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="text-[10px] font-bold text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">Total: <span id="total-records-count"><?php echo $totalRecords; ?></span> records</div>
    </div>

    <div id="tracker-view-container" class="relative">
        <?php 
            if ($viewMode === 'mobile') {
                require 'partials/tracker_mobile_view.php';
            } else {
                require 'partials/tracker_desktop_view.php';
            }
        ?>
    </div>

    <?php 
        // Handle Session Messages
        $sessionMsg = $_SESSION['tracker_msg'] ?? '';
        unset($_SESSION['tracker_msg']);
    ?>

</div>

<!-- (Rest of the modals: History, Summary, Help, Client Modal, etc.) -->
<?php include 'tracker_modals.php'; ?>

<script>
$(document).ready(function() {
    // Handle AJAX tab switching
    $(document).on('click', '.tab-link', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        const clientId = $(this).data('client-id');
        const $container = $('#tracker-view-container');
        
        // Update UI immediately (visual feedback)
        $('.tab-link').removeClass('bg-indigo-600 bg-rose-600 text-white border-indigo-700 border-rose-700 shadow-sm')
                     .addClass('bg-white dark:bg-slate-900 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-slate-800');
        
        // Is it a sub tab?
        const isSub = $(this).find('.fa-hard-hat').length > 0;
        if (isSub) {
            $(this).addClass('bg-rose-600 text-white border-rose-700 shadow-sm').removeClass('text-gray-500');
        } else {
            $(this).addClass('bg-indigo-600 text-white border-indigo-700 shadow-sm').removeClass('text-gray-500');
        }

        // Add loading state
        $container.addClass('opacity-50 pointer-events-none');

        // Fetch via handler
        const ajaxUrl = 'tracker_view_handler.php' + window.location.search + (window.location.search ? '&' : '?') + 'client_filter=' + clientId;
        
        $.getJSON(ajaxUrl, function(res) {
            if (res.success) {
                $container.html(res.html);
                $('#total-records-count').text(res.total);
                
                // Re-initialize Datepickers for newly loaded content
                if (typeof flatpickr !== 'undefined') {
                    $(".datepicker").flatpickr({
                        dateFormat: "Y-m-d",
                        allowInput: true,
                        onClose: function(selectedDates, dateStr, instance) {
                            instance.element.dispatchEvent(new Event('change'));
                        }
                    });
                }

                // Update URL without reload
                window.history.pushState({}, '', url);
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Load Failed',
                    text: res.message || 'The tracker view could not be refreshed.',
                    theme: $('html').hasClass('dark') ? 'dark' : 'default'
                });
            }
        }).fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Load Failed',
                text: 'The tracker view could not be refreshed. Please try again.',
                theme: $('html').hasClass('dark') ? 'dark' : 'default'
            });
        }).always(function() {
            $container.removeClass('opacity-50 pointer-events-none');
        });
    });
});

function toggleStats() {
    $('#dashboard-stats').toggleClass('hidden');
}

function setViewMode(mode) {
    document.cookie = "view_mode=" + mode + "; path=/; max-age=" + (86400 * 30);
    location.reload(); // Switching whole view mode still best with reload to ensure partials load correctly
}
</script>

</div> <!-- End max-width -->
</div> <!-- End themed background -->

<?php include 'footer.php'; ?>
