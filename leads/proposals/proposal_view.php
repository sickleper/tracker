<?php
require_once "../../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../../oauth2callback.php');
    exit();
}

require_once __DIR__ . "/../../tracker_data.php"; // For makeApiCall

$pageTitle = "View Proposal";
include_once "../../header.php";
include_once "../../nav.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$proposal = null;

if ($id > 0) {
    $res = makeApiCall("/api/proposals/{$id}");
    if ($res && ($res['success'] ?? false)) {
        $proposal = $res['data'];
    }
}

if (!$proposal) {
    echo "<div class='p-12 text-center'><h2 class='text-red-500 font-black uppercase'>Proposal Not Found</h2><a href='proposals_list.php' class='text-indigo-500 underline'>Back to Registry</a></div>";
    include_once "../../footer.php";
    exit;
}

$logoUrl = $proposal['company']['logo'] ?? 'https://via.placeholder.com/150';
$publicProposalUrl = rtrim($_ENV['APP_URL'] ?? 'https://app.webdesign-dublin.com/', '/') . '/public/proposal.php?h=' . rawurlencode($proposal['hash'] ?? '');
?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <div class="mb-8 flex justify-between items-center">
        <a href="proposals_list.php" class="text-xs font-black uppercase text-gray-400 hover:text-indigo-500 transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to Registry
        </a>
        <div class="flex gap-3">
            <button onclick="window.open('<?php echo htmlspecialchars($publicProposalUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank')" class="px-6 py-2 bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-200 transition-all shadow-sm">
                <i class="fas fa-external-link-alt mr-1"></i> Preview Public Link
            </button>
            <button onclick="window.print()" class="px-6 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-50 transition-all shadow-sm">
                <i class="fas fa-print mr-1"></i> Print / PDF
            </button>
            <?php if ($proposal['status'] === 'waiting'): ?>
                <button id="sendToClientBtn" onclick="sendToClient(<?php echo $proposal['id']; ?>)" class="px-6 py-2 bg-indigo-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-md active:scale-95">
                    <i class="fas fa-paper-plane mr-1"></i> Send to Client
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-base border-none overflow-hidden bg-white !text-slate-900 shadow-2xl printable-area">
        <!-- Force light mode for the proposal content regardless of system settings -->
        <div class="proposal-content-wrapper bg-white text-slate-900">
            <!-- Header -->
            <div class="p-12 border-b-4 border-indigo-600 flex justify-between items-start bg-slate-50">
                <div class="space-y-4">
                    <img src="<?php echo $logoUrl; ?>" class="max-h-20">
                    <div class="text-xs text-slate-500 font-medium">
                        <p class="font-bold text-slate-900"><?php echo htmlspecialchars($proposal['company']['company_name'] ?? 'Energy Retrofit Ireland'); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($proposal['company']['address'] ?? "2 Barberstown Ln N, Clonsilla
Co. Dublin, D15 V08R")); ?></p>
                    </div>
                </div>
                <div class="text-right space-y-1">
                    <h1 class="text-4xl font-black italic uppercase tracking-tighter text-indigo-600">Proposal</h1>
                    <p class="text-xs font-black uppercase text-slate-400">#<?php echo str_pad($proposal['id'], 5, '0', STR_PAD_LEFT); ?></p>
                    <div class="pt-4 text-xs font-bold uppercase text-slate-600">
                        <p>Date: <?php echo date('d M Y', strtotime($proposal['created_at'])); ?></p>
                        <p>Valid Until: <?php echo date('d M Y', strtotime($proposal['valid_till'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Client Info -->
            <div class="p-12 grid grid-cols-2 gap-12 bg-white">
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-4">Proposed For:</h4>
                    <div class="text-sm space-y-1">
                        <p class="text-lg font-black text-slate-900"><?php echo htmlspecialchars($proposal['lead']['client_name']); ?></p>
                        <p class="text-slate-600"><?php echo htmlspecialchars($proposal['lead']['client_email']); ?></p>
                        <p class="text-slate-600"><?php echo nl2br(htmlspecialchars($proposal['lead']['address'])); ?></p>
                        <?php if (!empty($proposal['lead']['mobile'])): ?>
                            <p class="text-slate-600"><?php echo htmlspecialchars($proposal['lead']['mobile']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="bg-indigo-50 p-6 rounded-3xl border border-indigo-100 flex flex-col justify-center items-center text-center">
                    <span class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-2">Total Project Investment</span>
                    <span class="text-4xl font-black italic tracking-tighter text-indigo-600">€<?php echo number_format($proposal['total'], 2); ?></span>
                    <span class="text-[9px] font-bold uppercase text-indigo-400 mt-2">Including all applicable taxes</span>
                </div>
            </div>

            <!-- Content -->
            <div class="px-12 pb-12 prose prose-indigo max-w-none">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-6 border-b pb-2">Project Overview & Scope</h4>
                <div class="text-slate-700 leading-relaxed">
                    <?php echo $proposal['description']; ?>
                </div>
            </div>

            <!-- Items Table -->
            <div class="px-12 pb-12">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-6 border-b pb-2">Investment Breakdown</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b-2 border-slate-100">
                            <th class="py-4 text-left">Item Description</th>
                            <th class="py-4 text-right">Quantity</th>
                            <th class="py-4 text-right">Unit Price</th>
                            <th class="py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($proposal['items'] as $item): ?>
                            <tr>
                                <td class="py-6">
                                    <p class="font-bold text-slate-900"><?php echo htmlspecialchars($item['item_name']); ?></p>
                                    <?php if (!empty($item['item_summary'])): ?>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($item['item_summary']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-6 text-right text-slate-600"><?php echo number_format($item['quantity'], 2); ?></td>
                                <td class="py-6 text-right text-slate-600">€<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="py-6 text-right font-bold text-slate-900">€<?php echo number_format($item['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-100">
                            <td colspan="3" class="py-6 text-right text-[10px] font-black uppercase text-slate-400">Sub-Total</td>
                            <td class="py-6 text-right font-bold text-slate-900">€<?php echo number_format($proposal['sub_total'], 2); ?></td>
                        </tr>
                        <?php if ($proposal['discount'] > 0): ?>
                            <tr>
                                <td colspan="3" class="py-1 text-right text-[10px] font-black uppercase text-red-400">Discount (<?php echo $proposal['discount_type'] === 'percent' ? $proposal['discount'].'%' : 'Fixed'; ?>)</td>
                                <td class="py-1 text-right font-bold text-red-500">-€<?php 
                                    $disc = $proposal['discount_type'] === 'percent' ? ($proposal['sub_total'] * ($proposal['discount']/100)) : $proposal['discount'];
                                    echo number_format($disc, 2); 
                                ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td colspan="3" class="py-1 text-right text-[10px] font-black uppercase text-slate-400">Estimated Tax</td>
                            <td class="py-1 text-right font-bold text-slate-900">€<?php echo number_format($proposal['total'] - ($proposal['sub_total'] - ($proposal['discount_type'] === 'percent' ? ($proposal['sub_total'] * ($proposal['discount']/100)) : $proposal['discount'])), 2); ?></td>
                        </tr>
                        <tr class="bg-indigo-600 text-white">
                            <td colspan="3" class="p-6 text-right text-xs font-black uppercase tracking-widest">Total Investment</td>
                            <td class="p-6 text-right text-2xl font-black italic tracking-tighter">€<?php echo number_format($proposal['total'], 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Footer -->
            <div class="p-12 bg-slate-900 text-white text-center">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-2">Thank you for choosing <?php echo htmlspecialchars($proposal['company']['company_name'] ?? 'Energy Retrofit Ireland'); ?></p>
                <p class="text-xs text-slate-400">This proposal is valid until <?php echo date('d M Y', strtotime($proposal['valid_till'])); ?>. Please contact us if you have any questions.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .proposal-content-wrapper, .proposal-content-wrapper * {
        background-color: white !important;
        color: #0f172a !important; /* slate-900 */
    }
    .proposal-content-wrapper .bg-slate-50, .proposal-content-wrapper .bg-slate-50 * {
        background-color: #f8fafc !important;
    }
    .proposal-content-wrapper .bg-indigo-50, .proposal-content-wrapper .bg-indigo-50 * {
        background-color: #eef2ff !important;
    }
    .proposal-content-wrapper .bg-gray-900, .proposal-content-wrapper .bg-slate-900, .proposal-content-wrapper .bg-slate-900 * {
        background-color: #0f172a !important;
        color: white !important;
    }
    .proposal-content-wrapper .bg-indigo-600, .proposal-content-wrapper .bg-indigo-600 * {
        background-color: #4f46e5 !important;
        color: white !important;
    }
    .proposal-content-wrapper .text-indigo-600 {
        color: #4f46e5 !important;
    }
    .proposal-content-wrapper .text-slate-500 {
        color: #64748b !important;
    }
    .proposal-content-wrapper .text-slate-400 {
        color: #94a3b8 !important;
    }

    @media print {
        body { background: white !important; }
        .nav-sidebar, .nav-top, .no-print, nav, button, a[href*=" registry"] { display: none !important; }
        .printable-area { shadow: none !important; border: none !important; margin: 0 !important; width: 100% !important; }
        .card-base { border: none !important; shadow: none !important; }
    }
</style>

<script>
// Function to determine SweetAlert2 theme based on dark mode
function getSwalTheme() {
    return $('html').hasClass('dark') ? 'dark' : 'default';
}

function sendToClient(id) {
    Swal.fire({
        title: 'Send Proposal?',
        text: "This will email the proposal link to the client.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, Send Now',
        theme: getSwalTheme()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Sending...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });
            
            $.post('proposal_handler.php', {
                action: 'send_email',
                id: id
            }, function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Sent!', text: 'The proposal has been delivered to the client.', theme: getSwalTheme() });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Failed to send email.', theme: getSwalTheme() });
                }
            }, 'json').fail(function() {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to contact the proposal service.', theme: getSwalTheme() });
            });
        }
    });
}
</script>

<?php include_once "../../footer.php"; ?>
