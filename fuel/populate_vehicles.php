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

try {
    $response = makeApiCall('/api/fuel/vehicles');
    
    if ($response && ($response['success'] ?? false)) {
        // Map to match frontend expectations
        $vehicles = array_map(function($v) {
            return [
                'vehicle_id' => $v['vehicle_id'],
                'license_plate' => $v['license_plate'],
                'make_model' => $v['make_model'],
                'user_id' => $v['user_id']
            ];
        }, $response['vehicles']);

        echo json_encode($vehicles);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    echo json_encode([]);
}
