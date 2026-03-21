<?php
/**
 * Tracker Request Handler
 * Handles all HTTP requests for the tracker system
 */

require_once __DIR__ . '/../config/tracker_config.php';
require_once __DIR__ . '/../services/GoogleDriveService.php';
require_once __DIR__ . '/../services/GoogleSheetsService.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/GmailIntegrationService.php';

class TrackerRequestHandler {
    
    private $conn;
    private $driveService;
    private $sheetsService;
    private $notificationService;
    private $gmailService;

    /**
     * Helper to make API calls to the Laravel API
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint relative to LARAVEL_API_URL
     * @param array $data Data to send (for POST/PUT)
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

        // Handle 204 No Content first, which often has an empty response body
        if ($http_code === 204) {
            return ['success' => true, 'message' => 'No content'];
        }

        // If a response body exists but it's not valid JSON, and we expected JSON (not 204)
        if ($decoded_response === null && !empty($response)) {
            error_log("Laravel API returned non-JSON response for {$method} {$endpoint}. Raw response: {$response}");
            throw new Exception("Laravel API returned invalid JSON response.");
        }
        
        // Handle HTTP errors (4xx, 5xx)
        if ($http_code >= 400) {
            $message = $decoded_response['message'] ?? 'Unknown API error';
            error_log("Laravel API error on {$method} {$endpoint} (Status: {$http_code}): {$message}. Raw response: {$response}");
            throw new Exception("Laravel API error on {$method} {$endpoint} (Status: {$http_code}): {$message}");
        }

        // If response is empty (e.g., 200 OK with no content), treat as success but empty array
        if ($decoded_response === null && empty($response)) {
            return [];
        }

        return $decoded_response;
    }
    
    public function __construct() {
        // $this->conn = $conn; // Removed
        $this->driveService = new GoogleDriveService();
        $this->sheetsService = new GoogleSheetsService();
        $this->notificationService = new NotificationService();
        $this->gmailService = new GmailIntegrationService();
    }
    
    /**
     * Main request handler - routes to appropriate method
     */
    public function handle($action) {
        switch ($action) {
            case 'send_whatsapp':
                return $this->handleSendWhatsApp();
                
            case 'create':
                return $this->handleCreate();
                
            case 'save_edit':
                return $this->handleSaveEdit();
                
            case 'worker_upload':
                return $this->handleWorkerUpload();
                
            case 'worker_complete':
                return $this->handleWorkerComplete();
                
            case 'update':
                return $this->handleUpdate();
                
            case 'delete':
                return $this->handleDelete();
                
            case 'get_history':
                return $this->handleGetHistory();
                
            case 'get_attachments':
                return $this->handleGetAttachments();
                
            case 'delete_attachment':
                return $this->handleDeleteAttachment();
                
            case 'export':
                return $this->handleExport();
                
            case 'full_sync':
                return $this->handleFullSync();
                
            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
                break;
        }
    }

    private function logHistoryApiCall($taskId, $fieldName, $oldValue, $newValue)
    {
        // Explicitly cast to string to satisfy Laravel API validation
        $oldValue = (string)$oldValue;
        $newValue = (string)$newValue;

        if ($oldValue == $newValue) return;

        $userId = $_SESSION['user_id'] ?? null; // Get user ID from session

        $logData = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ];

        try {
            $this->makeApiCall('POST', '/api/tasks/history', $logData);
        } catch (Exception $e) {
            error_log("Failed to log history via API: " . $e->getMessage());
        }
    }
    
    /**
     * Handle WhatsApp notification sending
     */
    private function handleSendWhatsApp() {
        header('Content-Type: application/json');
        
        try {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception("Invalid ID provided.");
            }
            $result = $this->notificationService->sendWhatsAppNotification($id);
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    /**
     * Handle task creation
     */
    private function handleCreate() {
        // TEMPORARY: Email Only Mode for Aspen
        if (isset($_POST['email_only']) && $_POST['email_only'] == '1') {
            $this->notificationService->sendAspenEmailNotification(0, $_POST);
            echo "<script>
                window.parent.postMessage({ type: 'order_saved', message: 'The Aspen email has been sent successfully.', clientId: '214' }, '*');
                if (window.top === window.parent) {
                    window.top.location.href = 'index.php?client_filter=214&msg=email_sent';
                }
            </script>";
            exit;
        }

        $po = trim($_POST['poNumber'] ?? '');
        if ($po === '') $po = '000000';

        $taskData = [
            'po_number' => $po,
            'heading' => $_POST['task'] ?? null,
            'contact' => nullify($_POST['contact']),
            'property' => nullify($_POST['property']),
            'property_code' => nullify($_POST['propertyCode']),
            'eircode' => nullify($_POST['eircode']),
            'location' => nullify($_POST['location']),
            'lat_lng' => nullify($_POST['lat_lng']),
            'assigned_to' => nullify($_POST['assignedTo']),
            'priority' => strtolower($_POST['priority'] ?? 'medium'),
            'status' => strtolower($_POST['status'] ?? 'incomplete'),
            'start_date' => nullify($_POST['openingDate']),
            'date_booked' => nullify($_POST['dateBooked'] ?? ''),
            'next_visit' => nullify($_POST['nextVisit'] ?? ''),
            'due_date' => nullify($_POST['closingDate'] ?? ''), // Assuming closingDate maps to due_date
            'invoice_sent' => $_POST['invoiceSent'] ?? 'No',
            'invoice_no' => nullify($_POST['invoiceNo'] ?? ''),
            'cert_sent' => $_POST['certSent'] ?? 'No',
            'whatsapp_sent' => 'No', // Default from old query
            'remarks' => nullify($_POST['remarks'] ?? ''),
            'invoice_contact' => nullify($_POST['invoiceContact'] ?? ''),
            'invoice_address' => nullify($_POST['invoiceAddress'] ?? ''),
            'invoice_email' => nullify($_POST['invoiceEmail'] ?? ''),
            'client_id' => !empty($_POST['clientId']) ? (int)$_POST['clientId'] : null,
            'tags' => nullify($_POST['tags'] ?? ''),
            'column_priority' => 0, // Default from old query
            'estimate_hours' => 0, // Default from old query
            'estimate_minutes' => 0, // Default from old query
        ];

        try {
            $apiResponse = $this->makeApiCall('POST', '/api/tasks', $taskData);
            $newId = $apiResponse['id'] ?? null; // Laravel API returns the created task with ID

            if ($newId) {
                // The rest of the logic remains largely the same for Google Sheet/Drive integration
                // as it uses the local $conn object (for now) and task data

                $syncSuccess = false;
                $clientId = $taskData['client_id'];
                $tags = $taskData['tags'];

                if ($clientId) {
                    $syncSuccess = $this->sheetsService->updateClientSheet([
                        'id' => $newId,
                        'poNumber' => $taskData['po_number'],
                        'heading' => $taskData['heading'],
                        'contact' => $taskData['contact'],
                        'propertyCode' => $taskData['property_code'],
                        'property' => $taskData['property'],
                        'location' => $taskData['location'],
                        'invoiceSent' => $taskData['invoice_sent'],
                        'invoiceNo' => $taskData['invoice_no'],
                        'assignedTo' => $taskData['assigned_to'],
                        'priority' => $taskData['priority'],
                        'start_date' => $taskData['start_date'],
                        'date_booked' => $taskData['date_booked'],
                        'next_visit' => $taskData['next_visit'],
                        'status' => $taskData['status'],
                        'remarks' => $taskData['remarks'],
                        'certSent' => $taskData['cert_sent'],
                        'rowColor' => '#ffffff', // Assuming default row color for new tasks
                        'tags' => $taskData['tags']
                    ], $clientId, false, null, -1);

                    // ALSO Sync to Tagged Subcontractor Sheet
                    if (!empty($tags)) {
                        $this->sheetsService->updateClientSheet([
                            'id' => $newId, 'poNumber' => $taskData['po_number'], 'heading' => $taskData['heading'], 'contact' => $taskData['contact'], 'propertyCode' => $taskData['property_code'], 'property' => $taskData['property'], 'location' => $taskData['location'], 'invoiceSent' => $taskData['invoice_sent'], 'invoiceNo' => $taskData['invoice_no'], 'assignedTo' => $taskData['assigned_to'], 'priority' => $taskData['priority'], 'start_date' => $taskData['start_date'], 'date_booked' => $taskData['date_booked'], 'next_visit' => $taskData['next_visit'], 'status' => $taskData['status'], 'remarks' => $taskData['remarks'], 'certSent' => $taskData['cert_sent'], 'rowColor' => '#ffffff', 'tags' => $taskData['tags']
                        ], $tags, false, null, -1);
                    }
                }

                if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                    // Upload attachments via API
                    $uploadApiResponse = $this->makeApiCall('POST', '/api/attachments/upload', ['po_number' => $taskData['po_number']], ['attachments' => $_FILES['attachments']]);
                    if (!($uploadApiResponse['success'] ?? false)) {
                        error_log("Failed to upload attachments for new task via API: " . ($uploadApiResponse['message'] ?? 'Unknown error'));
                    }
                }

                // Gmail Integration: Automatic PDF carry-over
                if (!empty($_POST['gmail_uid']) && !empty($_POST['gmail_pdfs'])) {
                    // This uses po_number, available in $taskData
                    $gmailPdfs = json_decode($_POST['gmail_pdfs'], true);
                    $this->gmailService->attachGmailPdfs($taskData['po_number'], $_POST['gmail_uid'], $gmailPdfs);
                }
                
                $_SESSION['tracker_msg'] = 'created';
                $_SESSION['sheet_sync'] = $syncSuccess ?? false;
                
                // Modal Communication: Notify parent and redirect
                $msg = ($_SESSION['tracker_msg'] ?? 'created') === 'created' ? 'The new order has been created successfully.' : 'The order has been updated successfully.';
                echo "<script>
                    window.parent.postMessage({ type: 'order_saved', message: '$msg', clientId: '" . ($clientId ?? '') . "' }, '*');
                    // Only redirect the top window if it is the direct parent (i.e., not a nested modal like Gmail Import)
                    if (window.top === window.parent) {
                        window.top.location.href = 'index.php" . ($clientId ? "?client_filter=$clientId" : "") . "';
                    }
                </script>";
                exit;
            } else {
                throw new Exception("Laravel API did not return a new task ID.");
            }
        } catch (Exception $e) {
            error_log("Error creating task via API: " . $e->getMessage());
            throw new Exception("Failed to create task: " . $e->getMessage());
        }
    }
    
    /**
     * Handle task editing/updating
     */
    private function handleSaveEdit() {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            throw new Exception("Invalid ID provided.");
        }
        
        // Fetch old data for history logging and sheet sync cleanup
        try {
            $oldTaskApi = $this->makeApiCall('GET', '/api/tasks/' . $id);
            $old = $oldTaskApi; // Use API response for 'old' data
        } catch (Exception $e) {
            throw new Exception("Task not found via API for ID: " . $id . " - " . $e->getMessage());
        }
        
        if (!$old) throw new Exception("Task not found");

        $po = trim($_POST['poNumber'] ?? '');
        if ($po === '') $po = '000000';

        $taskData = [
            'po_number' => $po,
            'heading' => $_POST['task'] ?? null,
            'contact' => nullify($_POST['contact'] ?? null),
            'property' => nullify($_POST['property'] ?? null),
            'property_code' => nullify($_POST['propertyCode'] ?? null),
            'eircode' => nullify($_POST['eircode'] ?? null),
            'location' => nullify($_POST['location'] ?? null),
            'lat_lng' => nullify($_POST['lat_lng'] ?? null),
            'assigned_to' => nullify($_POST['assignedTo'] ?? null),
            'priority' => strtolower($_POST['priority'] ?? 'medium'),
            'status' => strtolower($_POST['status'] ?? 'incomplete'),
            'start_date' => nullify($_POST['openingDate'] ?? null),
            'date_booked' => nullify($_POST['dateBooked'] ?? null),
            'next_visit' => nullify($_POST['nextVisit'] ?? null),
            'due_date' => nullify($_POST['closingDate'] ?? null),
            'invoice_sent' => $_POST['invoiceSent'] ?? 'No',
            'invoice_no' => nullify($_POST['invoiceNo'] ?? null),
            'cert_sent' => $_POST['certSent'] ?? 'No',
            'remarks' => nullify($_POST['remarks'] ?? null),
            'invoice_contact' => nullify($_POST['invoiceContact'] ?? null),
            'invoice_address' => nullify($_POST['invoiceAddress'] ?? null),
            'invoice_email' => nullify($_POST['invoiceEmail'] ?? null),
            'client_id' => !empty($_POST['clientId']) ? (int)$_POST['clientId'] : null,
            'row_color' => $_POST['rowColor'] ?? ($old['row_color'] ?? '#ffffff'), // Use old from API, or default
            'tags' => nullify($_POST['tags'] ?? null),
        ];

        try {
            $apiResponse = $this->makeApiCall('PUT', '/api/tasks/' . $id, $taskData);

            // Log history (still uses local DB and requires 'old' values)
            $this->logHistoryApiCall($id, 'PO Number', $old['po_number'], $po);
            $this->logHistoryApiCall($id, 'Status', $old['status'], $taskData['status']);
            $this->logHistoryApiCall($id, 'Assigned To', $old['assigned_to'], $taskData['assigned_to']);
            $this->logHistoryApiCall($id, 'Client ID', $old['client_id'], $taskData['client_id']);
            $this->logHistoryApiCall($id, 'Tags', $old['tags'], $taskData['tags']);
            
            // The rest of the logic remains largely the same for Google Sheet/Drive integration
            // as it uses the local $conn object (for now) and task data
            $syncSuccess = false;
            $clientId = $taskData['client_id'];
            $tags = $taskData['tags'];

            // Original code used $old for syncData. This needs to be adjusted.
            // If the API returns the updated task, use that. Otherwise, rely on $taskData.
            $syncData = [
                'id' => $id, 'poNumber' => $taskData['po_number'], 'heading' => $taskData['heading'], 'contact' => $taskData['contact'], 'propertyCode' => $taskData['property_code'], 'property' => $taskData['property'], 'location' => $taskData['location'], 'invoiceSent' => $taskData['invoice_sent'], 'invoiceNo' => $taskData['invoice_no'], 'assignedTo' => $taskData['assigned_to'], 'priority' => $taskData['priority'], 'start_date' => $taskData['start_date'], 'date_booked' => $taskData['date_booked'], 'next_visit' => $taskData['next_visit'], 'status' => $taskData['status'], 'remarks' => $taskData['remarks'], 'certSent' => $taskData['cert_sent'], 'rowColor' => $taskData['row_color'], 'tags' => $taskData['tags']
            ];
            
            // 1. Sync to current associations
            if ($clientId) {
                $syncSuccess = $this->sheetsService->updateClientSheet($syncData, $clientId, true, $old['po_number'], -1);
            }
            if (!empty($tags)) {
                $this->sheetsService->updateClientSheet($syncData, $tags, true, $old['po_number'], -1);
            }

            // 2. CLEANUP: Identify who needs to be removed
            $previousIds = array_filter(array_unique([$old['client_id'], $old['tags']]));
            $currentIds  = array_filter(array_unique([$clientId, $tags]));
            
            foreach ($previousIds as $oldId) {
                if (!in_array($oldId, $currentIds)) {
                    $this->sheetsService->updateClientSheet(['poNumber' => $po, 'status' => 'deleted'], $oldId, true, $old['po_number'], -1);
                }
            }

            // Send Notification ONLY if status became 'completed'
            if (strtolower($taskData['status']) === 'completed' && strtolower($old['status']) !== 'completed') {
                $this->notificationService->sendCompletionNotification($id);
            }

            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                // Upload attachments via API
                $uploadApiResponse = $this->makeApiCall('POST', '/api/attachments/upload', ['po_number' => $po], ['attachments' => $_FILES['attachments']]);
                if (!($uploadApiResponse['success'] ?? false)) {
                    error_log("Failed to upload attachments for updated task via API: " . ($uploadApiResponse['message'] ?? 'Unknown error'));
                }
            }

            // Gmail Integration: Automatic PDF carry-over
            if (!empty($_POST['gmail_uid']) && !empty($_POST['gmail_pdfs'])) {
                $gmailPdfs = json_decode($_POST['gmail_pdfs'], true);
                $this->gmailService->attachGmailPdfs($po, $_POST['gmail_uid'], $gmailPdfs);
            }
            
            $_SESSION['tracker_msg'] = 'updated';
            $_SESSION['sheet_sync'] = $syncSuccess ?? false;

            // Modal Communication: Notify parent and redirect
            $msg = ($_SESSION['tracker_msg'] ?? 'updated') === 'updated' ? 'The order has been updated successfully.' : 'The new order has been created successfully.';
            echo "<script>
                window.parent.postMessage({ type: 'order_saved', message: '$msg', clientId: '" . ($clientId ?? '') . "' }, '*');
                // Only redirect the top window if it is the direct parent (i.e., not a nested modal like Gmail Import)
                if (window.top === window.parent) {
                    window.top.location.href = 'index.php" . ($clientId ? "?client_filter=$clientId" : "") . "';
                }
            </script>";
            exit;

        } catch (Exception $e) {
            error_log("Error updating task via API: " . $e->getMessage());
            throw new Exception("Failed to update task: " . $e->getMessage());
        }
    }
    
    /**
     * Handle worker photo upload
     */
    private function handleWorkerUpload() {
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            throw new Exception("Invalid ID provided.");
        }
        
        // Fetch PO number from API
        try {
            $taskApi = $this->makeApiCall('GET', '/api/tasks/' . $id);
            $poNumber = $taskApi['po_number'] ?? null;
        } catch (Exception $e) {
            throw new Exception("Task not found via API for ID: " . $id . " - " . $e->getMessage());
        }

        if (!$poNumber) throw new Exception("PO Number not found for task ID: " . $id);

        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            try {
                $uploadApiResponse = $this->makeApiCall('POST', '/api/attachments/upload', ['po_number' => $poNumber], ['attachments' => $_FILES['images']]);
                if (($uploadApiResponse['success'] ?? false)) {
                    $count = $uploadApiResponse['uploaded_count'] ?? 0;
                    $this->logHistoryApiCall($id, 'Photos Uploaded', null, "Worker uploaded $count photos to Drive");
                    echo json_encode(['success' => true, 'message' => "$count photos uploaded to Google Drive!"]);
                } else {
                    throw new Exception($uploadApiResponse['message'] ?? 'Unknown API upload error');
                }
            } catch (Exception $e) {
                error_log("Worker upload API error: " . $e->getMessage());
                throw new Exception("Photo upload failed: " . $e->getMessage());
            }
        } else {
            throw new Exception("No images received");
        }
    }
    
    /**
     * Handle worker marking job as complete
     */
    private function handleWorkerComplete() {
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            throw new Exception("Invalid ID provided.");
        }
        $notes = trim($_POST['notes'] ?? '');
        
        // Fetch task details from API
        try {
            $job = $this->makeApiCall('GET', '/api/tasks/' . $id);
        } catch (Exception $e) {
            throw new Exception("Task not found via API for ID: " . $id . " - " . $e->getMessage());
        }
        
        if (!$job) throw new Exception("Job not found");

        $newRemarks = $job['remarks'] ?? '';
        if (!empty($notes)) {
            $timestamp = date('d/m H:i');
            $newRemarks .= "\n--- Worker Note ($timestamp) ---\n" . $notes;
        }

        $driveStatus = "";
        // Use API for image uploads instead of direct drive service to maintain consistency
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            try {
                $uploadApiResponse = $this->makeApiCall('POST', '/api/attachments/upload', ['po_number' => $job['po_number']], ['attachments' => $_FILES['images']]);
                if (($uploadApiResponse['success'] ?? false)) {
                    $count = $uploadApiResponse['uploaded_count'] ?? 0;
                    $driveStatus = " ($count photos uploaded)";
                } else {
                    $driveStatus = " (Warning: Photo upload failed)";
                }
            } catch (Exception $e) {
                $driveStatus = " (Warning: Photo upload failed)";
                error_log("Worker complete - upload error: " . $e->getMessage());
            }
        }

        // Update task status and remarks via API
        $updateData = [
            'status' => 'completed',
            'remarks' => $newRemarks
        ];
        
        try {
            $this->makeApiCall('PATCH', '/api/tasks/' . $id, $updateData);
            
            $this->logHistoryApiCall($id, 'Status', $job['status'], 'completed');
            $this->logHistoryApiCall($id, 'Worker Notes', 'None', ($notes ?: 'Marked complete') . $driveStatus);
            
            // Send Notification
            $this->notificationService->sendCompletionNotification($id);
            
            echo json_encode(['success' => true, 'message' => 'Job marked as completed!' . $driveStatus]);
        } catch (Exception $e) {
            error_log("Error updating task status via API: " . $e->getMessage());
            throw new Exception("Database update failed: " . $e->getMessage());
        }
    }
    
    private function handleUpdate() {
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            throw new Exception("Invalid ID provided.");
        }
        $field = $_POST['field'] ?? '';
        $value = $_POST['value'] ?? '';
        
        // Strict whitelist for allowed fields
        $mapping = [
            'poNumber'=>'po_number','task'=>'heading','contact'=>'contact','property'=>'property',
            'propertyCode'=>'property_code','eircode'=>'eircode','location'=>'location','lat_lng'=>'lat_lng',
            'assignedTo'=>'assigned_to','priority'=>'priority','status'=>'status','openingDate'=>'start_date',
            'dateBooked'=>'date_booked','nextVisit'=>'next_visit','closingDate'=>'due_date',
            'invoiceSent'=>'invoice_sent','invoiceNo'=>'invoice_no','certSent'=>'cert_sent',
            'whatsappSent'=>'whatsapp_sent','remarks'=>'remarks', 'rowColor' => 'row_color',
            'invoiceContact'=>'invoice_contact', 'invoiceAddress'=>'invoice_address', 'invoiceEmail'=>'invoice_email',
            'clientId'=>'client_id', 'tags'=>'tags'
        ];
        
        if (!isset($mapping[$field])) {
            throw new Exception("Invalid field name");
        }
        
        $dbField = $mapping[$field];
        
        // Fetch old value from API for history logging
        try {
            $oldTaskApi = $this->makeApiCall('GET', '/api/tasks/' . $id);
            $oldVal = $oldTaskApi[$dbField] ?? null;
        } catch (Exception $e) {
            throw new Exception("Task not found via API for ID: " . $id . " - " . $e->getMessage());
        }
        
        $val = nullify($value);
        if ($dbField === 'po_number' && ($val === null || $val === '')) {
            $val = '000000';
        }

        // Apply any specific formatting required by the API or DB
        if ($dbField === 'priority') { $val = strtolower($val); }
        if ($dbField === 'status') { $val = strtolower($val); } // Use $val here, not $value

        $updateData = [$dbField => $val];

        try {
            $apiResponse = $this->makeApiCall('PATCH', '/api/tasks/' . $id, $updateData);
            
            $this->logHistoryApiCall($id, $field, $oldVal, $val);

            // Send Notification ONLY if status field was updated to 'completed'
            if ($dbField === 'status' && strtolower($val) === 'completed' && strtolower($oldVal) !== 'completed') {
                $this->notificationService->sendCompletionNotification($id);
            }

            // Sync to Client Sheets (Fetch full task data from API after update)
            // Note: The API response from PATCH might not contain all fields for fullTask,
            // so making another GET is safer to ensure complete data for sheets sync.
            $fullTask = $this->makeApiCall('GET', '/api/tasks/' . $id);
            
            $syncSuccess = false;
            if ($fullTask) {
                // Ensure po_number from original $old is used for searching if it was changed
                $searchPo = ($dbField === 'po_number') ? $oldVal : $fullTask['po_number'];

                $syncData = [
                    'id' => $fullTask['id'], 'poNumber' => $fullTask['po_number'], 'heading'  => $fullTask['heading'], 'contact'  => $fullTask['contact'], 'propertyCode' => $fullTask['property_code'], 'property' => $fullTask['property'], 'location' => $fullTask['location'], 'invoiceSent' => $fullTask['invoice_sent'], 'invoiceNo' => $fullTask['invoice_no'], 'assignedTo' => $fullTask['assigned_to'], 'priority' => $fullTask['priority'], 'start_date' => $fullTask['start_date'], 'date_booked' => $fullTask['date_booked'], 'next_visit' => $fullTask['next_visit'], 'status'   => $fullTask['status'], 'remarks'  => $fullTask['remarks'], 'certSent' => $fullTask['cert_sent'], 'rowColor' => $fullTask['row_color'], 'tags' => $fullTask['tags']
                ];

                // 1. Sync to current associations (Update/Add)
                if (!empty($fullTask['client_id'])) {
                    $syncSuccess = $this->sheetsService->updateClientSheet($syncData, $fullTask['client_id'], true, $searchPo, -1);
                }
                if (!empty($fullTask['tags'])) {
                    $this->sheetsService->updateClientSheet($syncData, $fullTask['tags'], true, $searchPo, -1);
                }

                // 2. CLEANUP: If field was specifically changed, check if we need to delete from the OLD sheet
                // This logic still relies on the old value of the clientId/tags, so we use $oldVal as the key.
                // The new $fullTask will contain the updated values.
                if (($dbField === 'client_id' || $dbField === 'tags') && !empty($oldVal)) {
                    // Check if oldVal is no longer present in the fullTask's client_id or tags
                    $currentClientTags = array_filter(array_unique([$fullTask['client_id'], $fullTask['tags']]));
                    // Convert both to string for consistent comparison with array_filter and array_unique
                    $currentClientTags = array_map('strval', $currentClientTags);

                    // If oldVal (from client_id or tags) is not in the current client_id or tags, it means it was removed.
                    if (!in_array(strval($oldVal), $currentClientTags)) {
                        $this->sheetsService->updateClientSheet(['poNumber' => $fullTask['po_number'], 'status' => 'deleted'], $oldVal, true, $searchPo, -1);
                    }
                }
            }

            echo json_encode(['success' => true, 'sheet_sync' => $syncSuccess]);
        } catch (Exception $e) {
            error_log("Error updating single field via API: " . $e->getMessage());
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Handle task deletion
     */
    private function handleDelete() {
        header('Content-Type: application/json');
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
            exit;
        }
        
        // 1. Fetch task details for Sheet Sync
        try {
            $taskToDelete = $this->makeApiCall('GET', '/api/tasks/' . $id);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Task not found for deletion: ' . $e->getMessage()]);
            exit;
        }

        // 2. Sync to Sheets (Remove Row)
        if ($taskToDelete) {
            // Remove from Primary Client
            if (!empty($taskToDelete['client_id'])) {
                $this->sheetsService->updateClientSheet(['poNumber' => $taskToDelete['po_number'], 'status' => 'deleted'], $taskToDelete['client_id'], true, $taskToDelete['po_number'], -1);
            }
            // Remove from Subcontractor
            if (!empty($taskToDelete['tags'])) {
                $this->sheetsService->updateClientSheet(['poNumber' => $taskToDelete['po_number'], 'status' => 'deleted'], $taskToDelete['tags'], true, $taskToDelete['po_number'], -1);
            }
        }

        // 3. Delete from API
        try {
            $this->makeApiCall('DELETE', '/api/tasks/' . $id);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("Error deleting task via API: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle getting task history
     */
    private function handleGetHistory() {
        header('Content-Type: application/json');
        
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID provided.']);
            exit;
        }
        
        // Fetch history via API
        try {
            $historyApiResponse = $this->makeApiCall('GET', '/api/tasks/' . $id . '/history');
            if ($historyApiResponse && ($historyApiResponse['success'] ?? false) && isset($historyApiResponse['history'])) {
                echo json_encode(['success' => true, 'history' => $historyApiResponse['history']]);
            } else {
                echo json_encode(['success' => false, 'message' => $historyApiResponse['message'] ?? 'Error fetching history from API.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching history: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle getting attachments for a PO
     */
    private function handleGetAttachments() {
        header('Content-Type: application/json');
        
        $po = trim($_GET['po'] ?? '');
        if (empty($po)) {
            echo json_encode(['success' => false, 'message' => 'No PO provided']);
            exit;
        }

        try {
            $apiResponse = $this->makeApiCall('GET', '/api/attachments/by-po/' . $po);
            if ($apiResponse && ($apiResponse['success'] ?? false) && isset($apiResponse['attachments'])) {
                $rawAttachments = $apiResponse['attachments'];
                
                // Deduplicate by name/file path
                $uniqueFiles = [];
                foreach ($rawAttachments as $at) {
                    $name = $at['DocumentName'] ?? ($at['name'] ?? 'Unknown');
                    // Use name as key to ensure uniqueness
                    if (!isset($uniqueFiles[$name])) {
                        $uniqueFiles[$name] = $at;
                    }
                }
                
                echo json_encode(['success' => true, 'files' => array_values($uniqueFiles)]);
            } else {
                echo json_encode(['success' => false, 'message' => $apiResponse['message'] ?? 'Error fetching attachments from API.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching attachments: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle deleting an attachment
     */
    private function handleDeleteAttachment() {
        header('Content-Type: application/json');
        
        $docId = (int)($_POST['doc_id'] ?? 0);
        if (!$docId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Document ID provided.']);
            exit;
        }

        // Delete attachment via API. The API should handle file deletion (Drive or local) and database record deletion.
        try {
            $apiResponse = $this->makeApiCall('DELETE', '/api/attachments/' . $docId);
            if ($apiResponse && ($apiResponse['success'] ?? false)) {
                echo json_encode(['success' => true, 'message' => 'Attachment deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => $apiResponse['message'] ?? 'Error deleting attachment via API.']);
            }
        } catch (Exception $e) {
            error_log("Error deleting attachment via API: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Delete attachment failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * Handle CSV export
     */
    private function handleExport() {
        $queryParams = [
            'search' => $_GET['search'] ?? '',
            'property_filter' => $_GET['property_filter'] ?? '',
            'status_filter' => $_GET['status_filter'] ?? '',
            'priority_filter' => $_GET['priority_filter'] ?? '',
            'invoice_filter' => $_GET['invoice_filter'] ?? '',
            // Add other filters as needed by the Laravel API export method
        ];

        try {
            $api_token = function_exists('getTrackerApiToken') ? getTrackerApiToken() : ($_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null);
            if (!$api_token) {
                throw new Exception("API token not found for export.");
            }

            $ch = curl_init();
            $url = $_ENV['LARAVEL_API_URL'] . '/api/tasks/export?' . http_build_query($queryParams);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_token,
                'Accept: text/csv' // Request CSV directly from the API
            ]);
            // Get headers in the response
            curl_setopt($ch, CURLOPT_HEADER, true); 

            $api_response_full = curl_exec($ch); // Get full response including headers
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $api_headers = substr($api_response_full, 0, $header_size);
            $api_body = substr($api_response_full, $header_size);

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($api_response_full === false) {
                throw new Exception("cURL error for export: " . $curl_error);
            }

            if ($http_code >= 400) {
                // Assuming API returns JSON error even for CSV if status is 4xx/5xx
                $errorMessage = json_decode($api_body, true)['message'] ?? 'Unknown API error'; 
                throw new Exception("Laravel API export error (Status: {$http_code}): " . $errorMessage);
            }

            // Forward headers from API response
            $header_lines = explode("\n", $api_headers);
            foreach ($header_lines as $header) {
                // Ensure it's a valid header line and not an empty line
                if (str_contains($header, ':')) {
                    header(trim($header), false); // The `false` prevents replacing previous headers
                }
            }
            
            echo $api_body; // Echo only the body
            exit;

        } catch (Exception $e) {
            error_log("Error during CSV export: " . $e->getMessage());
            // Fallback for user in case of error, show a JSON message
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
            exit;
        }
    }
    
    /**
     * Handle full client sync to sheet
     */
    private function handleFullSync() {
        header('Content-Type: application/json');
        
        $clientId = (int)($_POST['id'] ?? 0);
        $result = $this->sheetsService->syncClientToSheet($clientId);
        echo json_encode($result);
    }
}
