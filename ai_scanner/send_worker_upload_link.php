<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/link_helper.php';

header('Content-Type: application/json');

if (!function_exists('receiptScannerNormalizeTemplateSid')) {
    function receiptScannerNormalizeTemplateSid(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with(strtolower($value), 'hx')) {
            return 'HX' . substr($value, 2);
        }

        return $value;
    }
}

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

$mode = strtolower(trim((string) ($_POST['mode'] ?? '')));
if (!in_array($mode, ['mileage', 'receipt'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Upload mode is required.']);
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
    rtrim(trackerAppUrl(), '/') . '/ai_scanner/driver_mileage.php',
    $userHash,
    $vehicleReg,
    $expires,
    $tenantId,
    $driverName,
    $mode
);

$isMileageMode = $mode === 'mileage';
$templateSid = $isMileageMode
    ? receiptScannerNormalizeTemplateSid((string) (
        $GLOBALS['twilio_whatsapp_driver_mileage_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_DRIVER_MILEAGE_TEMPLATE_SID']
        ?? $GLOBALS['twilio_whatsapp_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_TEMPLATE_SID']
        ?? ''
    ))
    : receiptScannerNormalizeTemplateSid((string) (
        $GLOBALS['twilio_whatsapp_receipt_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_RECEIPT_TEMPLATE_SID']
        ?? $GLOBALS['twilio_whatsapp_template_sid']
        ?? $_ENV['TWILIO_WHATSAPP_TEMPLATE_SID']
        ?? ''
    ));

$messageLines = $isMileageMode
    ? [
        'Mileage upload link',
        '',
        'Driver: ' . ($driverName !== '' ? $driverName : ('ID ' . $driverId)),
        'Vehicle: ' . $vehicleReg,
        '',
        'Open this link to upload an odometer photo for your mileage log:',
        $link,
        '',
        'This link expires on ' . date('d M Y H:i', $expires) . '.',
    ]
    : [
        'Receipt upload link',
        '',
        'Driver: ' . ($driverName !== '' ? $driverName : ('ID ' . $driverId)),
        'Vehicle: ' . $vehicleReg,
        '',
        'Open this link to upload receipts or invoices:',
        $link,
        '',
        'This link expires on ' . date('d M Y H:i', $expires) . '.',
    ];
$message = implode("\n", $messageLines);

$notificationService = new NotificationService();
$templateFallbackUsed = false;
$templateVars = $isMileageMode
    ? [
        '1' => $driverName !== '' ? $driverName : ('Driver ID ' . $driverId),
        '2' => $vehicleReg,
        '3' => $link,
        '4' => date('d M Y H:i', $expires),
    ]
    : [
        '1' => $driverName !== '' ? $driverName : ('Driver ID ' . $driverId),
        '2' => $link,
        '3' => date('d M Y H:i', $expires),
    ];

if ($templateSid !== '') {
    $sendResult = $notificationService->sendTemplatedWhatsApp($mobile, $templateSid, $templateVars);
    $sendResult['template_fallback'] = false;
    $sendResult['template_sid'] = $templateSid;
} else {
    $sendResult = $notificationService->sendDirectWhatsApp($mobile, $message);
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
    'message' => 'WhatsApp worker upload link sent successfully.',
    'link' => $link,
    'mode' => $mode,
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
