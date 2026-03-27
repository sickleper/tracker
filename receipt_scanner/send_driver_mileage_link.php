<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/link_helper.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

if (!isTrackerAdminUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Office access is required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$driverId = (int) ($_POST['driver_id'] ?? 0);
if ($driverId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Driver id is required.']);
    exit();
}

$userResponse = makeApiCall('/api/users/' . $driverId);
$driver = is_array($userResponse) && ($userResponse['success'] ?? false) ? ($userResponse['user'] ?? null) : null;
if (!is_array($driver)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Driver not found.']);
    exit();
}

$vehiclesResponse = makeApiCall('/api/fuel/vehicles');
$vehicles = is_array($vehiclesResponse) && ($vehiclesResponse['success'] ?? false) ? ($vehiclesResponse['vehicles'] ?? []) : [];
$assignedVehicle = null;
foreach ($vehicles as $vehicle) {
    if ((int) ($vehicle['user_id'] ?? 0) === $driverId) {
        $assignedVehicle = $vehicle;
        break;
    }
}

if (!$assignedVehicle) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No assigned vehicle was found for this driver.']);
    exit();
}

$mobile = trim((string) ($driver['mobile'] ?? ''));
if ($mobile === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Driver mobile number is missing.']);
    exit();
}

$expires = time() + (7 * 24 * 60 * 60);
$driverName = trim((string) ($driver['name'] ?? 'Driver'));
$vehicleReg = trim((string) ($assignedVehicle['license_plate'] ?? ''));
$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
$userHash = trim((string) ($driver['hash'] ?? ''));
if ($userHash === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Driver hash is missing.']);
    exit();
}
$link = receiptScannerBuildDriverMileageLink(
    rtrim(trackerAppUrl(), '/') . '/receipt_scanner/driver_mileage.php',
    $userHash,
    $vehicleReg,
    $expires,
    $tenantId,
    $driverName
);

$messageLines = [
    'Mileage upload link',
    '',
    'Driver: ' . ($driverName !== '' ? $driverName : ('ID ' . $driverId)),
    'Vehicle: ' . $vehicleReg,
    '',
    'Open this link and take a dashboard photo:',
    $link,
    '',
    'This link expires on ' . date('d M Y H:i', $expires) . '.',
];
$message = implode("\n", $messageLines);

$notificationService = new NotificationService();
$sendResult = $notificationService->sendDirectWhatsApp($mobile, $message);
$templateFallbackUsed = false;

if (!($sendResult['success'] ?? false) && (string) ($sendResult['error_code'] ?? '') === '63016') {
    $templateSid = trim((string) (
        $GLOBALS['twilio_whatsapp_driver_mileage_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_DRIVER_MILEAGE_TEMPLATE_SID']
        ?? $GLOBALS['twilio_whatsapp_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_TEMPLATE_SID']
        ?? ''
    ));
    if ($templateSid !== '') {
        $templateFallbackUsed = true;
        $sendResult = $notificationService->sendTemplatedWhatsApp($mobile, $templateSid, [
            '1' => $driverName !== '' ? $driverName : ('Driver ID ' . $driverId),
            '2' => $vehicleReg,
            '3' => $link,
            '4' => date('d M Y H:i', $expires),
        ]);
        $sendResult['template_fallback'] = true;
        $sendResult['template_sid'] = $templateSid;
    }
}

if (!($sendResult['success'] ?? false)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $sendResult['message'] ?? 'Failed to send WhatsApp message.',
        'link' => $link,
        'twilio' => [
            'sid' => $sendResult['sid'] ?? null,
            'status' => $sendResult['status'] ?? null,
            'error_code' => $sendResult['error_code'] ?? null,
            'error_message' => $sendResult['error_message'] ?? null,
            'more_info' => $sendResult['more_info'] ?? null,
            'template_fallback' => $sendResult['template_fallback'] ?? false,
            'template_sid' => $sendResult['template_sid'] ?? null,
        ],
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'WhatsApp upload link sent successfully.',
    'link' => $link,
    'driver' => [
        'id' => $driverId,
        'name' => $driverName,
        'mobile' => $mobile,
    ],
    'vehicle' => [
        'vehicle_id' => (int) ($assignedVehicle['vehicle_id'] ?? 0),
        'license_plate' => $vehicleReg,
    ],
    'expires_at' => date('c', $expires),
    'twilio' => [
        'sid' => $sendResult['sid'] ?? null,
        'status' => $sendResult['status'] ?? null,
        'error_code' => $sendResult['error_code'] ?? null,
        'error_message' => $sendResult['error_message'] ?? null,
        'template_fallback' => $sendResult['template_fallback'] ?? $templateFallbackUsed,
        'template_sid' => $sendResult['template_sid'] ?? null,
    ],
]);
