<?php
// tracker_view_handler.php
require_once 'tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

// Ensure we have the view mode
$viewMode = $_GET['view'] ?? ( (isset($_COOKIE['view_mode']) && $_COOKIE['view_mode'] === 'mobile') ? 'mobile' : 'desktop' );

ob_start();
if ($viewMode === 'mobile') {
    require 'partials/tracker_mobile_view.php';
} else {
    require 'partials/tracker_desktop_view.php';
}
$html = ob_get_clean();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'html' => $html,
    'total' => $totalRecords,
    'client_filter' => $clientFilter
]);
