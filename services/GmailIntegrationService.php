<?php
/**
 * Gmail Integration Service
 * Handles downloading PDF attachments from Gmail and uploading to Drive
 */

// require_once __DIR__ . '/GoogleDriveService.php'; // No longer needed

class GmailIntegrationService {

    public function __construct() {
    }

    /**
     * Helper to make API calls to the Laravel API
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint relative to LARAVEL_API_URL
     * @param array $data Data to send (for POST/PUT)
     * @param array $files Files array from $_FILES
     * @return array|false Decoded JSON response or false on error
     * @throws Exception if API token is missing or API call fails
     */
    private function makeApiCall(string $method, string $endpoint, array $data = [], array $files = []): array|false
    {
        $apiToken = function_exists('getTrackerApiToken') ? getTrackerApiToken() : ($_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null);
        if (!$apiToken) {
            throw new Exception("API token missing for API call to {$endpoint}");
        }

        $ch = curl_init();
        $url = $_ENV['LARAVEL_API_URL'] . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $apiToken,
            'Accept: application/json'
        ];

        if (!empty($files)) {
            $post = [];
            foreach ($data as $key => $value) {
                $post[$key] = $value;
            }
            foreach ($files as $name => $fileArray) {
                if (is_array($fileArray['tmp_name'])) { // Handle multiple files for the same input name
                    foreach ($fileArray['tmp_name'] as $key => $tmpName) {
                        if ($fileArray['error'][$key] == UPLOAD_ERR_OK) {
                            $post[$name . '[' . $key . ']'] = new CURLFile($tmpName, $fileArray['type'][$key], $fileArray['name'][$key]);
                        }
                    }
                } else { // Handle single file
                    if ($fileArray['error'] == UPLOAD_ERR_OK) {
                        $post[$name] = new CURLFile($fileArray['tmp_name'], $fileArray['type'], $fileArray['name']);
                    }
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            // Content-Type header is automatically set to multipart/form-data when using CURLFile
            // and CURLOPT_POSTFIELDS with an array. Do not set it manually.
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL error on {$method} {$endpoint}: {$curl_error}");
        }

        $decoded_response = json_decode($response, true);

        if ($http_code >= 400) {
            $message = $decoded_response['message'] ?? 'Unknown API error';
            throw new Exception("Laravel API error on {$method} {$endpoint} (Status: {$http_code}): {$message}");
        }

        return $decoded_response;
    }

    /**
     * Download and attach Gmail PDFs to a PO
     *
     * @param string $poNumber  PO number
     * @param string $uid       Gmail message UID
     * @param array  $pdfNames  Array of PDF filenames to download
     * @return int  Number of PDFs uploaded
     */
    public function attachGmailPdfs($poNumber, $uid, $pdfNames) {
        if (empty($pdfNames) || empty($uid)) return 0;

        try {
            // Fetch Retro-Fit (ID 1) category details for IMAP config via API
            $catResponse = $this->makeApiCall('GET', '/api/leads/categories/1');
            if (!$catResponse || !($catResponse['success'] ?? false)) {
                error_log("Gmail PDF Attach Error: Could not fetch category config via API.");
                return 0;
            }
            $config = $catResponse['data'];

            $cm     = new Webklex\PHPIMAP\ClientManager();
            $client = $cm->make([
                'host'          => $config['imap_host'],
                'port'          => $config['imap_port'],
                'encryption'    => $config['imap_encryption'],
                'validate_cert' => false,
                'username'      => $config['email'],
                'password'      => $config['smtp_password'], // Using smtp_password as per API response
                'protocol'      => 'imap',
                'options'       => ['fetch_body' => true]
            ]);

            $client->connect();
            $folder = $client->getFolder('INBOX');

            try {
                $message = $folder->query()->getMessageByUid($uid);
            } catch (\Webklex\PHPIMAP\Exceptions\MessageNotFoundException $e) {
                error_log("Gmail PDF Attach Error: Message UID $uid not found. " . $e->getMessage());
                return 0;
            }

            if (!$message) return 0;

            $attachments = $message->getAttachments();
            $count       = 0;

            foreach ($attachments as $at) {
                if (!in_array($at->getName(), $pdfNames)) continue;

                // Checking for existence is now handled by the API upload logic (upsert)
                // so we proceed with the download and upload.

                $tmpPath = tempnam(sys_get_temp_dir(), 'gmail_');
                file_put_contents($tmpPath, $at->getContent());

                // Prepare files array in $_FILES format for makeApiCall
                $filesToUpload = [
                    'attachments' => [
                        'tmp_name' => [$tmpPath],
                        'name'     => [$at->getName()],
                        'type'     => ['application/pdf'],
                        'error'    => [UPLOAD_ERR_OK]
                    ]
                ];

                try {
                    // Call the Laravel API endpoint for upload
                    $apiResponse = $this->makeApiCall('POST', '/api/attachments/upload', ['po_number' => $poNumber], $filesToUpload);
                    
                    if (($apiResponse['success'] ?? false)) {
                        $count += $apiResponse['uploaded_count'] ?? 0;
                    }
                } catch (Exception $e) {
                    error_log("Gmail PDF Attach Error: API upload failed for PO $poNumber: " . $e->getMessage());
                }

                // Always clean up the temp file
                if (file_exists($tmpPath)) unlink($tmpPath);
            }

            return $count;

        } catch (Exception $e) {
            error_log("Gmail PDF Attach Error: " . $e->getMessage());
            return 0;
        } finally {
            if (isset($client)) {
                try { $client->disconnect(); } catch (Exception $e) {}
            }
        }
    }
}
