<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// JSON response headers (placed here, after all requires, to avoid header-already-sent issues)
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $api_token = getTrackerApiToken();

    if (!$api_token) {
        throw new Exception("API token not found in session or cookie.");
    }

    $ch = curl_init();
    $apiUrl = $_ENV['LARAVEL_API_URL'] . '/api/emails/inbox'; // New API endpoint
    
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
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
        $message = $results['error'] ?? $results['message'] ?? 'Unknown API error';
        http_response_code($http_code);
        echo json_encode(['success' => false, 'error' => "Laravel API returned an error (Status: {$http_code}): " . $message]);
        exit();
    }

    echo json_encode($results, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log("AJAX Gmail Orders Error: " . $e->getMessage());
}
