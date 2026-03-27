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

function normalizeVehicleDate(string $value = null): ?DateTime
{
    if (empty($value) || $value === '0000-00-00') {
        return null;
    }

    try {
        return new DateTime($value);
    } catch (Exception $e) {
        return null;
    }
}

function buildComplianceStatus(string $label, ?DateTime $expiryDate, DateTime $today): array
{
    if ($expiryDate === null) {
        return [
            'label' => $label,
            'state' => 'missing',
            'days_left' => null,
            'text' => $label . ': Missing'
        ];
    }

    $isExpired = $today > $expiryDate;
    $daysLeft = (int) $today->diff($expiryDate)->days;

    if ($isExpired) {
        return [
            'label' => $label,
            'state' => 'expired',
            'days_left' => 0,
            'text' => $label . ': Expired'
        ];
    }

    if ($daysLeft <= 30) {
        return [
            'label' => $label,
            'state' => 'warning',
            'days_left' => $daysLeft,
            'text' => $label . ': ' . $daysLeft . 'd left'
        ];
    }

    return [
        'label' => $label,
        'state' => 'valid',
        'days_left' => $daysLeft,
        'text' => $label . ': Valid'
    ];
}

try {
    $response = makeApiCall('/api/fuel/vehicles');

    if (!($response['success'] ?? false)) {
        http_response_code(502);
        echo json_encode([
            'success' => false,
            'message' => $response['message'] ?? 'Failed to load vehicle registry'
        ]);
        exit;
    }

    $today = new DateTime();
    $safetyState = fuelLoadSafetyState();
    $vehicleFlags = $safetyState['vehicle_flags'] ?? [];
    $vehicles = array_map(function(array $vehicle) use ($today) {
        $statuses = [
            buildComplianceStatus('Tax', normalizeVehicleDate($vehicle['tax_expiry_date'] ?? null), $today),
            buildComplianceStatus('Insurance', normalizeVehicleDate($vehicle['insurance_expiry_date'] ?? null), $today),
            buildComplianceStatus('DOE', normalizeVehicleDate($vehicle['doe_expiry_date'] ?? null), $today)
        ];

        $rowState = 'valid';
        foreach ($statuses as $status) {
            if ($status['state'] === 'expired') {
                $rowState = 'expired';
                break;
            }
            if ($status['state'] === 'warning' && $rowState !== 'expired') {
                $rowState = 'warning';
            }
            if ($status['state'] === 'missing' && $rowState === 'valid') {
                $rowState = 'missing';
            }
        }

        return [
            'vehicle_id' => $vehicle['vehicle_id'],
            'license_plate' => $vehicle['license_plate'],
            'make_model' => $vehicle['make_model'],
            'user_name' => $vehicle['user']['name'] ?? 'Unassigned',
            'statuses' => $statuses,
            'row_state' => $rowState
        ];
    }, $response['vehicles'] ?? []);

    $vehicles = array_map(function(array $vehicle) use ($vehicleFlags, $safetyState): array {
        $vehicleId = (int) ($vehicle['vehicle_id'] ?? 0);
        $flag = $vehicleFlags[(string) $vehicleId] ?? null;
        $openDefects = fuelOpenSafetyDefectsForVehicle($safetyState, $vehicleId);

        if ($flag && !empty($flag['off_road'])) {
            $vehicle['row_state'] = 'off_road';
        }

        $vehicle['off_road'] = !empty($flag['off_road']);
        $vehicle['off_road_reason'] = (string) ($flag['reason'] ?? '');
        $vehicle['safety_severity'] = (string) ($flag['severity'] ?? '');
        $vehicle['active_defect_count'] = count($openDefects);

        return $vehicle;
    }, $vehicles);

    echo json_encode([
        'success' => true,
        'vehicles' => $vehicles,
        'vehicle_flags' => $vehicleFlags,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
