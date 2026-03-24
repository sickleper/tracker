<?php
require_once '../config.php';

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

if (trim((string) shell_exec('git -C ' . escapeshellarg($repoDir) . ' rev-parse --is-inside-work-tree 2>/dev/null')) !== 'true') {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Git not configured']);
    exit();
}

// Determine home directory for the current user
$userInfo = posix_getpwuid(posix_geteuid());
$userName = $userInfo['name'];
$userHome = $userInfo['dir'];

// Construct the git commands
// We use the same SSH config logic as deploy and check_updates
$sshConfig = $userHome . '/.ssh/config';
$gitSshCommand = "ssh -F " . escapeshellarg($sshConfig);

$cmd = "export HOME=" . escapeshellarg($userHome) . " && export GIT_SSH_COMMAND=" . escapeshellarg($gitSshCommand) . " && cd " . escapeshellarg($repoDir) . " && git push origin " . escapeshellarg($branch) . " 2>&1";

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
