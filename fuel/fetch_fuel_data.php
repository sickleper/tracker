<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

try {
    if ($action === 'stats') {
        $response = makeApiCall('/api/fuel/stats', ['vehicle_id' => $vehicleId]);
    } elseif ($action === 'mpl') {
        $response = makeApiCall('/api/fuel/overall-mpl');
    } else {
        $response = makeApiCall('/api/fuel/report', [
            'month' => $month,
            'vehicle_id' => $vehicleId
        ]);
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
