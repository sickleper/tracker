<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$events = [];
$currentDate = date('Y-m-d');
try {
    $response = makeApiCall('/api/fuel/vehicles');
    foreach (($response['vehicles'] ?? []) as $row) {
        $userName = $row['user']['name'] ?? 'Unassigned';
        $taxDate = $row['tax_expiry_date'] ?? null;
        $insuranceDate = $row['insurance_expiry_date'] ?? null;
        $doeDate = $row['doe_expiry_date'] ?? null;

        if ($taxDate) {
            $events[] = [
                'title' => "Tax: {$userName} - {$row['license_plate']}",
                'start' => $taxDate,
                'end' => $taxDate,
                'description' => "Make/Model: {$row['make_model']} - Tax" . ($taxDate < $currentDate ? ' (Expired)' : ''),
                'color' => 'red'
            ];
        }
        if ($insuranceDate) {
            $events[] = [
                'title' => "Insur: {$userName} - {$row['license_plate']}",
                'start' => $insuranceDate,
                'end' => $insuranceDate,
                'description' => "Make/Model: {$row['make_model']} - Insurance" . ($insuranceDate < $currentDate ? ' (Expired)' : ''),
                'color' => 'blue'
            ];
        }
        if ($doeDate) {
            $events[] = [
                'title' => "Doe: {$userName} - {$row['license_plate']}",
                'start' => $doeDate,
                'end' => $doeDate,
                'description' => "Make/Model: {$row['make_model']} - DOE" . ($doeDate < $currentDate ? ' (Expired)' : ''),
                'color' => 'green'
            ];
        }
    }
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

echo json_encode($events);
?>
