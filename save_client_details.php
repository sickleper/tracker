<?php
require_once __DIR__ . '/tracker_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// SECURITY: Authentication check
if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if (!$clientId) {
        echo json_encode(['success' => false, 'message' => 'Invalid Client ID']);
        exit();
    }

    // Capture all fields from POST
    $data = $_POST;
    unset($data['id']); // Remove ID from the data body

    // Call Laravel API to update details
    // The endpoint /api/clients/{id}/update-details was added to handle comprehensive updates
    $response = makeApiCall("/api/clients/{$clientId}/update-details", $data, 'POST');

    if ($response && ($response['success'] ?? false)) {
        echo json_encode(['success' => true, 'message' => 'Client details updated successfully']);
    } else {
        $msg = $response['message'] ?? 'Failed to update client details via API';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
