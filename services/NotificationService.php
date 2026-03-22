<?php
/**
 * Notification Service
 * Handles all email and WhatsApp notifications
 */

use Twilio\Rest\Client;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationService {

    private function trackerAppUrl(): string
    {
        if (function_exists('trackerAppUrl')) {
            return trackerAppUrl();
        }

        $configured = trim((string) ($_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? ''));
        return $configured !== '' ? rtrim($configured, '/') : '';
    }

    private function defaultAssetLogoUrl(): string
    {
        $appUrl = $this->trackerAppUrl();
        return $appUrl !== '' ? $appUrl . '/dist/images/logo.png' : '';
    }

    private function defaultSenderEmail(): string
    {
        return (string) ($GLOBALS['mail_from_address'] ?? $GLOBALS['mail_username'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? 'no-reply@localhost');
    }

    private function defaultSenderName(string $fallback = 'Work Order Tracker'): string
    {
        return (string) ($GLOBALS['mail_from_name'] ?? $_ENV['MAIL_FROM_NAME'] ?? $fallback);
    }

    private function supportEmail(): string
    {
        return (string) ($GLOBALS['mail_from_address'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? '');
    }

    private function configuredWorkorderDestinationEmail(): string
    {
        $settingsRes = $this->makeApiCall('GET', '/api/settings');
        if ($settingsRes && ($settingsRes['success'] ?? false)) {
            foreach ($settingsRes['data'] as $items) {
                foreach ($items as $setting) {
                    if ($setting['key'] === 'workorder_extraction_email' && !empty($setting['value'])) {
                        return (string) $setting['value'];
                    }
                }
            }
        }

        return (string) ($GLOBALS['mail_from_address'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? '');
    }

    public function __construct() {
        // Direct database connection is no longer used in this service.
    }

    /**
     * Helper to make API calls to the Laravel API
     * @param string $method HTTP method (GET, POST, PUT, DELETE, PATCH)
     * @param string $endpoint API endpoint relative to LARAVEL_API_URL
     * @param array $data Data to send (for POST/PUT/PATCH)
     * @param array $files Files array in $_FILES format
     * @return array|false Decoded JSON response or false on error
     * @throws Exception if API token is missing or API call fails
     */
    private function makeApiCall(string $method, string $endpoint, array $data = [], array $files = []): array|false
    {
        $apiToken = function_exists('getTrackerApiToken') ? getTrackerApiToken() : ($_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null);
        if (!$apiToken) {
            error_log("API token missing for API call to {$endpoint}. Session: " . json_encode($_SESSION));
            throw new Exception("API token missing for API call to {$endpoint}");
        }

        $ch = curl_init();
        $url = $_ENV['LARAVEL_API_URL'] . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $apiToken,
            'Accept: application/json'
        ];

        $tenantSlug = function_exists('trackerTenantSlug') ? trackerTenantSlug() : trim((string) ($_SERVER['TENANT_SLUG'] ?? $_ENV['TENANT_SLUG'] ?? ''));
        if ($tenantSlug !== '') {
            $headers[] = 'X-Tenant-Slug: ' . $tenantSlug;
        }

        if (!empty($files)) {
            $post = [];
            foreach ($data as $key => $value) {
                $post[$key] = $value;
            }
            foreach ($files as $name => $fileArray) {
                if (is_array($fileArray['tmp_name'])) {
                    foreach ($fileArray['tmp_name'] as $key => $tmpName) {
                        if ($fileArray['error'][$key] == UPLOAD_ERR_OK) {
                            $post[$name . '[' . $key . ']'] = new CURLFile($tmpName, $fileArray['type'][$key], $fileArray['name'][$key]);
                        }
                    }
                } else {
                    if ($fileArray['error'] == UPLOAD_ERR_OK) {
                        $post[$name] = new CURLFile($fileArray['tmp_name'], $fileArray['type'], $fileArray['name']);
                    }
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        } elseif ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
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
            throw new Exception("API call failed: {$curl_error}");
        }

        $decoded_response = json_decode($response, true);

        if ($http_code === 204) { // No Content
            return ['success' => true, 'message' => 'No content'];
        }

        if ($decoded_response === null && !empty($response)) {
            error_log("Laravel API returned non-JSON response for {$method} {$endpoint}. Raw response: {$response}");
            throw new Exception("API call failed: Invalid JSON response.");
        }

        if ($http_code >= 400) {
            $message = $decoded_response['message'] ?? 'Unknown API error';
            error_log("Laravel API error on {$method} {$endpoint} (Status: {$http_code}): {$message}. Raw response: {$response}");
            throw new Exception("API call error (Status: {$http_code}): {$message}");
        }

        return $decoded_response;
    }

    /**
     * Normalize API responses that may return either a wrapped resource or the resource itself.
     *
     * @param array|false $response
     * @param string $key Preferred wrapped key, e.g. 'task' or 'user'
     * @return array|null
     */
    private function extractResource(array|false $response, string $key): ?array
    {
        if (!is_array($response)) {
            return null;
        }

        if (isset($response[$key]) && is_array($response[$key])) {
            return $response[$key];
        }

        return $response;
    }
    
    /**
     * Send completion notification email
     * 
     * @param int $taskId Task ID
     * @return bool
     */
    public function sendCompletionNotification($taskId) {
        try {
            // 1. Fetch Task Details and Check if already sent via API
            $taskApiResponse = $this->makeApiCall('GET', "/api/tasks/{$taskId}");
            $task = $this->extractResource($taskApiResponse, 'task');

            if (!$task || empty($task['client_id'])) return false;

            // Fetch client details including spreadsheet_id via API
            $clientDetailsResponse = $this->makeApiCall('GET', "/api/clients/{$task['client_id']}/details");
            $client = $clientDetailsResponse['client'] ?? null;
            if ($client) {
                $task['clientName'] = $client['name'];
                $task['clientEmail'] = $client['email'];
                $task['spreadsheet_id'] = $client['spreadsheet_id'];
            } else {
                error_log("Completion Notify: Could not fetch client details for client_id {$task['client_id']}");
                // Proceed with available task data, some fields might be empty
            }

            // Prevent duplicate sends
            if (($task['email_sent'] ?? 'No') === 'Yes') {
                error_log("Completion Notify: Already sent for Task ID $taskId. Skipping.");
                return false;
            }

            // 2. Check notification eligibility from DB or .env
            $notifyNamesString = $GLOBALS['tracker_notify_names'] ?? $_ENV['TRACKER_NOTIFY_NAMES'] ?? '';
            $notifyNames = array_map('trim', explode(',', str_replace('"', '', $notifyNamesString)));
            $clientName = (string)($task['clientName'] ?? $task['client_name'] ?? '');

            if (!in_array($clientName, $notifyNames, true)) {
                error_log("Completion Notify: Client " . ($clientName ?: ($task['client_id'] ?? 'unknown')) . " not enabled for notifications.");
                return false;
            }
            // 3. Configure PHPMailer (DB priority)
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $GLOBALS['mail_host'] ?? $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $GLOBALS['mail_username'] ?? $_ENV['MAIL_USERNAME'];
            $mail->Password = $GLOBALS['mail_password'] ?? $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $GLOBALS['mail_encryption'] ?? $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $GLOBALS['mail_port'] ?? $_ENV['MAIL_PORT'] ?? 587;

            // Sender details
            $portalName = $clientName !== '' ? $clientName : $this->defaultSenderName();
            $mail->setFrom($this->defaultSenderEmail(), $portalName);
            
            // Recipients
            $recipients = [];
            if (!empty($task['invoice_email'])) {
                $extras = explode(',', $task['invoice_email']);
                foreach ($extras as $e) {
                    $trimmed = trim($e);
                    if (!empty($trimmed)) $recipients[] = $trimmed;
                }
            }

            // Deduplicate and Validate
            $recipients = array_unique($recipients);
            $recipientAdded = false;
            
            foreach ($recipients as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    error_log("PHPMailer: Adding address -> $email");
                    $mail->addAddress($email);
                    $recipientAdded = true;
                }
            }

            if (!$recipientAdded) {
                error_log("Completion Notify Error: No valid email addresses found for task ID $taskId");
                return false;
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "Job Completed: " . ($task['po_number'] ?? 'N/A') . " - " . ($task['property'] ?? 'N/A');
            
            $logoUrl = $this->defaultAssetLogoUrl();
            $teamName = htmlspecialchars($portalName, ENT_QUOTES, 'UTF-8');
            $logoHtml = $logoUrl !== ''
                ? "<img src='{$logoUrl}' alt='{$teamName}' style='max-width: 200px;'>"
                : '';
            $body = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        {$logoHtml}
                    </div>
                    <h2 style='color: #4f46e5; text-align: center;'>Work Order Completion Notice</h2>
                    <p>Hello <strong>" . htmlspecialchars($task['clientName'] ?? 'Client') . "</strong>,</p>
                    <p>This is an automated notification to inform you that the following work order has been marked as <strong>Completed</strong>:</p>
                    
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #eee; font-weight: bold; width: 120px;'>PO Number:</td>
                            <td style='padding: 8px; border: 1px solid #eee;'>" . htmlspecialchars($task['po_number'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #eee; font-weight: bold;'>Task:</td>
                            <td style='padding: 8px; border: 1px solid #eee;'>" . htmlspecialchars($task['heading'] ?? 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border: 1px solid #eee; font-weight: bold;'>Location:</td>
                            <td style='padding: 8px; border: 1px solid #eee;'>" . htmlspecialchars($task['property'] ?? '') . " " . htmlspecialchars($task['location'] ?? '') . "</td>
                        </tr>
                    </table>

                    <p>Detailed reports and job history are available in your shared Google Tracking Sheet:</p>
                    
                    " . (!empty($task['spreadsheet_id']) ? "
                    <div style='margin: 25px 0; text-align: center;'>
                        <a href='https://docs.google.com/spreadsheets/d/" . $task['spreadsheet_id'] . "' 
                           style='background-color: #10b981; color: white; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                           Open Google Tracking Sheet
                        </a>
                    </div>
                    " : "<p style='color: #666; font-style: italic; text-align: center;'>(Google Sheet link not configured for this client)</p>") . "

                    <br>
                    <p>Regards,<br><strong>{$teamName}</strong></p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 10px; color: #999;'>This is an automated message. Please do not reply to this email.</p>
                </div>
            ";
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            if ($mail->send()) {
                // Update email_sent status via API
                $this->makeApiCall('PATCH', "/api/tasks/{$taskId}", ['email_sent' => 'Yes']);
                // The logHistory call should be handled by the API itself or the calling TrackerRequestHandler
                error_log("Completion Notify SUCCESS: Sent for PO " . $task['po_number']);
                return true;
            }
        } catch (Exception $e) {
            error_log("Completion Notify ERROR: " . $e->getMessage());
            return false;
        }
        return false;
    }
    
    /**
     * Send WhatsApp notification
     * 
     * @param int $taskId Task ID
     * @return array Result array with success and message
     */
    public function sendWhatsAppNotification($taskId) {
        // Fetch Task via API
        $taskApiResponse = $this->makeApiCall('GET', "/api/tasks/{$taskId}");
        $task = $this->extractResource($taskApiResponse, 'task');
        
        if (!$task) throw new Exception("Task not found via API.");
        if (empty($task['assigned_to'])) throw new Exception("No user assigned to this job");

        // Fetch User Mobile via API
        $userApiResponse = $this->makeApiCall('GET', "/api/users/{$task['assigned_to']}");
        $user = $this->extractResource($userApiResponse, 'user');
        
        if (!$user || empty($user['mobile'])) {
            throw new Exception("Assigned user has no mobile number configured via API.");
        }

        $mobile = preg_replace('/[^0-9]/', '', $user['mobile']);
        if (str_starts_with($mobile, '0')) {
            $mobile = '+353' . substr($mobile, 1);
        } elseif (!str_starts_with($mobile, '+')) {
            $mobile = '+' . $mobile;
        }

        // Construct Message
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $publicLink = $protocol . $host . "/public/task_view.php?h=" . $task['hash'];

        $taskPreview = $task['heading'];
        if (strlen($taskPreview) > 150) {
            $taskPreview = substr($taskPreview, 0, 150) . "...";
        }

        $body = sprintf(
            "*New Job Details*\n------------------\n*PO:* %s\n*Location:* %s\n*Priority:* %s\n\n*Task:* %s\n\n*View Details & Update Status:*\n%s",
            $task['po_number'],
            ($task['property'] ? $task['property'] . " - " : "") . ($task['location'] ?: 'N/A'),
            ucfirst($task['priority']),
            $taskPreview,
            $publicLink
        );

        // Send WhatsApp (DB priority)
        $sid = $GLOBALS['twilio_sid'] ?? $_ENV['TWILIO_SID'] ?? '';
        $token = $GLOBALS['twilio_auth_token'] ?? $_ENV['TWILIO_AUTH_TOKEN'] ?? '';
        $templateSid = $GLOBALS['twilio_whatsapp_template_sid'] ?? $_ENV['TWILIO_WHATSAPP_TEMPLATE_SID'] ?? '';
        $fromNumber = $GLOBALS['twilio_whatsapp_from'] ?? $_ENV['TWILIO_WHATSAPP_FROM'] ?? $_ENV['TWILIO_PHONE_NUMBER'] ?? $_ENV['TWILIO_FROM'] ?? '+14155238886';
        
        if (!$sid || !$token) {
            throw new Exception("Twilio credentials missing in configuration");
        }

        $cleanFrom = preg_replace('/^whatsapp:/', '', $fromNumber);
        $from = 'whatsapp:' . $cleanFrom;
        $to = 'whatsapp:' . $mobile;

        error_log("WhatsApp: Sending from $from to $to");

        $twilioClient = new Client($sid, $token);
        
        if (isset($_ENV['TWILIO_EDGE']) && $_ENV['TWILIO_EDGE'] === 'ie1') {
            $twilioClient->setEdge('ie1');
        }

        if (!empty($templateSid)) {
            $vars = [
                '1' => str_replace(["\r", "\n", "\t"], ' ', (string)($task['po_number'] ?? 'N/A')),
                '2' => str_replace(["\r", "\n", "\t"], ' ', (string)(($task['property'] ? $task['property'] . " - " : "") . ($task['location'] ?: 'N/A'))),
                '3' => str_replace(["\r", "\n", "\t"], ' ', (string)ucfirst($task['priority'] ?? 'Medium')),
                '4' => (string)($taskPreview ?: 'No details'),
                '5' => (string)$publicLink
            ];

            $messageParams = [
                'from' => $from,
                'contentSid' => trim($templateSid),
                'contentVariables' => json_encode($vars)
            ];
        } else {
            $messageParams = ['from' => $from, 'body' => $body];
        }

        $message = $twilioClient->messages->create($to, $messageParams);

        if ($message->sid) {
            // Update whatsapp_sent status via API
            $this->makeApiCall('PATCH', "/api/tasks/{$taskId}", ['whatsapp_sent' => 'Yes']);
            // The logHistory call should be handled by the API itself or the calling TrackerRequestHandler
            return ['success' => true, 'message' => 'WhatsApp message sent successfully!'];
        }
        
        return ['success' => false, 'message' => 'Failed to send WhatsApp message'];
    }

    /**
     * Send client-branded work order email notification
     */
    public function sendClientWorkOrderEmail($client, $data, array $attachments = []) {
        try {
            $po = trim((string)($data['poNumber'] ?? ''));
            $addr = trim((string)($data['location'] ?? ''));
            $priority = trim((string)($data['priority'] ?? 'Medium'));
            $contact = trim((string)($data['contact'] ?? 'Not Provided'));
            $eircode = trim((string)($data['eircode'] ?? ''));
            $details = nl2br(htmlspecialchars((string)($data['task'] ?? ''), ENT_QUOTES, 'UTF-8'));

            $poHtml = htmlspecialchars($po !== '' ? $po : 'N/A', ENT_QUOTES, 'UTF-8');
            $addrHtml = htmlspecialchars($addr !== '' ? $addr : 'N/A', ENT_QUOTES, 'UTF-8');
            $priorityHtml = htmlspecialchars($priority !== '' ? $priority : 'Medium', ENT_QUOTES, 'UTF-8');
            $contactHtml = htmlspecialchars($contact !== '' ? $contact : 'Not Provided', ENT_QUOTES, 'UTF-8');
            $eircodeHtml = htmlspecialchars($eircode !== '' ? $eircode : 'Not Provided', ENT_QUOTES, 'UTF-8');
            
            $companyName = htmlspecialchars((string)($client['name'] ?? 'Property Management'), ENT_QUOTES, 'UTF-8');
            $logoUrl = $client['logo_url'] ?? $this->defaultAssetLogoUrl();
            
            $destEmail = $this->configuredWorkorderDestinationEmail();

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $GLOBALS['mail_host'] ?? $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $GLOBALS['mail_username'] ?? $_ENV['MAIL_USERNAME'];
            $mail->Password = $GLOBALS['mail_password'] ?? $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $GLOBALS['mail_encryption'] ?? $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $GLOBALS['mail_port'] ?? $_ENV['MAIL_PORT'] ?? 587;

            $mail->setFrom($GLOBALS['mail_from_address'] ?? $GLOBALS['mail_username'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? 'no-reply@localhost', $companyName . ' Work Orders');
            if ($destEmail !== '') {
                $mail->addAddress($destEmail);
            }

            foreach ($attachments as $attachment) {
                $path = $attachment['path'] ?? null;
                if (!empty($path) && is_file($path)) {
                    $mail->addAttachment($path, $attachment['name'] ?? basename($path));
                }
            }
            
            $mail->isHTML(true);
            $mail->Subject = "New Work Order: " . ($po !== '' ? $po : 'N/A') . " - " . ($addr !== '' ? $addr : 'N/A');
            
            $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; background: #ffffff;'>
                <div style='background: linear-gradient(135deg, #4f46e5 0%, #312e81 100%); padding: 44px 40px; text-align: center;'>
                    <img src='$logoUrl' style='height: 50px; margin-bottom: 20px;'>
                    <h2 style='color: white; margin: 0; text-transform: uppercase;'>New Work Order</h2>
                    <p style='margin: 14px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; letter-spacing: 1px; text-transform: uppercase;'>Submitted via client portal</p>
                </div>
                <div style='padding: 36px 40px; color: #334155;'>
                    <p>Hello,</p>
                    <p>A new work order has been submitted via the <strong>$companyName</strong> client portal.</p>
                    
                    <div style='display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin: 28px 0;'>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>PO Reference</div>
                            <div style='margin-top: 6px; font-size: 18px; font-weight: 800; color: #0f172a;'>$poHtml</div>
                        </div>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>Priority</div>
                            <div style='margin-top: 6px; font-size: 18px; font-weight: 800; color: #0f172a;'>$priorityHtml</div>
                        </div>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>Site Address</div>
                            <div style='margin-top: 6px; font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.5;'>$addrHtml</div>
                        </div>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>Contact</div>
                            <div style='margin-top: 6px; font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.5;'>$contactHtml</div>
                        </div>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>Eircode</div>
                            <div style='margin-top: 6px; font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.5;'>$eircodeHtml</div>
                        </div>
                        <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px 18px;'>
                            <div style='font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;'>Attachments</div>
                            <div style='margin-top: 6px; font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.5;'>" . (count($attachments) > 0 ? count($attachments) . " file(s) included" : "None uploaded") . "</div>
                        </div>
                    </div>

                    <div style='background: #f8fafc; padding: 22px; border-radius: 18px; border: 1px solid #e2e8f0; margin-bottom: 28px;'>
                        <h4 style='margin: 0 0 12px; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;'>Instructions</h4>
                        <div style='margin: 0; line-height: 1.7; color: #334155;'>$details</div>
                    </div>

                    <p style='font-size: 12px; color: #999; text-align: center;'>This is an automated transmission from the $companyName portal.</p>
                </div>
            </div>";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Dynamic WO Email Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send professional branded proposal email to client
     */
    public function sendProposalEmail($proposal) {
        try {
            $companyName = $proposal['company']['company_name'] ?? 'Project Proposal';
            $logoUrl = $proposal['company']['logo'] ?? $this->defaultAssetLogoUrl();
            $clientName = $proposal['lead']['client_name'];
            $clientEmail = $proposal['lead']['client_email'];
            
            // Generate Secure Public Link
            $cleanAppUrl = $this->trackerAppUrl();
            $publicLink = "{$cleanAppUrl}/public/proposal.php?h={$proposal['hash']}";

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $GLOBALS['mail_host'] ?? $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $GLOBALS['mail_username'] ?? $_ENV['MAIL_USERNAME'];
            $mail->Password = $GLOBALS['mail_password'] ?? $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $GLOBALS['mail_encryption'] ?? $_ENV['MAIL_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $GLOBALS['mail_port'] ?? $_ENV['MAIL_PORT'] ?? 587;

            $mail->setFrom($GLOBALS['mail_from_address'] ?? $GLOBALS['mail_username'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['MAIL_USERNAME'] ?? 'no-reply@localhost', $companyName);
            $mail->addAddress($clientEmail, $clientName);
            
            $mail->isHTML(true);
            $mail->Subject = "Project Proposal: " . ($proposal['lead']['client_name'] ?? 'New Project');
            
            $mail->Body = "
            <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 30px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.05);'>
                <div style='background: #4f46e5; padding: 60px 40px; text-align: center; color: white;'>
                    <img src='$logoUrl' style='height: 60px; margin-bottom: 30px; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));'>
                    <h1 style='margin: 0; text-transform: uppercase; letter-spacing: 2px; font-style: italic; font-weight: 900; font-size: 28px;'>Your Project Proposal</h1>
                    <p style='margin-top: 15px; opacity: 0.8; font-weight: 600;'>Ref: #" . str_pad($proposal['id'], 5, '0', STR_PAD_LEFT) . "</p>
                </div>
                <div style='padding: 50px 40px; color: #334155; line-height: 1.8; background: white;'>
                    <p style='font-size: 18px; font-weight: 700; color: #1e293b;'>Hello $clientName,</p>
                    <p>We are pleased to present our formal proposal for your upcoming project. Our team has carefully reviewed your requirements and prepared a comprehensive scope of work and investment breakdown.</p>
                    
                    <div style='margin: 40px 0; text-align: center;'>
                        <a href='$publicLink' style='display: inline-block; padding: 20px 45px; background: #4f46e5; color: white; text-decoration: none; border-radius: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; font-size: 14px; box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);'>View & Accept Proposal</a>
                    </div>

                    <div style='background: #f8fafc; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9;'>
                        <p style='margin: 0; font-size: 13px; font-weight: 600; color: #64748b;'>This link is private and secure. You can review all project details, download a PDF copy, and electronically sign the acceptance directly from your browser.</p>
                    </div>

                    <p style='margin-top: 40px;'>If you have any questions regarding the details outlined, please feel free to reply directly to this email.</p>
                    
                    <div style='margin-top: 50px; padding-top: 30px; border-top: 1px solid #f1f5f9;'>
                        <p style='margin: 0; font-weight: 800; color: #1e293b;'>Best Regards,</p>
                        <p style='margin: 5px 0 0; color: #64748b; font-weight: 600;'>$companyName Team</p>
                    </div>
                </div>
                <div style='background: #0f172a; padding: 30px; text-align: center; color: #475569; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;'>
                    Secure Transmission &copy; " . date('Y') . " $companyName
                </div>
            </div>";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Proposal Email Error: " . $e->getMessage());
            return false;
        }
    }
}
