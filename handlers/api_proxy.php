<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', '/home/workorders/tmp');
    session_start();
}

require_once __DIR__ . "/../config.php";

$endpoint = $_GET['endpoint'] ?? null;
if (!$endpoint) {
    echo json_encode(['success' => false, 'message' => 'No endpoint specified']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON input
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = file_get_contents("php://input");
} else {
    $data = ($method === 'GET') ? $_GET : $_POST;
    // Remove endpoint from data if present
    if (is_array($data)) unset($data['endpoint']);
}

$response = makeApiCall($endpoint, $data, $method);

header('Content-Type: application/json');
echo json_encode($response);
