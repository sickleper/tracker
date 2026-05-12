<?php
$pageTitle = 'AI Scanner';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api_helper.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$driversResponse = makeApiCall('/api/users', ['drivers_only' => 1], 'GET');
$fuelVehiclesResponse = makeApiCall('/api/fuel/vehicles');
$drivers = is_array($driversResponse) && ($driversResponse['success'] ?? false) ? ($driversResponse['users'] ?? []) : [];
$fuelVehicles = is_array($fuelVehiclesResponse) && ($fuelVehiclesResponse['success'] ?? false) ? ($fuelVehiclesResponse['vehicles'] ?? []) : [];

$driversById = [];
foreach ($drivers as $driver) {
    $driversById[(int) ($driver['id'] ?? 0)] = $driver;
}

$scannerDriverOptions = [];
foreach ($fuelVehicles as $vehicle) {
    $driverId = (int) ($vehicle['user_id'] ?? 0);
    if ($driverId <= 0 || !isset($driversById[$driverId])) {
        continue;
    }

    $driver = $driversById[$driverId];
    $scannerDriverOptions[] = [
        'id' => $driverId,
        'name' => (string) ($driver['name'] ?? 'Driver'),
        'mobile' => (string) ($driver['mobile'] ?? ''),
        'vehicle_reg' => (string) ($vehicle['license_plate'] ?? ''),
    ];
}

usort($scannerDriverOptions, static function (array $a, array $b): int {
    return strcasecmp($a['name'], $b['name']);
});

$pageCssFiles = [
    rtrim(trackerAppUrl(), '/') . '/fuel/main.css?v=' . time(),
];

include __DIR__ . '/../header.php';
include __DIR__ . '/../nav.php';
?>
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="heading-brand">AI Scanner</h1>
        <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Standalone upload and extraction workspace for worker receipts, invoices, mileage, and AI-assisted data capture.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
        <div class="xl:col-span-4 space-y-8">
            <div class="card-base border-none">
                <div class="section-header !bg-slate-800 dark:!bg-slate-950/70">
                    <h3>
                        <i class="fas fa-wand-magic-sparkles text-emerald-300"></i> Workspace
                    </h3>
                </div>
                <div class="p-8">
                    <p class="fuel-section-copy mb-6">Use this page when you want the scanner on its own. It handles office-side review and direct worker upload links without opening any other module.</p>
                    <div class="space-y-4">
                        <a href="app.php" target="_blank" rel="noopener noreferrer" class="fuel-action-btn fuel-action-btn-emerald w-full py-4 shadow-xl">
                            <i class="fas fa-up-right-from-square text-emerald-200"></i> Open Scanner Console
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-circle-info text-indigo-400"></i> How It Works
                    </h3>
                </div>
                <div class="p-8 space-y-4">
                    <p class="fuel-section-copy">Mileage uploads can be sent as a dedicated odometer link, while receipts and invoices can be sent as a separate document link.</p>
                    <p class="fuel-section-copy">AI scanner captures feed the same receipt and mileage records used by the main tracker.</p>
                </div>
            </div>

            <div class="card-base border-none">
                <div class="section-header !bg-emerald-700 dark:!bg-emerald-950/60">
                    <h3>
                        <i class="fab fa-whatsapp text-emerald-200"></i> Send Worker Link
                    </h3>
                </div>
                <div class="p-8">
                    <p class="fuel-section-copy mb-6">Send the worker a WhatsApp link for the exact task you need. Mileage links open the odometer uploader only. Receipt links open the receipt and invoice uploader only.</p>
                    <?php if (empty($scannerDriverOptions)): ?>
                        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">No drivers with assigned vehicles were found.</p>
                    <?php else: ?>
                        <form id="workerUploadLinkForm" class="space-y-4">
                            <div>
                                <label for="workerUploadSelect" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Worker</label>
                                <select id="workerUploadSelect" name="driver_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                    <?php foreach ($scannerDriverOptions as $driverOption): ?>
                                        <option value="<?php echo (int) $driverOption['id']; ?>">
                                            <?php echo htmlspecialchars($driverOption['name'] . ' | ' . $driverOption['vehicle_reg'] . ($driverOption['mobile'] !== '' ? ' | ' . $driverOption['mobile'] : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" id="workerUploadMode" name="mode" value="">
                            <div class="grid gap-3">
                                <button type="submit" data-mode="mileage" class="worker-upload-send-btn fuel-action-btn fuel-action-btn-emerald inline-flex w-full items-center justify-center gap-3 py-4 shadow-xl">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white/18 ring-1 ring-white/20">
                                        <i class="fab fa-whatsapp text-lg leading-none text-white"></i>
                                    </span>
                                    <span>Send Mileage Link</span>
                                </button>
                                <button type="submit" data-mode="receipt" class="worker-upload-send-btn fuel-action-btn fuel-action-btn-indigo inline-flex w-full items-center justify-center gap-3 py-4 shadow-xl">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white/18 ring-1 ring-white/20">
                                        <i class="fab fa-whatsapp text-lg leading-none text-white"></i>
                                    </span>
                                    <span>Send Receipt Link</span>
                                </button>
                            </div>
                        </form>
                        <div id="workerUploadLinkStatus" class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="xl:col-span-8">
            <div class="card-base border-none overflow-hidden">
                <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60">
                    <h3>
                        <i class="fas fa-camera-retro text-indigo-200"></i> AI Scanner Console
                    </h3>
                </div>
                <div class="bg-gray-50 dark:bg-slate-950 p-2 md:p-4">
                    <iframe
                        id="scannerConsoleFrame"
                        src="app.php"
                        title="AI Scanner"
                        class="block w-full min-h-[1200px] rounded-2xl border border-gray-200 dark:border-slate-800 bg-white"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
window.addEventListener('message', function(event) {
    if (event.origin !== window.location.origin) {
        return;
    }

    if (!event.data || event.data.type !== 'receipt_scanner_height') {
        return;
    }

    const frame = document.getElementById('scannerConsoleFrame');
    if (!frame) {
        return;
    }

    const nextHeight = Math.max(1200, Number(event.data.height) || 0);
    frame.style.height = `${nextHeight}px`;
});
</script>
<script>
const workerUploadLinkForm = document.getElementById('workerUploadLinkForm');
const workerUploadLinkStatus = document.getElementById('workerUploadLinkStatus');
const workerUploadSendButtons = Array.from(document.querySelectorAll('.worker-upload-send-btn'));
const workerUploadModeInput = document.getElementById('workerUploadMode');

function setWorkerUploadLinkStatus(type, html) {
    if (!workerUploadLinkStatus) {
        return;
    }

    workerUploadLinkStatus.className = 'mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold';
    if (type === 'success') {
        workerUploadLinkStatus.classList.add('block', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900');
    } else if (type === 'error') {
        workerUploadLinkStatus.classList.add('block', 'border-red-200', 'bg-red-50', 'text-red-900');
    } else {
        workerUploadLinkStatus.classList.add('block', 'border-slate-200', 'bg-slate-50', 'text-slate-800');
    }

    workerUploadLinkStatus.innerHTML = html;
}

if (workerUploadLinkForm) {
    workerUploadSendButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            if (workerUploadModeInput) {
                workerUploadModeInput.value = button.dataset.mode || '';
            }
        });
    });

    workerUploadLinkForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        const mode = workerUploadModeInput ? workerUploadModeInput.value : '';
        if (!mode) {
            setWorkerUploadLinkStatus('error', 'Choose a send action.');
            return;
        }

        const modeLabel = mode === 'mileage' ? 'mileage' : 'receipt';
        setWorkerUploadLinkStatus('info', `Sending WhatsApp ${modeLabel} link...`);
        workerUploadSendButtons.forEach(function(button) {
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-not-allowed');
        });

        try {
            const formData = new FormData(workerUploadLinkForm);
            formData.set('mode', mode);
            const response = await fetch('send_worker_upload_link.php', {
                method: 'POST',
                body: formData
            });
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Failed to send WhatsApp upload link.');
            }

            const safeLink = String(payload.link || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const detail = mode === 'mileage'
                ? 'The worker will only see the mileage upload screen.'
                : 'The worker will only see the receipt upload screen.';
            setWorkerUploadLinkStatus('success', `WhatsApp ${modeLabel} link sent.<br><span class="mt-2 block text-xs font-semibold text-emerald-800">${detail}</span><a href="${safeLink}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-black uppercase tracking-[0.18em] text-emerald-700 underline">Open worker page</a>`);
        } catch (error) {
            setWorkerUploadLinkStatus('error', error.message || 'Failed to send WhatsApp upload link.');
        } finally {
            workerUploadSendButtons.forEach(function(button) {
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
            });
            if (workerUploadModeInput) {
                workerUploadModeInput.value = '';
            }
        }
    });
}
</script>
</body>
</html>
