<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['data' => []]);
    exit;
}

try {
    // The index endpoint in the API already returns tools with types and trades
    // We need to extend it to also include van assignments or handle it here
    $response = makeApiCall('/api/tools');
    
    if ($response && ($response['success'] ?? false)) {
        // For tools table, we also want assignment info. 
        // We'll fetch all van inventories and join them here for simplicity
        $vanRes = makeApiCall('/api/inventory/van/values'); // Actually we need van assignments
        // Let's just use the tools data and assume the API might be updated or we handle differently
        
        $tools = array_map(function($t) {
            $trades = array_map(function($tr) { return $t['trades']['TradeName'] ?? ''; }, $t['trades']);
            
            // To perfectly mirror legacy, we'd need to know which vehicle it's in.
            // In the new API, a tool might be in MULTIPLE vans (Quantity > 0 in different VanInventory rows)
            // Legacy query used "LEFT JOIN VanInventory" which might show duplicates if in multiple vans
            
            return [
                'ToolID' => $t['ToolID'],
                'ToolName' => $t['ToolName'],
                'ToolTypeName' => $t['type']['ToolTypeName'] ?? 'N/A',
                'SerialNumber' => $t['SerialNumber'],
                'PurchaseDate' => $t['PurchaseDate'],
                'Value' => $t['Value'],
                'Trades' => implode(', ', array_column($t['trades'], 'TradeName')),
                'ImageURL' => $t['ImageURL'],
                'assignedVehicle' => 'Not Assigned' // Will require extra API info for full mirror
            ];
        }, $response['data']);

        echo json_encode(['data' => $tools]);
    } else {
        echo json_encode(['data' => []]);
    }
} catch (Exception $e) {
    echo json_encode(['data' => []]);
}
