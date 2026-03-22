<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$requestedUserId = (int) ($_POST['user_id'] ?? $sessionUserId);

if ($sessionUserId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User session not initialized']);
    exit;
}

try {
    $leaveDate = trim((string) ($_POST['leave_date'] ?? ''));
    $duration = trim((string) ($_POST['duration'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? ''));

    if ($leaveDate === '' || $reason === '') {
        echo json_encode(['status' => 'error', 'message' => 'Leave date and reason are required']);
        exit;
    }

    if ($duration === 'half day' && strpos($leaveDate, ' to ') !== false) {
        echo json_encode(['status' => 'error', 'message' => 'Half-day requests must be for a single date']);
        exit;
    }

    $data = [
        'user_id' => $requestedUserId,
        'leave_type_id' => $_POST['leave_type_id'] ?? null,
        'leave_date' => $leaveDate,
        'duration' => $duration,
        'half_day_type' => $_POST['half_day_type'] ?? null,
        'reason' => $reason,
    ];

    $response = makeApiCall('/api/leaves', $data, 'POST');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'message' => $response['message']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to book leave']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
