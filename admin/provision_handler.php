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
$adminConfigured = false;
$adminStatusMessage = '';
$stages = [];

$setStage = static function (string $key, string $status, string $message, array $extra = []) use (&$stages): void {
    $stages[$key] = array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra);
};

if (!$targetDir || !$appName || !$appUrl || !$apiUrl || !$tenantSlug) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required provisioning parameters.', 'stages' => $stages]);
    exit();
}

if (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid app URL.', 'stages' => $stages]);
    exit();
}

if (!filter_var($apiUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $apiUrl)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid API URL.', 'stages' => $stages]);
    exit();
}

if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $tenantSlug)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid tenant slug.', 'stages' => $stages]);
    exit();
}

if (!in_array($appMode, ['primary', 'tenant'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid app mode.', 'stages' => $stages]);
    exit();
}

if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $branch)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid branch name.', 'stages' => $stages]);
    exit();
}

// Security: Validate target directory name
if (preg_match('/[^a-zA-Z0-9_-]/', $targetDir)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid target directory name.', 'stages' => $stages]);
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
        $setStage('tenant', 'failed', 'Tenant creation failed.', [
            'api_message' => $createTenantRes['message'] ?? 'Unknown error',
        ]);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create tenant in API: ' . ($createTenantRes['message'] ?? 'Unknown error'),
            'stages' => $stages,
        ]);
        exit();
    }
    $tenantId = $createTenantRes['data']['id'] ?? null;
    $setStage('tenant', 'created', 'Tenant created in API.', ['tenant_id' => $tenantId]);
} else {
    $tenantId = $existingTenant['id'];
    $setStage('tenant', 'existing', 'Tenant already existed in API.', ['tenant_id' => $tenantId]);
}

// 2. Create or Assign Admin User if email provided
if ($adminEmail !== '') {
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid admin email address.', 'stages' => $stages]);
        exit();
    }

    $assignAdminToTenant = static function (int $tenantId, int $userId): array {
        return makeApiCall("/api/tenants/{$tenantId}/assign-user", [
            'user_id' => $userId,
            'is_office' => 1,
            'cascade' => true
        ], 'POST') ?: ['success' => false, 'message' => 'Assignment request failed.'];
    };

    $userPayload = [
        'name' => $adminName !== '' ? $adminName : 'Admin',
        'email' => $adminEmail,
        'status' => 'active',
        'is_office' => 1,
    ];

    if ($adminPassword !== '') {
        $userPayload['password'] = $adminPassword;
    }

    // Attempt to create the user
    $createUserRes = makeApiCall('/api/users/create', $userPayload, 'POST');

    // If user creation failed, it might be because they already exist.
    // Check for the specific error message and attempt to assign them instead.
    if ($createUserRes && ($createUserRes['success'] ?? false)) {
        $createdUserId = (int)($createUserRes['data']['id'] ?? $createUserRes['user']['id'] ?? 0);
        if ($createdUserId > 0) {
            $assignRes = $assignAdminToTenant($tenantId, $createdUserId);
            if ($assignRes['success'] ?? false) {
                $adminConfigured = true;
                $adminStatusMessage = 'Admin user created and assigned.';
                $setStage('admin', 'configured', $adminStatusMessage, ['email' => $adminEmail, 'user_id' => $createdUserId]);
            } else {
                $adminStatusMessage = 'Admin user created, but tenant assignment failed: ' . ($assignRes['message'] ?? 'Unknown error');
                $setStage('admin', 'partial', $adminStatusMessage, ['email' => $adminEmail, 'user_id' => $createdUserId]);
            }
        } else {
            $adminStatusMessage = 'Admin user created, but the API did not return a user ID.';
            $setStage('admin', 'partial', $adminStatusMessage, ['email' => $adminEmail]);
        }
    } else {
        if (strpos($createUserRes['message'] ?? '', 'The email has already been taken') !== false) {
            // Find the user ID to perform the assignment
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

            if ($targetUser) {
                $assignRes = $assignAdminToTenant($tenantId, (int)$targetUser['id']);
                if ($assignRes['success'] ?? false) {
                    $adminConfigured = true;
                    $adminStatusMessage = 'Existing admin user assigned to tenant.';
                    $setStage('admin', 'configured', $adminStatusMessage, ['email' => $adminEmail, 'user_id' => (int)$targetUser['id']]);
                } else {
                    $adminStatusMessage = 'Existing user found, but tenant assignment failed: ' . ($assignRes['message'] ?? 'Unknown error');
                    $setStage('admin', 'partial', $adminStatusMessage, ['email' => $adminEmail, 'user_id' => (int)$targetUser['id']]);
                }
            } else {
                $adminStatusMessage = 'Admin email already exists, but the matching user could not be resolved.';
                $setStage('admin', 'failed', $adminStatusMessage, ['email' => $adminEmail]);
            }
        } else {
            $adminStatusMessage = 'Admin user setup failed: ' . ($createUserRes['message'] ?? 'Unknown error');
            $setStage('admin', 'failed', $adminStatusMessage, ['email' => $adminEmail]);
        }
    }
} else {
    $setStage('admin', 'skipped', 'No admin email was provided.');
}

// 3. Provision physical instance
$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$scriptPath = $repoDir . '/provision_tenant_clone.sh';

if (!is_executable($scriptPath)) {
    $setStage('clone', 'failed', 'Provisioning script not found or not executable.');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Provisioning script not found or not executable.', 'stages' => $stages]);
    exit();
}

$command = sprintf(
    "/usr/bin/bash %s %s %s %s %s %s %s %s 2>&1",
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
$setStage(
    'clone',
    $exitCode === 0 ? 'configured' : 'failed',
    $exitCode === 0 ? 'Tenant clone created and bootstrapped.' : 'Tenant clone provisioning failed.',
    ['path' => $repoDir . '/../' . $targetDir, 'exit_code' => $exitCode]
);

$successMessage = $exitCode === 0
    ? ($adminEmail !== ''
        ? ($adminConfigured
            ? "Successfully provisioned '{$appName}' and configured admin."
            : "Successfully provisioned '{$appName}', but admin setup is incomplete.")
        : "Successfully provisioned '{$appName}'.")
    : 'Provisioning failed.';

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $successMessage,
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
    'admin_status' => $adminStatusMessage,
    'stages' => $stages,
]);
