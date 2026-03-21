<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

try {
    // We can filter unassigned tools in the API or handle it here
    $response = makeApiCall('/api/tools');
    
    if ($response && ($response['success'] ?? false)) {
        // For now, mirroring the legacy behavior of returning all tools or filtering
        // Legacy script filter: "LEFT JOIN VanInventory vi ON t.ToolID = vi.ToolID WHERE vi.ToolID IS NULL"
        // Since we want to simplify, we'll return all tools for selection
        echo json_encode($response['data']);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    echo json_encode([]);
}
