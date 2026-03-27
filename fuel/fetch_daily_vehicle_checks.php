<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/daily_checks_repository.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $today = date('Y-m-d');
    $vehicleRes = makeApiCall('/api/fuel/vehicles');
    $vehicles = ($vehicleRes && ($vehicleRes['success'] ?? false)) ? ($vehicleRes['vehicles'] ?? []) : [];
    $checks = fuelDailyChecksForDate(fuelLoadDailyChecks(), $today);

    echo json_encode([
        'success' => true,
        'date' => $today,
        'vehicles' => array_map(static function(array $vehicle): array {
            return [
                'vehicle_id' => (int) ($vehicle['vehicle_id'] ?? 0),
                'license_plate' => (string) ($vehicle['license_plate'] ?? ''),
                'make_model' => (string) ($vehicle['make_model'] ?? ''),
            ];
        }, $vehicles),
        'checks' => $checks,
        'items' => fuelDailyCheckItems(),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
