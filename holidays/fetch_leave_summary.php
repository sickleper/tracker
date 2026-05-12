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
$year = resolveYearFilter($rawYear) ?? date('Y');
$requestedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = isTrackerAdminUser();

try {
    $params = ['year' => $year];
    if ($requestedUserId) {
        if (!$isAdmin && $requestedUserId !== $sessionUserId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
        $params['user_id'] = $requestedUserId;
    } elseif (!$isAdmin && $sessionUserId > 0) {
        $params['user_id'] = $sessionUserId;
    }

    $response = makeApiCall('/api/leaves/summary', $params);
    
    if ($response && ($response['success'] ?? false)) {
        if (isset($response['data']) && is_array($response['data'])) {
            $response['data'] = array_values(array_filter($response['data'], function ($row) {
                return is_array($row) ? !$isHolidayLeaveType($row) : false;
            }));
        }
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to fetch summary']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
