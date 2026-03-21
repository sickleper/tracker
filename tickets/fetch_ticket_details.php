<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

$ticketId = $_POST['ticketId'] ?? $_GET['ticketId'] ?? null;

if (!$ticketId) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing ticket ID']);
    exit;
}

try {
    $response = makeApiCall("/api/tickets/{$ticketId}");
    
    if ($response && ($response['success'] ?? false)) {
        // Include current user ID for frontend logic (e.g., highlighting own replies)
        $response['currentUserId'] = $_SESSION['user_id'] ?? null;
        
        // Fetch all users for assignment dropdown
        $usersResponse = makeApiCall('/api/users');
        $response['allUsers'] = ($usersResponse && ($usersResponse['success'] ?? false)) ? $usersResponse['users'] : [];
        
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'error' => $response['message'] ?? 'Failed to fetch ticket details']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
