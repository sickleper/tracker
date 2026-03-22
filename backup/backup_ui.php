<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

require_once "../tracker_data.php";

// SECURITY: Only allow the configured super admin to access this page
$superAdminEmail = trackerSuperAdminEmail();
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    header('Location: ../index.php');
    exit();
}

$pageTitle = "Database Backups";
include "../header.php";
include "../nav.php";
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Top Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Database Backups</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage and download your system backups securely.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
            <button id="directDownloadBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black uppercase tracking-widest py-4 px-8 rounded-2xl shadow-xl transition-all active:scale-95 flex items-center justify-center gap-2 text-xs">
                <i class="fas fa-file-download"></i> Direct Download
            </button>
            <button id="startBackupBtn" class="bg-gray-900 dark:bg-slate-800 hover:bg-gray-800 text-white font-black uppercase tracking-widest py-4 px-8 rounded-2xl shadow-xl transition-all active:scale-95 flex items-center justify-center gap-2 text-xs">
                <i class="fas fa-database text-emerald-500"></i> Run Background
            </button>
        </div>
    </div>

    <!-- Progress Section -->
    <div id="progressSection" class="hidden mb-8 bg-blue-50/50 dark:bg-blue-900/20 p-8 rounded-3xl border border-blue-100 dark:border-blue-900/50 relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
        <h3 class="text-lg font-black text-gray-800 dark:text-white uppercase italic tracking-tight mb-4 flex items-center gap-3">
            <span class="inline-block w-3 h-3 rounded-full bg-blue-500 animate-pulse"></span>
            System Backup in Progress...
        </h3>
        <div class="w-full bg-gray-200 dark:bg-slate-800 rounded-full h-3 mb-4 overflow-hidden shadow-inner">
            <div id="progressBar" class="bg-gradient-to-r from-blue-500 to-indigo-500 h-3 rounded-full transition-all duration-500 relative" style="width: 0%">
                <div class="absolute inset-0 bg-white/30 animate-[shimmer_2s_infinite]"></div>
            </div>
        </div>
        <p id="progressText" class="text-[10px] text-gray-600 dark:text-blue-300 font-black uppercase tracking-widest bg-white/80 dark:bg-slate-900 inline-block px-4 py-2 rounded-xl border border-gray-200 dark:border-blue-900/50 shadow-sm">Initializing system check...</p>
    </div>

    <!-- Backups List -->
    <div class="card-base border-none">
        <div class="table-container">
            <table class="min-w-full text-sm text-left">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-8 py-4">Date / Time</th>
                        <th class="px-8 py-4">Folder Name</th>
                        <th class="px-8 py-4">Total Size</th>
                        <th class="px-8 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="backupList" class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                    <tr>
                        <td colspan="4" class="px-8 py-24 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500"></i>
                                <span class="font-black text-gray-400 uppercase tracking-[0.2em] text-[10px]">Syncing with local storage...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<script>
$(document).ready(function() {
    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    }

    loadBackups();
    checkProgress();

    setInterval(checkProgress, 2000);

    $('#directDownloadBtn').click(function() {
        Swal.fire({
            title: 'Generating Backup...',
            text: 'Preparing secure download. This may take a moment.',
            icon: 'info',
            showConfirmButton: false,
            allowOutsideClick: false,
            theme: getSwalTheme(),
            didOpen: () => {
                Swal.showLoading();
                window.location.href = 'backup_action.php?action=run_and_download';
                setTimeout(() => Swal.close(), 5000);
            }
        });
    });

    $('#startBackupBtn').click(function() {
        Swal.fire({
            title: 'Initialize Backup?',
            text: "This will start a background database snapshot.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            confirmButtonText: 'Confirm & Run',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $(this).prop('disabled', true).addClass('opacity-50');
                $('#progressSection').removeClass('hidden');
                $('#progressBar').css('width', '5%');
                $('#progressText').text('Initializing backup routine...');

                $.post('backup_action.php', { action: 'start' }, function(response) {
                    if(response.status === 'success') {
                        Swal.fire({ title: 'Started!', text: 'Backup running in background.', icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, theme: getSwalTheme() });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message, theme: getSwalTheme() });
                        $('#startBackupBtn').prop('disabled', false).removeClass('opacity-50');
                        $('#progressSection').addClass('hidden');
                    }
                }, 'json');
            }
        });
    });

    function loadBackups() {
        $.get('backup_action.php', { action: 'list' }, function(data) {
            let html = '';
            if (!data || data.length === 0) {
                html = '<tr><td colspan="4" class="px-8 py-16 text-center text-gray-400 font-bold italic">No snapshots on record.</td></tr>';
            } else {
                data.forEach(function(backup) {
                    html += `<tr class="table-row-hover transition-all">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm shadow-sm">
                                    <i class="fas fa-history"></i>
                                </div>
                                <span class="font-black text-gray-900 dark:text-gray-100 text-sm italic tracking-tight">${backup.date}</span>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-gray-500 dark:text-gray-400 font-mono text-xs">${backup.name}</td>
                        <td class="px-8 py-5"><span class="px-3 py-1 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 text-[9px] font-black rounded-lg uppercase tracking-widest border border-black/5 dark:border-white/5">${backup.size}</span></td>
                        <td class="px-8 py-5 text-right flex items-center justify-end gap-3">
                            <a href="backup_action.php?action=download&folder=${backup.name}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-5 py-2.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-600 hover:text-white rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-sm active:scale-95">
                                <i class="fas fa-download mr-2"></i> Download
                            </a>
                            <button onclick="deleteBackup('${backup.name}')" class="p-3 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all active:scale-95">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#backupList').html(html);
        }, 'json');
    }

    window.deleteBackup = function(folder) {
        Swal.fire({
            title: 'Delete Snapshot?',
            text: "This will expunge the backup from storage.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, remove it',
            theme: getSwalTheme()
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('backup_action.php', { action: 'delete', folder: folder }, function(response) {
                    if (response.success) {
                        Swal.fire({ icon:'success', title:'Removed', timer:1500, showConfirmButton:false, theme: getSwalTheme() });
                        loadBackups();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: response.message, theme: getSwalTheme() });
                    }
                }, 'json');
            }
        });
    }

    function checkProgress() {
        $.get('backup_action.php', { action: 'progress' }, function(data) {
            if (data.active) {
                $('#startBackupBtn').prop('disabled', true).addClass('opacity-50');
                $('#progressSection').removeClass('hidden');
                $('#progressText').text(data.message);
                if (data.message.includes('Processing')) $('#progressBar').css('width', '65%');
            } else if (!$('#progressSection').hasClass('hidden')) {
                loadBackups();
                $('#progressBar').css('width', '100%').addClass('bg-emerald-500');
                $('#progressText').text('Snapshot Verified & Completed!');
                $('#progressText').removeClass('bg-white/80 dark:bg-slate-900').addClass('bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-400');
                
                setTimeout(function() {
                    $('#progressSection').addClass('hidden');
                    $('#progressBar').css('width', '0%').removeClass('bg-emerald-500');
                    $('#startBackupBtn').prop('disabled', false).removeClass('opacity-50');
                    Swal.fire({ icon: 'success', title: 'Snapshot Ready', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, theme: getSwalTheme() });
                }, 3000);
            }
        }, 'json');
    }
});
</script>

<?php include "../footer.php"; ?>
