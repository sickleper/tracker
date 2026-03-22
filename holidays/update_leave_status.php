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

$id = $_POST['leave_id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {
    $response = makeApiCall("/api/leaves/{$id}", [
        'status' => $status,
        'reject_reason' => $_POST['reject_reason'] ?? null,
        'approve_reason' => $_POST['approve_reason'] ?? null,
    ], 'PATCH');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'message' => $response['message']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to update leave status']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
