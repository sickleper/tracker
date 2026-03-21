<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// Handle both JSON and traditional POST
$input = json_decode(file_get_contents("php://input"), true);
$ticketId = $input['ticketId'] ?? $_POST['ticketId'] ?? null;
$message = $input['message'] ?? $_POST['message'] ?? '';

if (!$ticketId || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

try {
    $response = makeApiCall("/api/tickets/{$ticketId}/reply", ['message' => $message], 'POST');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'reply' => $response['reply']]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to add reply']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
