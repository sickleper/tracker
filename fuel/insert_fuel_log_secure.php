<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/daily_checks_repository.php';
require_once __DIR__ . '/safety_repository.php';
require_once __DIR__ . '/upload_helpers.php';

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

$isAdmin = isTrackerAdminUser();
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
if (!$isAdmin && $sessionUserId <= 0) {
    $sessionUserId = fuelCurrentUserId();
}

if (!$isAdmin && $sessionUserId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unable to resolve user identity.']);
    exit;
}

function fuelLogNumber($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    return is_numeric($value) ? (float) $value : null;
}

try {
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $logDate = trim((string) ($_POST['date'] ?? date('Y-m-d')));
    $today = date('Y-m-d');
    $startMileage = fuelLogNumber($_POST['start_mileage'] ?? null);
    $finishMileage = fuelLogNumber($_POST['finish_mileage'] ?? null);
    $fuelAmount = fuelLogNumber($_POST['fuel_amount'] ?? null);

    if ($vehicleId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Choose a vehicle first.']);
        exit;
    }

    if ($startMileage === null || $finishMileage === null || $fuelAmount === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Start mileage, finish mileage, and fuel amount are required.']);
        exit;
    }

    if ($startMileage < 0 || $finishMileage < 0 || $fuelAmount <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Mileage must be non-negative and fuel amount must be greater than zero.']);
        exit;
    }

    if ($finishMileage < $startMileage) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Finish mileage must be greater than or equal to start mileage.']);
        exit;
    }

    $safetyState = fuelLoadSafetyState();
    $vehicleFlag = $safetyState['vehicle_flags'][(string) $vehicleId] ?? null;
    if (!empty($vehicleFlag['off_road'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => !empty($vehicleFlag['reason'])
                ? 'Vehicle is off road: ' . $vehicleFlag['reason']
                : 'This vehicle is currently marked off road and cannot be used.',
        ]);
        exit;
    }

    if ($logDate === $today) {
        $checks = fuelLoadDailyChecks();
        $checkIndex = fuelFindDailyCheckIndex($checks, $vehicleId, $today);
        $dailyCheck = $checkIndex >= 0 ? $checks[$checkIndex] : null;

        if (!$dailyCheck) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Complete today\'s daily vehicle check before logging fuel or mileage.']);
            exit;
        }

        if ((string) ($dailyCheck['status'] ?? '') !== 'pass') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'This vehicle has a failed daily check today. Review the check before logging use.']);
            exit;
        }
    }

    $uploadedImage = null;
    if (isset($_FILES['image_file']) && (int) ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int) $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Receipt image upload failed.']);
            exit;
        }

        $targetDir = fuelReceiptUploadDir();
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $originalName = (string) ($_FILES['image_file']['name'] ?? 'receipt');
        $uploadedImage = fuelBuildReceiptUploadName($originalName);
        if (!move_uploaded_file($_FILES['image_file']['tmp_name'], fuelReceiptUploadPath($uploadedImage))) {
            throw new RuntimeException('Unable to store receipt image.');
        }
    }

    $requestedUserId = (int) ($_POST['user_id'] ?? 0);
    if (!$isAdmin) {
        $requestedUserId = $sessionUserId;
    } elseif ($requestedUserId <= 0) {
        $requestedUserId = $sessionUserId;
    }

    if ($requestedUserId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid driver is required.']);
        exit;
    }

    $data = [
        'vehicle_id' => $vehicleId,
        'user_id' => $requestedUserId,
        'date' => $logDate,
        'start_mileage' => $startMileage,
        'finish_mileage' => $finishMileage,
        'fuel_amount' => $fuelAmount,
        'image_file' => $uploadedImage,
    ];

    $response = makeApiCall('/api/fuel/logs', $data, 'POST');
    
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(['success' => true, 'message' => 'Log saved successfully']);
        } else {
            if ($uploadedImage && is_file(fuelReceiptUploadPath($uploadedImage))) {
                @unlink(fuelReceiptUploadPath($uploadedImage));
            }
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to save log']);
        }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
