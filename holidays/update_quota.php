<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if (!isTrackerAdminUser()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
    exit;
}

try {
    $data = [
        'user_id' => $_POST['user_id'] ?? 0,
        'leave_type_id' => $_POST['leave_type_id'] ?? 0,
        'no_of_leaves' => $_POST['no_of_leaves'] ?? 0,
    ];

    $response = makeApiCall('/api/leaves/quota', $data, 'POST');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'message' => $response['message']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to update quota']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
