<?php
require_once __DIR__ . '/config.php';

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

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$clientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid Client ID']);
    exit();
}

try {
    $apiResponse = makeApiCall("/api/clients/{$clientId}/details");

    if ($apiResponse && ($apiResponse['success'] ?? false) && isset($apiResponse['client'])) {
        $client = $apiResponse['client'];
        echo json_encode(['success' => true, 'client' => $client]);
    } else {
        $message = $apiResponse['message'] ?? 'Client not found or API error.';
        echo json_encode(['success' => false, 'message' => $message]);
    }
} catch (Exception $e) {
    error_log("Error fetching client details via API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
