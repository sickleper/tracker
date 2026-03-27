<?php
$pageTitle = 'Fuel Receipt Scanner';
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
        <h1 class="heading-brand">Fuel Receipt Scanner</h1>
        <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Standalone scanner workspace for workers uploading receipts, mileage, and direct fuel-log captures.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
        <div class="xl:col-span-4 space-y-8">
            <div class="card-base border-none">
                <div class="section-header !bg-slate-800 dark:!bg-slate-950/70">
                    <h3>
                        <i class="fas fa-receipt text-emerald-300"></i> Scanner Access
                    </h3>
                </div>
                <div class="p-8">
                    <p class="fuel-section-copy mb-6">This is the direct scanner page. Use it when a worker needs the scanner as its own screen rather than inside the fuel tab.</p>
                    <div class="space-y-4">
                        <a href="../fuel/index.php#scanner" class="fuel-action-btn fuel-action-btn-indigo w-full py-4 shadow-xl">
                            <i class="fas fa-camera-retro text-emerald-300"></i> Open In Fuel Module
                        </a>
                        <a href="../fuel/index.php#logs" class="fuel-action-btn fuel-action-btn-emerald w-full py-4 shadow-xl">
                            <i class="fas fa-gas-pump text-emerald-200"></i> View Fuel Logs
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-base border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-circle-info text-indigo-400"></i> Notes
                    </h3>
                </div>
                <div class="p-8 space-y-4">
                    <p class="fuel-section-copy">Worker uploads use the logged-in user id and assigned vehicle when creating fuel logs.</p>
                    <p class="fuel-section-copy">Direct captures still land in the same fuel log pipeline used by the main fleet module.</p>
                </div>
            </div>

            <div class="card-base border-none">
                <div class="section-header !bg-emerald-700 dark:!bg-emerald-950/60">
                    <h3>
                        <i class="fab fa-whatsapp text-emerald-200"></i> Driver Upload Link
                    </h3>
                </div>
                <div class="p-8">
                    <p class="fuel-section-copy mb-6">Send a WhatsApp link to a driver. The link opens a mobile upload page tied to that driver id and assigned vehicle.</p>
                    <?php if (empty($scannerDriverOptions)): ?>
                        <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">No drivers with assigned vehicles were found.</p>
                    <?php else: ?>
                        <form id="driverMileageLinkForm" class="space-y-4">
                            <div>
                                <label for="driverMileageSelect" class="mb-2 block text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Driver</label>
                                <select id="driverMileageSelect" name="driver_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                    <?php foreach ($scannerDriverOptions as $driverOption): ?>
                                        <option value="<?php echo (int) $driverOption['id']; ?>">
                                            <?php echo htmlspecialchars($driverOption['name'] . ' | ' . $driverOption['vehicle_reg'] . ($driverOption['mobile'] !== '' ? ' | ' . $driverOption['mobile'] : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="fuel-action-btn fuel-action-btn-emerald w-full py-4 shadow-xl">
                                <i class="fab fa-whatsapp text-emerald-100"></i> Send WhatsApp Link
                            </button>
                        </form>
                        <div id="driverMileageLinkStatus" class="mt-4 hidden rounded-2xl border px-4 py-3 text-sm font-semibold"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="xl:col-span-8">
            <div class="card-base border-none overflow-hidden">
                <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60">
                    <h3>
                        <i class="fas fa-camera-retro text-indigo-200"></i> Scanner Console
                    </h3>
                </div>
                <div class="bg-gray-50 dark:bg-slate-950 p-2 md:p-4">
                    <iframe
                        id="scannerConsoleFrame"
                        src="app.php"
                        title="Fuel Receipt Scanner"
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
const driverMileageLinkForm = document.getElementById('driverMileageLinkForm');
const driverMileageLinkStatus = document.getElementById('driverMileageLinkStatus');

function setDriverMileageLinkStatus(type, html) {
    if (!driverMileageLinkStatus) {
        return;
    }

    driverMileageLinkStatus.className = 'mt-4 rounded-2xl border px-4 py-3 text-sm font-semibold';
    if (type === 'success') {
        driverMileageLinkStatus.classList.add('block', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-900');
    } else if (type === 'error') {
        driverMileageLinkStatus.classList.add('block', 'border-red-200', 'bg-red-50', 'text-red-900');
    } else {
        driverMileageLinkStatus.classList.add('block', 'border-slate-200', 'bg-slate-50', 'text-slate-800');
    }

    driverMileageLinkStatus.innerHTML = html;
}

if (driverMileageLinkForm) {
    driverMileageLinkForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        setDriverMileageLinkStatus('info', 'Sending WhatsApp link...');

        try {
            const response = await fetch('send_driver_mileage_link.php', {
                method: 'POST',
                body: new FormData(driverMileageLinkForm)
            });
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || 'Failed to send WhatsApp link.');
            }

            const safeLink = String(payload.link || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            setDriverMileageLinkStatus('success', `WhatsApp link sent.<br><a href="${safeLink}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex text-xs font-black uppercase tracking-[0.18em] text-emerald-700 underline">Open upload page</a>`);
        } catch (error) {
            setDriverMileageLinkStatus('error', error.message || 'Failed to send WhatsApp link.');
        }
    });
}
</script>
</body>
</html>
