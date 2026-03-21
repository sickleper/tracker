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
$checkDate = isset($_GET['check_date']) ? $_GET['check_date'] : '';

if ($vehicleID > 0 && $checkDate) {
    try {
        $response = makeApiCall("/api/inventory/history-details", [
            'vehicle_id' => $vehicleID,
            'check_date' => $checkDate
        ]);
        
        if ($response && ($response['success'] ?? false)) {
            // Map to match frontend expectations
            $mapped = array_map(function($item) {
                return [
                    'Quantity' => $item['Quantity'],
                    'ToolID' => $item['ToolID'],
                    'ToolName' => $item['tool']['ToolName'] ?? 'Unknown',
                    'ToolTypeID' => $item['tool']['ToolTypeID'] ?? '',
                    'SerialNumber' => $item['tool']['SerialNumber'] ?? '',
                    'PurchaseDate' => $item['tool']['PurchaseDate'] ?? '',
                    'Value' => $item['tool']['Value'] ?? 0,
                    'ImageURL' => $item['tool']['ImageURL'] ?? ''
                ];
            }, $response['data']);
            echo json_encode($mapped);
        } else {
            echo json_encode([]);
        }
    } catch (Exception $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
