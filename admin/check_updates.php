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

if (!isTrackerAdminUser()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

// Determine home directory for the current user
$userInfo = posix_getpwuid(posix_geteuid());
$userName = $userInfo['name'];
$userHome = $userInfo['dir'];
$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);

if (!trackerGitIsConfigured($repoDir)) {
    echo json_encode(['success' => false, 'message' => 'Git not configured']);
    exit();
}

// Fetch from remote using the web user's SSH config when present, otherwise the repo-level SSH config.
$cmd = trackerGitCommand($repoDir, 'fetch origin', trackerGitSshEnv($repoDir, $userHome)) . ' 2>&1';
exec($cmd, $fetchOutput, $fetchExitCode);

if ($fetchExitCode !== 0) {
    $sshDiagnostics = trackerGitSshDiagnostics($repoDir, $userHome);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to fetch from remote', 
        'output' => implode("\n", $fetchOutput),
        'ssh_diagnostics' => $sshDiagnostics,
    ]);
    exit();
}

$branch = trim((string) ($_GET['branch'] ?? 'main'));
if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $branch)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid branch']);
    exit();
}

$localCommit = trackerGitOutput($repoDir, 'rev-parse ' . escapeshellarg($branch));
$remoteCommit = trackerGitOutput($repoDir, 'rev-parse ' . escapeshellarg('origin/' . $branch));

$isAhead = (int) trackerGitOutput($repoDir, 'rev-list --count ' . escapeshellarg('origin/' . $branch . '..' . $branch));
$isBehind = (int) trackerGitOutput($repoDir, 'rev-list --count ' . escapeshellarg($branch . '..origin/' . $branch));

// Get remote commit message if update available
$remoteMessage = '';
if ($isBehind > 0) {
    $remoteMessage = trackerGitOutput($repoDir, 'log -1 --pretty=%s ' . escapeshellarg('origin/' . $branch));
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
