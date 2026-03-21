<?php
require_once __DIR__ . '/../vendor/autoload.php';

// config.php

// Load .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // Path to workorders project root
$dotenv->load();

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

// ... rest of env variables ...

// Google Maps API Key
$googleMapsApiKey = $_SERVER['GOOGLE_MAPS_API_KEY'] ?? $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

// Project API
$projectApiBaseUrl = $_SERVER['PROJECT_API_BASE_URL'] ?? $_ENV['PROJECT_API_BASE_URL'] ?? '';
$laravelApiUrl = $_SERVER['LARAVEL_API_URL'] ?? $_ENV['LARAVEL_API_URL'] ?? '';

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
$ga4_property_id = $_ENV['GA4_PROPERTY_ID'] ?? '431987483';
$ga4_cache_file = $_ENV['GA4_CACHE_FILE'] ?? __DIR__ . '/cache/analytics2_data.json';

// Google Drive
$google_drive_credentials_path = $_ENV['GOOGLE_DRIVE_CREDENTIALS_PATH'] ?? '';
$gallery_folder_id = $GLOBALS['gallery_folder_id'] ?? $_ENV['GALLERY_FOLDER_ID'] ?? '1IkR1DOk2v_HHKRXHJHTaajPutQQioAv4';

// Email cache
$email_cache_dir = $_ENV['EMAIL_CACHE_DIR'] ?? 'cache';

// Google Calendar
$google_calendar_id = $GLOBALS['google_calendar_id'] ?? $_ENV['GOOGLE_CALENDAR_ID'] ?? 'admin@energyretrofitireland.ie';
$vehicle_docs_endpoint = $_ENV['VEHICLE_DOCS_ENDPOINT'] ?? '/fuel/vehicle_docs_calendar.php';
$gun_licences_endpoint = $_ENV['GUN_LICENCES_ENDPOINT'] ?? '/jobs/gun_licences_calendar.php';
$lead_callout_endpoint = $_ENV['LEAD_CALLOUT_ENDPOINT'] ?? '/jobs/lead_callout_data.php';

// Google Stats
$google_stats_property_id = $_ENV['GOOGLE_STATS_PROPERTY_ID'] ?? '431987483';
$google_stats_cache_file = $_ENV['GOOGLE_STATS_CACHE_FILE'] ?? 'cache/analytics_data.json';

// Gun licences
$gun_licence_upload_dir = $_ENV['GUN_LICENCE_UPLOAD_DIR'] ?? '/var/www/html/project-management/public/jobs/uploads/gun_license/';
$gun_licence_web_dir = $_ENV['GUN_LICENCE_WEB_DIR'] ?? 'jobs/uploads/gun_license/';

// Handyman
$handyman_category_id = $_ENV['HANDYMAN_CATEGORY_ID'] ?? 5;

// Heatmap
$heatmap_cache_file = $_ENV['HEATMAP_CACHE_FILE'] ?? '/var/www/html/project-management/public/jobs/cache/leads_cache.json';
$heatmap_api_key = $_ENV['HEATMAP_API_KEY'] ?? '';

// Inventory
$inventory_driver_ids = $_ENV['INVENTORY_DRIVER_IDS'] ?? [1, 2, 82, 89];

// Invoice2go
$invoice2go_api_url = $_ENV['INVOICE2GO_API_URL'] ?? 'https://api.invoice2go.com/v2/invoices';
$invoice2go_api_token = $_ENV['INVOICE2GO_API_TOKEN'] ?? '';

// Bathrooms
$bathrooms_category_id = $_ENV['BATHROOMS_CATEGORY_ID'] ?? 4;

// Retrofit
$retrofit_category_id = $_ENV['RETROFIT_CATEGORY_ID'] ?? 1;

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
$site_visits_lat = $GLOBALS['default_lat'] ?? $_ENV['SITE_VISITS_LAT'] ?? '53.4106';
$site_visits_lng = $GLOBALS['default_lng'] ?? $_ENV['SITE_VISITS_LNG'] ?? '-6.4426';

// Submit
$submit_upload_dir = $_ENV['SUBMIT_UPLOAD_DIR'] ?? 'uploads/';

// Tasks List Item
$tasks_list_item_google_drive_url = $GLOBALS['google_script_drive_url'] ?? $_ENV['TASKS_LIST_ITEM_GOOGLE_DRIVE_URL'] ?? 'https://script.google.com/macros/s/AKfycbxuXfuRKhb45bKGGHaxw71KRK2VNgp6YzSIMSXDg4XgkCDOtKlXgveSjgloMt8EH2Xo/exec';
$tasks_list_item_gallery_url = $GLOBALS['google_script_gallery_url'] ?? $_ENV['TASKS_LIST_ITEM_GALLERY_URL'] ?? 'https://script.google.com/a/macros/energyretrofitireland.ie/s/AKfycbwgQX6DqDgy-CGdmVIOXg9IfK4m-xmHCJDYd2rfdSV-Qqq1XPpO-wHZ_ZbvCBSFOKmN_w/exec';

// XML Dump Leads
$xmldump_leads_latitude_from = $_ENV['XMLDUMP_LEADS_LATITUDE_FROM'] ?? '53.4106';
$xmldump_leads_longitude_from = $_ENV['XMLDUMP_LEADS_LONGITUDE_FROM'] ?? '-6.4426';
