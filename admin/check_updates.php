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
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Determine home directory for the current user
$userInfo = posix_getpwuid(posix_geteuid());
$userName = $userInfo['name'];
$userHome = $userInfo['dir'];
$repoDir = '/home/workorders/trackers';

if (!is_dir($repoDir . '/.git')) {
    echo json_encode(['success' => false, 'message' => 'Git not configured']);
    exit();
}

// Fetch from remote - force HOME and explicitly set GIT_SSH_COMMAND to use the correct config
$sshConfig = $userHome . '/.ssh/config';
$gitSshCommand = "ssh -F " . escapeshellarg($sshConfig);

$cmd = "export HOME=" . escapeshellarg($userHome) . " && export GIT_SSH_COMMAND=" . escapeshellarg($gitSshCommand) . " && cd " . escapeshellarg($repoDir) . " && git fetch origin 2>&1";
exec($cmd, $fetchOutput, $fetchExitCode);

if ($fetchExitCode !== 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch from remote', 
        'output' => implode("\n", $fetchOutput)
    ]);
    exit();
}

$branch = trim((string) ($_GET['branch'] ?? 'main'));
$localCommit = trim((string) shell_exec("cd " . escapeshellarg($repoDir) . " && git rev-parse $branch"));
$remoteCommit = trim((string) shell_exec("cd " . escapeshellarg($repoDir) . " && git rev-parse origin/$branch"));

$isAhead = (int) shell_exec("cd " . escapeshellarg($repoDir) . " && git rev-list --count origin/$branch..$branch");
$isBehind = (int) shell_exec("cd " . escapeshellarg($repoDir) . " && git rev-list --count $branch..origin/$branch");

// Get remote commit message if update available
$remoteMessage = '';
if ($isBehind > 0) {
    $remoteMessage = trim((string) shell_exec("cd " . escapeshellarg($repoDir) . " && git log -1 --pretty=%s origin/$branch"));
}

echo json_encode([
    'success' => true,
    'update_available' => ($isBehind > 0),
    'push_needed' => ($isAhead > 0),
    'is_ahead' => $isAhead,
    'is_behind' => $isBehind,
    'local_commit' => substr($localCommit, 0, 7),
    'remote_commit' => substr($remoteCommit, 0, 7),
    'remote_message' => $remoteMessage,
    'branch' => $branch
]);
