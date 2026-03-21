<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$pageTitle = "Printable Callout List";
include_once "../header.php";
include_once "../nav.php";
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

$period = $_GET['period'] ?? 'this';
$allowedPeriods = ['last', 'this', 'next'];
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'this';
}

function getPeriodBounds(string $period): array {
    switch ($period) {
        case 'last':
            $start = strtotime('monday last week');
            $end = strtotime('sunday last week');
            break;
        case 'next':
            $start = strtotime('monday next week');
            $end = strtotime('sunday next week');
            break;
        default:
            $start = strtotime('monday this week');
            $end = strtotime('sunday this week');
            break;
    }

    return [
        date('Y-m-d 00:00:00', $start),
        date('Y-m-d 23:59:59', $end),
    ];
}

/**
 * Clean AI-generated headers from text fields
 */
function cleanAiText($text) {
    if (empty($text)) return '';
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $patterns = [
        '/Update Source/i',
        '/Email AI Assistant\s*\([\d\-\s:]+\)/i',
        '/New Summary/i'
    ];
    $text = preg_replace($patterns, '', $text);
    $text = strip_tags($text);
    $lines = array_map(static function ($line) {
        return trim((string) preg_replace('/[^\S\n]+/', ' ', $line));
    }, explode("\n", $text));
    $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));
    return trim($text);
}

[$periodStart, $periodEnd] = getPeriodBounds($period);

// Fetch filtered leads from API
$leads = [];
$apiResponse = makeApiCall('/api/leads', ['period' => $period]);
if ($apiResponse && ($apiResponse['success'] ?? false)) {
    $leads = $apiResponse['data'];
}

// Filter and Group
$groupedLeads = [];
foreach ($leads as $l) {
    if (empty($l['follow_ups']) || !is_array($l['follow_ups'])) {
        continue;
    }

    $validFollowUps = array_values(array_filter($l['follow_ups'], static function ($followUp) use ($periodStart, $periodEnd) {
        $date = $followUp['next_follow_up_date'] ?? null;
        return !empty($date) && $date >= $periodStart && $date <= $periodEnd;
    }));

    if (empty($validFollowUps)) {
        continue;
    }

    usort($validFollowUps, static function ($a, $b) {
        return strtotime($a['next_follow_up_date']) <=> strtotime($b['next_follow_up_date']);
    });

    $activeFollowUp = $validFollowUps[0];
    $followUpDate = $activeFollowUp['next_follow_up_date'];
    $date = date('Y-m-d', strtotime($followUpDate));

    // Map required fields for the old template logic
    $l['catname'] = $l['category']['category_name'] ?? 'General';
    $l['catemail'] = $l['category']['email'] ?? '';
    $l['logo_url'] = $l['category']['logo_url'] ?? '';
    $l['followup_id'] = $activeFollowUp['id'] ?? null;
    $l['next_follow_up_date'] = $followUpDate;
    $l['next_follow_up_day_label'] = date('l j F', strtotime($followUpDate));
    $l['next_follow_up_time_label'] = date('H:i', strtotime($followUpDate));
    $l['remark'] = $activeFollowUp['remark'] ?? '';
    $l['confirmation_email_sent'] = $activeFollowUp['confirmation_email_sent'] ?? false;

    $groupedLeads[$date][] = $l;
}

foreach ($groupedLeads as &$dateLeads) {
    usort($dateLeads, static function ($a, $b) {
        return strtotime($a['next_follow_up_date']) <=> strtotime($b['next_follow_up_date']);
    });
}
unset($dateLeads);

ksort($groupedLeads);
?>

<div class="max-w-5xl mx-auto px-4 py-8 no-print">
    <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8">
        <div class="flex items-center gap-4">
            <a href="leads_callout_map.php" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-full transition-all" title="Back to Map">
                <i class="fas fa-arrow-left text-xl"></i>
            </a>
            <h1 class="heading-brand">Printable Callout List</h1>
        </div>
        
        <div class="flex flex-wrap items-center gap-3 bg-white dark:bg-slate-900 p-2 rounded-2xl shadow-soft border border-gray-100 dark:border-slate-800">
            <a href="?period=last" class="px-5 py-2.5 <?= $period === 'last' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' ?> rounded-xl font-bold text-xs uppercase tracking-wider transition-all">Last Week</a>
            <a href="?period=this" class="px-5 py-2.5 <?= $period === 'this' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' ?> rounded-xl font-bold text-xs uppercase tracking-wider transition-all">This Week</a>
            <a href="?period=next" class="px-5 py-2.5 <?= $period === 'next' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200' ?> rounded-xl font-bold text-xs uppercase tracking-wider transition-all">Next Week</a>
            <button onclick="window.print()" class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-md hover:bg-emerald-700">
                <i class="fas fa-print mr-2 text-xs"></i> Print Now
            </button>
        </div>
    </div>
</div>

<div class="max-w-5xl mx-auto px-4 pb-12 print-container">
    <?php if (empty($groupedLeads)): ?>
        <div class="card-base p-12 text-center border-2 border-dashed">
            <i class="fas fa-calendar-times text-4xl text-gray-300 dark:text-gray-700 mb-4"></i>
            <p class="text-gray-500 dark:text-gray-400 font-medium">No callouts scheduled for this period.</p>
        </div>
    <?php else: ?>
        <?php foreach ($groupedLeads as $date => $leads): ?>
            <div class="mb-10 break-inside-avoid">
                <div class="flex items-baseline gap-4 border-b-4 border-indigo-600 dark:border-indigo-500 pb-2 mb-6">
                    <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase italic"><?= date('l', strtotime($date)) ?></h3>
                    <span class="text-xl font-bold text-gray-400 dark:text-gray-500"><?= date('d F Y', strtotime($date)) ?></span>
                </div>

                <div class="grid gap-4">
                    <?php foreach ($leads as $lead): ?>
                        <div class="card-base p-6 flex flex-col md:flex-row gap-6 relative overflow-hidden <?= $lead['confirmation_email_sent'] ? 'bg-emerald-50/50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/50' : '' ?>">
                            <!-- Time Badge -->
                            <div class="md:w-32 flex-shrink-0">
                                <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 tabular-nums">
                                    <?= date('H:i', strtotime($lead['next_follow_up_date'])) ?>
                                </div>
                                <div class="inline-block mt-2 px-2 py-1 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 rounded text-[9px] font-black uppercase tracking-wider border border-indigo-200 dark:border-indigo-800">
                                    <?= htmlspecialchars($lead['catname']) ?>
                                </div>
                            </div>

                            <!-- Main Info -->
                            <div class="flex-grow min-w-0">
                                <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-1 truncate flex items-center gap-2">
                                    <?= htmlspecialchars($lead['client_name']) ?>
                                    <?php if ($lead['confirmation_email_sent']): ?>
                                        <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300 text-[10px] font-black rounded-full flex items-center gap-1 uppercase">
                                            <i class="fas fa-check-circle"></i> Confirmed
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <div class="flex items-start gap-2 text-gray-600 dark:text-gray-400 mb-3 font-medium">
                                    <i class="fas fa-map-marker-alt mt-1 text-gray-400 dark:text-gray-600"></i>
                                    <span><?= htmlspecialchars($lead['address']) ?></span>
                                </div>
                                
                                <?php if (!empty($lead['remark'])): ?>
                                    <div class="bg-gray-50 dark:bg-slate-900/50 rounded-xl p-4 border border-gray-100 dark:border-slate-800 text-sm text-gray-700 dark:text-gray-300 italic leading-relaxed">
                                        <?= nl2br(cleanAiText($lead['remark'])) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Interactive Manual Notes -->
                                <div class="mt-4">
                                    <div class="flex gap-2 no-print mb-2">
                                        <textarea id="note-<?= $lead['followup_id'] ?>"
                                                  oninput="this.parentElement.nextElementSibling.innerText = this.value; this.style.height = ''; this.style.height = this.scrollHeight + 'px';" 
                                                  class="w-full p-3 bg-amber-50/30 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-xl text-sm italic placeholder:text-amber-200 dark:placeholder:text-amber-900/50 focus:ring-2 focus:ring-amber-100 outline-none transition-all min-h-[45px] overflow-hidden block dark:text-gray-200" 
                                                  placeholder="Type extra notes or directions..."></textarea>
                                        <button onclick="saveNote(<?= $lead['followup_id'] ?>)" 
                                                class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-sm h-fit self-end">
                                            Save
                                        </button>
                                    </div>
                                    <div class="print-note hidden text-sm text-gray-800 dark:text-gray-200 mt-4 border-l-4 border-amber-400 pl-4 py-1 italic whitespace-pre-wrap"></div>
                                </div>
                            </div>

                            <!-- Contact -->
                            <div class="md:w-48 flex-shrink-0 border-t md:border-t-0 md:border-l border-gray-100 dark:border-slate-800 pt-4 md:pt-0 md:pl-6 space-y-3">
                                <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400 font-bold">
                                    <i class="fas fa-phone-alt"></i>
                                    <span><?= htmlspecialchars($lead['mobile'] ?? 'N/A') ?></span>
                                </div>
                                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">Lead ID: #<?= $lead['id'] ?></div>
                                
                                <div class="no-print pt-2 flex flex-col gap-2">
                                    <button onclick="openBookingModal(<?= htmlspecialchars(json_encode($lead)) ?>)" 
                                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider transition-all w-full justify-center border
                                       <?= $lead['confirmation_email_sent'] ? 'bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-gray-500 border-gray-300 dark:border-slate-700 cursor-not-allowed' : 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-800 hover:bg-emerald-600 hover:text-white' ?>"
                                       <?= $lead['confirmation_email_sent'] ? 'disabled' : '' ?>>
                                        <i class="fas <?= $lead['confirmation_email_sent'] ? 'fa-check-double' : 'fa-check-circle' ?>"></i> <?= $lead['confirmation_email_sent'] ? 'Sent' : 'Confirm' ?>
                                    </button>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($lead['address']) ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-400 rounded-lg font-bold text-[10px] uppercase tracking-wider hover:bg-indigo-600 hover:text-white transition-all w-full justify-center border border-transparent dark:border-slate-700">
                                        <i class="fas fa-location-arrow"></i> Directions
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Booking Confirmation Modal -->
<div id="bookingModal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl w-full max-w-5xl overflow-hidden border border-gray-100 dark:border-slate-800 transform transition-all scale-95 opacity-0" id="modalContainer">
        <div class="bg-indigo-600 px-8 py-4 text-white flex justify-between items-center">
            <h3 class="text-lg font-black uppercase tracking-tight italic">Branded Booking Confirmation</h3>
            <button onclick="closeBookingModal()" class="text-white/80 hover:text-white transition-colors"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <div class="flex flex-col md:flex-row min-h-[400px]">
            <!-- LEFT: BRANDED PREVIEW -->
            <div class="md:w-7/12 p-6 border-r border-gray-100 dark:border-slate-800 bg-gray-50/50 dark:bg-slate-950/50 max-h-[75vh] overflow-y-auto">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-2 border-b border-gray-200 text-[9px] font-black text-gray-400 uppercase tracking-widest flex items-center justify-between">
                        <span class="flex items-center gap-2"><i class="fas fa-eye text-emerald-500"></i> Branded Email Preview</span>
                        <span class="text-[8px] text-amber-600 uppercase font-black">Click text to edit body</span>
                    </div>
                    <div class="p-6" id="email-live-preview" style="font-family: sans-serif; line-height: 1.6;">
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img id="preview-logo" src="" style="max-height: 40px; display: none;">
                        </div>
                        <div id="preview-body" contenteditable="true" oninput="syncPreviewToTextarea()" style="font-size: 13px; color: #333; white-space: pre-wrap; padding: 10px; outline: none;" class="hover:bg-emerald-50/30 rounded-lg transition-all"></div>
                        
                        <div style='margin-top: 20px; padding: 12px; background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; color: #92400e; font-size: 11px; text-align: center;'>
                            <span style='font-size: 14px; margin-bottom: 4px; display: block;'>🕒</span>
                            <strong>Note:</strong> Visit times are approximate (+/- 30 mins) depending on traffic. We will call you the day before to confirm your exact time.
                        </div>

                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; font-size: 10px; color: #888;">
                            <p>
                                <strong id="preview-company-name" style="color: #333;"></strong><br>
                                <span id="preview-contact-info"></span>
                            </p>
                            <img src="https://app.energyretrofitireland.ie/img/efi-certs-header.webp" style="max-width: 100%; margin-top: 10px; opacity: 0.7;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: DETAILS & ACTIONS -->
            <div class="md:w-5/12 p-8 bg-white dark:bg-slate-900 flex flex-col justify-between">
                <div class="grid gap-4">
                    <textarea id="modal-message" class="hidden"></textarea>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Recipient</label>
                        <div id="modal-recipient" class="text-sm font-bold text-gray-900 dark:text-white bg-gray-50 dark:bg-slate-950 p-3 rounded-xl border border-gray-100 dark:border-slate-800"></div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1.5">Subject</label>
                        <input type="text" id="modal-subject" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold text-gray-900 dark:text-white focus:ring-4 focus:ring-indigo-50 dark:focus:ring-indigo-900/20 outline-none transition-all">
                    </div>
                    <div class="p-3 bg-emerald-50 dark:bg-emerald-950/20 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 text-[10px] text-emerald-800 dark:text-emerald-400 font-bold leading-relaxed">
                        <i class="fas fa-info-circle mr-1"></i>
                        Editing the preview on the left updates the email content in real-time.
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    <button onclick="closeBookingModal()" class="flex-1 py-3 bg-white dark:bg-slate-800 text-gray-500 dark:text-gray-400 rounded-2xl font-black text-xs uppercase tracking-widest border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700 transition-all">Cancel</button>
                    <button onclick="sendConfirmation()" id="sendBtn" class="flex-1 py-3 bg-emerald-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentLead = null;

    function syncPreviewToTextarea() {
        document.getElementById('modal-message').value = document.getElementById('preview-body').innerText;
    }

    function openBookingModal(lead) {
        currentLead = lead;
        const modal = document.getElementById('bookingModal');
        const container = document.getElementById('modalContainer');
        
        document.getElementById('modal-recipient').innerText = lead.client_email || 'No email provided';
        
        const day = lead.next_follow_up_day_label || '';
        const time = lead.next_follow_up_time_label || '';
        
        const firstName = (lead.client_name || '').trim().split(/\s+/)[0] || 'there';
        document.getElementById('modal-subject').value = `Booking Confirmation [#${lead.id}] - ${lead.catname}`;
        
        const msg = `Hi ${firstName},\n\nJust a quick note to confirm your consultation with ${lead.catname}, scheduled for ${day} at approximately ${time}.\n\nIf you need to reschedule or have any questions, please contact us at ${lead.catemail}.\n\nBest regards,\nDavid Doheny\nProject Manager\n${lead.catname}`;
        
        document.getElementById('modal-message').value = msg;
        document.getElementById('preview-body').innerText = msg;
        
        const logo = document.getElementById('preview-logo');
        if (lead.logo_url) { logo.src = lead.logo_url; logo.style.display = 'inline-block'; }
        else { logo.src = ''; logo.style.display = 'none'; }

        document.getElementById('preview-company-name').innerText = lead.catname;
        document.getElementById('preview-contact-info').innerText = `Email: ${lead.catemail}`;

        modal.classList.remove('hidden');
        setTimeout(() => {
            container.classList.remove('scale-95', 'opacity-0');
            container.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeBookingModal() {
        const container = document.getElementById('modalContainer');
        container.classList.add('scale-95', 'opacity-0');
        container.classList.remove('scale-100', 'opacity-100');
        setTimeout(() => { document.getElementById('bookingModal').classList.add('hidden'); }, 200);
    }

    async function sendConfirmation() {
        const btn = document.getElementById('sendBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending...';

        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            data: {
                action: 'send_confirmation_email',
                lead_id: currentLead.id,
                followup_id: currentLead.followup_id,
                subject: document.getElementById('modal-subject').value,
                message: document.getElementById('modal-message').value,
                recipient: currentLead.client_email
            },
            success: function(res) {
                if (res.success) {
                    Swal.fire('Sent!', 'Branded confirmation has been sent.', 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Confirmation';
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to send confirmation email.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Confirmation';
            }
        });
    }

    async function saveNote(followupId) {
        const textarea = document.getElementById('note-' + followupId);
        const note = textarea.value.trim();
        if (!note) return Swal.fire('Info', 'Please type something before saving.', 'info');

        const btn = textarea.nextElementSibling;
        btn.disabled = true; btn.innerText = '...';

        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            data: { action: 'append_followup_remark', followup_id: followupId, note: note },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon:'success', title:'Saved', text:'Note appended to job.', timer:1500, showConfirmButton:false }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.disabled = false; btn.innerText = 'Save';
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to save note.', 'error');
                btn.disabled = false;
                btn.innerText = 'Save';
            }
        });
    }
</script>

<style>
    @media print {
        .no-print, nav, header, footer, #bookingModal { display: none !important; }
        body { background: white !important; padding: 0 !important; color: black !important; }
        .print-container { max-width: 100% !important; width: 100% !important; padding: 0 !important; }
        .bg-white { border: 1px solid #eee !important; box-shadow: none !important; background-color: white !important; color: black !important; }
        .card-base { border: 1px solid #eee !important; box-shadow: none !important; background-color: white !important; color: black !important; }
        .bg-emerald-50\/50 { background-color: #f0fdf4 !important; }
        .text-gray-900, .text-gray-700, .text-gray-600 { color: black !important; }
        .break-inside-avoid { break-inside: avoid; }
        .print-note { display: block !important; }
        @page { margin: 1cm; }
    }
    .tabular-nums { font-variant-numeric: tabular-nums; }
</style>

<?php include_once "../footer.php"; ?>
