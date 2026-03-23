<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../tracker_data.php';

function fetchVehicleReminderConfig(): array {
    $defaults = [
        'document_days' => 30,
        'service_days' => 30,
    ];

    $settingsResponse = makeApiCall('/api/settings');
    if (!is_array($settingsResponse) || empty($settingsResponse['success'])) {
        return $defaults;
    }

    $general = $settingsResponse['data']['general'] ?? [];
    foreach ($general as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = $item['key'] ?? '';
        $value = (int) ($item['value'] ?? 0);
        if ($key === 'vehicle_document_reminder_days' && $value > 0) {
            $defaults['document_days'] = min(120, max(3, $value));
        } elseif ($key === 'vehicle_service_reminder_days' && $value > 0) {
            $defaults['service_days'] = min(120, max(3, $value));
        }
    }
    return $defaults;
}

header('Content-Type: application/json; charset=utf-8');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
    ]);
    exit();
}

$host = $_ENV['DB_SECONDARY_HOST'] ?? 'localhost';
$db = $_ENV['DB_SECONDARY_DATABASE'] ?? '';
$user = $_ENV['DB_SECONDARY_USERNAME'] ?? '';
$pass = $_ENV['DB_SECONDARY_PASSWORD'] ?? '';

// Fallback to primary DB if secondary DB appears to be unconfigured placeholders
if (strpos($db, 'YOUR_') !== false || $db === '') {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $db = $_ENV['DB_DATABASE'] ?? '';
    $user = $_ENV['DB_USERNAME'] ?? '';
    $pass = $_ENV['DB_PASSWORD'] ?? '';
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to connect to the fuel database.',
    ]);
    exit();
}

$config = fetchVehicleReminderConfig();
$limit = min(10, max(2, (int) ($_GET['limit'] ?? 5)));
$documentWindow = $config['document_days'];
$serviceWindow = $config['service_days'];
$today = new DateTimeImmutable('today');
$rows = [];

$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Tenant context not available.',
    ]);
    $conn->close();
    exit();
}

$stmt = $conn->prepare("
    SELECT v.vehicle_id, v.license_plate, v.tax_expiry_date, v.insurance_expiry_date, v.doe_expiry_date, v.service_due
    FROM vehicles v
    JOIN users u ON u.id = v.user_id
    WHERE u.tenant_id = ?
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error preparing reminder query.',
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param('i', $tenantId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($vehicle = $result->fetch_assoc()) {
        $expiryMap = [
            'Tax Disk' => $vehicle['tax_expiry_date'],
            'Insurance Disk' => $vehicle['insurance_expiry_date'],
            'DOE Disk' => $vehicle['doe_expiry_date'],
        ];

        foreach ($expiryMap as $label => $expiryDate) {
            if ($expiryDate === null || $expiryDate === '') {
                continue;
            }

            try {
                $expiryObj = new DateTimeImmutable($expiryDate);
            } catch (Exception $e) {
                continue;
            }

            $interval = $today->diff($expiryObj);
            $daysUntil = (int) $interval->format('%r%a');

            if ($daysUntil > $documentWindow) {
                continue;
            }

            $status = $daysUntil < 0 ? 'Expired' : 'Expiring soon';
            $rows[] = [
                'vehicle_id' => $vehicle['vehicle_id'],
                'license_plate' => $vehicle['license_plate'] ?? 'Unknown',
                'doc_type' => $label,
                'status' => $status,
                'date' => $expiryObj->format('Y-m-d'),
                'days_until' => $daysUntil,
            ];
        }
        $serviceDue = $vehicle['service_due'] ?? null;
        if ($serviceDue) {
            try {
                $serviceDueDate = new DateTimeImmutable($serviceDue);
                $interval = $today->diff($serviceDueDate);
                $serviceDays = (int) $interval->format('%r%a');
                if ($serviceDays <= $serviceWindow) {
                    $rows[] = [
                        'vehicle_id' => $vehicle['vehicle_id'],
                        'license_plate' => $vehicle['license_plate'] ?? 'Unknown',
                        'doc_type' => 'Service Due',
                        'status' => $serviceDays < 0 ? 'Overdue' : 'Service Due Soon',
                        'date' => $serviceDueDate->format('Y-m-d'),
                        'days_until' => $serviceDays,
                    ];
                }
            } catch (Exception $e) {
                // ignore invalid date
            }
        }
    }
    $result->close();
}

$stmt->close();
$conn->close();

usort($rows, function ($a, $b) {
    return $a['days_until'] <=> $b['days_until'];
});

$rows = array_slice($rows, 0, $limit);

echo json_encode([
    'success' => true,
    'reminders' => array_values($rows),
]);
