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
        <title>Driver Mileage Upload</title>
        <link rel="stylesheet" href="/dist/output.css?v=<?php echo time(); ?>">
    </head>
    <body class="min-h-screen bg-slate-950 px-4 py-10 text-slate-100">
        <div class="mx-auto max-w-md rounded-3xl border border-red-900/60 bg-slate-900/90 p-6 shadow-2xl">
            <h1 class="text-xl font-black">Link unavailable</h1>
            <p class="mt-3 text-sm text-slate-300">This mileage upload link is invalid or has expired. Ask the office to send a new WhatsApp link.</p>
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Driver Mileage Upload</title>
    <link rel="stylesheet" href="/dist/output.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="mx-auto flex min-h-screen max-w-lg items-center px-4 py-8">
        <div class="w-full rounded-[32px] border border-slate-200/80 bg-white/95 p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900/95">
            <div class="mb-6">
                <p class="text-[11px] font-black uppercase tracking-[0.28em] text-emerald-600 dark:text-emerald-300">Mileage Upload</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight">Take odometer photo</h1>
                <p class="mt-3 text-sm font-medium text-slate-500 dark:text-slate-400">Take a dashboard photo with the camera or choose one from the phone gallery. The system will read the odometer automatically.</p>
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

            <form id="driverMileageForm" class="space-y-5" enctype="multipart/form-data">
                <div>
                    <label for="mileagePhoto" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Mileage Photo</label>
                    <input id="mileagePhoto" name="mileage_photo" type="file" accept="image/*" capture="environment" required class="hidden">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button type="button" id="cameraUploadTrigger" class="inline-flex w-full items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-emerald-800 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
                            <i class="fas fa-camera mr-2"></i> Use Camera
                        </button>
                        <button type="button" id="galleryUploadTrigger" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-4 text-sm font-black uppercase tracking-[0.18em] text-slate-800 shadow-sm dark:border-slate-700 dark:bg-slate-950/70 dark:text-slate-100">
                            <i class="fas fa-image mr-2"></i> Choose Photo
                        </button>
                    </div>
                    <p id="selectedMileagePhoto" class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">No photo selected yet.</p>
                    <p class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">Use a clear photo of the main odometer reading. Avoid trip, range, or fuel screens.</p>
                </div>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-gradient-to-r from-indigo-600 to-emerald-600 px-5 py-4 text-sm font-black uppercase tracking-[0.18em] text-white shadow-lg">
                    <i class="fas fa-camera mr-2"></i> Upload Mileage
                </button>
            </form>

            <div id="driverUploadStatus" class="mt-5 hidden rounded-3xl border px-4 py-4 text-sm font-semibold"></div>
        </div>
    </div>
    <script>
    const driverMileageForm = document.getElementById('driverMileageForm');
    const driverUploadStatus = document.getElementById('driverUploadStatus');
    const mileagePhotoInput = document.getElementById('mileagePhoto');
    const cameraUploadTrigger = document.getElementById('cameraUploadTrigger');
    const galleryUploadTrigger = document.getElementById('galleryUploadTrigger');
    const selectedMileagePhoto = document.getElementById('selectedMileagePhoto');

    function updateSelectedMileagePhoto() {
        const file = mileagePhotoInput.files && mileagePhotoInput.files[0] ? mileagePhotoInput.files[0] : null;
        selectedMileagePhoto.textContent = file ? `Selected: ${file.name}` : 'No photo selected yet.';
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

    cameraUploadTrigger.addEventListener('click', function() {
        mileagePhotoInput.setAttribute('capture', 'environment');
        mileagePhotoInput.click();
    });

    galleryUploadTrigger.addEventListener('click', function() {
        mileagePhotoInput.removeAttribute('capture');
        mileagePhotoInput.click();
    });

    mileagePhotoInput.addEventListener('change', updateSelectedMileagePhoto);

    driverMileageForm.addEventListener('submit', async function(event) {
        event.preventDefault();

        const formData = new FormData(driverMileageForm);
        const file = formData.get('mileage_photo');
        if (!file || !file.name) {
            setDriverUploadStatus('error', 'Choose a mileage photo first.');
            return;
        }

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
            updateSelectedMileagePhoto();
        } catch (error) {
            setDriverUploadStatus('error', error.message || 'Mileage upload failed.');
        }
    });
    </script>
</body>
</html>
