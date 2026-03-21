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
    $response = makeApiCall('/api/fuel/defects');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to fetch defects']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
