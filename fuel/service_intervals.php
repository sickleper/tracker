<?php
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit;
}

function normalizeServiceDateValue($value): string
{
    if ($value === null) {
        return '';
    }

    $normalized = trim((string) $value);
    if ($normalized === '' || $normalized === '0' || $normalized === '0000-00-00') {
        return '';
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) ? $normalized : '';
}

// ─── API Call ─────────────────────────────────────────────────────────────────
$res = makeApiCall('/api/fuel/vehicles');
$vehicles = ($res && ($res['success'] ?? false)) ? $res['vehicles'] : [];

// ─── Status Helper ────────────────────────────────────────────────────────────
function getServiceStatus(int $milesLeft, ?string $serviceDue): array {
    $today        = new DateTime();
    $mileageOverdue = $milesLeft <= 0;
    $mileageSoon    = $milesLeft > 0 && $milesLeft <= 1000;
    $dateOverdue    = false;
    $dateSoon       = false;

    if ($serviceDue) {
        $daysUntilDue = (int)(new DateTime())->diff(new DateTime($serviceDue))->format('%r%a');
        $dateOverdue  = $daysUntilDue < 0;
        $dateSoon     = $daysUntilDue >= 0 && $daysUntilDue <= 30;
    }

    if ($mileageOverdue || $dateOverdue) {
        return ['priority' => 1, 'label' => 'OVERDUE',  'row' => 'bg-red-50/50 dark:bg-red-900/10',    'badge' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 ring-red-200 dark:ring-red-900/50'];
    } elseif ($mileageSoon || $dateSoon) {
        return ['priority' => 2, 'label' => 'DUE SOON', 'row' => 'bg-amber-50/50 dark:bg-amber-900/10',  'badge' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 ring-amber-200 dark:ring-amber-900/50'];
    } else {
        return ['priority' => 3, 'label' => 'OK',       'row' => 'bg-emerald-50/50 dark:bg-emerald-900/10','badge' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 ring-emerald-200 dark:ring-emerald-900/50'];
    }
}

// Sort: overdue first
usort($vehicles, function ($a, $b) {
    $milesLeftA = $a['service_mileage_threshold'] - ($a['last_mileage'] - $a['last_service_mileage']);
    $milesLeftB = $b['service_mileage_threshold'] - ($b['last_mileage'] - $b['last_service_mileage']);
    $statusA = getServiceStatus($milesLeftA, $a['service_due']);
    $statusB = getServiceStatus($milesLeftB, $b['service_due']);
    return $statusA['priority'] - $statusB['priority'];
});
?>

<!-- Legend -->
<div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 px-6 py-4 mb-6 text-sm text-slate-600 dark:text-slate-400 shadow-sm">
    <span class="font-black uppercase tracking-widest text-[10px] text-slate-400">Legend:</span>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 ring-red-200 dark:ring-red-900/50">✕ OVERDUE</span>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 ring-amber-200 dark:ring-amber-900/50">⚠ DUE SOON</span>
    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 ring-emerald-200 dark:ring-emerald-900/50">✓ OK</span>
</div>

<!-- Table -->
<div class="table-container rounded-2xl border border-slate-200 dark:border-slate-800">
    <table id="service_table" class="w-full text-sm">
        <thead class="bg-slate-900 dark:bg-black text-white">
        <tr class="table-header-row">
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Status</th>
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Reg</th>
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Make / Model</th>
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Driver</th>
            <th class="px-4 py-4 text-right text-white text-[10px] font-black uppercase tracking-widest">Last Svc</th>
            <th class="px-4 py-4 text-right text-white text-[10px] font-black uppercase tracking-widest">Current</th>
            <th class="px-4 py-4 text-right text-white text-[10px] font-black uppercase tracking-widest">Remaining</th>
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Due Date</th>
            <th class="px-4 py-4 text-left text-white text-[10px] font-black uppercase tracking-widest">Update Service Data</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
        <?php foreach ($vehicles as $v):
            $lastMi = $v['last_mileage'] ?? 0;
            $milesLeft = $v['service_mileage_threshold'] - ($lastMi - $v['last_service_mileage']);
            $status    = getServiceStatus($milesLeft, $v['service_due']);

            $daysLabel = 'N/A';
            if ($v['service_due']) {
                $days = (int)(new DateTime())->diff(new DateTime($v['service_due']))->format('%r%a');
                if ($days < 0) {
                    $daysLabel = '<span class="font-bold text-red-600 dark:text-red-400">' . abs($days) . 'd overdue</span>';
                } elseif ($days === 0) {
                    $daysLabel = '<span class="font-bold text-amber-600 dark:text-amber-400">Due TODAY</span>';
                } else {
                    $daysLabel = $days . ' days';
                }
            }

            if ($milesLeft <= 0) {
                $milesLabel = '<span class="font-bold text-red-600 dark:text-red-400">' . number_format(abs($milesLeft)) . ' mi overdue</span>';
            } elseif ($milesLeft <= 1000) {
                $milesLabel = '<span class="font-bold text-amber-600 dark:text-amber-400">' . number_format($milesLeft) . ' mi</span>';
            } else {
                $milesLabel = number_format($milesLeft) . ' mi';
            }
            ?>
            <tr id="service-row-<?= (int) $v['vehicle_id'] ?>" data-vehicle-id="<?= (int) $v['vehicle_id'] ?>" class="<?= $status['row'] ?> hover:brightness-95 dark:hover:brightness-110 transition-all border-l-4
                <?= $status['priority'] === 1 ? 'border-l-red-500' : ($status['priority'] === 2 ? 'border-l-amber-500' : 'border-l-emerald-500') ?>">
                <td class="px-4 py-4">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset <?= $status['badge'] ?>">
                        <?= $status['priority'] === 1 ? '✕' : ($status['priority'] === 2 ? '⚠' : '✓') ?>
                        <?= $status['label'] ?>
                    </span>
                </td>
                <td class="px-4 py-4"><span class="font-mono text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg px-2 py-1 border border-black/5 dark:border-white/5"><?= htmlspecialchars($v['license_plate']) ?></span></td>
                <td class="px-4 py-4 text-slate-600 dark:text-slate-400 font-medium"><?= htmlspecialchars($v['make_model']) ?></td>
                <td class="px-4 py-4 font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($v['user']['name'] ?? 'N/A') ?></td>
                <td class="px-4 py-4 font-mono text-slate-500 dark:text-slate-400 text-right"><?= number_format($v['last_service_mileage']) ?></td>
                <td class="px-4 py-4 font-mono text-slate-500 dark:text-slate-400 text-right"><?= number_format($lastMi) ?></td>
                <td class="px-4 py-4 font-mono text-right"><?= $milesLabel ?></td>
                <td class="px-4 py-4">
                    <div class="flex flex-col">
                        <span class="text-gray-900 dark:text-gray-100 font-bold"><?= $v['service_due'] ? date('d M Y', strtotime($v['service_due'])) : '<span class="text-slate-400 italic text-xs">Not set</span>' ?></span>
                        <span class="text-[10px] uppercase font-black tracking-tighter text-gray-400"><?= $daysLabel ?></span>
                    </div>
                </td>
                <td class="px-4 py-4">
                    <form action="update_service_interval.php" method="post" class="service-interval-form space-y-3 min-w-[320px]">
                        <input type="hidden" name="vehicle_id" value="<?= (int) $v['vehicle_id'] ?>">
                        <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Last Service</span>
                                <input type="number" name="last_service_mileage" min="0" value="<?= htmlspecialchars($v['last_service_mileage']) ?>" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            </label>
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Interval</span>
                                <input type="number" name="new_service_interval" min="0" value="<?= htmlspecialchars($v['service_mileage_threshold']) ?>" required class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            </label>
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Next Due</span>
                                <input type="date" name="service_due" value="<?= htmlspecialchars(normalizeServiceDateValue($v['service_due'] ?? null)) ?>" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            </label>
                            <label class="block">
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Completed On</span>
                                <input type="date" name="service_completed_on" value="" class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            </label>
                        </div>
                        <div class="flex flex-col md:flex-row gap-3">
                            <label class="block flex-1">
                                <span class="block text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Work Notes</span>
                                <input type="text" name="service_notes" value="" placeholder="Oil, filter, brakes, tyres..." class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-950 px-3 py-2 text-xs font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition">
                            </label>
                            <button type="submit" class="self-end px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white transition-all shadow-md active:scale-95 font-black uppercase tracking-widest text-[10px]" title="Save Changes">
                                <i class="fas fa-save mr-1"></i> Save
                            </button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
