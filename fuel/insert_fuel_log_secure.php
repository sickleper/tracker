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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $data = [
        'vehicle_id' => $_POST['vehicle_id'] ?? null,
        'user_id' => $_POST['user_id'] ?? ($_SESSION['user_id'] ?? 1),
        'date' => $_POST['date'] ?? date('Y-m-d'),
        'start_mileage' => $_POST['start_mileage'] ?? null,
        'finish_mileage' => $_POST['finish_mileage'] ?? null,
        'fuel_amount' => $_POST['fuel_amount'] ?? null,
        'image_file' => $_POST['image_file'] ?? null,
    ];

    $response = makeApiCall('/api/fuel/logs', $data, 'POST');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['success' => true, 'message' => 'Log saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to save log']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
