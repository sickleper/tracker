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
    $response = makeApiCall("/api/ticket-groups");
    if ($response && ($response['success'] ?? false)) {
        $groups = [];
        foreach ($response['data'] as $group) {
            $groups[$group['id']] = $group['group_name'];
        }
        echo json_encode(['success' => true, 'groups' => $groups]);
    } else {
        error_log("API Error in get_ticket_groups.php: " . json_encode($response));
        echo json_encode(['success' => false, 'error' => $response['message'] ?? 'Failed to fetch groups']);
    }
} catch (Exception $e) {
    error_log("Exception in get_ticket_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
