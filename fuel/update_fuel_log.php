<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_id'])) {
    $log_id = $_POST['log_id'];
    
    $data = [
        'vehicle_id' => $_POST['vehicle_id'] ?? null,
        'user_id' => $_POST['user_id'] ?? null,
        'date' => $_POST['date'] ?? null,
        'start_mileage' => $_POST['start_mileage'] ?? null,
        'finish_mileage' => $_POST['finish_mileage'] ?? null,
        'fuel_amount' => $_POST['fuel_amount'] ?? null,
        'image_file' => $_POST['image_file'] ?? null,
    ];

    // Handle File Upload if provided
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES["image_file"]["name"]);
        if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $targetDir . $fileName)) {
            $data['image_file'] = $fileName;
        }
    }

    try {
        $response = makeApiCall("/api/fuel/logs/{$log_id}", $data, 'PATCH');
        
        if ($response && ($response['success'] ?? false)) {
            echo json_encode(['success' => true, 'message' => 'Fuel log updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $response['message'] ?? 'Failed to update log']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
