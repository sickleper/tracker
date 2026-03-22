<?php
/**
 * Dynamic Work Order Form
 * Usage: workorder_form.php?h=SECURE_HASH
 */
require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall

$hash = $_GET['h'] ?? $_GET['key'] ?? '';

if (empty($hash)) {
    die("Invalid access. Please use your unique secure portal link.");
}

// Fetch client details via secure hash endpoint
$clientRes = makeApiCall("/api/public/clients/by-hash/{$hash}");

if (!$clientRes || !($clientRes['success'] ?? false)) {
    die("Error: This portal link is no longer valid or could not be verified.");
}

$client = $clientRes['client'];
$clientId = $client['id'];

// Security: Check if form is enabled for this client
if (!($client['is_wo_form_enabled'] ?? false)) {
    die("This work order form is not enabled for this company.");
}

// Client Branding & Config
$companyName = $client['name'] ?? 'Property Management';
$fallbackLogoUrl = trackerAppUrl() !== '' ? trackerAppUrl() . '/dist/images/logo.png' : '../dist/images/logo.png';
$logoUrl = !empty($client['logo_url']) ? $client['logo_url'] : $fallbackLogoUrl;
$prefix = $client['wo_prefix'] ?? 'WO-';
$destEmail = $client['wo_destination_email'] ?? '';

// Generate a temporary PO number (Prefix + random number) - can be overridden by user
$tempPo = $prefix . date('ymd') . rand(100, 999);

// UI Theme
$primaryColor = "indigo"; // We could make this dynamic too if needed
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 dark:bg-slate-950 transition-colors duration-300">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order | <?php echo htmlspecialchars($companyName); ?></title>
    <link rel="stylesheet" href="../dist/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .form-input {
            width: 100%;
            padding: 1rem 1.1rem;
            background: rgba(248, 250, 252, 0.92);
            border: 1px solid #dbe4f0;
            border-radius: 1rem;
            font-weight: 600;
            outline: none;
            transition: all 0.2s ease;
            color: #0f172a;
        }
        .dark .form-input {
            background: rgba(15, 23, 42, 0.92);
            border-color: #1e293b;
            color: white;
        }
        .form-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.14);
        }
        .card-shadow { box-shadow: 0 28px 70px -24px rgb(15 23 42 / 0.25); }
        .soft-grid {
            background-image:
                linear-gradient(to right, rgba(148, 163, 184, 0.10) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(148, 163, 184, 0.10) 1px, transparent 1px);
            background-size: 22px 22px;
        }
    </style>
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-950 text-slate-900 dark:text-slate-100">
    <div class="fixed inset-0 soft-grid opacity-50"></div>
    <div class="fixed -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>
    <div class="fixed top-1/3 -right-24 h-72 w-72 rounded-full bg-cyan-400/20 blur-3xl"></div>
    <div class="relative mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-6 sm:px-6 lg:px-8">
        <div class="grid w-full gap-6 lg:grid-cols-[0.92fr_1.08fr]">
            <aside class="hidden overflow-hidden rounded-[2rem] border border-white/10 bg-slate-950/70 text-white shadow-2xl backdrop-blur-xl lg:flex lg:flex-col">
                <div class="border-b border-white/10 bg-gradient-to-br from-indigo-600 via-indigo-700 to-slate-900 p-8">
                    <div class="flex items-center justify-between gap-4">
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="h-12 max-w-[220px] object-contain">
                        <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.3em] text-indigo-100">Secure Portal</span>
                    </div>
                    <h1 class="mt-8 text-3xl font-black leading-none tracking-tight">Submit a work order</h1>
                    <p class="mt-4 max-w-md text-sm leading-7 text-indigo-100/85">
                        Use this portal to send a new job straight into the system. Keep the reference number, site details, and job notes together in one submission.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2 text-[10px] font-black uppercase tracking-[0.25em]">
                        <span class="rounded-full bg-white/10 px-3 py-2">Reference-led routing</span>
                        <span class="rounded-full bg-white/10 px-3 py-2">Fast site lookup</span>
                        <span class="rounded-full bg-white/10 px-3 py-2">PDF / image uploads</span>
                    </div>
                </div>

                <div class="flex-1 space-y-4 p-8">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-5">
                        <div class="text-[10px] font-black uppercase tracking-[0.3em] text-indigo-200">Portal summary</div>
                        <div class="mt-3 grid gap-3 text-sm text-slate-100">
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-400">Company</span>
                                <span class="text-right font-bold"><?php echo htmlspecialchars($companyName); ?></span>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-400">Reference</span>
                                <span class="text-right font-bold uppercase tracking-wider"><?php echo htmlspecialchars($prefix); ?></span>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-400">Priority routing</span>
                                <span class="text-right font-bold">Saved instantly</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">1. Reference</div>
                            <p class="mt-2 text-sm leading-6 text-slate-200">Enter the PO or job reference exactly as you want it stored.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">2. Site</div>
                            <p class="mt-2 text-sm leading-6 text-slate-200">Use the address field or search by Eircode to fill it faster.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                            <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-400">3. Attachments</div>
                            <p class="mt-2 text-sm leading-6 text-slate-200">Add PDFs or photos if they help explain the job.</p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-emerald-400/20 bg-emerald-400/10 p-5 text-sm text-emerald-50">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-shield-alt mt-0.5 text-emerald-300"></i>
                            <div>
                                <div class="font-black uppercase tracking-[0.2em] text-[10px] text-emerald-200">Secure transmission</div>
                                <p class="mt-2 leading-6 text-emerald-50/90">
                                    This portal is tied to your client record. Submissions are routed directly into the work order system and can include supporting files.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <main class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white/95 shadow-2xl backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/92">
                <div class="border-b border-slate-200/80 bg-white px-6 py-6 dark:border-slate-800 dark:bg-slate-900 sm:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-[0.35em] text-indigo-500 dark:text-indigo-400">Client work order portal</div>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Submit Work Order</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                                Fill in the reference, site details, and job instructions. If you have a PDF or photos, attach them with the submission.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-xs font-bold uppercase tracking-[0.2em] text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                            Authorized for <?php echo htmlspecialchars($companyName); ?>
                        </div>
                    </div>
                </div>

                <form id="workorderForm" class="space-y-8 p-6 sm:p-8 lg:p-10">
                    <input type="hidden" name="client_id" value="<?php echo $clientId; ?>">

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Purchase Order / Ref *</label>
                            <input type="text" name="poNumber" required value="<?php echo htmlspecialchars($tempPo); ?>" class="form-input uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300" placeholder="PO-XXXXX">
                            <p class="ml-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">This reference is used to route the job internally.</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Priority Level *</label>
                            <select name="priority" required class="form-input">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                            <p class="ml-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Choose the urgency that best matches the work.</p>
                        </div>
                    </div>

                    <div class="rounded-[1.75rem] border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-950/50 sm:p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black uppercase tracking-[0.24em] text-indigo-600 dark:text-indigo-400">Site Identification</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use Eircode lookup to prefill the address where possible.</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div class="space-y-2">
                                <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Eircode Lookup (Optional)</label>
                                <div class="flex gap-2">
                                    <input type="text" id="eircodeInput" name="eircode" class="form-input !py-3 uppercase" placeholder="D24 WF60" autocomplete="postal-code">
                                    <button type="button" onclick="lookupEircode()" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 text-white transition hover:bg-indigo-700 active:scale-[0.98]">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Full Site Address *</label>
                                <input type="text" id="locationAddress" name="location" required class="form-input !py-3" placeholder="Enter full address">
                            </div>

                            <div class="space-y-2">
                                <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Site Contact (Name / Phone)</label>
                                <input type="text" name="contact" class="form-input !py-3" placeholder="Resident contact info">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="ml-1 block text-[10px] font-black uppercase tracking-[0.22em] text-slate-400">Detailed Job Instructions *</label>
                        <textarea name="task" required rows="6" class="form-input min-h-[180px] leading-relaxed" placeholder="Describe the work clearly. Include access notes, urgency, and anything the contractor needs to know."></textarea>
                    </div>

                    <div class="rounded-[1.75rem] border border-dashed border-indigo-200 bg-indigo-50/70 p-5 dark:border-indigo-900/50 dark:bg-indigo-950/20 sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg">
                                    <i class="fas fa-paperclip"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-black uppercase tracking-[0.24em] text-indigo-700 dark:text-indigo-300">Attachments</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">Attach PDFs, images, or sketches to help explain the job.</p>
                                </div>
                            </div>
                            <div class="hidden rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 shadow-sm dark:bg-slate-900 dark:text-slate-300 sm:inline-flex">
                                Optional
                            </div>
                        </div>

                        <div class="mt-5 relative rounded-[1.5rem] border-2 border-dashed border-indigo-200 bg-white/80 p-6 text-center transition hover:border-indigo-400 dark:border-indigo-900/60 dark:bg-slate-900/50">
                            <input id="attachmentInput" type="file" name="attachments[]" multiple accept="image/*,.pdf" class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0">
                            <div class="pointer-events-none">
                                <i class="fas fa-cloud-upload-alt text-4xl text-indigo-500"></i>
                                <p class="mt-3 text-sm font-black uppercase tracking-[0.2em] text-slate-700 dark:text-slate-200">Click or drag files here</p>
                                <p class="mt-2 text-[11px] font-semibold text-slate-500 dark:text-slate-400">PDFs and images are sent with the submission. Max 5MB per file.</p>
                            </div>
                        </div>

                        <div id="attachmentSummary" class="mt-4 rounded-2xl bg-white px-4 py-3 text-sm text-slate-600 shadow-sm dark:bg-slate-900 dark:text-slate-300">
                            No files selected.
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Secure transmission | <?php echo htmlspecialchars($companyName); ?></p>
                        <button type="submit" class="inline-flex items-center justify-center gap-3 rounded-3xl bg-slate-950 px-7 py-4 font-black uppercase tracking-[0.18em] text-white shadow-2xl transition hover:scale-[1.01] hover:bg-indigo-600 active:scale-[0.99] dark:bg-indigo-600 dark:hover:bg-indigo-500">
                            <i class="fas fa-paper-plane text-emerald-300"></i> Dispatch Work Order
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script>
        function getSwalTheme() {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
        }

        function renderAttachmentSummary() {
            const input = document.getElementById('attachmentInput');
            const summary = document.getElementById('attachmentSummary');
            if (!input || !summary) return;

            const files = Array.from(input.files || []);
            if (!files.length) {
                summary.innerHTML = 'No files selected.';
                return;
            }

            const list = files.map(file => `<li class="truncate">${file.name}</li>`).join('');
            summary.innerHTML = `
                <div class="font-black uppercase tracking-[0.18em] text-[10px] text-slate-500 dark:text-slate-400">${files.length} file(s) selected</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">${list}</ul>
            `;
        }

        function lookupEircode() {
            const eircode = $('#eircodeInput').val().trim();
            if (!eircode) return;
            const btn = $('button[onclick="lookupEircode()"]');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.getJSON(`../query_address.php?eircode=${encodeURIComponent(eircode)}`)
                .done(function(data) {
                    if (data.error) {
                        Swal.fire({ icon:'error', title:'Error', text:data.error, theme: getSwalTheme() });
                        return;
                    }
                    $('#locationAddress').val(data.address || '');
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Address Found',
                        showConfirmButton: false,
                        timer: 1500,
                        theme: getSwalTheme()
                    });
                })
                .fail(function() {
                    Swal.fire({ icon:'error', title:'Lookup Failed', text:'Could not fetch the address right now.', theme: getSwalTheme() });
                })
                .always(function() {
                    btn.prop('disabled', false).html('<i class="fas fa-search"></i>');
                });
        }

        $(document).ready(function() {
            $('#attachmentInput').on('change', renderAttachmentSummary);
            renderAttachmentSummary();

            $('#workorderForm').on('submit', function(e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const originalHtml = btn.html();
                
                btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Sending...');

                const formData = new FormData(this);
                
                $.ajax({
                    url: 'workorder_handler.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Dispatched Successfully',
                                text: 'The work order has been received and added to our system.',
                                theme: getSwalTheme()
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Submission Failed', text: res.message || 'Unknown error', theme: getSwalTheme() });
                            btn.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: () => {
                        Swal.fire({ icon: 'error', title: 'Connection Error', text: 'Failed to reach server.', theme: getSwalTheme() });
                        btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });
    </script>
</body>
</html>
