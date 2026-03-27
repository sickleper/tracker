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
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $toolId = (int) ($_POST['tool_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $price = is_numeric($_POST['price'] ?? null) ? (float) $_POST['price'] : null;

    if ($vehicleId <= 0 || $toolId <= 0 || $quantity <= 0 || $price === null || $price < 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Choose a van and tool, use a positive quantity, and enter a valid non-negative value.']);
        exit;
    }

    $data = [
        'VehicleID' => $vehicleId,
        'ToolID' => $toolId,
        'Quantity' => $quantity,
        'Condition' => $_POST['condition'] ?? 'Good',
        'Price' => $price,
        'Remarks' => $_POST['remarks'] ?? '',
    ];

    try {
        $response = makeApiCall('/api/inventory/assign', $data, 'POST');
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
