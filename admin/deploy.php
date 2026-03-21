<?php
$pageTitle = "Tracker Deploy";
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    header('Location: ../index.php');
    exit();
}

$repoDir = '/home/workorders/trackers';
$allowedBranches = ['main', 'master'];
$repoConfigured = is_dir($repoDir . '/.git');
$currentBranch = $repoConfigured ? trim((string) @shell_exec('cd ' . escapeshellarg($repoDir) . ' && git rev-parse --abbrev-ref HEAD 2>/dev/null')) : '';
$currentCommit = $repoConfigured ? trim((string) @shell_exec('cd ' . escapeshellarg($repoDir) . ' && git rev-parse --short HEAD 2>/dev/null')) : '';
$lastCommitMessage = $repoConfigured ? trim((string) @shell_exec('cd ' . escapeshellarg($repoDir) . ' && git log -1 --pretty=%s 2>/dev/null')) : '';
$workingTreeDirty = $repoConfigured ? trim((string) @shell_exec('cd ' . escapeshellarg($repoDir) . ' && git status --porcelain 2>/dev/null')) !== '' : false;
$logDir = $repoDir . '/storage/deploy_logs';
$latestLog = '';

if (is_dir($logDir)) {
    $logs = glob($logDir . '/deploy-*.log') ?: [];
    rsort($logs);
    $latestLog = $logs[0] ?? '';
}

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Tracker Deploy</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Owner-only deploy trigger for `/home/workorders/trackers` using the GitHub repo.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back To Admin
            </a>
        </div>
    </div>

    <div class="mb-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/20 p-5">
        <div class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-300">Controlled Deploy</div>
        <p class="mt-2 text-sm font-medium text-indigo-900 dark:text-indigo-100">This page does not edit live files. It runs a guarded Git deploy script for the tracker repo only and logs the result.</p>
    </div>

    <?php if (!$repoConfigured): ?>
        <div class="mb-8 rounded-3xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-600 dark:text-amber-300">Git Not Configured</div>
            <p class="mt-2 text-sm font-medium text-amber-900 dark:text-amber-100">`/home/workorders/trackers` does not contain a `.git` directory yet, so admin deploys cannot run.</p>
            <div class="mt-4 text-xs leading-6 text-amber-800 dark:text-amber-200 font-mono">
                cd /home/workorders/trackers<br>
                git init<br>
                git remote add origin &lt;github-repo-url&gt;<br>
                git fetch origin<br>
                git checkout -b main --track origin/main
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Current Branch</div>
            <div class="mt-3 text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($currentBranch ?: 'unknown'); ?></div>
        </div>
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Current Commit</div>
            <div class="mt-3 text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($currentCommit ?: 'unknown'); ?></div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($lastCommitMessage ?: 'No commit message available'); ?></div>
        </div>
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm relative overflow-hidden" id="updateStatusCard">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Update Status</div>
            <div id="updateStatusText" class="mt-3 text-2xl font-black text-slate-900 dark:text-white">Checking...</div>
            <div id="updateStatusDetails" class="mt-2 text-xs text-slate-500 dark:text-slate-400 italic">Click check to see updates</div>
            <button id="checkUpdatesBtn" class="mt-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-indigo-100 dark:hover:bg-indigo-800/50 transition-all flex items-center gap-2">
                <i class="fas fa-sync-alt" id="syncIcon"></i> Check Updates
            </button>
        </div>
        <div class="rounded-3xl border <?php echo $workingTreeDirty ? 'border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20' : 'border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20'; ?> p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] <?php echo $workingTreeDirty ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300'; ?>">Working Tree</div>
            <div class="mt-3 text-2xl font-black <?php echo $workingTreeDirty ? 'text-amber-900 dark:text-amber-100' : 'text-emerald-900 dark:text-emerald-100'; ?>"><?php echo $workingTreeDirty ? 'Dirty' : 'Clean'; ?></div>
            <div class="mt-2 text-xs <?php echo !$repoConfigured ? 'text-slate-600 dark:text-slate-300' : ($workingTreeDirty ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'); ?>">
                <?php
                if (!$repoConfigured) {
                    echo 'Git deploy is unavailable until the repo is initialized.';
                } else {
                    echo $workingTreeDirty ? 'Deploy script will stop until local changes are resolved.' : 'Safe to run a fast-forward deploy.';
                }
                ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[0.9fr_1.1fr] gap-6">
        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-rocket text-indigo-400 mr-2"></i> Run Deploy</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Allowed branches only. No arbitrary shell input.</p>
            </div>
            <form id="deployForm" class="space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Branch</label>
                    <select name="branch" id="deployBranch" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($allowedBranches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $currentBranch === $branch ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4 text-xs text-slate-600 dark:text-slate-300">
                    The deploy script will:
                    <div class="mt-2 font-mono text-[11px] leading-6">
                        1. verify the repo is clean<br>
                        2. fetch origin<br>
                        3. checkout the selected branch<br>
                        4. pull with <code>--ff-only</code><br>
                        5. write a deploy log
                    </div>
                </div>
                <button type="submit" id="runDeployBtn" <?php echo !$repoConfigured ? 'disabled' : ''; ?> class="w-full <?php echo !$repoConfigured ? 'bg-slate-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700'; ?> text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-cloud-download-alt"></i> Deploy Tracker
                </button>
            </form>
        </div>

        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-terminal text-indigo-400 mr-2"></i> Deploy Output</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Latest run output and log reference.</p>
            </div>
            <div id="deployStatus" class="mb-4 text-sm font-bold text-slate-500 dark:text-slate-400">No deploy has been run in this session.</div>
            <pre id="deployOutput" class="min-h-[360px] whitespace-pre-wrap rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-950 p-6 text-xs leading-6 text-slate-200 overflow-auto"><?php
                if ($latestLog && is_readable($latestLog)) {
                    echo htmlspecialchars((string) file_get_contents($latestLog));
                } else {
                    echo 'No deploy log available yet.';
                }
            ?></pre>
            <?php if ($latestLog): ?>
                <div class="mt-4 text-[10px] font-black uppercase tracking-widest text-gray-400">
                    Latest Log: <?php echo htmlspecialchars($latestLog); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function getSwalTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
}

function checkUpdates() {
    const btn = $('#checkUpdatesBtn');
    const icon = $('#syncIcon');
    const branch = $('#deployBranch').val();
    
    btn.prop('disabled', true);
    icon.addClass('fa-spin');
    $('#updateStatusText').text('Checking...');
    
    $.ajax({
        url: 'check_updates.php',
        method: 'GET',
        data: { branch: branch },
        success: function(res) {
            btn.prop('disabled', false);
            icon.removeClass('fa-spin');
            
            if (res.success) {
                if (res.update_available) {
                    $('#updateStatusText').text('Update Available!').addClass('text-amber-600 dark:text-amber-400');
                    $('#updateStatusDetails').text('New: ' + res.remote_commit + ' - ' + res.remote_message);
                    $('#updateStatusCard').addClass('bg-amber-50/50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/50');
                } else {
                    $('#updateStatusText').text('Up to Date').removeClass('text-amber-600 dark:text-amber-400').addClass('text-emerald-600 dark:text-emerald-400');
                    $('#updateStatusDetails').text('Current: ' + res.local_commit);
                    $('#updateStatusCard').removeClass('bg-amber-50/50 dark:bg-amber-900/10 border-amber-200 dark:border-amber-800/50').addClass('bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800/50');
                }
            } else {
                $('#updateStatusText').text('Error');
                $('#updateStatusDetails').text(res.message);
            }
        },
        error: function() {
            btn.prop('disabled', false);
            icon.removeClass('fa-spin');
            $('#updateStatusText').text('Check Failed');
        }
    });
}

// Initial check on load
$(document).ready(function() {
    if ($('#checkUpdatesBtn').length) {
        checkUpdates();
    }
});

$('#checkUpdatesBtn').on('click', function(e) {
    e.preventDefault();
    checkUpdates();
});

$('#deployForm').on('submit', function(e) {
    e.preventDefault();

    const btn = $('#runDeployBtn');
    const original = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Deploying...');
    $('#deployStatus').text('Deploy started...');

    $.ajax({
        url: 'deploy_runner.php',
        method: 'POST',
        data: { branch: $('#deployBranch').val() },
        success: function(res) {
            btn.prop('disabled', false).html(original);
            $('#deployStatus').text(res.message || 'Deploy completed.');
            $('#deployOutput').text(res.output || 'No output returned.');

            Swal.fire({
                icon: res.success ? 'success' : 'error',
                title: res.success ? 'Deploy Complete' : 'Deploy Failed',
                text: res.message || 'Deploy finished.',
                theme: getSwalTheme()
            });
        },
        error: function(xhr) {
            btn.prop('disabled', false).html(original);
            const message = xhr.responseJSON?.message || 'Deploy request failed.';
            $('#deployStatus').text(message);
            $('#deployOutput').text(xhr.responseJSON?.output || message);
            Swal.fire({ icon: 'error', title: 'Deploy Failed', text: message, theme: getSwalTheme() });
        }
    });
});
</script>

<?php include '../footer.php'; ?>
