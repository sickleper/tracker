<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/daily_checks_repository.php';
require_once __DIR__ . '/safety_repository.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
$notes = trim((string) ($_POST['notes'] ?? ''));
$date = date('Y-m-d');
$items = fuelDailyCheckItems();
$checklist = [];
$failedItems = [];
$failedKeys = [];

foreach ($items as $key => $label) {
    $value = strtolower(trim((string) ($_POST['checks'][$key] ?? '')));
    if (!in_array($value, ['pass', 'fail'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Complete every daily check item before submitting.']);
        exit;
    }

    $checklist[$key] = $value;
    if ($value === 'fail') {
        $failedItems[] = $label;
        $failedKeys[] = $key;
    }
}

if ($vehicleId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a vehicle first.']);
    exit;
}

try {
    $vehicleRes = makeApiCall("/api/fuel/vehicles/{$vehicleId}");
    $vehicle = ($vehicleRes && ($vehicleRes['success'] ?? false)) ? ($vehicleRes['vehicle'] ?? null) : null;
    if (!$vehicle) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
        exit;
    }

    $checks = fuelLoadDailyChecks();
    $index = fuelFindDailyCheckIndex($checks, $vehicleId, $date);
    $existing = $index >= 0 ? $checks[$index] : [];
    $status = empty($failedItems) ? 'pass' : 'fail';
    $defectCreated = (bool) ($existing['defect_created'] ?? false);
    $severity = 'low';
    $offRoad = false;
    $matchingDefect = null;

    if ($status === 'fail') {
        $criticalFailures = ['tyres', 'lights', 'mirrors', 'brakes'];
        $highFailures = ['fluids', 'bodywork'];

        if (array_intersect($failedKeys, $criticalFailures)) {
            $severity = 'critical';
            $offRoad = true;
        } elseif (array_intersect($failedKeys, $highFailures)) {
            $severity = count($failedKeys) > 1 ? 'high' : 'medium';
        } else {
            $severity = 'low';
        }
    }

    $defectText = '';

    if ($status === 'fail' && !$defectCreated) {
        $defectText = 'Daily vehicle check failed for ' . ($vehicle['license_plate'] ?? ('Vehicle #' . $vehicleId)) . '. Failed items: ' . implode(', ', $failedItems);
        if ($notes !== '') {
            $defectText .= '. Notes: ' . $notes;
        }

        $defectResponse = makeApiCall('/api/fuel/defects', [
            'vehicle_id' => $vehicleId,
            'defect_details' => $defectText,
            'notes' => 'Created automatically from daily vehicle check on ' . $date,
        ], 'POST');

        $defectCreated = (bool) ($defectResponse['success'] ?? false);
    }

    if ($status === 'fail') {
        $state = fuelLoadSafetyState();
        $matchingDefect = fuelUpsertSafetyDefect($state, [
            'id' => (string) ($existing['safety_defect_id'] ?? ''),
            'vehicle_id' => $vehicleId,
            'vehicle_label' => (string) ($vehicle['license_plate'] ?? ''),
            'defect_details' => $defectText !== '' ? $defectText : ('Daily vehicle check failed for ' . ($vehicle['license_plate'] ?? ('Vehicle #' . $vehicleId)) . '. Failed items: ' . implode(', ', $failedItems)),
            'notes' => $notes,
            'date' => $date,
            'severity' => $severity,
            'off_road' => $offRoad,
            'status' => 'open',
            'source' => 'daily_check',
            'api_synced' => $defectCreated,
            'created_by' => (string) ($_SESSION['user_name'] ?? 'User'),
        ]);
        fuelSaveSafetyState($state);
    } elseif (!empty($existing['safety_defect_id'])) {
        $state = fuelLoadSafetyState();
        fuelResolveSafetyDefect($state, (string) $existing['safety_defect_id'], $date);
        fuelSaveSafetyState($state);
    }

    $record = [
        'id' => $existing['id'] ?? uniqid('daily_check_', true),
        'date' => $date,
        'vehicle_id' => $vehicleId,
        'vehicle_label' => (string) ($vehicle['license_plate'] ?? ''),
        'checked_by' => (string) ($_SESSION['user_name'] ?? 'User'),
        'checked_by_id' => (int) ($_SESSION['user_id'] ?? 0),
        'status' => $status,
        'notes' => $notes,
        'checklist' => $checklist,
        'failed_items' => $failedItems,
        'defect_created' => $defectCreated,
        'severity' => $severity,
        'off_road' => $offRoad,
        'safety_defect_id' => $matchingDefect['id'] ?? ($existing['safety_defect_id'] ?? null),
        'updated_at' => date('c'),
        'created_at' => $existing['created_at'] ?? date('c'),
    ];

    if ($index >= 0) {
        $checks[$index] = $record;
    } else {
        $checks[] = $record;
    }

    fuelSaveDailyChecks($checks);

    echo json_encode([
        'success' => true,
        'message' => $status === 'pass'
            ? 'Daily vehicle check passed and saved.'
            : ($offRoad ? 'Daily vehicle check saved. A critical defect was logged and the vehicle is now off road.' : 'Daily vehicle check saved. A defect has been logged for failed items.'),
        'record' => $record,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
