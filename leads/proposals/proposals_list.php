<?php
require_once "../../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../../oauth2callback.php');
    exit();
}

$pageTitle = "Proposal Registry";
include_once "../../header.php";
include_once "../../nav.php";

$proposals = [];
$propRes = makeApiCall("/api/proposals");
if ($propRes && ($propRes['success'] ?? false)) {
    $proposals = $propRes['data'];
}
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="heading-brand">Proposal Registry</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage and track all sent quotes.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="../leads.php" class="px-6 py-3 bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-slate-700 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all shadow-sm flex items-center gap-2">
                <i class="fas fa-arrow-left text-indigo-400"></i> Back to Leads
            </a>
        </div>
    </div>

    <!-- View Navigation (Mirroring Leads page for consistency) -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="../leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="proposals_list.php" class="border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-file-invoice"></i> Proposal Registry
            </a>
        </nav>
    </div>

    <!-- Data Table -->
    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-database text-indigo-400"></i> Sent Proposals (<?php echo count($proposals); ?>)
            </h3>
        </div>
        <div class="table-container">
            <table id="proposalsTable" class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-left">Lead / Client</th>
                        <th class="px-6 py-4 text-right">Total Amount</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                    <?php foreach ($proposals as $p): ?>
                        <tr class="table-row-hover">
                            <td class="px-6 py-4">
                                <span class="text-[10px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest"><?php echo date('d M Y', strtotime($p['created_at'])); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-900 dark:text-white"><?php echo htmlspecialchars($p['lead']['client_name'] ?? 'N/A'); ?></span>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-black tracking-widest"><?php echo htmlspecialchars($p['lead']['client_email'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-indigo-600 dark:text-indigo-400">
                                €<?php echo number_format($p['total'], 2); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php 
                                    $statusClasses = [
                                        'waiting' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                                        'accepted' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                                        'declined' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800'
                                    ];
                                    $cls = $statusClasses[strtolower($p['status'])] ?? 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-slate-700';
                                ?>
                                <span class="px-2 py-1 <?php echo $cls; ?> text-[9px] font-black rounded-lg uppercase tracking-wider border">
                                    <?php echo htmlspecialchars($p['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if ($p['status'] === 'waiting'): ?>
                                        <button onclick="sendToClient(<?php echo $p['id']; ?>)" class="p-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 rounded-xl transition-all" title="Send to Client"><i class="fas fa-paper-plane"></i></button>
                                    <?php endif; ?>
                                    <a href="proposal_view.php?id=<?php echo $p['id']; ?>" class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-all" title="View Proposal"><i class="fas fa-eye"></i></a>
                                    <button onclick="copyLink('<?php echo $p['hash']; ?>')" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-xl transition-all" title="Copy Client Link"><i class="fas fa-link"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function safeJsString(value) {
        return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    }

    $(document).ready(function() {
        $('#proposalsTable').DataTable({
            order: [[0, 'desc']],
            responsive: true,
            dom: 'rtp',
            pageLength: 50
        });
    });

    function copyLink(hash) {
        const cleanAppUrl = window.appUrl.replace(/\/$/, "");
        const link = cleanAppUrl + '/public/proposal.php?h=' + hash;
        navigator.clipboard.writeText(link).then(() => {
            Swal.fire({
                icon: 'success',
                title: 'Link Copied!',
                text: 'The client-side proposal link is ready to share.',
                timer: 1500,
                showConfirmButton: false,
                background: 'var(--card-bg)',
                color: 'var(--text-main)'
            });
        }).catch(() => {
            Swal.fire('Error', 'Failed to copy the proposal link.', 'error');
        });
    }

    function sendToClient(id) {
        Swal.fire({
            title: 'Send Proposal?',
            text: "This will email the proposal link to the client.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, Send Now'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Sending...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                $.post('proposal_handler.php', {
                    action: 'send_email',
                    id: id
                }, function(res) {
                    if (res.success) {
                        Swal.fire('Sent!', 'The proposal has been delivered.', 'success');
                    } else {
                        Swal.fire('Error', res.message || 'Failed to send email.', 'error');
                    }
                }, 'json').fail(function() {
                    Swal.fire('Error', 'Failed to contact the proposal service.', 'error');
                });
            }
        });
    }
</script>

<?php include_once "../../footer.php"; ?>
