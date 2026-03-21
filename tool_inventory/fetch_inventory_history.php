<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$vehicleID = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

if ($vehicleID > 0) {
    try {
        $response = makeApiCall("/api/inventory/history/{$vehicleID}");
        if ($response && ($response['success'] ?? false)) {
            echo json_encode($response['data']);
        } else {
            echo json_encode([]);
        }
    } catch (Exception $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
