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

$vehicleID = $_GET['vehicle_id'] ?? null;

try {
    $response = makeApiCall('/api/inventory/van/values'); // Contains last_inventory_date
    
    if ($response && ($response['success'] ?? false)) {
        $lastCheckTime = 'N/A';
        if ($vehicleID) {
            foreach ($response['vans'] as $van) {
                if ($van['vehicle_id'] == $vehicleID) {
                    $lastCheckTime = $van['last_inventory_date'] ?? 'N/A';
                    break;
                }
            }
        }
        echo json_encode(['success' => true, 'lastCheckTime' => $lastCheckTime]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
