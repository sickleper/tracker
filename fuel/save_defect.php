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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $defectDetails = trim((string) ($_POST['defect_details'] ?? ''));
    $severity = fuelNormalizeSeverity($_POST['severity'] ?? 'medium');
    $offRoad = !empty($_POST['off_road']);
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $rectifiedOn = trim((string) ($_POST['rectified_on'] ?? ''));

    if ($vehicleId <= 0 || $defectDetails === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Vehicle and defect details are required.']);
        exit;
    }

    $data = [
        'vehicle_id' => $vehicleId,
        'defect_details' => $defectDetails,
        'rectified_on' => $rectifiedOn ?: null,
        'notes' => $notes,
    ];

    try {
        $vehicleRes = makeApiCall("/api/fuel/vehicles/{$vehicleId}");
        $vehicle = ($vehicleRes && ($vehicleRes['success'] ?? false)) ? ($vehicleRes['vehicle'] ?? null) : null;

        $response = makeApiCall('/api/fuel/defects', $data, 'POST');
        
        if ($response && ($response['success'] ?? false)) {
            $state = fuelLoadSafetyState();
            fuelUpsertSafetyDefect($state, [
                'vehicle_id' => $vehicleId,
                'vehicle_label' => (string) ($vehicle['license_plate'] ?? ''),
                'defect_details' => $defectDetails,
                'notes' => $notes,
                'date' => date('Y-m-d'),
                'severity' => $severity,
                'off_road' => $offRoad,
                'status' => $rectifiedOn !== '' ? 'rectified' : 'open',
                'rectified_on' => $rectifiedOn !== '' ? $rectifiedOn : null,
                'source' => 'manual',
                'api_synced' => true,
                'created_by' => (string) ($_SESSION['user_name'] ?? 'User'),
            ]);
            fuelSaveSafetyState($state);

            echo json_encode([
                'success' => true,
                'message' => $offRoad ? 'Defect saved. Vehicle marked off road.' : 'Defect saved successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to save defect']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
