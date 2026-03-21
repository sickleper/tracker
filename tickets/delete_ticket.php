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

$input = json_decode(file_get_contents("php://input"), true);
$ticketId = $input['ticketId'] ?? $_POST['ticketId'] ?? $_GET['id'] ?? null;

if (!$ticketId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing ticket ID']);
    exit;
}

try {
    $response = makeApiCall("/api/tickets/{$ticketId}", [], 'DELETE');
    
    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['status' => 'success', 'message' => 'Ticket deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $response['message'] ?? 'Failed to delete ticket']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
