<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/upload_helpers.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$isAdmin = isTrackerAdminUser();
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
if (!$isAdmin && $sessionUserId <= 0) {
    $sessionUserId = fuelCurrentUserId();
}

if (!$isAdmin && $sessionUserId <= 0) {
    http_response_code(401);
    echo json_encode(["logs" => [], "logCounts" => [], "error" => "Unable to resolve user identity."]);
    exit;
}

$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$query = ['vehicle_id' => $vehicleId];
if (!$isAdmin && $sessionUserId > 0) {
    $query['user_id'] = $sessionUserId;
}

try {
    $response = makeApiCall('/api/fuel/logs', $query);
    
    if ($response && ($response['success'] ?? false)) {
        $rawLogs = array_values(array_filter($response['logs'] ?? [], 'is_array'));
        $rawLogs = fuelFilterLogsByUserScope($rawLogs, $isAdmin, $sessionUserId);

        // Map to match frontend expectations
        $logs = array_map(function(array $l) {
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
        }, $rawLogs);

        if ($isAdmin) {
            $logCounts = array_values(array_filter($response['logCounts'] ?? [], 'is_array'));
        } else {
            $logCounts = fuelRebuildLogCountsForUser(
                $logs,
                $sessionUserId,
                $_SESSION['user_name'] ?? 'My Logs'
            );

            if (!$logCounts) {
                $logCounts = array_values(array_filter($response['logCounts'] ?? [], static function(array $count) use ($sessionUserId): bool {
                    return (int) ($count['user_id'] ?? 0) === $sessionUserId;
                }));
            }
        }

        echo json_encode(["logs" => $logs, "logCounts" => $logCounts], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(["logs" => [], "logCounts" => [], "error" => $response['message'] ?? "API Error"]);
    }
} catch (Exception $e) {
    echo json_encode(["logs" => [], "logCounts" => [], "error" => $e->getMessage()]);
}
