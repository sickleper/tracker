<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

function fuelRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function fuelIsList(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }

    return array_keys($value) === range(0, count($value) - 1);
}

if (!isTrackerAuthenticated()) {
    fuelRespond(['success' => false, 'message' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? '';
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

try {
    if ($action === 'stats') {
        $response = makeApiCall('/api/fuel/stats', ['vehicle_id' => $vehicleId]);
        if (!($response['success'] ?? false)) {
            fuelRespond([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to load fleet stats.',
                'data' => []
            ], 502);
        }

        fuelRespond([
            'success' => true,
            'data' => $response['data'] ?? []
        ]);
    } elseif ($action === 'mpl') {
        $response = makeApiCall('/api/fuel/overall-mpl');
        if (!($response['success'] ?? false)) {
            fuelRespond([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to load efficiency report.',
                'labels' => [],
                'datasets' => []
            ], 502);
        }

        fuelRespond([
            'success' => true,
            'labels' => $response['labels'] ?? [],
            'datasets' => $response['datasets'] ?? []
        ]);
    } else {
        $response = makeApiCall('/api/fuel/report', [
            'month' => $month,
            'year' => $year,
            'vehicle_id' => $vehicleId
        ]);

        if (is_array($response) && fuelIsList($response)) {
            fuelRespond([
                'success' => true,
                'data' => $response
            ]);
        }

        if (!($response['success'] ?? false)) {
            fuelRespond([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to load anomaly report.',
                'data' => []
            ], 502);
        }

        fuelRespond([
            'success' => true,
            'data' => $response['data'] ?? $response['report'] ?? []
        ]);
    }
} catch (Exception $e) {
    fuelRespond(['success' => false, 'message' => $e->getMessage()], 500);
}
