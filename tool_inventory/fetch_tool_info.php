<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo "<p class='text-danger'>Unauthorized.</p>";
    exit;
}

$toolID = $_GET['tool_id'] ?? null;

if ($toolID && is_numeric($toolID)) {
    try {
        $response = makeApiCall("/api/tools"); // We'll find by ID in results or add specific endpoint
        
        if ($response && ($response['success'] ?? false)) {
            $tool = null;
            foreach ($response['data'] as $t) {
                if ($t['ToolID'] == $toolID) {
                    $tool = $t;
                    break;
                }
            }

            if ($tool) {
                $toolName = htmlspecialchars($tool['ToolName']);
                $serialNumber = htmlspecialchars($tool['SerialNumber'] ?? 'N/A');
                $purchaseDate = htmlspecialchars($tool['PurchaseDate'] ?? 'N/A');
                $value = htmlspecialchars($tool['Value'] ?? '0.00');
                $imageURL = $tool['ImageURL'] ?? '';

                echo "<div class='card'>";
                echo "<div class='card-body'>";
                echo "<h5 class='card-title'>$toolName</h5>";
                echo "<h6 class='card-subtitle mb-2 text-muted'>Serial Number: $serialNumber</h6>";
                echo "<p class='card-text'><strong>Purchase Date:</strong> $purchaseDate</p>";
                echo "<p class='card-text'><strong>Value:</strong> <span id='val'>$value</span></p>";
                if ($imageURL) {
                    echo "<p class='card-text'><img src='$imageURL' alt='$toolName' class='img-fluid' style='max-width: 100px;'></p>";
                }
                echo "</div>";
                echo "</div>";
            } else {
                echo "<p class='text-danger'>Tool information not found.</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='text-danger'>Error: " . $e->getMessage() . "</p>";
    }
}
