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

$id = $_POST['leave_id'] ?? $_GET['id'] ?? null;
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = isTrackerAdminUser();
$leaveId = (int) $id;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing Leave ID']);
    exit;
}

if (!$isAdmin && $sessionUserId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!$isAdmin && $sessionUserId > 0) {
        $leaveRes = makeApiCall("/api/leaves/{$leaveId}", [], 'GET');
        if (!($leaveRes && ($leaveRes['success'] ?? false))) {
            echo json_encode(['status' => 'error', 'message' => $leaveRes['message'] ?? 'Failed to validate leave']);
            exit;
        }

        $leaveData = $leaveRes['data'] ?? $leaveRes;
        $ownerId = (int) ($leaveData['user_id'] ?? 0);
        if ($ownerId <= 0 || $ownerId !== $sessionUserId) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }

        $status = strtolower((string)($leaveData['status'] ?? ''));
        if ($status !== 'pending') {
            echo json_encode(['status' => 'error', 'message' => 'Only pending requests can be deleted']);
            exit;
        }
    }

    $response = makeApiCall("/api/leaves/{$id}", [], 'DELETE');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'message' => $response['message']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to delete leave']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
