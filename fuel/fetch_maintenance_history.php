<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../tracker_data.php';
require_once __DIR__ . '/maintenance_history_repository.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    exit('Authentication required');
}

$entries = fuelLoadMaintenanceHistory();
$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
if ($vehicleId > 0) {
    $entries = fuelMaintenanceHistoryForVehicle($entries, $vehicleId);
}

$entries = array_slice($entries, 0, 25);
?>
<?php if (!$entries): ?>
    <div class="fuel-panel-state fuel-panel-state-muted !min-h-0 py-10">
        <i class="fas fa-wrench text-xl"></i>
        <span>No maintenance history logged yet.</span>
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($entries as $entry): ?>
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-5">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg px-2 py-1 border border-black/5 dark:border-white/5">
                                <?= htmlspecialchars((string) ($entry['vehicle_label'] ?? 'Vehicle')) ?>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 ring-indigo-200 dark:ring-indigo-900/50">
                                <?= htmlspecialchars((string) ($entry['event_type'] ?? 'service')) ?>
                            </span>
                        </div>
                        <div class="mt-3 text-sm font-bold text-slate-900 dark:text-slate-100">
                            <?= htmlspecialchars((string) ($entry['summary'] ?? 'Service update recorded')) ?>
                        </div>
                        <?php if (!empty($entry['notes'])): ?>
                            <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                <?= nl2br(htmlspecialchars((string) $entry['notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                            <?= htmlspecialchars(date('d M Y', strtotime((string) ($entry['completed_on'] ?? date('Y-m-d'))))) ?>
                        </div>
                        <div class="mt-2 text-xs font-bold text-slate-500 dark:text-slate-400">
                            by <?= htmlspecialchars((string) ($entry['updated_by'] ?? 'User')) ?>
                        </div>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-950/60 px-3 py-2">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mileage</div>
                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100"><?= number_format((int) ($entry['mileage_at_service'] ?? 0)) ?> mi</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-950/60 px-3 py-2">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Interval</div>
                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100"><?= number_format((int) ($entry['service_interval'] ?? 0)) ?> mi</div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-950/60 px-3 py-2">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Next Due</div>
                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100">
                            <?= !empty($entry['next_due']) ? htmlspecialchars(date('d M Y', strtotime((string) $entry['next_due']))) : 'Not set' ?>
                        </div>
                    </div>
                    <div class="rounded-xl bg-slate-50 dark:bg-slate-950/60 px-3 py-2">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Recorded</div>
                        <div class="mt-1 text-sm font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars(date('d M Y', strtotime((string) ($entry['created_at'] ?? date('c'))))) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
