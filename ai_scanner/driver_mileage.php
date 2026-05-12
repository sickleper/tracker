<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/link_helper.php';

$driverUploadContext = receiptScannerResolveDriverMileageRequest($_GET);

if (!$driverUploadContext) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Worker Upload</title>
        <link rel="stylesheet" href="/dist/output.css?v=<?php echo time(); ?>">
    </head>
    <body class="min-h-screen bg-slate-950 px-4 py-10 text-slate-100">
        <div class="mx-auto max-w-md rounded-3xl border border-red-900/60 bg-slate-900/90 p-6 shadow-2xl">
            <h1 class="text-xl font-black">Link unavailable</h1>
            <p class="mt-3 text-sm text-slate-300">This worker upload link is invalid or has expired. Ask the office to send a new WhatsApp link.</p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

$GLOBALS['receipt_scanner_public_driver_context'] = $driverUploadContext;
define('RECEIPT_SCANNER_ALLOW_PUBLIC_DRIVER_UPLOAD', true);
define('RECEIPT_SCANNER_BOOTSTRAP_ONLY', true);
require_once __DIR__ . '/app.php';

$driverName = trim((string) ($driverUploadContext['driver_name'] ?? 'Driver'));
$vehicleReg = trim((string) ($driverUploadContext['vehicle_reg'] ?? ''));
$expiresAt = (int) ($driverUploadContext['expires'] ?? 0);
$expiresLabel = $expiresAt > 0 ? date('d M Y H:i', $expiresAt) : 'Unknown';
$uploadMode = strtolower(trim((string) ($driverUploadContext['mode'] ?? 'both')));
if (!in_array($uploadMode, ['mileage', 'receipt', 'both'], true)) {
    $uploadMode = 'both';
}
$showMileage = $uploadMode !== 'receipt';
$showReceipt = $uploadMode !== 'mileage';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Worker Upload</title>
    <link rel="stylesheet" href="/dist/output.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-8">
        <div class="w-full rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900/95">
            <div class="mb-6">
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-600 dark:text-emerald-300">Worker Upload</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">
                    <?php echo $uploadMode === 'mileage' ? 'Send mileage' : ($uploadMode === 'receipt' ? 'Send receipts' : 'Send mileage or receipts'); ?>
                </h1>
                <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">
                    <?php if ($uploadMode === 'mileage'): ?>
                        Use this secure link to upload an odometer photo. Mileage uploads are saved straight into the tracker for this worker and vehicle.
                    <?php elseif ($uploadMode === 'receipt'): ?>
                        Use this secure link to upload receipts or invoices. Receipt uploads are saved straight into the tracker for this worker and vehicle.
                    <?php else: ?>
                        Use this secure link to upload an odometer photo, receipt, or invoice. Mileage and receipt uploads are saved straight into the tracker for this worker and vehicle.
                    <?php endif; ?>
                </p>
            </div>

            <div class="mb-6 grid gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                <div>
                    <div class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Driver</div>
                    <div class="mt-1 text-base font-bold"><?php echo htmlspecialchars($driverName !== '' ? $driverName : 'Assigned Driver'); ?></div>
                </div>
                <div>
                    <div class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Vehicle</div>
                    <div class="mt-1 text-base font-bold"><?php echo htmlspecialchars($vehicleReg); ?></div>
                </div>
                <div>
                    <div class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Link Expires</div>
                    <div class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($expiresLabel); ?></div>
                </div>
            </div>

            <?php if ($showMileage && $showReceipt): ?>
                <div class="mb-5 grid grid-cols-2 gap-2 rounded-3xl border border-slate-200 bg-slate-100 p-2 dark:border-slate-800 dark:bg-slate-950/70">
                    <button type="button" id="tabMileage" data-tab="mileage" class="driver-tab inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-black uppercase tracking-[0.18em] transition">
                        <i class="fas fa-gauge-high mr-2"></i> Mileage
                    </button>
                    <button type="button" id="tabReceipt" data-tab="receipt" class="driver-tab inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-black uppercase tracking-[0.18em] transition">
                        <i class="fas fa-receipt mr-2"></i> Receipt
                    </button>
                </div>
            <?php endif; ?>

            <section id="panelMileage" class="driver-panel<?php echo $showMileage ? ' space-y-5' : ' hidden'; ?>">
                <form id="driverMileageForm" class="space-y-5" enctype="multipart/form-data">
                    <div>
                        <label for="mileagePhoto" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Mileage Photo</label>
                        <input id="mileageCameraInput" type="file" accept="image/*" capture="environment" class="sr-only">
                        <input id="mileageGalleryInput" type="file" accept="image/*" class="sr-only">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label for="mileageCameraInput" class="inline-flex w-full cursor-pointer items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-emerald-800 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
                                <i class="fas fa-camera mr-2"></i> Use Camera
                            </label>
                            <label for="mileageGalleryInput" class="inline-flex w-full cursor-pointer items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-slate-800 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100">
                                <i class="fas fa-image mr-2"></i> Choose Photo
                            </label>
                        </div>
                        <p id="selectedMileagePhoto" class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">No photo selected yet.</p>
                        <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">Use a clear photo of the main odometer reading. Avoid trip, range, or fuel screens.</p>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-indigo-600 to-emerald-600 px-5 py-4 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg">
                        <i class="fas fa-camera mr-2"></i> Upload Mileage
                    </button>
                </form>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Upload History</p>
                            <h2 class="mt-1 text-base font-black">Recent Mileage Uploads</h2>
                        </div>
                    </div>
                    <div id="mileageHistoryList" class="space-y-3">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Loading mileage history...</p>
                    </div>
                </div>
            </section>

            <section id="panelReceipt" class="driver-panel<?php echo $showReceipt ? ($showMileage ? ' hidden space-y-5' : ' space-y-5') : ' hidden'; ?>">
                <form id="driverReceiptForm" class="space-y-5" enctype="multipart/form-data">
                    <div>
                        <label for="receiptFile" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Receipt Or Invoice</label>
                        <input id="receiptCameraInput" type="file" accept="image/*" capture="environment" class="sr-only">
                        <input id="receiptFileInput" type="file" accept="image/*,.pdf,application/pdf" class="sr-only">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label for="receiptCameraInput" class="inline-flex w-full cursor-pointer items-center justify-center rounded-2xl border border-sky-200 bg-sky-50 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-sky-800 shadow-sm dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100">
                                <i class="fas fa-camera mr-2"></i> Photo
                            </label>
                            <label for="receiptFileInput" class="inline-flex w-full cursor-pointer items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-slate-800 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100">
                                <i class="fas fa-file-upload mr-2"></i> Choose File
                            </label>
                        </div>
                        <p id="selectedReceiptFile" class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">No file selected yet.</p>
                        <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">You can upload fuel receipts, supplier receipts, or construction invoices as a photo or PDF. They will be saved directly against this worker upload link.</p>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-sky-600 to-indigo-600 px-5 py-4 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg">
                        <i class="fas fa-receipt mr-2"></i> Upload Receipt
                    </button>
                </form>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/70">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">Upload History</p>
                            <h2 class="mt-1 text-base font-black">Recent Receipt Uploads</h2>
                        </div>
                    </div>
                    <div id="receiptHistoryList" class="space-y-3">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Loading receipt history...</p>
                    </div>
                </div>
            </section>

            <div id="driverUploadStatus" class="mt-5 hidden rounded-3xl border px-4 py-4 text-sm font-semibold"></div>
        </div>
    </div>
    <script>
    const defaultTab = <?php echo json_encode($showMileage ? 'mileage' : 'receipt'); ?>;
    const tabsEnabled = <?php echo $showMileage && $showReceipt ? 'true' : 'false'; ?>;
    const driverTabs = Array.from(document.querySelectorAll('.driver-tab'));
    const driverPanels = {
        mileage: document.getElementById('panelMileage'),
        receipt: document.getElementById('panelReceipt')
    };
    const driverMileageForm = document.getElementById('driverMileageForm');
    const driverReceiptForm = document.getElementById('driverReceiptForm');
    const driverUploadStatus = document.getElementById('driverUploadStatus');
    const mileageCameraInput = document.getElementById('mileageCameraInput');
    const mileageGalleryInput = document.getElementById('mileageGalleryInput');
    const selectedMileagePhoto = document.getElementById('selectedMileagePhoto');
    const receiptCameraInput = document.getElementById('receiptCameraInput');
    const receiptFileInput = document.getElementById('receiptFileInput');
    const selectedReceiptFile = document.getElementById('selectedReceiptFile');
    const mileageHistoryList = document.getElementById('mileageHistoryList');
    const receiptHistoryList = document.getElementById('receiptHistoryList');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatHistoryDate(value) {
        if (!value) {
            return 'Unknown time';
        }

        const parsed = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        return parsed.toLocaleString([], {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function renderMileageHistory(history) {
        if (!Array.isArray(history) || history.length === 0) {
            mileageHistoryList.innerHTML = '<p class="text-sm font-medium text-slate-500 dark:text-slate-400">No mileage uploads yet on this link.</p>';
            return;
        }

        mileageHistoryList.innerHTML = history.map(function(item) {
            const mileage = item && item.mileage ? Number(item.mileage).toLocaleString() + ' km' : 'Mileage saved';
            const vehicle = escapeHtml(item.vehicle_reg || '<?php echo htmlspecialchars($vehicleReg); ?>');
            const createdAt = formatHistoryDate(item.created_at || '');
            const openFile = item.file_url
                ? `<a href="${escapeHtml(item.file_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-700 ring-1 ring-emerald-200 dark:bg-slate-900 dark:text-emerald-200 dark:ring-emerald-900/50"><i class="fas fa-image mr-1.5"></i> Open</a>`
                : '';

            return `
                <div class="rounded-2xl border border-emerald-200 bg-white px-4 py-3 shadow-sm dark:border-emerald-900/40 dark:bg-slate-900/80">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-slate-900 dark:text-slate-100">${escapeHtml(mileage)}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">${vehicle}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100">Mileage</span>
                            ${openFile}
                        </div>
                    </div>
                    <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">${escapeHtml(createdAt)}</p>
                </div>
            `;
        }).join('');
    }

    function renderReceiptHistory(history) {
        if (!Array.isArray(history) || history.length === 0) {
            receiptHistoryList.innerHTML = '<p class="text-sm font-medium text-slate-500 dark:text-slate-400">No receipts uploaded yet on this link.</p>';
            return;
        }

        receiptHistoryList.innerHTML = history.map(function(item) {
            const merchant = escapeHtml(item.merchant_name || 'Receipt');
            const total = item.total_amount ? 'EUR ' + Number(item.total_amount).toFixed(2) : 'Saved';
            const category = escapeHtml(item.category || 'Other');
            const createdAt = formatHistoryDate(item.created_at || '');
            const receiptDate = item.transaction_date ? escapeHtml(item.transaction_date) : '';
            const statusText = item.record_status === 'synced_to_api'
                ? 'Synced'
                : 'Saved';
            const openFile = item.file_url
                ? `<a href="${escapeHtml(item.file_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-full bg-white/80 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-sky-700 ring-1 ring-sky-200 dark:bg-slate-900 dark:text-sky-200 dark:ring-sky-900/50"><i class="fas fa-file-lines mr-1.5"></i> Open</a>`
                : '';

            return `
                <div class="rounded-2xl border border-sky-200 bg-white px-4 py-3 shadow-sm dark:border-sky-900/40 dark:bg-slate-900/80">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-slate-900 dark:text-slate-100">${merchant}</p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">${escapeHtml(total)}${receiptDate ? ` • ${receiptDate}` : ''}</p>
                            <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">Status: ${escapeHtml(statusText)}</p>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="rounded-full bg-sky-100 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-sky-800 dark:bg-sky-950/50 dark:text-sky-100">${category}</span>
                            ${openFile}
                        </div>
                    </div>
                    <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">${escapeHtml(createdAt)}</p>
                </div>
            `;
        }).join('');
    }

    async function loadPublicHistory(type) {
        const requestUrl = new URL(window.location.href);
        requestUrl.searchParams.set('action', 'public_history');
        requestUrl.searchParams.set('type', type);

        const response = await fetch(requestUrl.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();
        if (!payload.success) {
            throw new Error(payload.error || 'Failed to load upload history.');
        }

        if (type === 'mileage') {
            renderMileageHistory(payload.history || []);
        } else {
            renderReceiptHistory(payload.history || []);
        }
    }

    async function refreshPublicHistory() {
        try {
            const historyRequests = [];
            if (mileageHistoryList) {
                historyRequests.push(loadPublicHistory('mileage'));
            }
            if (receiptHistoryList) {
                historyRequests.push(loadPublicHistory('receipt'));
            }
            await Promise.all(historyRequests);
        } catch (error) {
            if (mileageHistoryList) {
                mileageHistoryList.innerHTML = '<p class="text-sm font-medium text-red-600 dark:text-red-300">Could not load mileage history.</p>';
            }
            if (receiptHistoryList) {
                receiptHistoryList.innerHTML = '<p class="text-sm font-medium text-red-600 dark:text-red-300">Could not load receipt history.</p>';
            }
        }
    }

    function setActiveTab(tabKey) {
        driverTabs.forEach(function(tabButton) {
            const isActive = tabButton.dataset.tab === tabKey;
            tabButton.className = 'driver-tab inline-flex items-center justify-center rounded-2xl px-4 py-3 text-sm font-black uppercase tracking-[0.18em] transition';
            if (isActive) {
                tabButton.classList.add('bg-slate-900', 'text-white', 'shadow-lg', 'dark:bg-white', 'dark:text-slate-900');
            } else {
                tabButton.classList.add('text-slate-500', 'hover:bg-white', 'hover:text-slate-900', 'dark:text-slate-300', 'dark:hover:bg-slate-900', 'dark:hover:text-white');
            }
        });

        Object.keys(driverPanels).forEach(function(key) {
            driverPanels[key].classList.toggle('hidden', key !== tabKey);
        });
    }

    function selectedFileFromInputs(inputs) {
        for (const input of inputs) {
            if (input && input.files && input.files[0]) {
                return input.files[0];
            }
        }
        return null;
    }

    function clearInputValue(input) {
        if (input) {
            input.value = '';
        }
    }

    function updateSelectedMileagePhoto() {
        const file = selectedFileFromInputs([mileageCameraInput, mileageGalleryInput]);
        selectedMileagePhoto.textContent = file ? `Selected: ${file.name}` : 'No photo selected yet.';
    }

    function updateSelectedReceiptFile() {
        const file = selectedFileFromInputs([receiptCameraInput, receiptFileInput]);
        selectedReceiptFile.textContent = file ? `Selected: ${file.name}` : 'No file selected yet.';
    }

    function setDriverUploadStatus(type, message) {
        driverUploadStatus.className = 'mt-5 rounded-3xl border px-4 py-4 text-sm font-semibold';
        if (type === 'success') {
            driverUploadStatus.classList.add('block', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900', 'dark:border-emerald-900/50', 'dark:bg-emerald-950/30', 'dark:text-emerald-100');
        } else if (type === 'error') {
            driverUploadStatus.classList.add('block', 'border-red-200', 'bg-red-50', 'text-red-900', 'dark:border-red-900/50', 'dark:bg-red-950/30', 'dark:text-red-100');
        } else {
            driverUploadStatus.classList.add('block', 'border-slate-200', 'bg-slate-50', 'text-slate-800', 'dark:border-slate-700', 'dark:bg-slate-950/40', 'dark:text-slate-200');
        }
        driverUploadStatus.textContent = message;
    }

    if (tabsEnabled) {
        driverTabs.forEach(function(tabButton) {
            tabButton.addEventListener('click', function() {
                setActiveTab(tabButton.dataset.tab);
            });
        });
    }

    if (mileageCameraInput) {
        mileageCameraInput.addEventListener('change', function() {
            clearInputValue(mileageGalleryInput);
            updateSelectedMileagePhoto();
        });
    }

    if (mileageGalleryInput) {
        mileageGalleryInput.addEventListener('change', function() {
            clearInputValue(mileageCameraInput);
            updateSelectedMileagePhoto();
        });
    }

    if (receiptCameraInput) {
        receiptCameraInput.addEventListener('change', function() {
            clearInputValue(receiptFileInput);
            updateSelectedReceiptFile();
        });
    }

    if (receiptFileInput) {
        receiptFileInput.addEventListener('change', function() {
            clearInputValue(receiptCameraInput);
            updateSelectedReceiptFile();
        });
    }

    if (driverMileageForm) {
        driverMileageForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const file = selectedFileFromInputs([mileageCameraInput, mileageGalleryInput]);
            if (!file || !file.name) {
                setDriverUploadStatus('error', 'Choose a mileage photo first.');
                return;
            }

            const formData = new FormData();
            formData.append('mileage_photo', file, file.name);

            setDriverUploadStatus('info', 'Uploading and reading odometer...');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.error || payload.message || 'Mileage upload failed.');
                }

                const mileageValue = payload.data && payload.data.mileage ? Number(payload.data.mileage).toLocaleString() : 'saved';
                setDriverUploadStatus('success', `Mileage saved: ${mileageValue} km`);
                driverMileageForm.reset();
                clearInputValue(mileageCameraInput);
                clearInputValue(mileageGalleryInput);
                updateSelectedMileagePhoto();
                await loadPublicHistory('mileage');
            } catch (error) {
                setDriverUploadStatus('error', error.message || 'Mileage upload failed.');
            }
        });
    }

    if (driverReceiptForm) {
        driverReceiptForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const file = selectedFileFromInputs([receiptCameraInput, receiptFileInput]);
            if (!file || !file.name) {
                setDriverUploadStatus('error', 'Choose a receipt or invoice first.');
                return;
            }

            const formData = new FormData();
            formData.append('receipt', file, file.name);

            setDriverUploadStatus('info', 'Uploading and reading receipt...');

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.error || payload.message || 'Receipt upload failed.');
                }

                const details = payload.data || {};
                const merchant = details.merchant || 'Receipt';
                const total = details.total ? `, total ${details.total}` : '';
                setDriverUploadStatus('success', `${merchant} uploaded${total}.`);
                driverReceiptForm.reset();
                clearInputValue(receiptCameraInput);
                clearInputValue(receiptFileInput);
                updateSelectedReceiptFile();
                await loadPublicHistory('receipt');
            } catch (error) {
                setDriverUploadStatus('error', error.message || 'Receipt upload failed.');
            }
        });
    }

    setActiveTab(defaultTab);
    refreshPublicHistory();
    </script>
</body>
</html>
