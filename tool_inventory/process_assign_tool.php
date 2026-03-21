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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = [
        'VehicleID' => $_POST['vehicle_id'] ?? null,
        'ToolID' => $_POST['tool_id'] ?? null,
        'Quantity' => $_POST['quantity'] ?? 1,
        'Condition' => $_POST['condition'] ?? 'Good',
        'Price' => $_POST['price'] ?? 0,
        'Remarks' => $_POST['remarks'] ?? '',
    ];

    try {
        $response = makeApiCall('/api/inventory/assign', $data, 'POST');
        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
