<?php
require_once __DIR__ . '/../tracker_data.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['is_enabled'])) {
    $id = (int) $_POST['id'];
    $isEnabled = (int) $_POST['is_enabled'];

    $response = makeApiCall("/api/leave-types/{$id}/toggle-status", ['is_enabled' => $isEnabled], 'PATCH');

    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['success' => true, 'message' => 'Leave type status updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to update leave type status.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
