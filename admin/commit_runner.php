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
    echo json_encode(['success' => false, 'message' => 'Forbidden: Only SuperAdmin can commit.']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$message = trim((string) ($_POST['message'] ?? ''));
if (empty($message)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Commit message is required']);
    exit();
}

$repoDir = '/home/workorders/trackers';

if (!is_dir($repoDir . '/.git')) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Git not configured']);
    exit();
}

// Determine home directory for the current user
$userInfo = posix_getpwuid(posix_geteuid());
$userName = $userInfo['name'];
$userHome = $userInfo['dir'];

// Construct the git commands
// 1. git add .
// 2. git commit -m "..."
$commands = [
    "export HOME=" . escapeshellarg($userHome),
    "cd " . escapeshellarg($repoDir),
    "git add .",
    "git commit -m " . escapeshellarg($message)
];

$fullCommand = implode(' && ', $commands) . ' 2>&1';

$output = [];
$exitCode = 1;
exec($fullCommand, $output, $exitCode);

// Logging
$logDir = $repoDir . '/storage/deploy_logs';
if (is_dir($logDir) || mkdir($logDir, 0775, true)) {
    $timestamp = date('Ymd-His');
    $logPath = $logDir . '/deploy-' . $timestamp . '.log';
    $header = [
        'Action: Save (Commit)',
        'Time: ' . date('Y-m-d H:i:s'),
        'Requested By: ' . ($_SESSION['email'] ?? 'unknown'),
        'Message: ' . $message,
        'Exit Code: ' . $exitCode,
        str_repeat('-', 60),
    ];
    file_put_contents($logPath, implode(PHP_EOL, array_merge($header, $output)) . PHP_EOL);
}

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? 'Changes committed successfully.' : 'Failed to commit changes.',
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
]);
