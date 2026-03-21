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

$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$allowedBranches = ['main', 'master'];
$branch = trim((string) ($_POST['branch'] ?? 'main'));
if (!in_array($branch, $allowedBranches, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid deploy branch']);
    exit();
}

$repoDir = '/home/workorders/trackers';
$scriptPath = $repoDir . '/deploy.sh';
$logDir = $repoDir . '/storage/deploy_logs';

if (!is_dir($repoDir . '/.git')) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Git is not configured for /home/workorders/trackers yet.',
    ]);
    exit();
}

if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare deploy log directory']);
    exit();
}

$timestamp = date('Ymd-His');
$logPath = $logDir . '/deploy-' . $timestamp . '.log';

// Determine home directory for the current user
$userName = posix_getpwuid(posix_geteuid())['name'];
$userHome = '/home/' . $userName;
$sshConfig = $userHome . '/.ssh/config';
$gitSshCommand = "ssh -F " . escapeshellarg($sshConfig);

$command = "export HOME=" . escapeshellarg($userHome) . " && export GIT_SSH_COMMAND=" . escapeshellarg($gitSshCommand) . " && bash " . escapeshellarg($scriptPath) . " " . escapeshellarg($branch) . " 2>&1";

$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);

$header = [
    'Deploy Time: ' . date('Y-m-d H:i:s'),
    'Requested By: ' . ($_SESSION['email'] ?? 'unknown'),
    'Branch: ' . $branch,
    'Exit Code: ' . $exitCode,
    str_repeat('-', 60),
];

file_put_contents($logPath, implode(PHP_EOL, array_merge($header, $output)) . PHP_EOL);

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? 'Deploy completed successfully.' : 'Deploy failed.',
    'branch' => $branch,
    'exit_code' => $exitCode,
    'log_path' => $logPath,
    'output' => implode("\n", $output),
]);
