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

$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$requestedUserId = $_GET['user_id'] ?? null;
$isAdmin = isTrackerAdminUser();
$rawYear = $_GET['year'] ?? null;
$year = resolveYearFilter($rawYear);
$resolvedUserId = null;

if ($requestedUserId === null) {
    $resolvedUserId = $sessionUserId;
} elseif (is_string($requestedUserId) && strtolower(trim($requestedUserId)) === 'all') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    $resolvedUserId = 'all';
} else {
    $requestedUserId = (int) $requestedUserId;
    if (!$isAdmin && $requestedUserId !== $sessionUserId) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
    $resolvedUserId = $requestedUserId;
}

if (!$resolvedUserId) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    $params = [];
    if ($year) {
        $params['year'] = $year;
    }
    if ($resolvedUserId !== 'all') {
        $params['user_id'] = (int) $resolvedUserId;
    }

    $response = makeApiCall('/api/leaves', $params);
    
    if ($response && ($response['success'] ?? false)) {
        // error_log("LEAVES_DEBUG: " . json_encode($response['data']));
        // Map to match frontend expectations for grouped dates
        $mapped = array_map(function($l) {
            if (!is_array($l) || $isHolidayLeaveType($l)) {
                return null;
            }

            $typeName = $l['leave_type']['type_name'] ?? ($l['type_name'] ?? 'Unknown');
            return [
                'id' => $l['id'],
                'user_id' => $l['user_id'],
                'user_name' => $l['user']['name'] ?? ($l['user_name'] ?? 'Unknown'),
                'start_date' => $l['start_date'],
                'end_date' => $l['end_date'],
                'days_count' => $l['days_count'],
                'type_name' => $typeName,
                'duration' => $l['duration'],
                'status' => $l['status'],
                'reason' => $l['reason'],
                'unique_id' => $l['unique_id']
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
