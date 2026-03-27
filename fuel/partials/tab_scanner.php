<div id="tab-scanner" class="tab-pane hidden space-y-8">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
        <div class="xl:col-span-4 space-y-8">
            <div class="card-base border-none">
                <div class="section-header !bg-slate-800 dark:!bg-slate-950/70">
                    <h3>
                        <i class="fas fa-receipt text-emerald-300"></i> Capture Workspace
                    </h3>
                </div>
                <div class="p-8">
                    <p class="fuel-section-copy mb-6">Use the scanner to turn a fuel receipt and an odometer photo into a real fuel log entry. Successful captures write into the same log feed shown on the Logs tab.</p>
                    <div class="space-y-4">
                        <a href="#logs" onclick="window.location.hash='logs'; return false;" class="fuel-action-btn fuel-action-btn-indigo w-full py-4 shadow-xl">
                            <i class="fas fa-gas-pump text-emerald-300"></i> Back To Fuel Logs
                        </a>
                        <a href="<?php echo rtrim(trackerAppUrl(), '/'); ?>/receipt_scanner/index.php" target="_blank" rel="noopener noreferrer" class="fuel-action-btn fuel-action-btn-emerald w-full py-4 shadow-xl">
                            <i class="fas fa-up-right-from-square text-emerald-200"></i> Open Scanner Direct
                        </a>
                        <?php if (!empty($fuelScannerPublicMileageLink)): ?>
                            <a href="<?php echo htmlspecialchars($fuelScannerPublicMileageLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="fuel-action-btn fuel-action-btn-indigo w-full py-4 shadow-xl">
                                <i class="fas fa-mobile-screen-button text-emerald-300"></i> Open My Public Mileage Page
                            </a>
                        <?php endif; ?>
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
                    <p class="fuel-section-copy">Today’s captures respect the same daily-check and off-road rules as the manual log form.</p>
                    <p class="fuel-section-copy">Receipt thumbnails for scanner-created fuel logs are copied into the fleet upload folder so they render normally in the logs table.</p>
                    <?php if (!empty($fuelScannerPublicMileageLink) && !empty($currentFuelScannerVehicle['license_plate'])): ?>
                        <p class="fuel-section-copy">Your current public mileage-upload link is tied to vehicle <?php echo htmlspecialchars((string) $currentFuelScannerVehicle['license_plate'], ENT_QUOTES, 'UTF-8'); ?>.</p>
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
                        id="scannerConsoleFrameEmbedded"
                        src="<?php echo rtrim(trackerAppUrl(), '/'); ?>/receipt_scanner/app.php"
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

    const frame = document.getElementById('scannerConsoleFrameEmbedded');
    if (!frame) {
        return;
    }

    const nextHeight = Math.max(1200, Number(event.data.height) || 0);
    frame.style.height = `${nextHeight}px`;
});
</script>
