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
    $response = makeApiCall('/api/users', ['team_only' => 1]);

    if (!($response['success'] ?? false)) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $response['message'] ?? 'Failed to load driver registry'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'users' => $response['users'] ?? []
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
