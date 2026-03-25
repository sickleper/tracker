<?php
require_once '../config.php';
require_once '../api_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isTrackerSuperAdmin() || !trackerIsPrimaryApp()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$targetDir = trim((string)($input['target_dir'] ?? ''));
$appName = trim((string)($input['app_name'] ?? ''));
$appUrl = trim((string)($input['app_url'] ?? ''));
$apiUrl = trim((string)($input['api_url'] ?? $_ENV['LARAVEL_API_URL'] ?? ''));
$tenantSlug = trim((string)($input['tenant_slug'] ?? ''));
$appMode = trim((string)($input['app_mode'] ?? 'tenant'));
$branch = trim((string)($input['branch'] ?? 'main'));

// Admin User Info
$adminEmail = trim((string)($input['admin_email'] ?? ''));
$adminName = trim((string)($input['admin_name'] ?? 'Admin'));
$adminPassword = (string)($input['admin_password'] ?? '');

if (!$targetDir || !$appName || !$appUrl || !$apiUrl || !$tenantSlug) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required provisioning parameters.']);
    exit();
}

// Security: Validate target directory name
if (preg_match('/[^a-zA-Z0-9_-]/', $targetDir)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid target directory name.']);
    exit();
}

// 1. Ensure Tenant exists in API
$tenantsRes = makeApiCall('/api/tenants');
$existingTenant = null;
if ($tenantsRes && ($tenantsRes['success'] ?? false)) {
    foreach (($tenantsRes['data'] ?? []) as $tenant) {
        if ($tenant['slug'] === $tenantSlug) {
            $existingTenant = $tenant;
            break;
        }
    }
}

$tenantId = null;
if (!$existingTenant) {
    $createTenantRes = makeApiCall('/api/tenants', [
        'name' => $appName,
        'slug' => $tenantSlug,
        'status' => 'active'
    ], 'POST');

    if (!$createTenantRes || !($createTenantRes['success'] ?? false)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create tenant in API: ' . ($createTenantRes['message'] ?? 'Unknown error')]);
        exit();
    }
    $tenantId = $createTenantRes['data']['id'] ?? null;
} else {
    $tenantId = $existingTenant['id'];
}

// 2. Create or Assign Admin User if email provided
if ($adminEmail !== '') {
    // Check if user already exists
    $usersRes = makeApiCall('/api/users?all=1');
    $targetUser = null;
    if ($usersRes && ($usersRes['success'] ?? false)) {
        foreach (($usersRes['users'] ?? []) as $user) {
            if (strtolower($user['email']) === strtolower($adminEmail)) {
                $targetUser = $user;
                break;
            }
        }
    }

    if (!$targetUser) {
        $userPayload = [
            'email' => $adminEmail,
            'name' => $adminName,
            'is_office' => 1,
            'status' => 'active',
            'tenant_id' => $tenantId
        ];
        if ($adminPassword !== '') {
            $userPayload['password'] = $adminPassword;
        }
        makeApiCall('/api/users/create', $userPayload, 'POST');
    } else {
        // User exists, assign to tenant and ensure office role
        makeApiCall("/api/tenants/{$tenantId}/assign-user", [
            'user_id' => $targetUser['id'],
            'is_office' => 1,
            'cascade' => true
        ], 'POST');
    }
}

// 3. Provision physical instance
$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$scriptPath = $repoDir . '/provision_tenant_clone.sh';

if (!is_executable($scriptPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Provisioning script not found or not executable.']);
    exit();
}

$command = sprintf(
    "bash %s %s %s %s %s %s %s %s 2>&1",
    escapeshellarg($scriptPath),
    escapeshellarg($targetDir),
    escapeshellarg($appName),
    escapeshellarg($appUrl),
    escapeshellarg($apiUrl),
    escapeshellarg($tenantSlug),
    escapeshellarg($appMode),
    escapeshellarg($branch)
);

$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? "Successfully provisioned '{$appName}' and configured admin." : 'Provisioning failed.',
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
]);
