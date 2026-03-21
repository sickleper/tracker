<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = [
        'name' => $_POST['name'] ?? null,
        'email' => $_POST['email'] ?? null,
        'mobile' => $_POST['mobile'] ?? null,
        'is_driver' => true,
        'status' => 'active'
    ];

    if (!empty($data['name']) && !empty($data['email'])) {
        try {
            $response = makeApiCall('/api/users/create', $data, 'POST');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Driver added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to add driver']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
