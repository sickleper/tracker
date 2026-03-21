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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id'])) {
    $vehicleId = $_POST['vehicle_id'];
    $data = [
        'license_plate' => $_POST['license_plate'] ?? null,
        'make_model' => $_POST['make_model'] ?? null,
        'user_id' => $_POST['user_id'] ?? null,
    ];

    try {
        $response = makeApiCall("/api/fuel/vehicles/{$vehicleId}", $data, 'PATCH');
        
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(['success' => true, 'message' => 'Vehicle updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to update vehicle']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
