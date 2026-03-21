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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'vehicle_id' => $_POST['vehicle_id'] ?? null,
        'defect_details' => $_POST['defect_details'] ?? null,
        'rectified_on' => $_POST['rectified_on'] ?? null,
        'notes' => $_POST['notes'] ?? null,
    ];

    try {
        $response = makeApiCall('/api/fuel/defects', $data, 'POST');
        
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(['success' => true, 'message' => 'Defect saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to save defect']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
