<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tracker_data.php';

$response = ['success' => false, 'message' => 'Invalid request.'];

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Note: The modal sends 'task_id', but the original script used 'id'
    $id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : (isset($_POST['id']) ? (int)$_POST['id'] : null);
    $isWeatherDependent = isset($_POST['is_weather_dependent']) ? (int)$_POST['is_weather_dependent'] : null;

    if ($id && ($isWeatherDependent === 0 || $isWeatherDependent === 1)) {
        try {
            // Update via API
            $apiResponse = makeApiCall("/api/tasks/{$id}", [
                'is_weather_dependent' => $isWeatherDependent
            ], 'PATCH');

            if ($apiResponse && ($apiResponse['success'] ?? false)) {
                $response = ['success' => true, 'message' => 'Weather dependency updated via API.'];
            } else {
                $msg = $apiResponse['message'] ?? 'API update failed.';
                $response = ['success' => false, 'message' => $msg];
            }
        } catch (Exception $e) {
            $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid ID or weather dependency value provided.'];
    }
}

echo json_encode($response);
