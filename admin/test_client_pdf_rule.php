<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$clientId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($clientId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit();
}

if (empty($_FILES['pdf']['tmp_name']) || !is_uploaded_file($_FILES['pdf']['tmp_name'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'PDF file is required']);
    exit();
}

$apiToken = getTrackerApiToken();
if (!$apiToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'API token not found']);
    exit();
}

$ch = curl_init();
$url = rtrim($_ENV['LARAVEL_API_URL'], '/') . "/api/clients/{$clientId}/test-pdf-rule";
$file = new CURLFile($_FILES['pdf']['tmp_name'], $_FILES['pdf']['type'] ?: 'application/pdf', $_FILES['pdf']['name']);

$postFields = [
    'pdf' => $file,
    'pdf_profile' => $_POST['pdf_profile'] ?? '',
    'pdf_start_marker' => $_POST['pdf_start_marker'] ?? '',
    'pdf_end_marker' => $_POST['pdf_end_marker'] ?? '',
    'po' => $_POST['po'] ?? '',
];

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiToken,
        'Accept: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'cURL error: ' . $curlError]);
    exit();
}

$decoded = json_decode($response, true);
http_response_code($httpCode ?: 200);
echo json_encode($decoded ?: ['success' => false, 'message' => 'Invalid API response']);
