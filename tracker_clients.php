<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php'; // For $_ENV['LARAVEL_API_URL']

// Ensure session is started for access to $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Function to make API calls (duplicate of makeApiCall in gmail_import_modal.php)
function makeTrackerClientsApiCall($endpoint, $queryParams = [], $method = 'GET') {
    $api_token = getTrackerApiToken();
    if (!$api_token) {
        error_log("API token not found for endpoint: " . $endpoint);
        return false;
    }

    $ch = curl_init();
    $url = $_ENV['LARAVEL_API_URL'] . $endpoint;
    if ($method === 'GET' && !empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_token,
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("cURL error for {$endpoint}: " . $curl_error);
        return false;
    }

    $decoded_response = json_decode($response, true);

    if ($http_code >= 400) {
        $message = $decoded_response['message'] ?? 'Unknown API error';
        error_log("Laravel API error for {$endpoint} (Status: {$http_code}): " . $message);
        return false;
    }
    return $decoded_response;
}

try {
    $api_response_data = makeTrackerClientsApiCall('/api/clients/full-list');

    if ($api_response_data && ($api_response_data['success'] ?? false)) {
        $clients = $api_response_data['data'];
        echo json_encode(['status' => 'success', 'data' => $clients]);
    } else {
        throw new Exception("Failed to fetch clients from API: " . ($api_response_data['message'] ?? 'Unknown error'));
    }

} catch (Exception $e) {
    error_log("Error in tracker_clients.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve clients: ' . $e->getMessage()]);
}
?>
