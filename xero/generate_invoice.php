<?php
// public/jobs/xero/generate_invoice.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; // To load $_ENV['LARAVEL_API_URL']

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = (int)$_POST['id'];
$preview = (int)($_POST['preview'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

try {
    $api_token = getTrackerApiToken();

    $ch = curl_init();
    $apiUrl = $_ENV['LARAVEL_API_URL'] . '/api/xero/invoice/generate';
    
    $postData = [
        'id' => $id,
        'preview' => (bool)$preview,
    ];

    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $api_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($api_response === false) {
        throw new Exception("cURL error when calling Laravel API: " . $curl_error);
    }

    $results = json_decode($api_response, true);

    if ($http_code >= 400 || ($results['success'] ?? false) === false) {
        $message = $results['message'] ?? 'Unknown API error';
        http_response_code($http_code);
        echo json_encode(['success' => false, 'message' => "Laravel API returned an error (Status: {$http_code}): " . $message]);
        exit();
    }

    echo json_encode($results);

} catch (Exception $e) {
    error_log("Generate Invoice Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
