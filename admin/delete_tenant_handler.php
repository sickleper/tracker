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

$tenantId = (int)($input['tenant_id'] ?? 0);
$targetDir = trim((string)($input['target_dir'] ?? ''));

if (!$tenantId || !$targetDir) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing required tenant deletion parameters.']);
    exit();
}

if ($tenantId === 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'The Default Tenant (ID 1) can never be deleted!']);
    exit();
}

// Security: No path traversal
if (preg_match('/[^a-zA-Z0-9_-]/', $targetDir)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid target directory name.']);
    exit();
}

// 1. CALL API TO DELETE TENANT RECORDS
$deleteApiRes = makeApiCall("/api/tenants/{$tenantId}", [], 'DELETE');

// Proceed if the API call was successful OR if the tenant was already gone (404)
if (!$deleteApiRes || (!($deleteApiRes['success'] ?? false) && strpos($deleteApiRes['message'] ?? '', 'No query results for model') === false)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete tenant database records: ' . ($deleteApiRes['message'] ?? 'API error')]);
    exit();
}

// 2. RUN SHELL SCRIPT TO DELETE PHYSICAL DIRECTORY
$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$scriptPath = $repoDir . '/delete_tenant_clone.sh';

if (!is_executable($scriptPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Physical deletion script not found or not executable. DB records were deleted.']);
    exit();
}

$command = sprintf("sudo bash %s %s 2>&1", escapeshellarg($scriptPath), escapeshellarg($targetDir));
$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? "Successfully deleted tenant records and directory '{$targetDir}'." : "DB records deleted but directory removal failed.",
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
]);
