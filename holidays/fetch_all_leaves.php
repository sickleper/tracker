<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$year = $_GET['year'] ?? date('Y');

try {
    $response = makeApiCall('/api/leaves/all', ['year' => $year]);
    
    if ($response && ($response['success'] ?? false)) {
        $mapped = array_map(function($l) {
            return [
                'id' => $l['id'],
                'user_id' => $l['user_id'],
                'user_name' => $l['user']['name'] ?? 'Unknown',
                'leave_date' => $l['leave_date'],
                'type_name' => $l['leave_type']['type_name'] ?? 'Unknown',
                'duration' => $l['duration'],
                'status' => $l['status'],
                'reason' => $l['reason']
            ];
        }, $response['data']);

        echo json_encode(['status' => 'success', 'data' => $mapped]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to fetch leaves']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
