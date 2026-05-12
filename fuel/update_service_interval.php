<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/maintenance_history_repository.php';

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

function normalizeServiceDateInput($value): string
{
    if ($value === null) {
        return '';
    }

    $normalized = trim((string) $value);
    if ($normalized === '' || $normalized === '0' || $normalized === '0000-00-00') {
        return '';
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) ? $normalized : '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
$newServiceInterval = (int) ($_POST['new_service_interval'] ?? 0);
$lastServiceMileage = (int) ($_POST['last_service_mileage'] ?? -1);
$serviceDue = normalizeServiceDateInput($_POST['service_due'] ?? null);
$serviceCompletedOn = normalizeServiceDateInput($_POST['service_completed_on'] ?? null);
$serviceNotes = trim((string) ($_POST['service_notes'] ?? ''));

if ($vehicleId <= 0 || $newServiceInterval <= 0 || $lastServiceMileage < 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Use a valid vehicle, positive service interval, and non-negative last service mileage.'
    ]);
    exit;
}

try {
    $vehicleRes = makeApiCall("/api/fuel/vehicles/{$vehicleId}");
    $vehicle = ($vehicleRes && ($vehicleRes['success'] ?? false)) ? ($vehicleRes['vehicle'] ?? null) : null;
    $currentMileage = isset($vehicle['last_mileage']) ? (int) $vehicle['last_mileage'] : null;

    if ($currentMileage !== null && $lastServiceMileage > $currentMileage) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Last service mileage cannot be higher than the vehicle\'s current mileage.'
        ]);
        exit;
    }

    $payload = [
        'service_mileage_threshold' => $newServiceInterval,
        'last_service_mileage' => $lastServiceMileage,
    ];
    if ($serviceDue !== '') {
        $payload['service_due'] = $serviceDue;
    }

    $response = makeApiCall("/api/fuel/vehicles/{$vehicleId}", $payload, 'PATCH');

    if ($response && ($response['success'] ?? false)) {
        $previousServiceMileage = isset($vehicle['last_service_mileage']) ? (int) $vehicle['last_service_mileage'] : null;
        $previousInterval = isset($vehicle['service_mileage_threshold']) ? (int) $vehicle['service_mileage_threshold'] : null;
        $previousDue = normalizeServiceDateInput($vehicle['service_due'] ?? null);

        $shouldLogHistory = $serviceCompletedOn !== ''
            || $serviceNotes !== ''
            || $previousServiceMileage === null
            || $previousServiceMileage !== $lastServiceMileage;

        if ($shouldLogHistory) {
            $summaryParts = ['Service record updated'];
            if ($previousServiceMileage !== $lastServiceMileage) {
                $summaryParts[] = 'last service mileage set to ' . number_format($lastServiceMileage) . ' mi';
            }
            if ($previousInterval !== $newServiceInterval) {
                $summaryParts[] = 'interval set to ' . number_format($newServiceInterval) . ' mi';
            }
            if ($previousDue !== $serviceDue && $serviceDue !== '') {
                $summaryParts[] = 'next due ' . $serviceDue;
            }

            fuelAddMaintenanceHistoryEntry([
                'vehicle_id' => $vehicleId,
                'vehicle_label' => (string) ($vehicle['license_plate'] ?? ('Vehicle #' . $vehicleId)),
                'event_type' => 'service',
                'summary' => implode(' | ', $summaryParts),
                'notes' => $serviceNotes,
                'completed_on' => $serviceCompletedOn !== '' ? $serviceCompletedOn : date('Y-m-d'),
                'mileage_at_service' => $lastServiceMileage,
                'service_interval' => $newServiceInterval,
                'next_due' => $serviceDue !== '' ? $serviceDue : null,
                'updated_by' => (string) ($_SESSION['user_name'] ?? 'User'),
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => $shouldLogHistory ? 'Service schedule updated and added to maintenance history.' : 'Service schedule updated.'
        ]);
    } else {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $response['message'] ?? 'Error updating vehicle.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
