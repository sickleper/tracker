<?php
/**
 * Google Sheets Service
 * Handles all Google Sheets operations for client trackers
 */

require_once __DIR__ . '/GoogleClientService.php';

class GoogleSheetsService {
    
    private $conn;
    
    public function __construct($conn = null) {
        $this->conn = $conn;
    }
    
    /**
     * Convert hex color to Google RGB format
     * 
     * @param string $hex Hex color code
     * @return array|null RGB array or null
     */
    private function hexToGoogleRGB($hex) {
        if (empty($hex) || $hex === 'transparent' || $hex === '#ffffff' || strtolower($hex) === 'white') return null;
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        if (strlen($hex) != 6) return null;
        return [
            'red' => hexdec(substr($hex, 0, 2)) / 255,
            'green' => hexdec(substr($hex, 2, 2)) / 255,
            'blue' => hexdec(substr($hex, 4, 2)) / 255
        ];
    }

    /**
     * Helper to make API calls to the Laravel API
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint relative to LARAVEL_API_URL
     * @param array $data Data to send (for POST/PUT)
     * @return array|false Decoded JSON response or false on error
     * @throws Exception if API token is missing or API call fails
     */
    private function makeApiCall(string $method, string $endpoint, array $data = []): array|false
    {
        $apiToken = function_exists('getTrackerApiToken') ? getTrackerApiToken() : ($_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null);
        if (!$apiToken) {
            error_log("API token missing for API call to {$endpoint}");
            return false;
        }

        $ch = curl_init();
        $url = $_ENV['LARAVEL_API_URL'] . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $apiToken,
            'Accept: application/json'
        ];

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
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
            error_log("cURL error on {$method} {$endpoint}: {$curl_error}");
            return false;
        }

        $decoded_response = json_decode($response, true);

        if ($http_code >= 400) {
            $message = $decoded_response['message'] ?? 'Unknown API error';
            error_log("Laravel API error on {$method} {$endpoint} (Status: {$http_code}): {$message}. Raw response: {$response}");
            return false;
        }

        return $decoded_response;
    }


    /**
     * Save task data to a local CSV file for fallback.
     * 
     * @param array $taskData The task data to save.
     * @param int $clientId The ID of the client.
     * @param bool $isUpdate Whether this is an update operation.
     * @param string|null $searchPo PO to search for when updating/deleting.
     * @return bool True on success, false on failure.
     */
    private function _saveSheetLocally($taskData, $clientId, $clientName, $isUpdate = false, $searchPo = null) {
        $fileName = preg_replace('/\s+/', '_', $clientName) . ".csv";
        $filePath = __DIR__ . "/../local_sheets/{$fileName}";
        $headers = ['id', 'poNumber', 'heading', 'propertyCode', 'property', 'location', 'invoiceSent', 'invoiceNo', 'assignedTo', 'priority', 'start_date', 'date_booked', 'next_visit', 'status', 'remarks', 'certSent', 'rowColor', 'tags'];
        $poNumber = $taskData['poNumber'] ?? '';

        try {
            // Read existing data if file exists
            $existingData = [];
            if (file_exists($filePath)) {
                if (($handle = fopen($filePath, 'r')) !== FALSE) {
                    $fileHeaders = fgetcsv($handle); // Read headers
                    while (($row = fgetcsv($handle)) !== FALSE) {
                        if (count($fileHeaders) == count($row)) { // Avoid malformed rows
                            $existingData[] = array_combine($fileHeaders, $row);
                        }
                    }
                    fclose($handle);
                }
            }

            // Prepare new/updated row
            $newRow = [];
            foreach ($headers as $header) {
                $newRow[$header] = $taskData[$header] ?? '';
            }

            $currentStatus = strtolower($taskData['status'] ?? '');
            $shouldRemove = in_array($currentStatus, ['closed', 'cancelled', 'deleted']);
            
            $foundAndUpdated = false;
            if ($isUpdate || $shouldRemove) {
                foreach ($existingData as $key => $row) {
                    if (isset($row['poNumber']) && $row['poNumber'] == ($searchPo ?: $poNumber)) {
                        if ($shouldRemove) {
                            unset($existingData[$key]); // Mark for deletion
                            error_log("Local Sync Info: Deleted row with PO $poNumber from local sheet $filePath.");
                        } else {
                            $existingData[$key] = $newRow; // Update existing row
                            error_log("Local Sync Info: Updated row with PO $poNumber in local sheet $filePath.");
                        }
                        $foundAndUpdated = true;
                        break;
                    }
                }
            }

            if (!$foundAndUpdated && !$shouldRemove) {
                $existingData[] = $newRow; // Add as new row if not found for update/delete
                error_log("Local Sync Info: Appended new row for PO $poNumber to local sheet $filePath.");
            }

            // Write all data back to the file
            if (($handle = fopen($filePath, 'w')) !== FALSE) {
                fputcsv($handle, $headers); // Write headers
                foreach ($existingData as $row) {
                    // Ensure row values are in the correct order as per headers
                    $orderedRow = [];
                    foreach ($headers as $header) {
                        $orderedRow[] = $row[$header] ?? '';
                    }
                    fputcsv($handle, $orderedRow);
                }
                fclose($handle);
            } else {
                error_log("Local Sync Error: Could not open local sheet file for writing: $filePath");
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("Local Sync Error for Client ID $clientId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update client sheet with task data
     * 
     * @param array $taskData Task data array
     * @param int $clientId Client user ID
     * @param bool $isUpdate Whether this is an update operation
     * @param string|null $searchPo PO to search for when updating
     * @param int $targetRowIndex Target row index for update (-1 to search)
     * @return bool Success status
     */
    public function updateClientSheet($taskData, $clientId, $isUpdate = false, $searchPo = null, $targetRowIndex = -1) {
        if (!$clientId) {
            error_log("Sheet Sync Info: Skipping sync, no clientId provided.");
            return false;
        }
        
        error_log("Sheet Sync Info: Starting sync for client $clientId (PO: " . ($taskData['poNumber'] ?? 'N/A') . ")");

        // 1. Get Folder ID from environment
        $folderId = $_ENV['CLIENT_TRACKER_FOLDER_ID'] ?? getenv('CLIENT_TRACKER_FOLDER_ID') ?? '0ABkYG16SaQZoUk9PVA';
        $folderId = trim($folderId);

        // --- START REFACTORING DATABASE CALLS TO API CALLS ---

        // Get Client Name
        $clientName = 'Unknown';
        $clientResponse = $this->makeApiCall('GET', "/api/clients/{$clientId}");
        if ($clientResponse && isset($clientResponse['client']['name'])) {
            $clientName = trim($clientResponse['client']['name']);
        }

        // Get Assigned User Name
        $assignedName = 'Unassigned';
        if (!empty($taskData['assignedTo'])) {
            $assignedUserResponse = $this->makeApiCall('GET', "/api/users/{$taskData['assignedTo']}");
            if ($assignedUserResponse && isset($assignedUserResponse['name'])) {
                $assignedName = trim($assignedUserResponse['name']);
            }
        }

        // Get Spreadsheet ID
        $spreadsheetId = null;
        $spreadsheetIdResponse = $this->makeApiCall('GET', "/api/clients/{$clientId}/spreadsheet-id");
        if ($spreadsheetIdResponse && isset($spreadsheetIdResponse['spreadsheet_id'])) {
            $spreadsheetId = trim($spreadsheetIdResponse['spreadsheet_id']);
        }

        // --- END REFACTORING DATABASE CALLS TO API CALLS ---

        // STRICT SANITY CHECK
        // If spreadsheetId is invalid (empty, '.', or too short), set to null to trigger search/creation logic
        if (empty($spreadsheetId) || $spreadsheetId === '.' || $spreadsheetId === $folderId || strlen($spreadsheetId) < 15) {
            $spreadsheetId = null;
        }

        try {
            $client = GoogleClientService::getClient([Google_Service_Drive::DRIVE, Google_Service_Sheets::SPREADSHEETS]);
            $driveService = new Google_Service_Drive($client);
            $sheetsService = new Google_Service_Sheets($client);

            // 2.5 Verify existing spreadsheet still exists and is not trashed (with batch caching)
            if ($spreadsheetId) {
                static $verifiedSheets = [];
                if (!isset($verifiedSheets[$spreadsheetId])) {
                    error_log("DEBUG: Attempting driveService->files->get for spreadsheetId: " . ($spreadsheetId ?? 'null'));
                    try {
                        $file = $driveService->files->get($spreadsheetId, [
                            'supportsAllDrives' => true,
                            'fields' => 'id, trashed'
                        ]);
                        if ($file->getTrashed()) {
                            $spreadsheetId = null;
                        } else {
                            $verifiedSheets[$spreadsheetId] = true;
                        }
                    } catch (Exception $e) {
                        $spreadsheetId = null;
                    }
                }
            }

            // 3. Resolve Spreadsheet ID
            if (!$spreadsheetId) {
                $escapedName = str_replace("'", "\\'", $clientName);
                $q = "name = '$escapedName Tracker' and mimeType = 'application/vnd.google-apps.spreadsheet' and trashed = false and '$folderId' in parents";
                
                $files = $driveService->files->listFiles([
                    'q' => $q, 
                    'supportsAllDrives' => true, 
                    'includeItemsFromAllDrives' => true,
                    'fields' => 'files(id, name, trashed)'
                ]);
                
                $found = $files->getFiles();
                if (count($found) > 0) {
                    foreach ($found as $file) {
                        if (!$file->getTrashed()) {
                            $spreadsheetId = $file->id;
                            break;
                        }
                    }
                }
                
                if (!$spreadsheetId) {
                    error_log("Sheet Sync Info: No spreadsheet found for client '$clientName' (ID: $clientId). Attempting to create one in folder '$folderId'.");
                    $fileMetadata = new Google_Service_Drive_DriveFile([
                        'name' => $clientName . ' Tracker',
                        'mimeType' => 'application/vnd.google-apps.spreadsheet',
                        'parents' => [$folderId]
                    ]);
                    $file = $driveService->files->create($fileMetadata, ['fields' => 'id', 'supportsAllDrives' => true]);
                    $spreadsheetId = $file->id;
                    error_log("Sheet Sync Info: Successfully created new sheet with ID '$spreadsheetId' for client ID $clientId.");

                    $headers = [['PO NUMBER', 'TASK/JOB', 'PROPERTY CODE', 'PROJECT/LOCATION/SITE DETAILS', 'INV STATUS', 'INV NO', 'ASSIGNED', 'PRIORITY', 'OPEN DATE', 'BOOKED', 'NEXT', 'STATUS', 'REMARKS', 'CERT']];
                    $body = new Google_Service_Sheets_ValueRange(['values' => $headers]);
                    $sheetsService->spreadsheets_values->update($spreadsheetId, 'Sheet1!A1', $body, ['valueInputOption' => 'USER_ENTERED']);
                }
                
                // Update or Insert spreadsheet_id via Laravel API
                $apiUpdateResponse = $this->makeApiCall('PUT', "/api/clients/{$clientId}/spreadsheet-id", ['spreadsheet_id' => $spreadsheetId]);
                if (!($apiUpdateResponse['success'] ?? false)) {
                    error_log("Sheet Sync Error: Failed to update client_details spreadsheet_id via API for client {$clientId}.");
                }
            }

            // 4. Prepare Styling & Data
            $rowColor = $this->hexToGoogleRGB($taskData['rowColor'] ?? '');
            $statusColors = [
                'completed' => '#16a34a', 
                'closed' => '#4b5563', 
                'pending' => '#ff0000', 
                'in progress' => '#2563eb', 
                'on hold' => '#ea580c', 
                'cancelled' => '#4e342e', 
                'open' => '#9333ea', 
                'incomplete' => '#9333ea'
            ];
            $priorityColors = [
                'urgent' => '#dc2626', 
                'high' => '#f97316', 
                'medium' => '#0d9488', 
                'low' => '#16a34a', 
                'emergency' => '#7f1d1d'
            ];

            $statusColor = $this->hexToGoogleRGB($statusColors[strtolower($taskData['status'] ?? '')] ?? '');
            $priorityColor = $this->hexToGoogleRGB($priorityColors[strtolower($taskData['priority'] ?? '')] ?? '');

            $values = [
                $taskData['poNumber'] ?? '', 
                $taskData['heading'] ?? '',
                $taskData['propertyCode'] ?? '',
                trim((($taskData['property'] ?? '') ? $taskData['property'] . " - " : "") . ($taskData['location'] ?? '')),
                $taskData['invoiceSent'] ?? '', 
                $taskData['invoiceNo'] ?? '', 
                $assignedName,
                $taskData['priority'] ?? '', 
                $taskData['start_date'] ?? '', 
                $taskData['date_booked'] ?? '',
                $taskData['next_visit'] ?? '', 
                $taskData['status'] ?? '', 
                $taskData['remarks'] ?? '',
                $taskData['certSent'] ?? ''
            ];

            // 5. Update, Append, or Delete
            if ($isUpdate && $targetRowIndex === -1) {
                $targetPo = trim($searchPo ?: ($taskData['poNumber'] ?? ''));
                if (!empty($targetPo)) {
                    // Static cache for PO -> Row Index to minimize API calls in batch imports
                    static $rowIndexCache = [];
                    $cacheKey = "$spreadsheetId:$targetPo";
                    
                    if (isset($rowIndexCache[$cacheKey])) {
                        $targetRowIndex = $rowIndexCache[$cacheKey];
                    } else {
                        $response = $sheetsService->spreadsheets_values->get($spreadsheetId, 'Sheet1!A:A');
                        $rows = $response->getValues();
                        if ($rows) {
                            foreach ($rows as $idx => $r) {
                                if (isset($r[0]) && trim($r[0]) === $targetPo) { 
                                    $targetRowIndex = $idx; 
                                    $rowIndexCache[$cacheKey] = $idx; // Save to cache
                                    break; 
                                }
                            }
                        }
                    }
                }
            }

            $currentStatus = strtolower($taskData['status'] ?? '');
            $shouldRemove = in_array($currentStatus, ['closed', 'cancelled', 'deleted']);

            if ($shouldRemove) {
                if ($targetRowIndex > -1) {
                    // Delete row logic
                    $batchUpdateResponse = $sheetsService->spreadsheets->batchUpdate($spreadsheetId, new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => [
                            'deleteDimension' => [
                                'range' => [
                                    'sheetId' => 0, // Assuming first sheet
                                    'dimension' => 'ROWS',
                                    'startIndex' => $targetRowIndex,
                                    'endIndex' => $targetRowIndex + 1
                                ]
                            ]
                        ]
                    ]));
                    error_log("Sheet Sync Info: Deleted row at index $targetRowIndex due to status $currentStatus");
                }
                return true; // Successfully handled (removed or wasn't there)
            }

            if ($targetRowIndex > -1) {
                $cells = [];
                foreach ($values as $colIdx => $val) {
                    $cell = ['userEnteredValue' => ['stringValue' => (string)$val]];
                    $bg = $rowColor;
                    $lowerVal = strtolower((string)$val);
                    if ($colIdx === 7 && $priorityColor) $bg = $priorityColor;
                    if ($colIdx === 11 && $statusColor) $bg = $statusColor;
                    if ($colIdx === 4 && ($lowerVal === 'yes' || $lowerVal === 'paid' || $lowerVal === 'drafted')) $bg = $this->hexToGoogleRGB('#16a34a');
                    if ($colIdx === 13 && $lowerVal === 'yes') $bg = $this->hexToGoogleRGB('#16a34a');
                    
                    if ($bg) {
                        $cell['userEnteredFormat'] = [
                            'backgroundColor' => $bg, 
                            'textFormat' => ['foregroundColor' => ['red'=>1,'green'=>1,'blue'=>1]],
                            'wrapStrategy' => ($colIdx === 1 ? 'WRAP' : 'OVERFLOW_CELL')
                        ];
                    } else {
                        $cell['userEnteredFormat'] = [
                            'backgroundColor' => ['red'=>1,'green'=>1,'blue'=>1], 
                            'textFormat' => ['foregroundColor' => ['red'=>0,'green'=>0,'blue'=>0]],
                            'wrapStrategy' => ($colIdx === 1 ? 'WRAP' : 'OVERFLOW_CELL')
                        ];
                    }
                    $cells[] = $cell;
                }

                $request = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                    'requests' => [['updateCells' => [
                        'rows' => [['values' => $cells]],
                        'fields' => 'userEnteredValue,userEnteredFormat.backgroundColor,userEnteredFormat.textFormat,userEnteredFormat.wrapStrategy',
                        'range' => ['sheetId' => 0, 'startRowIndex' => $targetRowIndex, 'endRowIndex' => $targetRowIndex + 1, 'startColumnIndex' => 0, 'endColumnIndex' => 14]
                    ]]]
                ]);
                $sheetsService->spreadsheets->batchUpdate($spreadsheetId, $request);
            } else {
                $body = new Google_Service_Sheets_ValueRange(['values' => [$values]]);
                $sheetsService->spreadsheets_values->append($spreadsheetId, 'Sheet1!A:O', $body, ['valueInputOption' => 'USER_ENTERED']);
            }
            
            return $spreadsheetId;
        } catch (Exception $e) {
            error_log("Sheet Sync Error for Client ID $clientId: " . $e->getMessage());
            $localSaveSuccess = $this->_saveSheetLocally($taskData, $clientId, $clientName, $isUpdate, $searchPo);
            if ($localSaveSuccess) {
                error_log("Sheet Sync Info: Data successfully saved locally as fallback for Client ID $clientId (PO: " . ($taskData['poNumber'] ?? 'N/A') . ").");
                return "LOCAL_SAVE_SUCCESS"; // Special return to indicate local save for syncClientToSheet
            } else {
                error_log("Sheet Sync Error: Local save also failed for Client ID $clientId (PO: " . ($taskData['poNumber'] ?? 'N/A') . ").");
                return false;
            }
        }
    }
    
    /**
     * Reset client sheet (clear all data except headers)
     * 
     * @param int $clientId Client user ID
     * @return bool Success status
     */
    public function resetClientSheet($clientId) {
        if (!$clientId) return false;
        
        // Fetch spreadsheet_id via API
        $spreadsheetId = null;
        $spreadsheetIdResponse = $this->makeApiCall('GET', "/api/clients/{$clientId}/spreadsheet-id");
        if ($spreadsheetIdResponse && isset($spreadsheetIdResponse['spreadsheet_id'])) {
            $spreadsheetId = trim($spreadsheetIdResponse['spreadsheet_id']);
        }

        if (!$spreadsheetId || strlen($spreadsheetId) < 15) return false;

        try {
            $client = GoogleClientService::getClient([Google_Service_Sheets::SPREADSHEETS]);
            $service = new Google_Service_Sheets($client);
            
            // 1. Clear Values below header row (A2:N)
            $range = 'Sheet1!A2:N1000'; // Define a generous range to clear
            $requestBody = new Google_Service_Sheets_ClearValuesRequest();
            $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

            // 2. Clear Formatting for the same range
            $requests = [
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 1, // Row 2 (0-based)
                            'endRowIndex' => 1000,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => 14
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1], // White
                                'textFormat' => ['foregroundColor' => ['red' => 0, 'green' => 0, 'blue' => 0], 'bold' => false]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
                    ]
                ])
            ];
            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => $requests]);
            $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            
            return true;
        } catch (Exception $e) {
            error_log("Sheet Reset Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sync all client tasks to their sheet
     * 
     * @param int $clientId Client user ID
     * @return array Result with success status and message
     */
    public function syncClientToSheet($clientId) {
        if (!$clientId) return ['success' => false, 'message' => 'No client ID provided'];

        // 1. Determine if this client is a subcontractor (DB priority)
        $subNamesString = $GLOBALS['tracker_sub_names'] ?? $_ENV['TRACKER_SUB_NAMES'] ?? '';
        $subNames = array_map('trim', explode(',', str_replace('"', '', $subNamesString)));
        
        // Fetch client details to get the name
        $clientRes = $this->makeApiCall('GET', "/api/users/{$clientId}");
        $clientName = $clientRes['user']['name'] ?? '';
        $isSubcontractor = in_array($clientName, $subNames);

        // --- START REFACTORING DATABASE CALLS TO API CALLS ---

        $tasks = [];
        $taskApiResponse = $this->makeApiCall('GET', "/api/clients/{$clientId}/tasks", [
            'include_subcontractor_tasks' => $isSubcontractor ? 1 : 0
        ]);

        if ($taskApiResponse && ($taskApiResponse['success'] ?? false) && isset($taskApiResponse['tasks'])) {
            $tasks = $taskApiResponse['tasks'];
        } else {
            return ['success' => false, 'message' => $taskApiResponse['message'] ?? 'Error fetching tasks from API.'];
        }
        
        if (empty($tasks)) return ['success' => false, 'message' => 'No records found in database for this client.'];

        // --- END REFACTORING DATABASE CALLS TO API CALLS ---

        // 2. Resolve Spreadsheet ID (ensures it exists and is updated in DB)
        // We use a dummy data call to updateClientSheet to handle creation/resolution logic
        $firstTask = $tasks[0];
        $spreadsheetId = $this->updateClientSheet([
            'poNumber' => $firstTask['po_number'],
            'heading' => $firstTask['heading'],
            'status' => $firstTask['status']
        ], $clientId, false);

        if ($spreadsheetId === "LOCAL_SAVE_SUCCESS") {
            return ['success' => true, 'message' => 'Google Sheet sync failed, but data saved locally as fallback.'];
        }

        if (!$spreadsheetId) return ['success' => false, 'message' => 'Could not resolve Google Sheet ID'];

        // 3. Clear existing data
        $this->resetClientSheet($clientId);

        try {
            $client = GoogleClientService::getClient([Google_Service_Sheets::SPREADSHEETS]);
            $service = new Google_Service_Sheets($client);

            $values = [];
            $requests = [];
            $statusColors = [
                'completed' => '#16a34a', 
                'closed' => '#4b5563', 
                'pending' => '#ff0000', 
                'in progress' => '#2563eb', 
                'on hold' => '#ea580c', 
                'cancelled' => '#4e342e', 
                'open' => '#9333ea', 
                'incomplete' => '#9333ea'
            ];
            $priorityColors = [
                'urgent' => '#dc2626', 
                'high' => '#f97316', 
                'medium' => '#0d9488', 
                'low' => '#16a34a', 
                'emergency' => '#7f1d1d'
            ];

            foreach ($tasks as $idx => $t) {
                $rowIndex = $idx + 1; // Row 2 starts at index 1
                $assignedName = $t['assignedName'] ?: 'Unassigned';
                
                $rowValues = [
                    $t['po_number'] ?? '', 
                    $t['heading'] ?? '', 
                    $t['property_code'] ?? '',
                    trim(($t['property'] ? $t['property'] . " - " : "") . ($t['location'] ?? '')),
                    $t['invoice_sent'] ?? '', 
                    $t['invoice_no'] ?? '', 
                    $assignedName,
                    $t['priority'] ?? '', 
                    $t['start_date'] ?? '', 
                    $t['date_booked'] ?? '',
                    $t['next_visit'] ?? '', 
                    $t['status'] ?? '', 
                    $t['remarks'] ?? '', 
                    $t['cert_sent'] ?? ''
                ];
                $values[] = $rowValues;

                // Prepare Styling
                $rowColor = $this->hexToGoogleRGB($t['row_color'] ?? '');
                $priorityColor = $this->hexToGoogleRGB($priorityColors[strtolower($t['priority'] ?? '')] ?? '');
                $statusColor = $this->hexToGoogleRGB($statusColors[strtolower($t['status'] ?? '')] ?? '');

                $cells = [];
                foreach ($rowValues as $colIdx => $val) {
                    $cell = ['userEnteredValue' => ['stringValue' => (string)$val]];
                    $bg = $rowColor;
                    $lowerVal = strtolower((string)$val);
                    if ($colIdx === 7 && $priorityColor) $bg = $priorityColor;
                    if ($colIdx === 11 && $statusColor) $bg = $statusColor;
                    if ($colIdx === 4 && ($lowerVal === 'yes' || $lowerVal === 'paid')) $bg = $this->hexToGoogleRGB('#16a34a');
                    if ($colIdx === 13 && $lowerVal === 'yes') $bg = $this->hexToGoogleRGB('#16a34a');

                    if ($bg) {
                        $cell['userEnteredFormat'] = [
                            'backgroundColor' => $bg, 
                            'textFormat' => ['foregroundColor' => ['red'=>1,'green'=>1,'blue'=>1]],
                            'wrapStrategy' => ($colIdx === 1 ? 'WRAP' : 'OVERFLOW_CELL')
                        ];
                    } else {
                        $cell['userEnteredFormat'] = [
                            'backgroundColor' => ['red'=>1,'green'=>1,'blue'=>1], 
                            'textFormat' => ['foregroundColor' => ['red'=>0,'green'=>0,'blue'=>0]],
                            'wrapStrategy' => ($colIdx === 1 ? 'WRAP' : 'OVERFLOW_CELL')
                        ];
                    }
                    $cells[] = $cell;
                }

                $requests[] = ['updateCells' => [
                    'rows' => [['values' => $cells]],
                    'fields' => 'userEnteredValue,userEnteredFormat.backgroundColor,userEnteredFormat.textFormat,userEnteredFormat.wrapStrategy',
                    'range' => ['sheetId' => 0, 'startRowIndex' => $rowIndex, 'endRowIndex' => $rowIndex + 1, 'startColumnIndex' => 0, 'endColumnIndex' => 14]
                ]];
            }

            // Batch Update Values (A2 onwards)
            $body = new Google_Service_Sheets_ValueRange(['values' => $values]);
            $service->spreadsheets_values->update($spreadsheetId, 'Sheet1!A2', $body, ['valueInputOption' => 'USER_ENTERED']);

            // Batch Update Styles
            $batchRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest(['requests' => $requests]);
            $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);

            return ['success' => true, 'message' => "Synced " . count($tasks) . " records to Google Sheet."];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Sync Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Cleanup invalid spreadsheet IDs from database
     */
    public function cleanupInvalidSpreadsheetIds() {
        // This logic is now handled by the Laravel API endpoint
        $apiResponse = $this->makeApiCall('PUT', '/api/clients/cleanup-spreadsheet-ids');

        if (!($apiResponse['success'] ?? false)) {
            error_log("Sheet Sync Error: Failed to cleanup invalid spreadsheet IDs via API: " . ($apiResponse['message'] ?? 'Unknown API error'));
            return false;
        }
        return true;
    }
}
