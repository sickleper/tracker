<?php
require_once "../config.php";
require_once "../tracker_data.php";

$hash = $_GET['h'] ?? $_GET['key'] ?? '';
$proposal = null;

if (!empty($hash)) {
    // Fetch proposal via public hash endpoint
    $res = makeApiCall("/api/public/proposals/by-hash/{$hash}");
    if ($res && ($res['success'] ?? false)) {
        $proposal = $res['data'];
    }
}

if (!$proposal) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Proposal Not Found</h2><p>This link may have expired or is invalid.</p></div>");
}

$logoUrl = $proposal['company']['logo'] ?? 'https://app.webdesign-dublin.com/dist/images/logo.png';
$status = $proposal['status'] ?? 'waiting';
$proposalHashJson = json_encode($hash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Proposal - <?php echo htmlspecialchars($proposal['company']['company_name']); ?></title>
    <link rel="stylesheet" href="../dist/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .proposal-container { max-width: 900px; margin: 40px auto; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 pb-20">

    <!-- Action Bar -->
    <div class="fixed top-0 left-0 right-0 bg-white/80 backdrop-blur-md border-b border-slate-200 z-50 no-print">
        <div class="max-w-5xl mx-auto px-6 py-4 flex justify-between items-center">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" class="h-8 md:h-10">
            <div class="flex gap-3">
                <button onclick="window.print()" class="px-4 py-2 text-xs font-black uppercase tracking-widest text-slate-500 hover:text-indigo-600 transition-all">
                    <i class="fas fa-print mr-2"></i> Print / PDF
                </button>
                <?php if ($status === 'waiting'): ?>
                    <button onclick="openAcceptModal()" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-200 transition-all active:scale-95">
                        Accept & Sign
                    </button>
                <?php else: ?>
                    <div class="px-6 py-3 bg-emerald-100 text-emerald-700 rounded-xl text-xs font-black uppercase tracking-widest border border-emerald-200">
                        <i class="fas fa-check-circle mr-2"></i> <?php echo strtoupper($status); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="proposal-container pt-20 px-6">
        <div class="bg-white rounded-[40px] shadow-2xl shadow-indigo-100/50 overflow-hidden border border-white">
            
            <!-- Header -->
            <div class="p-12 border-b-8 border-indigo-600 flex flex-col md:flex-row justify-between items-start bg-slate-50/50">
                <div class="space-y-6">
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" class="max-h-20">
                    <div class="text-sm text-slate-500">
                        <p class="font-bold text-slate-900 text-lg"><?php echo htmlspecialchars($proposal['company']['company_name']); ?></p>
                        <p class="mt-1 leading-relaxed"><?php echo nl2br(htmlspecialchars($proposal['company']['address'])); ?></p>
                    </div>
                </div>
                <div class="text-right mt-8 md:mt-0">
                    <h1 class="text-5xl font-black italic uppercase tracking-tighter text-indigo-600 leading-none">Proposal</h1>
                    <p class="text-xs font-black uppercase text-slate-400 mt-2">Ref: #<?php echo str_pad($proposal['id'], 5, '0', STR_PAD_LEFT); ?></p>
                    <div class="mt-8 space-y-1 text-sm font-bold uppercase text-slate-600">
                        <p>Issued: <span class="text-slate-900"><?php echo date('d M Y', strtotime($proposal['created_at'])); ?></span></p>
                        <p>Valid Until: <span class="text-slate-900"><?php echo date('d M Y', strtotime($proposal['valid_till'])); ?></span></p>
                    </div>
                </div>
            </div>

            <!-- Client & Summary -->
            <div class="p-12 grid grid-cols-1 md:grid-cols-2 gap-12 bg-white">
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-4 flex items-center gap-2">
                        <span class="w-8 h-[1px] bg-indigo-200"></span> Proposed For
                    </h4>
                    <div class="space-y-1">
                        <p class="text-2xl font-black text-slate-900"><?php echo htmlspecialchars($proposal['lead']['client_name']); ?></p>
                        <p class="text-slate-500 font-medium"><?php echo htmlspecialchars($proposal['lead']['client_email']); ?></p>
                        <p class="text-slate-500 leading-relaxed pt-2"><?php echo nl2br(htmlspecialchars($proposal['lead']['address'])); ?></p>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 p-8 rounded-[32px] text-white shadow-xl shadow-indigo-200 flex flex-col justify-center items-center text-center">
                    <span class="text-[10px] font-black uppercase tracking-widest opacity-70 mb-2 italic">Total Project Investment</span>
                    <span class="text-5xl font-black italic tracking-tighter">€<?php echo number_format($proposal['total'], 2); ?></span>
                    <span class="text-[9px] font-bold uppercase opacity-60 mt-3 tracking-widest">Includes all applicable taxes</span>
                </div>
            </div>

            <!-- Project Content -->
            <div class="px-12 py-8 bg-white border-t border-slate-100">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-500 mb-8 flex items-center gap-2">
                    <span class="w-8 h-[1px] bg-indigo-200"></span> Project Scope & Details
                </h4>
                <div class="prose prose-indigo max-w-none text-slate-700 leading-relaxed text-lg italic font-medium">
                    <?php echo $proposal['description']; ?>
                </div>
            </div>

            <!-- Items -->
            <div class="px-12 pb-12 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black uppercase tracking-widest text-slate-400 border-b-2 border-slate-100">
                                <th class="py-6">Item Description</th>
                                <th class="py-6 text-right">Quantity</th>
                                <th class="py-6 text-right">Unit Price</th>
                                <th class="py-6 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($proposal['items'] as $item): ?>
                                <tr class="group">
                                    <td class="py-8">
                                        <p class="font-bold text-slate-900 text-lg"><?php echo htmlspecialchars($item['item_name']); ?></p>
                                        <?php if (!empty($item['item_summary'])): ?>
                                            <p class="text-sm text-slate-500 mt-2 leading-relaxed max-w-md italic"><?php echo htmlspecialchars($item['item_summary']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-8 text-right font-bold text-slate-600"><?php echo number_format($item['quantity'], 1); ?></td>
                                    <td class="py-8 text-right font-bold text-slate-600">€<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="py-8 text-right font-black text-slate-900 text-lg">€<?php echo number_format($item['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-4 border-slate-100 bg-slate-50/30">
                                <td colspan="3" class="py-8 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Total Investment (Inc. Tax)</td>
                                <td class="py-8 text-right font-black text-3xl italic tracking-tighter text-indigo-600">€<?php echo number_format($proposal['total'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Status Footer -->
            <div class="p-12 bg-slate-900 text-white text-center">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-3">Thank you for choosing <?php echo htmlspecialchars($proposal['company']['company_name']); ?></p>
                <p class="text-xs text-slate-400 font-medium italic opacity-80">This proposal is a legally binding offer valid until <?php echo date('d M Y', strtotime($proposal['valid_till'])); ?>.</p>
            </div>
        </div>
    </div>

    <!-- Acceptance Modal -->
    <div id="acceptModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-6 bg-slate-900/90 backdrop-blur-md">
        <div class="bg-white rounded-[40px] max-w-lg w-full overflow-hidden border border-white shadow-2xl">
            <div class="p-10 text-center space-y-6">
                <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mx-auto text-3xl">
                    <i class="fas fa-signature"></i>
                </div>
                <h3 class="text-3xl font-black italic uppercase tracking-tighter">Accept Proposal</h3>
                <p class="text-slate-500 font-medium">By typing your name below, you agree to the terms and conditions outlined in this proposal.</p>
                
                <div class="space-y-4 text-left">
                    <label class="text-[10px] font-black uppercase tracking-widest text-slate-400 ml-2">Full Legal Name</label>
                    <input type="text" id="signerName" class="w-full p-5 bg-slate-50 border border-slate-100 rounded-2xl font-bold text-lg focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all" placeholder="Enter your full name">
                </div>

                <div class="flex gap-4 pt-4">
                    <button onclick="closeAcceptModal()" class="flex-1 py-4 bg-slate-100 text-slate-500 font-black uppercase tracking-widest rounded-2xl hover:bg-slate-200 transition-all">Cancel</button>
                    <button onclick="submitAcceptance()" class="flex-1 py-4 bg-indigo-600 text-white font-black uppercase tracking-widest rounded-2xl hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-200">Sign & Accept</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAcceptModal() {
            $('#acceptModal').removeClass('hidden');
        }
        function closeAcceptModal() {
            $('#acceptModal').addClass('hidden');
        }
        function submitAcceptance() {
            const name = $('#signerName').val();
            if (!name) {
                return Swal.fire('Error', 'Please enter your full name to sign.', 'error');
            }

            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            $.post('proposal_public_handler.php', {
                action: 'accept',
                hash: <?php echo $proposalHashJson; ?>,
                signer_name: name
            }, function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Proposal Accepted!',
                        text: 'Thank you. A confirmation email has been sent to both parties.',
                        confirmButtonText: 'Great!'
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Failed to accept proposal.', 'error');
                }
            }, 'json').fail(function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to submit acceptance.';
                Swal.fire('Error', msg, 'error');
            });
        }
    </script>
</body>
</html>
