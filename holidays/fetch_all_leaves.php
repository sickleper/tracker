<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/leave_utils.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$rawYear = $_GET['year'] ?? null;
$year = resolveYearFilter($rawYear);

try {
    $params = [];
    if ($year) {
        $params['year'] = $year;
    }

    $response = makeApiCall('/api/leaves/all', $params);
    
    if ($response && ($response['success'] ?? false)) {
        $mapped = array_map(function($l) {
            if (!is_array($l) || $isHolidayLeaveType($l)) {
                return null;
            }

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

        $mapped = array_values(array_filter($mapped, fn($row) => is_array($row)));

        echo json_encode(['status' => 'success', 'data' => $mapped]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to fetch leaves']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
