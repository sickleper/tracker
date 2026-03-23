<?php
require_once __DIR__ . '/../vendor/autoload.php';

// config.php

// Load .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // Path to workorders project root
$dotenv->load();

if (!defined('TRACKER_BOOTSTRAP_CONFIG_PATH')) {
    define('TRACKER_BOOTSTRAP_CONFIG_PATH', __DIR__ . '/storage/app_bootstrap.json');
}

if (!function_exists('trackerDefaultBootstrapConfig')) {
    function trackerDefaultBootstrapConfig(): array
    {
        return [
            'app_name' => '',
            'app_url' => '',
            'laravel_api_url' => '',
            'default_tenant' => '',
            'updated_at' => '',
            'updated_by' => '',
        ];
    }
}

if (!function_exists('trackerNormalizeBootstrapConfig')) {
    function trackerNormalizeBootstrapConfig(array $config): array
    {
        $normalized = trackerDefaultBootstrapConfig();

        $normalized['app_name'] = trim((string) ($config['app_name'] ?? ''));
        $normalized['app_url'] = rtrim(trim((string) ($config['app_url'] ?? '')), '/');
        $normalized['laravel_api_url'] = rtrim(trim((string) ($config['laravel_api_url'] ?? '')), '/');
        $normalized['default_tenant'] = trim((string) ($config['default_tenant'] ?? $config['default_tenant_slug'] ?? ''));
        $normalized['updated_at'] = trim((string) ($config['updated_at'] ?? ''));
        $normalized['updated_by'] = trim((string) ($config['updated_by'] ?? ''));

        return $normalized;
    }
}

if (!function_exists('trackerLoadBootstrapConfig')) {
    function trackerLoadBootstrapConfig(bool $forceReload = false): array
    {
        static $cached = null;

        if (!$forceReload && is_array($cached)) {
            return $cached;
        }

        $config = trackerDefaultBootstrapConfig();
        $path = TRACKER_BOOTSTRAP_CONFIG_PATH;

        if (is_file($path) && is_readable($path)) {
            $raw = file_get_contents($path);
            $decoded = json_decode($raw ?: '', true);
            if (is_array($decoded)) {
                $config = trackerNormalizeBootstrapConfig($decoded);
            }
        }

        $cached = $config;
        return $cached;
    }
}

if (!function_exists('trackerSaveBootstrapConfig')) {
    function trackerSaveBootstrapConfig(array $config, ?string $updatedBy = null): array
    {
        $normalized = trackerNormalizeBootstrapConfig($config);
        $normalized['updated_at'] = gmdate('c');
        $normalized['updated_by'] = trim((string) ($updatedBy ?? ''));

        $dir = dirname(TRACKER_BOOTSTRAP_CONFIG_PATH);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create tracker bootstrap config directory.');
        }

        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode tracker bootstrap config.');
        }

        $result = file_put_contents(TRACKER_BOOTSTRAP_CONFIG_PATH, $json . PHP_EOL, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException('Unable to write tracker bootstrap config.');
        }

        return trackerLoadBootstrapConfig(true);
    }
}

$trackerBootstrapConfig = trackerLoadBootstrapConfig();
$trackerBootstrapMap = [
    'APP_URL' => 'app_url',
    'LARAVEL_API_URL' => 'laravel_api_url',
    'TENANT_SLUG' => 'default_tenant',
];

foreach ($trackerBootstrapMap as $envKey => $configKey) {
    $value = trim((string) ($trackerBootstrapConfig[$configKey] ?? ''));
    if ($value === '') {
        continue;
    }

    $_ENV[$envKey] = $value;
    $_SERVER[$envKey] = $value;
    putenv($envKey . '=' . $value);
}

$GLOBALS['tracker_bootstrap_config'] = $trackerBootstrapConfig;

// Global Session Configuration
define('SESSION_SAVE_PATH', '/home/workorders/tmp');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', SESSION_SAVE_PATH);
    session_start();
}

if (!function_exists('getTrackerApiToken')) {
    function getTrackerApiToken(): ?string
    {
        $token = $_SESSION['api_token'] ?? $_COOKIE['apitoken'] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }
}

if (!function_exists('isTrackerAuthenticated')) {
    function isTrackerAuthenticated(): bool
    {
        return getTrackerApiToken() !== null;
    }
}

if (!function_exists('trackerAppUrl')) {
    function trackerAppUrl(): string
    {
        $configured = trim((string) ($_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host !== '') {
            return $scheme . '://' . $host;
        }

        return '';
    }
}

if (!function_exists('trackerSuperAdminEmail')) {
    function trackerSuperAdminEmail(): string
    {
        return trim((string) ($GLOBALS['super_admin_email'] ?? $_ENV['SUPER_ADMIN_EMAIL'] ?? ''));
    }
}

if (!function_exists('trackerTenantSlug')) {
    function trackerResolveTenantSlugFromApi(int $tenantId, ?string $apiToken): string
    {
        if ($tenantId <= 0 || !is_string($apiToken) || $apiToken === '') {
            return '';
        }

        $baseUrl = trim((string) ($_ENV['LARAVEL_API_URL'] ?? getenv('LARAVEL_API_URL') ?? ''));
        if ($baseUrl === '') {
            return '';
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => rtrim($baseUrl, '/') . '/api/tenants/' . $tenantId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $apiToken,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return '';
        }

        $decoded = json_decode($response, true);
        $slug = trim((string) ($decoded['data']['slug'] ?? $decoded['slug'] ?? ''));

        return $slug;
    }

    function trackerTenantSlug(bool $refresh = false): string
    {
        static $resolved = null;

        if ($refresh) {
            $resolved = null;
        }

        $impersonatedSlug = trim((string) ($_SESSION['impersonated_tenant_slug'] ?? ''));
        if ($impersonatedSlug !== '') {
            if ($resolved === null || $resolved !== $impersonatedSlug) {
                $_SESSION['tenant_slug'] = $impersonatedSlug;
                $resolved = $impersonatedSlug;
            }
            return $resolved;
        }

        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [
            $_SERVER['HTTP_X_TENANT_SLUG'] ?? null,
            $_GET['tenant_slug'] ?? null,
            $_POST['tenant_slug'] ?? null,
            $_SESSION['tenant_slug'] ?? null,
            $_COOKIE['tenant_slug'] ?? null,
            $_SERVER['TENANT_SLUG'] ?? null,
            $_ENV['TENANT_SLUG'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                $_SESSION['tenant_slug'] = $candidate;
                $resolved = $candidate;
                return $resolved;
            }
        }

        $tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
        $apiToken = getTrackerApiToken();
        $slug = trackerResolveTenantSlugFromApi($tenantId, $apiToken);

        if ($slug !== '') {
            $_SESSION['tenant_slug'] = $slug;
            $resolved = $slug;
            return $resolved;
        }

        $resolved = '';
        return $resolved;
    }
}

if (!function_exists('gs')) {
    function gs(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $GLOBALS)) {
            return $GLOBALS[$key];
        }
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        $upper = strtoupper($key);
        if (isset($_ENV[$upper])) {
            return $_ENV[$upper];
        }
        return $default;
    }
}

if (!function_exists('featureEnabled')) {
    function featureEnabled(string $key, bool $default = false): bool
    {
        $value = gs($key, $default ? '1' : '0');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('trackerEnvIntList')) {
    function trackerEnvIntList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), static fn ($item) => $item > 0));
        }

        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded), static fn ($item) => $item > 0));
        }

        return array_values(array_filter(
            array_map('intval', array_map('trim', explode(',', $value))),
            static fn ($item) => $item > 0
        ));
    }
}

// ... rest of env variables ...

// Google Maps API Key
$googleMapsApiKey = $_SERVER['GOOGLE_MAPS_API_KEY'] ?? $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

// Project API
$projectApiBaseUrl = $_SERVER['PROJECT_API_BASE_URL'] ?? $_ENV['PROJECT_API_BASE_URL'] ?? '';
$laravelApiUrl = $_SERVER['LARAVEL_API_URL'] ?? $_ENV['LARAVEL_API_URL'] ?? '';
$appUrl = trackerAppUrl();

// Load Global Settings from Database via API
require_once __DIR__ . '/api_helper.php';
loadGlobalSettings();

// Firebase configuration variables
$firebaseConfig = [
    'apiKey' => $_ENV['FIREBASE_API_KEY'] ?? '',
    'authDomain' => $_ENV['FIREBASE_AUTH_DOMAIN'] ?? '',
    'projectId' => $_ENV['FIREBASE_PROJECT_ID'] ?? '',
    'storageBucket' => $_ENV['FIREBASE_STORAGE_BUCKET'] ?? '',
    'messagingSenderId' => $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?? '',
    'appId' => $_ENV['FIREBASE_APP_ID'] ?? '',
    'measurementId' => $_ENV['FIREBASE_MEASUREMENT_ID'] ?? '',
    'vapidKey' => $_ENV['FIREBASE_VAPID_KEY'] ?? ''
];

// Primary Database Credentials
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbDatabase = $_ENV['DB_DATABASE'] ?? '';
$dbUsername = $_ENV['DB_USERNAME'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

// Secondary Database Credentials
$dbSecondaryHost = $_ENV['DB_SECONDARY_HOST'] ?? 'localhost';
$dbSecondaryDatabase = $_ENV['DB_SECONDARY_DATABASE'] ?? '';
$dbSecondaryUsername = $_ENV['DB_SECONDARY_USERNAME'] ?? '';
$dbSecondaryPassword = $_ENV['DB_SECONDARY_PASSWORD'] ?? '';

// API credentials
$apiEmail = $_ENV['API_EMAIL'] ?? '';
$apiPassword = $_ENV['API_PASSWORD'] ?? '';

// Mail/SMTP Configuration
$mailHost = $_ENV['MAIL_HOST'] ?? '';
$mailUsername = $_ENV['MAIL_USERNAME'] ?? '';
$mailPassword = $_ENV['MAIL_PASSWORD'] ?? '';
$mailPort = $_ENV['MAIL_PORT'] ?? '587';

// Development/Testing
$seeding = $_ENV['SEEDING'] ?? false;

// Proposal settings
$company_id = $_ENV['COMPANY_ID'] ?? 1;
$added_by = $_ENV['ADDED_BY'] ?? 2;
$last_updated_by = $_ENV['LAST_UPDATED_BY'] ?? 2;
$currency_id = $_ENV['CURRENCY_ID'] ?? 3;
$vat_rate = $_ENV['VAT_RATE'] ?? 0.23;

// Cache file paths
$leads_cache_file = $_ENV['LEADS_CACHE_FILE'] ?? 'cache/leads_cache.json';
$tickets_cache_file = $_ENV['TICKETS_CACHE_FILE'] ?? 'cache/tickets_results.json';

// Eircode API Key
$eircode_api_key = $_ENV['EIRCODE_API_KEY'] ?? '';

// Google Analytics 4
$ga4_property_id = $_ENV['GA4_PROPERTY_ID'] ?? '';
$ga4_cache_file = $_ENV['GA4_CACHE_FILE'] ?? __DIR__ . '/cache/analytics2_data.json';

// Google Drive
$google_drive_credentials_path = $_ENV['GOOGLE_DRIVE_CREDENTIALS_PATH'] ?? '';
$gallery_folder_id = $GLOBALS['gallery_folder_id'] ?? $_ENV['GALLERY_FOLDER_ID'] ?? '';

// Email cache
$email_cache_dir = $_ENV['EMAIL_CACHE_DIR'] ?? 'cache';

// Google Calendar
$google_calendar_id = $GLOBALS['google_calendar_id'] ?? $_ENV['GOOGLE_CALENDAR_ID'] ?? '';
$vehicle_docs_endpoint = $_ENV['VEHICLE_DOCS_ENDPOINT'] ?? '/fuel/vehicle_docs_calendar.php';
$gun_licences_endpoint = $_ENV['GUN_LICENCES_ENDPOINT'] ?? '/jobs/gun_licences_calendar.php';
$lead_callout_endpoint = $_ENV['LEAD_CALLOUT_ENDPOINT'] ?? '/jobs/lead_callout_data.php';

// Google Stats
$google_stats_property_id = $_ENV['GOOGLE_STATS_PROPERTY_ID'] ?? '';
$google_stats_cache_file = $_ENV['GOOGLE_STATS_CACHE_FILE'] ?? 'cache/analytics_data.json';

// Gun licences
$gun_licence_upload_dir = $_ENV['GUN_LICENCE_UPLOAD_DIR'] ?? '/jobs/uploads/gun_license/';
$gun_licence_web_dir = $_ENV['GUN_LICENCE_WEB_DIR'] ?? '/jobs/uploads/gun_license/';

// Heatmap
$heatmap_cache_file = $_ENV['HEATMAP_CACHE_FILE'] ?? '/jobs/cache/leads_cache.json';
$heatmap_api_key = $_ENV['HEATMAP_API_KEY'] ?? '';

// Inventory
$inventory_driver_ids = trackerEnvIntList($_ENV['INVENTORY_DRIVER_IDS'] ?? []);

// Invoice2go
$invoice2go_api_url = $_ENV['INVOICE2GO_API_URL'] ?? 'https://api.invoice2go.com/v2/invoices';
$invoice2go_api_token = $_ENV['INVOICE2GO_API_TOKEN'] ?? '';

// Login
$login_auth_file = $_ENV['LOGIN_AUTH_FILE'] ?? '';
$login_credentials_file = $_ENV['LOGIN_CREDENTIALS_FILE'] ?? null;

// Map
$map_api_key = $_ENV['MAP_API_KEY'] ?? '';

// Social Media Post
$social_media_post_url = $_ENV['SOCIAL_MEDIA_POST_URL'] ?? '/postToSocial';

// Proposal Form Wrap
$proposal_form_wrap_leads_cache = $_ENV['PROPOSAL_FORM_WRAP_LEADS_CACHE'] ?? 'cache/leads.json';

// Firebase
$firebase_notification_role_id = $_ENV['FIREBASE_NOTIFICATION_ROLE_ID'] ?? 1;
$fcm_api_key = $_ENV['FCM_API_KEY'] ?? '';
$fcm_to = $_ENV['FCM_TO'] ?? '';

// Site Visits
$site_visits_lat = $GLOBALS['default_lat'] ?? $_ENV['SITE_VISITS_LAT'] ?? '';
$site_visits_lng = $GLOBALS['default_lng'] ?? $_ENV['SITE_VISITS_LNG'] ?? '';

// Submit
$submit_upload_dir = $_ENV['SUBMIT_UPLOAD_DIR'] ?? 'uploads/';

// Tasks List Item
$tasks_list_item_google_drive_url = $GLOBALS['google_script_drive_url'] ?? $_ENV['TASKS_LIST_ITEM_GOOGLE_DRIVE_URL'] ?? '';
$tasks_list_item_gallery_url = $GLOBALS['google_script_gallery_url'] ?? $_ENV['TASKS_LIST_ITEM_GALLERY_URL'] ?? '';

// XML Dump Leads
$xmldump_leads_latitude_from = $_ENV['XMLDUMP_LEADS_LATITUDE_FROM'] ?? '';
$xmldump_leads_longitude_from = $_ENV['XMLDUMP_LEADS_LONGITUDE_FROM'] ?? '';
