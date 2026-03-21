<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

try {
    $response = makeApiCall("/api/ticket-types");
    if ($response && ($response['success'] ?? false)) {
        $types = [];
        foreach ($response['data'] as $type) {
            $types[$type['id']] = $type['type'];
        }
        echo json_encode(['success' => true, 'types' => $types]);
    } else {
        echo json_encode(['success' => false, 'error' => $response['message'] ?? 'Failed to fetch types']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
