<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// SECURITY: Only allow websites.dublin@gmail.com to access backup actions (DB priority)
$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

function proxyApiDownload(string $apiUrl, string $apiToken): void
{
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiToken,
            'Accept: application/octet-stream'
        ],
    ]);

    $rawResponse = curl_exec($ch);
    if ($rawResponse === false) {
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => 'Download proxy failed.']);
        curl_close($ch);
        exit;
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
    curl_close($ch);

    $rawHeaders = substr($rawResponse, 0, $headerSize);
    $body = substr($rawResponse, $headerSize);

    if ($statusCode >= 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo $body ?: json_encode(['status' => 'error', 'message' => 'Download request failed.']);
        exit;
    }

    $filename = 'backup.zip';
    if (preg_match('/filename="?([^";]+)"?/i', $rawHeaders, $matches)) {
        $filename = $matches[1];
    }

    header_remove('Content-Type');
    header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
}

try {
    switch ($action) {
        case 'start':
            $response = makeApiCall('/api/backups/start', [], 'POST');
            echo json_encode($response);
            break;

        case 'progress':
            $response = makeApiCall('/api/backups/progress');
            echo json_encode($response);
            break;

        case 'list':
            $response = makeApiCall('/api/backups');
            if ($response && ($response['success'] ?? false)) {
                echo json_encode($response['data']);
            } else {
                echo json_encode([]);
            }
            break;

        case 'download':
            $folder = $_GET['folder'] ?? '';
            if (!$folder) {
                echo json_encode(['status' => 'error', 'message' => 'Missing folder']);
                exit;
            }

            $apiToken = getTrackerApiToken() ?? '';
            $apiUrl = $_ENV['LARAVEL_API_URL'] . "/api/backups/{$folder}/download";
            proxyApiDownload($apiUrl, $apiToken);
            break;

        case 'run_and_download':
            $apiToken = getTrackerApiToken() ?? '';
            $apiUrl = $_ENV['LARAVEL_API_URL'] . "/api/backups/run-and-download";
            proxyApiDownload($apiUrl, $apiToken);
            break;

        case 'delete':
            $folder = $_POST['folder'] ?? '';
            if (!$folder) {
                echo json_encode(['status' => 'error', 'message' => 'Missing folder name']);
                exit;
            }
            $response = makeApiCall("/api/backups/{$folder}", [], 'DELETE');
            echo json_encode($response);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
