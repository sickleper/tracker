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
    $data = [
        'ToolName' => $_POST['toolName'] ?? null,
        'ToolTypeID' => $_POST['toolTypeID'] ?? null,
        'SerialNumber' => $_POST['serialNumber'] ?? null,
        'Value' => $_POST['value'] ?? 0,
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
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
