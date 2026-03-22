<?php
// Function to make API calls
if (!function_exists('makeApiCall')) {
    function makeApiCall($endpoint, $data = [], $method = 'GET') {
        if (session_status() === PHP_SESSION_NONE) {
            // Ensure session is started if not already
            if (defined('SESSION_SAVE_PATH')) {
                ini_set('session.save_path', SESSION_SAVE_PATH);
            }
            session_start();
        }

        $api_token = function_exists('getTrackerApiToken') ? getTrackerApiToken() : ($_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null);

        // Check if it's a public endpoint (doesn't require token)
        $isPublicEndpoint = (strpos($endpoint, '/api/public/') !== false);
        $isSettingsEndpoint = ($endpoint === '/api/settings');

        if (!$api_token && !$isPublicEndpoint) {
            if ($isSettingsEndpoint) {
                // Silently fail for settings if no token (user not logged in yet)
                return false;
            }
            error_log("API token not found for endpoint: " . $endpoint);
            return false;
        }

        $ch = curl_init();
        
        // Ensure LARAVEL_API_URL is available
        $baseUrl = $_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? '';
        if (empty($baseUrl)) {
            error_log("LARAVEL_API_URL not set in environment. Endpoint: " . $endpoint);
            return false;
        }

        $url = rtrim($baseUrl, '/') . $endpoint;

        $headers = [
            'Accept: application/json'
        ];

        $tenantSlug = function_exists('trackerTenantSlug') ? trackerTenantSlug() : trim((string) ($_SERVER['TENANT_SLUG'] ?? $_ENV['TENANT_SLUG'] ?? ''));
        if ($tenantSlug !== '') {
            $headers[] = 'X-Tenant-Slug: ' . $tenantSlug;
        }

        if ($api_token) {
            $headers[] = 'Authorization: Bearer ' . $api_token;
        }

        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($data);
        } elseif (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT', 'DELETE'])) {
            // Check if data is already a JSON string (from api_proxy or raw payload)
            $isJson = false;
            if (is_string($data)) {
                json_decode($data);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $isJson = true;
                }
            }

            if ($isJson) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($api_response === false) {
            error_log("cURL error for {$endpoint}: " . $curl_error);
            return false;
        }

        $responseData = json_decode($api_response, true);
        if ($http_code >= 400) {
            error_log("Laravel API error for {$endpoint} (Status: {$http_code}): " . ($responseData['message'] ?? 'Unknown error'));
            return $responseData;
        }

        return $responseData;
    }
}

// Function to load global settings from API and map them to $GLOBALS
if (!function_exists('loadGlobalSettings')) {
    function loadGlobalSettings() {
        if (isset($GLOBALS['settings_loaded'])) return;

        $settingsRes = makeApiCall('/api/settings');
        if ($settingsRes && ($settingsRes['success'] ?? false)) {
            foreach ($settingsRes['data'] as $group => $items) {
                foreach ($items as $s) {
                    if (!is_null($s['value']) && $s['value'] !== '') {
                        $key = $s['key'];
                        $val = $s['value'];

                        switch($key) {
                            case 'google_maps_api_key': $GLOBALS['googleMapsApiKey'] = $val; break;
                            case 'eircode_api_key': $GLOBALS['eircode_api_key'] = $val; break;
                            case 'vat_rate': $GLOBALS['vat_rate'] = $val; break;
                            case 'callout_days': $GLOBALS['callout_days'] = $val; break;
                            case 'google_calendar_id': $GLOBALS['google_calendar_id'] = $val; break;
                            case 'gallery_folder_id': $GLOBALS['gallery_folder_id'] = $val; break;
                            case 'tracker_notify_names': $GLOBALS['tracker_notify_names'] = $val; break;
                            case 'tracker_client_names': $GLOBALS['tracker_client_names'] = $val; break;
                            case 'tracker_sub_names': $GLOBALS['tracker_sub_names'] = $val; break;
                            case 'super_admin_email': $GLOBALS['super_admin_email'] = $val; break;
                            case 'mail_host': $GLOBALS['mail_host'] = $val; break;
                            case 'mail_username': $GLOBALS['mail_username'] = $val; break;
                            case 'mail_password': $GLOBALS['mail_password'] = $val; break;
                            case 'mail_from_address': $GLOBALS['mail_from_address'] = $val; break;
                            case 'mail_from_name': $GLOBALS['mail_from_name'] = $val; break;
                            case 'mail_encryption': $GLOBALS['mail_encryption'] = $val; break;
                            case 'mail_port': $GLOBALS['mail_port'] = $val; break;
                            case 'default_lat': $GLOBALS['default_lat'] = $val; break;
                            case 'default_lng': $GLOBALS['default_lng'] = $val; break;
                        }
                        $GLOBALS[$key] = $val;
                    }
                }
            }
            $GLOBALS['settings_loaded'] = true;
        }
    }
}

if (!function_exists('isTrackerSuperAdmin')) {
    function isTrackerSuperAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            if (defined('SESSION_SAVE_PATH')) {
                ini_set('session.save_path', SESSION_SAVE_PATH);
            }
            session_start();
        }

        // 1. Check SuperAdmin Email Match
        $superAdminEmail = trackerSuperAdminEmail();
        if ($superAdminEmail !== '' && ($_SESSION['email'] ?? '') === $superAdminEmail) {
            return true;
        }

        // 2. Check Role ID (1 is usually Admin/SuperAdmin)
        if (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('isTrackerAdminUser')) {
    function isTrackerAdminUser() {
        if (session_status() === PHP_SESSION_NONE) {
            if (defined('SESSION_SAVE_PATH')) {
                ini_set('session.save_path', SESSION_SAVE_PATH);
            }
            session_start();
        }

        $superAdminEmail = trackerSuperAdminEmail();
        if ($superAdminEmail !== '' && ($_SESSION['email'] ?? '') === $superAdminEmail) {
            return true;
        }

        if (isset($_SESSION['role_id']) && (int) $_SESSION['role_id'] === 1) {
            return true;
        }

        if (!empty($_SESSION['is_office'])) {
            return true;
        }

        $currentUserRes = makeApiCall('/api/user');
        if (is_array($currentUserRes)) {
            $roleId = (int) ($currentUserRes['data']['role_id'] ?? $currentUserRes['role_id'] ?? 0);
            $isOffice = (bool) ($currentUserRes['data']['is_office'] ?? $currentUserRes['is_office'] ?? false);
            if ($roleId > 0) {
                $_SESSION['role_id'] = $roleId;
            }
            $_SESSION['is_office'] = $isOffice;

            return $roleId === 1 || $isOffice;
        }

        return false;
    }
}
