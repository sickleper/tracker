<?php
require_once __DIR__ . '/../tracker_data.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$response = makeApiCall('/api/leave-types/all');

if ($response && ($response['success'] ?? false)) {
    echo json_encode(['success' => true, 'data' => $response['data']]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch leave types.']);
}
