<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Authentication required"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestData = json_decode(file_get_contents("php://input"), true);
    $ticketId = $requestData['ticketId'] ?? null;
    
    if (!$ticketId) {
        echo json_encode(["success" => false, "error" => "Missing ticket ID"]);
        exit;
    }

    // Pass all received data except ticketId to the API
    $payload = $requestData;
    unset($payload['ticketId']);

    try {
        $response = makeApiCall("/api/tickets/{$ticketId}", $payload, 'PATCH');
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $response['message'] ?? "Failed to update ticket via API"]);
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "error" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid request method"]);
}
