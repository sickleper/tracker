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
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;

    $title = $input['title'] ?? null;
    $message = $input['message'] ?? null;
    $priority = $input['priority'] ?? 'low';
    $categoryId = $input['category_id'] ?? null;
    $labelId = $input['label_id'] ?? null;

    if ($title && $message) {
        try {
            $response = makeApiCall("/api/tickets", [
                'title' => $title,
                'message' => $message,
                'priority' => $priority,
                'category_id' => $categoryId,
                'label_id' => $labelId
            ], 'POST');

            if ($response && ($response['success'] ?? false)) {
                echo json_encode(["success" => true, "message" => "Ticket created successfully"]);
            } else {
                echo json_encode(["success" => false, "error" => $response['message'] ?? "Failed to create ticket via API"]);
            }
        } catch (Exception $e) {
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Subject and Message are required"]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid request method"]);
}
