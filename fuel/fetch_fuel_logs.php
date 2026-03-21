<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;

try {
    $response = makeApiCall('/api/fuel/logs', ['vehicle_id' => $vehicleId]);
    
    if ($response && ($response['success'] ?? false)) {
        // Map to match frontend expectations
        $logs = array_map(function($l) {
            return [
                'log_id' => $l['log_id'],
                'vehicle_id' => $l['vehicle_id'],
                'date' => $l['date'],
                'start_mileage' => $l['start_mileage'],
                'finish_mileage' => $l['finish_mileage'],
                'fuel_amount' => $l['fuel_amount'],
                'user_id' => $l['user_id'],
                'image_file' => $l['image_file'],
                'make_model' => $l['vehicle']['make_model'] ?? 'Unknown',
                'license_plate' => $l['vehicle']['license_plate'] ?? 'Unknown',
                'driver_name' => $l['user']['name'] ?? 'Unknown',
                'driver_email' => $l['user']['email'] ?? ''
            ];
        }, $response['logs']);

        echo json_encode(["logs" => $logs, "logCounts" => $response['logCounts']], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(["logs" => [], "logCounts" => [], "error" => $response['message'] ?? "API Error"]);
    }
} catch (Exception $e) {
    echo json_encode(["logs" => [], "logCounts" => [], "error" => $e->getMessage()]);
}
