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
    $toolID = $_POST['toolID'] ?? null;
    $data = [
        'ToolName' => $_POST['toolName'] ?? null,
        'ToolTypeID' => $_POST['toolTypeID'] ?? null,
        'SerialNumber' => $_POST['serialNumber'] ?? null,
        'Value' => $_POST['value'] ?? null,
        'TradeIDs' => $_POST['toolTradeID'] ?? [],
    ];

    if ($toolID) {
        try {
            $response = makeApiCall("/api/tools/{$toolID}", $data, 'PATCH');
            echo json_encode($response);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
