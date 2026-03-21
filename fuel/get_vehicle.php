<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$vehicleId = $_GET['vehicle_id'] ?? null;

if (!$vehicleId) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $response = makeApiCall("/api/fuel/vehicles/{$vehicleId}");
    
    if ($response && ($response['success'] ?? false)) {
        // Map to match frontend expectations
        $v = $response['vehicle'];
        $v['username'] = $v['user']['name'] ?? 'Unassigned';
        echo json_encode(['success' => true, 'data' => $v]);
    } else {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Vehicle not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
