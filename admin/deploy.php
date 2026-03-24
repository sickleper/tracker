<?php
$pageTitle = "Tracker Deploy";
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$isSuperAdmin = isTrackerSuperAdmin();
$isAdminUser = isTrackerAdminUser();

if (!$isAdminUser) {
    header('Location: ../index.php');
    exit();
}

$repoDir = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$allowedBranches = ['main', 'master'];
$repoConfigured = trim((string) @shell_exec('git -C ' . escapeshellarg($repoDir) . ' rev-parse --is-inside-work-tree 2>/dev/null')) === 'true';
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
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">System Maintenance</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1"><?php echo $isSuperAdmin ? 'Version control and synchronized updates across all instances.' : 'Update this site from GitHub using the office-safe update flow.'; ?></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back To Admin
            </a>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="grid grid-cols-1 <?php echo $isSuperAdmin ? 'lg:grid-cols-4' : 'lg:grid-cols-3'; ?> gap-6 mb-12">
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Current Branch</div>
            <div class="mt-3 text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($currentBranch ?: 'unknown'); ?></div>
        </div>
        
        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">Current Version</div>
            <div class="mt-3 text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($currentCommit ?: 'unknown'); ?></div>
            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400 truncate"><?php echo htmlspecialchars($lastCommitMessage ?: 'No message'); ?></div>
        </div>

        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-sm relative overflow-hidden" id="updateStatusCard">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">GitHub Sync</div>
            <div id="updateStatusText" class="mt-3 text-2xl font-black text-slate-900 dark:text-white">Checking...</div>
            <div id="updateStatusDetails" class="mt-2 text-xs text-slate-500 dark:text-slate-400 italic">...</div>
            <button id="checkUpdatesBtn" class="mt-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800 px-3 py-1.5 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-indigo-100 dark:hover:bg-indigo-800/50 transition-all flex items-center gap-2">
                <i class="fas fa-sync-alt" id="syncIcon"></i> Check Repository
            </button>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="rounded-3xl border <?php echo $workingTreeDirty ? 'border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20' : 'border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20'; ?> p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] <?php echo $workingTreeDirty ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300'; ?>">Local Changes</div>
            <div class="mt-3 text-2xl font-black <?php echo $workingTreeDirty ? 'text-amber-900 dark:text-amber-100' : 'text-emerald-900 dark:text-emerald-100'; ?>"><?php echo $workingTreeDirty ? 'Modified' : 'Clean'; ?></div>
            <div class="mt-2 text-xs <?php echo $workingTreeDirty ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300'; ?>">
                <?php echo $workingTreeDirty ? 'You have unsaved edits on this site.' : 'No local edits detected.'; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="rounded-3xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 p-6 shadow-sm">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-600 dark:text-emerald-300">Office Access</div>
            <div class="mt-3 text-2xl font-black text-emerald-900 dark:text-emerald-100">Update Only</div>
            <div class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">
                Office users can check GitHub and pull the latest version, but cannot commit or push code.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 3-Step Workflow: Horizontal Columns on Large Screens -->
    <div class="grid grid-cols-1 <?php echo $isSuperAdmin ? 'lg:grid-cols-3' : 'lg:grid-cols-1'; ?> gap-8 mb-12">
        
        <?php if ($isSuperAdmin): ?>
        <!-- STEP 1: SAVE (COMMIT) -->
        <div class="card-base relative flex flex-col <?php echo $workingTreeDirty ? 'ring-4 ring-amber-500/30' : 'opacity-70'; ?>">
            <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center font-black shadow-lg z-10">1</div>
            <div class="section-header">
                <h3>Save Local Edits</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Commit changes to this server</p>
            </div>
            
            <div class="p-6 flex-1 flex flex-col justify-center">
                <?php if ($workingTreeDirty): ?>
                <form id="commitForm" class="space-y-4">
                    <textarea id="commitMessage" name="message" rows="3" required placeholder="What did you change? (e.g., 'Update logo')" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-medium dark:text-white outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                    <button type="submit" id="runCommitBtn" class="w-full bg-amber-600 hover:bg-amber-700 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-save"></i> Step 1: Save
                    </button>
                </form>
                <?php else: ?>
                <div class="py-12 text-center text-emerald-600 dark:text-emerald-400">
                    <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-double text-2xl"></i>
                    </div>
                    <p class="font-black uppercase tracking-widest text-xs">Everything Saved</p>
                    <p class="text-xs opacity-70 mt-1">No pending local changes.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- STEP 2: SHARE (PUSH) -->
        <div id="step2Card" class="card-base relative flex flex-col <?php echo !$workingTreeDirty ? 'ring-4 ring-emerald-500/30' : 'opacity-50 pointer-events-none'; ?>">
            <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center font-black shadow-lg z-10">2</div>
            <div class="section-header">
                <h3>Share with GitHub</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Sync changes to master repo</p>
            </div>
            <div class="p-6 flex-1 flex flex-col justify-center">
                <div class="mb-8 text-center px-4">
                    <p id="pushStatusText" class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed italic">
                        Uploads your saved local version so other sites can see the updates.
                    </p>
                </div>
                <button type="button" id="runPushBtn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3 mt-auto">
                    <i class="fas fa-cloud-upload-alt"></i> Step 2: Push
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- STEP 3: UPDATE (PULL) -->
        <div id="step3Card" class="card-base relative flex flex-col border-indigo-100 dark:border-indigo-900/50">
            <div class="absolute -top-4 -left-4 w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-black shadow-lg z-10"><?php echo $isSuperAdmin ? '3' : '1'; ?></div>
            <div class="section-header">
                <h3>Update This Site</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Download latest from GitHub</p>
            </div>
            <div class="p-6 flex-1 flex flex-col justify-center">
                <form id="deployForm" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black uppercase tracking-widest text-gray-400 ml-1">Branch to pull:</label>
                        <select name="branch" id="deployBranch" class="w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                            <?php foreach ($allowedBranches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $currentBranch === $branch ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" id="runDeployBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                        <i class="fas fa-cloud-download-alt"></i> Step 3: Update
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Activity Log: Full Width Bottom -->
    <div class="card-base border-none bg-slate-900 text-slate-200 overflow-hidden flex flex-col">
        <div class="section-header border-slate-800 bg-slate-950/50 px-6 py-4">
            <h3 class="text-white flex items-center gap-3">
                <i class="fas fa-terminal text-indigo-400"></i> 
                Activity Log
                <span id="deployStatus" class="ml-4 px-3 py-1 bg-white/5 rounded-full text-[9px] font-black uppercase tracking-widest text-indigo-400">System Idle</span>
            </h3>
        </div>
        <pre id="deployOutput" class="p-8 text-[11px] font-mono leading-relaxed overflow-auto max-h-[600px] min-h-[300px] bg-slate-900"><?php
            if ($latestLog && is_readable($latestLog)) {
                echo htmlspecialchars((string) file_get_contents($latestLog));
            } else {
                echo 'System ready. Select a step above to begin.';
            }
        ?></pre>
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
    $('#updateStatusText').text('Syncing...');
    
    $.ajax({
        url: 'check_updates.php',
        method: 'GET',
        data: { branch: branch },
        success: function(res) {
            btn.prop('disabled', false);
            icon.removeClass('fa-spin');
            
            if (res.success) {
                // Update Step 2 (Push) UI
                const pushBtn = $('#runPushBtn');
                const pushCard = $('#step2Card');
                if (res.push_needed) {
                    $('#pushStatusText').text('You have ' + res.is_ahead + ' new commit(s) to push to GitHub.').removeClass('italic');
                    pushBtn.removeClass('bg-emerald-600 opacity-50').addClass('bg-amber-600 hover:bg-amber-700');
                    pushCard.addClass('ring-4 ring-amber-500/30');
                } else {
                    $('#pushStatusText').text('Local version matches GitHub. No push needed.').addClass('italic');
                    pushBtn.addClass('opacity-50');
                    pushCard.removeClass('ring-4 ring-amber-500/30');
                }

                // Update Step 3 (Update) UI
                const deployBtn = $('#runDeployBtn');
                const step3Card = $('#step3Card');
                if (res.update_available) {
                    $('#updateStatusText').text('Update Ready').addClass('text-amber-500');
                    $('#updateStatusDetails').text('New: ' + res.remote_commit + ' - ' + res.remote_message);
                    $('#updateStatusCard').addClass('bg-amber-50/10 border-amber-500/30');
                    deployBtn.addClass('ring-4 ring-indigo-500/50');
                    step3Card.addClass('ring-4 ring-indigo-500/30');
                } else {
                    $('#updateStatusText').text('Synced').removeClass('text-amber-500').addClass('text-emerald-500');
                    $('#updateStatusDetails').text('Matching GitHub @ ' + res.local_commit);
                    $('#updateStatusCard').removeClass('bg-amber-50/10 border-amber-500/30').addClass('bg-emerald-50/10 border-emerald-500/30');
                    deployBtn.removeClass('ring-4 ring-indigo-500/50');
                    step3Card.removeClass('ring-4 ring-indigo-500/30');
                }
            } else {
                $('#updateStatusText').text('Status Error');
                $('#updateStatusDetails').text(res.message);
            }
        },
        error: function() {
            btn.prop('disabled', false);
            icon.removeClass('fa-spin');
            $('#updateStatusText').text('Network Error');
        }
    });
}

$(document).ready(function() {
    if ($('#checkUpdatesBtn').length) {
        checkUpdates();
    }
});

$('#checkUpdatesBtn').on('click', function(e) {
    e.preventDefault();
    checkUpdates();
});

$('#commitForm').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#runCommitBtn');
    const original = btn.html();
    const message = $('#commitMessage').val();
    
    btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Saving...');
    
    $.ajax({
        url: 'commit_runner.php',
        method: 'POST',
        data: { message: message },
        success: function(res) {
            btn.prop('disabled', false).html(original);
            $('#deployOutput').text(res.output || res.message);
            
            Swal.fire({
                icon: res.success ? 'success' : 'error',
                title: res.success ? 'Changes Saved' : 'Save Failed',
                text: res.message,
                theme: getSwalTheme()
            }).then(() => {
                if (res.success) window.location.reload();
            });
        },
        error: function(xhr) {
            btn.prop('disabled', false).html(original);
            const message = xhr.responseJSON?.message || 'Save request failed.';
            Swal.fire({ icon: 'error', title: 'Error', text: message, theme: getSwalTheme() });
        }
    });
});

$('#runPushBtn').on('click', function(e) {
    e.preventDefault();
    const btn = $(this);
    const original = btn.html();
    const branch = $('#deployBranch').val();
    
    btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Sharing...');
    $('#deployStatus').text('Uploading to GitHub...');

    $.ajax({
        url: 'push_runner.php',
        method: 'POST',
        data: { branch: branch },
        success: function(res) {
            btn.prop('disabled', false).html(original);
            $('#deployStatus').text(res.message || 'Push completed.');
            $('#deployOutput').text(res.output || 'No output returned.');

            Swal.fire({
                icon: res.success ? 'success' : 'error',
                title: res.success ? 'Changes Shared' : 'Share Failed',
                text: res.message,
                theme: getSwalTheme()
            }).then(() => {
                if (res.success) checkUpdates();
            });
        },
        error: function(xhr) {
            btn.prop('disabled', false).html(original);
            const message = xhr.responseJSON?.message || 'Share request failed.';
            $('#deployStatus').text(message);
            $('#deployOutput').text(xhr.responseJSON?.output || message);
            Swal.fire({ icon: 'error', title: 'Share Failed', text: message, theme: getSwalTheme() });
        }
    });
});

$('#deployForm').on('submit', function(e) {
    e.preventDefault();

    const btn = $('#runDeployBtn');
    const original = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Updating...');
    $('#deployStatus').text('Downloading from GitHub...');

    $.ajax({
        url: 'deploy_runner.php',
        method: 'POST',
        data: { branch: $('#deployBranch').val() },
        success: function(res) {
            btn.prop('disabled', false).html(original);
            $('#deployStatus').text(res.message || 'Update completed.');
            $('#deployOutput').text(res.output || 'No output returned.');

            Swal.fire({
                icon: res.success ? 'success' : 'error',
                title: res.success ? 'Site Updated' : 'Update Failed',
                text: res.message,
                theme: getSwalTheme()
            });
        },
        error: function(xhr) {
            btn.prop('disabled', false).html(original);
            const message = xhr.responseJSON?.message || 'Update request failed.';
            $('#deployStatus').text(message);
            $('#deployOutput').text(xhr.responseJSON?.output || message);
            Swal.fire({ icon: 'error', title: 'Update Failed', text: message, theme: getSwalTheme() });
        }
    });
});
</script>

<?php include '../footer.php'; ?>
