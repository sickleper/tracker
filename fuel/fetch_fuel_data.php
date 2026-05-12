<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/upload_helpers.php';

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

$isAdmin = isTrackerAdminUser();
$action = $_GET['action'] ?? '';
$vehicleId = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
if (!$isAdmin && $sessionUserId <= 0) {
    $sessionUserId = fuelCurrentUserId();
}

if (!$isAdmin && $sessionUserId <= 0) {
    fuelRespond(['success' => false, 'message' => 'Unable to resolve user identity.'], 401);
}

$requestedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (!$isAdmin && $requestedUserId > 0 && $requestedUserId !== $sessionUserId) {
    fuelRespond(['success' => false, 'message' => 'Forbidden'], 403);
}

if (!$isAdmin && $requestedUserId <= 0) {
    $requestedUserId = $sessionUserId;
}

$reportParams = ['vehicle_id' => $vehicleId];
if ($requestedUserId > 0) {
    $reportParams['user_id'] = $requestedUserId;
}

if (!function_exists('fuelFilterReportByUser')) {
    function fuelFilterReportByUser(array $rows, bool $isAdmin, int $userId): array
    {
        if ($isAdmin || $userId <= 0) {
            return $rows;
        }

        return array_values(array_filter($rows, static function($row) use ($userId): bool {
            if (!is_array($row)) {
                return false;
            }

            $rowUserId = (int) (
                $row['user_id']
                ?? ($row['user']['id'] ?? 0)
            );

            return $rowUserId > 0 && $rowUserId === $userId;
        }));
    }
}

try {
    if ($action === 'stats') {
        $response = makeApiCall('/api/fuel/stats', $reportParams);
        if (!($response['success'] ?? false)) {
            fuelRespond([
                'success' => false,
                'message' => $response['message'] ?? 'Failed to load fleet stats.',
                'data' => []
            ], 502);
        }

        $statsData = $response['data'] ?? [];
        if (is_array($statsData) && fuelIsList($statsData)) {
            $statsData = fuelFilterReportByUser($statsData, $isAdmin, $requestedUserId);
        }

        fuelRespond([
            'success' => true,
            'data' => $statsData
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
            'vehicle_id' => $vehicleId,
            'user_id' => $requestedUserId > 0 ? $requestedUserId : null,
        ]);

        if (is_array($response) && fuelIsList($response)) {
            $response = fuelFilterReportByUser($response, $isAdmin, $requestedUserId);
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

        $reportData = $response['data'] ?? $response['report'] ?? [];
        if (is_array($reportData) && fuelIsList($reportData)) {
            $reportData = fuelFilterReportByUser($reportData, $isAdmin, $requestedUserId);
        }

        fuelRespond([
            'success' => true,
            'data' => $reportData
        ]);
    }
} catch (Exception $e) {
    fuelRespond(['success' => false, 'message' => $e->getMessage()], 500);
}
