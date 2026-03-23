<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';
require_once __DIR__ . '/services/NotificationService.php';

header('Content-Type: application/json; charset=utf-8');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$reminders = is_array($input['reminders'] ?? null) ? array_values($input['reminders']) : [];
if (empty($reminders)) {
    echo json_encode(['success' => true, 'message' => 'No notifications to forward.']);
    exit();
}

$notificationService = new NotificationService();
$result = $notificationService->sendAdminReminderWhatsApp($reminders);

if ($result['success']) {
    echo json_encode($result);
    exit();
}

http_response_code(500);
echo json_encode($result);
