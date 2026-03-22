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
// We use the same SSH config logic as deploy and check_updates
$sshConfig = $userHome . '/.ssh/config';
$gitSshCommand = "ssh -F " . escapeshellarg($sshConfig);

$cmd = "export HOME=" . escapeshellarg($userHome) . " && export GIT_SSH_COMMAND=" . escapeshellarg($gitSshCommand) . " && cd " . escapeshellarg($repoDir) . " && git push origin " . escapeshellarg($branch) . " 2>&1";

$output = [];
$exitCode = 1;
exec($cmd, $output, $exitCode);

echo json_encode([
    'success' => $exitCode === 0,
    'message' => $exitCode === 0 ? 'Changes pushed to GitHub successfully.' : 'Failed to push changes to GitHub.',
    'exit_code' => $exitCode,
    'output' => implode("\n", $output),
]);
