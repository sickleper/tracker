<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once "../../config.php";
require_once __DIR__ . "/../../tracker_data.php"; // For makeApiCall
require_once "../../services/NotificationService.php";

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true) ?? $_POST;

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
        exit;
    }

    $action = $data['action'] ?? 'save';

    if ($action === 'send_email') {
        $id = $data['id'] ?? 0;
        $res = makeApiCall("/api/proposals/{$id}");
        if ($res && ($res['success'] ?? false)) {
            $notif = new NotificationService();
            $sendSuccess = $notif->sendProposalEmail($res['data']);
            if ($sendSuccess) {
                echo json_encode(['success' => true, 'message' => 'Email sent successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send email via NotificationService.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Proposal not found.']);
        }
        exit;
    }

    // 1. Save to API first
    $apiRes = makeApiCall('/api/proposals', $data, 'POST');

    if ($apiRes && ($apiRes['success'] ?? false)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Proposal saved successfully.',
            'data' => $apiRes['data']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $apiRes['message'] ?? 'Failed to save proposal.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
