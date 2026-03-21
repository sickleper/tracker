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

$data = json_decode(file_get_contents("php://input"), true);
$vehicleID = isset($data['vehicle_id']) ? intval($data['vehicle_id']) : 0;

if ($vehicleID > 0) {
    try {
        $response = makeApiCall('/api/inventory/stocktake', ['vehicle_id' => $vehicleID], 'POST');
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID.']);
}
