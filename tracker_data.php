<?php
require_once __DIR__ . '/config.php'; // For $_ENV['LARAVEL_API_URL'] and Global Session Configuration
require_once __DIR__ . '/api_helper.php';

// Function to make API calls is now in api_helper.php

    // --- GLOBAL SETTINGS OVERRIDE ---
    // This ensures that database settings take precedence over .env
    if (!isset($skipSettingsOverride)) {
        loadGlobalSettings();
    }

// Pagination & Filter settings
$search = isset($_GET['search']) ? $_GET['search'] : '';
$propFilter = isset($_GET['property_filter']) ? $_GET['property_filter'] : '';
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$priorityFilter = isset($_GET['priority_filter']) ? $_GET['priority_filter'] : '';
$invoiceFilter = isset($_GET['invoice_filter']) ? $_GET['invoice_filter'] : '';
$clientFilter = isset($_GET['client_filter']) ? (int)$_GET['client_filter'] : 0;
$showClosed = isset($_GET['show_closed']) ? (int)$_GET['show_closed'] : 0; // 0 = hide, 1 = show all
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : ''; // '' or 'today' or '3days' // Changed from $date_filter to $date
$todayOnly = ($dateFilter === 'today') ? 1 : 0; // Backward compatibility if needed

$clientSheetId = null;
if ($clientFilter > 0) {
    $response = makeApiCall("/api/clients/{$clientFilter}/spreadsheet-id");
    if ($response && ($response['success'] ?? false)) {
        $clientSheetId = $response['spreadsheet_id'];
    }
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 300;
$offset = ($page - 1) * $limit;

// Sorting settings
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Note: Sorting is now handled by the Laravel API endpoint for tasks,
// these variables are primarily for constructing the API query params.
$allowedSortColumns = [
    'id', 'poNumber', 'task', 'property', 'clientName',
    'contact', 'propertyCode', 'assignedTo', 'priority',
    'is_weather_dependent', 'status', 'openingDate',
    'dateBooked', 'nextVisit', 'certSent', 'inv_sent'
];

$orderBy = in_array($sort, $allowedSortColumns) ? $sort : 'id';
$orderDir = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';

// Stats
$stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'urgent' => 0, 'invoiced' => 0];
$statsQueryParams = [
    'search' => $search,
    'property_filter' => $propFilter,
    'status_filter' => $statusFilter,
    'priority_filter' => $priorityFilter,
    'invoice_filter' => $invoiceFilter,
    'client_filter' => $clientFilter,
    'show_closed' => $showClosed,
    'date_filter' => $dateFilter,
];

$stats_api_response_data = makeApiCall('/api/tasks/stats', $statsQueryParams);
if ($stats_api_response_data) {
    $stats = $stats_api_response_data;
}


// Total records for pagination SHOULD respect active filters (now handled by Laravel API)
$totalRecords = 0;
// No longer directly queried here, obtained from /api/tasks response
$totalPages = 1; // Placeholder, will be updated from API response

// Fetch Work Orders from Laravel API
$workOrders = [];
$queryParams = [
    'search' => $search,
    'property_filter' => $propFilter,
    'status_filter' => $statusFilter,
    'priority_filter' => $priorityFilter,
    'invoice_filter' => $invoiceFilter,
    'client_filter' => $clientFilter,
    'show_closed' => $showClosed,
    'date_filter' => $dateFilter,
    'sort' => $sort,
    'order' => $order,
    'page' => $page,
    'limit' => $limit,
];

$tasks_api_response_data = makeApiCall('/api/tasks', $queryParams);
if ($tasks_api_response_data) {
    $workOrders = $tasks_api_response_data['data'] ?? [];
    $totalRecords = $tasks_api_response_data['total'] ?? 0;
    $totalPages = $tasks_api_response_data['last_page'] ?? 1;
}

// Fetch Unique Clients for Tabs
$clientTabs = [];
$clientTabsResponse = makeApiCall('/api/clients/tabs');
if ($clientTabsResponse && ($clientTabsResponse['success'] ?? false)) {
    $allClients = $clientTabsResponse['clients'];
    
    // Filter clients based on tracker_client_names (DB priority)
    $trackerClientNamesString = $GLOBALS['tracker_client_names'] ?? $_ENV['TRACKER_CLIENT_NAMES'] ?? '';
    if (!empty($trackerClientNamesString)) {
        $allowedNames = array_map('trim', explode(',', str_replace('"', '', $trackerClientNamesString)));
        $clientTabs = array_filter($allClients, function($client) use ($allowedNames) {
            return in_array((string)$client['name'], $allowedNames);
        });
    } else {
        $clientTabs = $allClients;
    }
}

$users = [];
$usersResponse = makeApiCall('/api/users');
if ($usersResponse && ($usersResponse['success'] ?? false)) {
    $users = $usersResponse['users'];
}

$subcontractors = [];
$subcontractorsResponse = makeApiCall('/api/subcontractors');
if ($subcontractorsResponse && ($subcontractorsResponse['success'] ?? false)) {
    $subcontractors = $subcontractorsResponse['subcontractors'];
}

// Identify subcontractors that currently have assigned tasks to show as filter tabs
$assignedSubcontractorTabs = [];
if (!empty($users)) {
    // To ensure tabs don't disappear when one is selected, we need to know who has tasks GLOBALLY
    // We can use the /api/subcontractors endpoint which we already called
    foreach ($users as $user) {
        if (!empty($user['is_subcontractor'])) {
            $assignedSubcontractorTabs[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'is_sub' => true
            ];
        }
    }
}

// Check if viewing a subcontractor tab
$isSubcontractorView = in_array((string)$clientFilter, array_column($subcontractors, 'id')) || in_array((int)$clientFilter, array_column($assignedSubcontractorTabs, 'id'));

$priorityOptions = ['Low', 'Medium', 'High', 'Urgent', 'Emergency'];
$statusOptions = ['Open', 'Pending', 'In Progress', 'On Hold', 'Completed', 'Closed', 'Cancelled'];
$yesNoOptions = ['Yes', 'No', 'Not Required', 'Drafted', 'Paid'];

function getPriorityColor($priority) {
    switch(strtolower($priority)) {
        case 'emergency': return 'bg-red-900 text-white border-red-950 animate-pulse font-bold';
        case 'urgent': return 'bg-red-600 text-white border-red-700';
        case 'high': return 'bg-orange-500 text-white border-orange-600';
        case 'medium': return 'bg-teal-600 text-white border-teal-700';
        case 'low': return 'bg-green-600 text-white border-green-700';
        default: return 'bg-gray-600 text-white border-gray-700';
    }
}

function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'cancelled': return 'bg-[#4e342e] text-white border-[#3e2723]';
        case 'closed': return 'bg-gray-600 text-white border-gray-700';
        case 'completed': return 'bg-green-600 text-white border-green-700';
        case 'pending': return 'bg-[#ff0000] text-white font-bold border-red-700';
        case 'in progress': return 'bg-blue-600 text-white border-blue-700';
        case 'on hold': return 'bg-orange-600 text-white border-orange-700';
        case 'open': case 'incomplete': return 'bg-purple-600 text-white border-purple-700';
        default: return 'bg-gray-600 text-white border-gray-700';
    }
}

function getYesNoColor($val) {
    $v = strtolower($val);
    if ($v === 'paid') return 'bg-blue-600 text-white border-blue-700';
    if ($v === 'yes') return 'bg-green-600 text-white border-green-700';
    if ($v === 'drafted') return 'bg-orange-400 text-white border-orange-500';
    if ($v === 'not required') return 'bg-gray-400 text-white border-gray-500';
    return 'bg-gray-100 text-gray-400 border-gray-200';
}

function sortLink($column, $currentSort, $currentOrder) {
    $p = $_GET; 
    unset($p['msg']); // Remove message parameter to avoid security triggers and URL clutter
    $p['sort'] = $column;
    $p['order'] = ($currentSort === $column && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    return '?' . http_build_query($p);
}

function sortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort !== $column) return '<i class="fas fa-sort text-gray-300 ml-1"></i>';
    return ($currentOrder === 'ASC') ? '<i class="fas fa-sort-up ml-1"></i>' : '<i class="fas fa-sort-down ml-1"></i>';
}

function getQueryUrl($params = []) {
    $p = $_GET;
    foreach($params as $k => $v) {
        if ($v === null) unset($p[$k]);
        else $p[$k] = $v;
    }
    return '?' . http_build_query($p);
}

function renderProgressCircle($current, $total, $label, $colorClass, $icon, $filterUrl = '#') {
    $percentage = ($total > 0) ? round(($current / $total) * 100) : 0;
    $circumference = 2 * pi() * 35;
    $offset = $circumference - ($percentage / 100) * $circumference;
    
    // Detect if this circle's filter is active
    $isActive = false;
    $parsedUrl = parse_url($filterUrl);
    $query = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $query);
    }
    
    if (isset($query['status_filter']) && isset($_GET['status_filter']) && $_GET['status_filter'] === $query['status_filter']) {
        $isActive = true;
    } elseif (isset($query['priority_filter']) && isset($_GET['priority_filter']) && $_GET['priority_filter'] === $query['priority_filter']) {
        $isActive = true;
    } elseif (isset($query['invoice_filter']) && isset($_GET['invoice_filter']) && $_GET['invoice_filter'] === $query['invoice_filter']) {
        $isActive = true;
    }
    ?>
    <a href="<?php echo htmlspecialchars($filterUrl); ?>" class="flex flex-col items-center card-base p-4 rounded-xl border <?php echo $isActive ? 'border-indigo-500 ring-2 ring-indigo-100 dark:ring-indigo-900/30' : 'border-gray-100 dark:border-slate-800'; ?> transition-transform hover:scale-105 cursor-pointer block no-underline hover:no-underline relative">
        <?php if ($isActive): ?>
            <div class="absolute -top-2 -right-1 bg-indigo-600 text-white text-[8px] font-bold px-2 py-0.5 rounded-full shadow-sm uppercase z-10">Active</div>
        <?php endif; ?>
        <div class="relative w-20 h-20 flex items-center justify-center">
            <svg class="w-full h-full transform -rotate-90">
                <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="6" fill="transparent" class="text-gray-100 dark:text-slate-800" />
                <circle cx="40" cy="40" r="35" stroke="currentColor" stroke-width="6" fill="transparent" 
                    stroke-dasharray="<?php echo $circumference; ?>" stroke-dashoffset="<?php echo $offset; ?>" 
                    class="<?php echo $colorClass; ?> transition-all duration-1000 ease-out" />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-base font-bold text-gray-800 dark:text-white"><?php echo $current; ?></span>
                <span class="text-[7px] font-bold text-gray-400 dark:text-gray-500 uppercase"><?php echo $percentage; ?>%</span>
            </div>
        </div>
        <div class="mt-2 text-center text-[9px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest flex items-center gap-1">
            <i class="<?php echo $icon; ?> <?php echo $colorClass; ?>"></i> <?php echo $label; ?>
        </div>
    </a>
    <?php
}
?>
