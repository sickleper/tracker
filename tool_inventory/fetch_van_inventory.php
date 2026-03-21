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

$vehicleID = isset($_GET['vehicle_id']) ? intval($_GET['vehicle_id']) : 0;

if ($vehicleID <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $response = makeApiCall("/api/inventory/van/{$vehicleID}");
    
    if ($response && ($response['success'] ?? false)) {
        // Map to match frontend expectations
        $mapped = array_map(function($item) {
            return [
                'InventoryID' => $item['InventoryID'],
                'ToolName' => $item['tool']['ToolName'] ?? 'Unknown',
                'ToolTypeName' => $item['tool']['type']['ToolTypeName'] ?? 'Unknown',
                'Quantity' => $item['Quantity'],
                'Condition' => $item['Condition'],
                'Price' => $item['Price'],
                'Remarks' => $item['Remarks']
            ];
        }, $response['data']);

        echo json_encode($mapped);
    } else {
        echo json_encode([]);
    }
} catch (Exception $e) {
    echo json_encode([]);
}
