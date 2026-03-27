<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/safety_repository.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $response = makeApiCall('/api/fuel/defects');
    $state = fuelLoadSafetyState();
    $localDefects = $state['defects'] ?? [];
    $apiDefects = [];
    $partialMessage = null;

    if ($response && ($response['success'] ?? false)) {
        $apiDefects = array_map(static function(array $defect): array {
            return [
                'id' => (string) ($defect['id'] ?? ''),
                'vehicle_id' => (int) ($defect['vehicle_id'] ?? ($defect['vehicle']['vehicle_id'] ?? 0)),
                'vehicle' => [
                    'vehicle_id' => (int) ($defect['vehicle_id'] ?? ($defect['vehicle']['vehicle_id'] ?? 0)),
                    'license_plate' => (string) ($defect['vehicle']['license_plate'] ?? ''),
                ],
                'vehicle_label' => (string) ($defect['vehicle']['license_plate'] ?? ''),
                'date' => (string) ($defect['date'] ?? substr((string) ($defect['created_at'] ?? date('c')), 0, 10)),
                'defect_details' => (string) ($defect['defect_details'] ?? ''),
                'notes' => (string) ($defect['notes'] ?? ''),
                'severity' => fuelNormalizeSeverity($defect['severity'] ?? 'medium'),
                'off_road' => !empty($defect['off_road']),
                'status' => empty($defect['rectified_on']) ? 'open' : 'rectified',
                'rectified_on' => $defect['rectified_on'] ?? null,
                'source' => 'api',
            ];
        }, $response['data'] ?? []);
    } else {
        $partialMessage = $response['message'] ?? 'Backend defect feed is unavailable. Showing local safety records only.';
    }
    
    echo json_encode([
        'success' => true,
        'partial' => $partialMessage !== null,
        'message' => $partialMessage,
        'data' => fuelMergeDefects($apiDefects, $localDefects),
        'vehicle_flags' => $state['vehicle_flags'] ?? [],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
