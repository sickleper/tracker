<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', '/home/workorders/tmp');
    session_start();
}

require_once __DIR__ . "/../config.php";

/** @var string|null $endpoint */
$endpoint = $_GET['endpoint'] ?? null;
if (!is_string($endpoint) || trim($endpoint) === '') {
    echo json_encode(['success' => false, 'message' => 'No endpoint specified']);
    http_response_code(400);
    exit;
}

$endpoint = rawurldecode(trim($endpoint));
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if (!str_starts_with($endpoint, '/')) {
    echo json_encode(['success' => false, 'message' => 'Invalid endpoint']);
    http_response_code(400);
    exit;
}

$allowed = [
    'GET' => ['#^/api/attendances$#', '#^/api/attendances/[0-9]+$#'],
    'POST' => ['#^/api/attendances$#', '#^/api/attendances/clock-in$#', '#^/api/attendances/clock-out$#'],
    'PATCH' => ['#^/api/attendances/[0-9]+$#'],
    'DELETE' => ['#^/api/attendances/[0-9]+$#'],
];

if (!isset($allowed[$method])) {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    http_response_code(405);
    exit;
}

$isAllowedEndpoint = false;
foreach ($allowed[$method] as $pattern) {
    if (preg_match($pattern, $endpoint) === 1) {
        $isAllowedEndpoint = true;
        break;
    }
}

if (!$isAllowedEndpoint) {
    echo json_encode(['success' => false, 'message' => 'Endpoint is not allowed']);
    http_response_code(403);
    exit;
}

// Handle JSON input
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, "application/json") !== false) {
    $data = file_get_contents("php://input");
} else {
    $data = ($method === 'GET') ? $_GET : $_POST;
    // Remove endpoint from data if present
    if (is_array($data)) unset($data['endpoint']);
}

// Allowlist is enforced above; forward safely to API helper
$response = makeApiCall($endpoint, $data, $method);

header('Content-Type: application/json');
echo json_encode($response);
