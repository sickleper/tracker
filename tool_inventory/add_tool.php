<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $toolName = trim((string) ($_POST['toolName'] ?? ''));
    $toolTypeId = (int) ($_POST['toolTypeID'] ?? 0);
    $value = is_numeric($_POST['value'] ?? null) ? (float) $_POST['value'] : null;

    if ($toolName === '' || $toolTypeId <= 0 || $value === null || $value < 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Tool name, category, and a valid non-negative value are required.']);
        exit;
    }

    $data = [
        'ToolName' => $toolName,
        'ToolTypeID' => $toolTypeId,
        'SerialNumber' => $_POST['serialNumber'] ?? null,
        'Value' => $value,
        'TradeIDs' => $_POST['toolTradeID'] ?? [],
    ];

    try {
        $response = makeApiCall('/api/tools', $data, 'POST');
        
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(['success' => true, 'toolID' => $response['data']['ToolID']]);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to add tool']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
