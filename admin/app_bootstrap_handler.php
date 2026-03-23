<?php
require_once '../config.php';

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.',
    ]);
    exit();
}

if (!function_exists('isTrackerSuperAdmin') || !isTrackerSuperAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Superadmin access required.',
    ]);
    exit();
}

function normalizeBootstrapUrl(string $value): string
{
    return rtrim(trim($value), '/');
}

function validateBootstrapUrl(string $value, string $label): ?string
{
    if ($value === '') {
        return null;
    }

    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return $label . ' must be a valid absolute URL.';
    }

    return null;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    echo json_encode([
        'success' => true,
        'data' => trackerLoadBootstrapConfig(true),
    ]);
    exit();
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit();
}

$appName = trim((string) ($_POST['app_name'] ?? ''));
$appUrl = normalizeBootstrapUrl((string) ($_POST['app_url'] ?? ''));
$laravelApiUrl = normalizeBootstrapUrl((string) ($_POST['laravel_api_url'] ?? ''));
$defaultTenantSlug = trim((string) ($_POST['default_tenant'] ?? $_POST['default_tenant_slug'] ?? ''));

if ($defaultTenantSlug !== '') {
    $defaultTenantSlug = preg_replace('/[^a-zA-Z0-9._-]/', '', $defaultTenantSlug) ?? '';
}

$errors = array_filter([
    validateBootstrapUrl($appUrl, 'Tracker App URL'),
    validateBootstrapUrl($laravelApiUrl, 'Laravel API URL'),
]);

if ($errors !== []) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors),
    ]);
    exit();
}

try {
    $savedConfig = trackerSaveBootstrapConfig([
        'app_name' => $appName,
        'app_url' => $appUrl,
        'laravel_api_url' => $laravelApiUrl,
        'default_tenant' => $defaultTenantSlug,
    ], (string) ($_SESSION['email'] ?? $_SESSION['user_name'] ?? ''));

    echo json_encode([
        'success' => true,
        'message' => 'App connection settings saved.',
        'data' => $savedConfig,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
