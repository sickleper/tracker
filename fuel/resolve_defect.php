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

if (!isTrackerAdminUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$defectId = trim((string) ($_POST['defect_id'] ?? ''));
$defectKey = trim((string) ($_POST['defect_key'] ?? ''));
$rectifiedOn = date('Y-m-d');
if ($defectId === '' && $defectKey === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Defect reference is required.']);
    exit;
}

try {
    $state = fuelLoadSafetyState();
    $defect = null;
    if ($defectId !== '') {
        $defect = fuelResolveSafetyDefect($state, $defectId);
    }
    if (!$defect && $defectKey !== '') {
        $defect = fuelResolveSafetyDefectByKey($state, $defectKey, $rectifiedOn);
    }
    $apiUpdated = false;

    if (
        !$defect
        && $defectId !== ''
        && strpos($defectId, 'fuel_defect_') !== 0
        && strpos($defectId, 'daily_check_') !== 0
    ) {
        $apiResponse = makeApiCall("/api/fuel/defects/{$defectId}", [
            'rectified_on' => $rectifiedOn,
        ], 'PATCH');

        if ($apiResponse && ($apiResponse['success'] ?? false)) {
            $apiUpdated = true;
            if ($defectKey !== '') {
                $defect = fuelResolveSafetyDefectByKey($state, $defectKey, $rectifiedOn);
            }
        } else {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => $apiResponse['message'] ?? 'Failed to update defect.']);
            exit;
        }
    } elseif (!$defect) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Defect not found.']);
        exit;
    }

    fuelSaveSafetyState($state);

    echo json_encode([
        'success' => true,
        'message' => (!empty($defect['off_road']) || $apiUpdated)
            ? 'Defect marked rectified. Vehicle road status has been recalculated.'
            : 'Defect marked rectified.',
        'defect' => $defect,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
