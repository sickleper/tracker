<?php
require_once '../config.php';
require_once 'git_runtime.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isTrackerSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only SuperAdmin can push.']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$branch = trim((string) ($_POST['branch'] ?? 'main'));
$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);

if (!trackerGitIsConfigured($repoDir)) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Git not configured']);
    exit();
}

if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $branch)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid branch']);
    exit();
}

// Determine home directory for the current user
$userInfo = posix_getpwuid(posix_geteuid());
$userName = $userInfo['name'];
$userHome = $userInfo['dir'];

// Use the web user's SSH config when present, otherwise the repo-level SSH config.
$cmd = trackerGitCommand($repoDir, 'push origin ' . escapeshellarg($branch), trackerGitSshEnv($repoDir, $userHome)) . ' 2>&1';

$output = [];
$exitCode = 1;
exec($cmd, $output, $exitCode);

// Logging
$logDir = $repoDir . '/storage/deploy_logs';
if (is_dir($logDir) || mkdir($logDir, 0775, true)) {
    $timestamp = date('Ymd-His');
    $logPath = $logDir . '/deploy-' . $timestamp . '.log';
    $header = [
        'Action: Share (Push)',
        'Time: ' . date('Y-m-d H:i:s'),
        'Requested By: ' . ($_SESSION['email'] ?? 'unknown'),
        'Branch: ' . $branch,
        'Exit Code: ' . $exitCode,
        str_repeat('-', 60),
    ];
    file_put_contents($logPath, implode(PHP_EOL, array_merge($header, $output)) . PHP_EOL);
}

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? 'Changes pushed to GitHub successfully.' : 'Failed to push changes to GitHub.',
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
]);
