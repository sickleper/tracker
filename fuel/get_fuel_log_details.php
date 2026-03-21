<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$logId = isset($_GET['log_id']) ? (int)$_GET['log_id'] : null;

if (!$logId) {
    echo json_encode(['error' => 'Log ID not provided or is invalid.']);
    exit;
}

try {
    $response = makeApiCall("/api/fuel/logs/{$logId}");
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode($response['log']);
    } else {
        echo json_encode(['error' => $response['message'] ?? 'Log not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
