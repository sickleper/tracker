<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$toolID = $_GET['tool_id'] ?? null;

if ($toolID) {
    try {
        $response = makeApiCall("/api/tools/{$toolID}");
        
        if ($response && ($response['success'] ?? false)) {
            $tool = $response['data'];
            // Flatten trades for legacy JS if needed
            $trades = [];
            if (!empty($tool['trades'])) {
                foreach($tool['trades'] as $tr) $trades[] = $tr['TradeID'];
            }
            $tool['Trades'] = implode(',', $trades);
            echo json_encode($tool);
        } else {
            echo json_encode(['error' => 'Tool not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
