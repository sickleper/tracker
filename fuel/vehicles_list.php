<?php
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit;
}

// ─── Badge Helper ─────────────────────────────────────────────────────────────
function getBadge(string $label, bool $isExpired, int $daysLeft): string {
    if ($isExpired) {
        $icon = '✕';
        $text = "$label: Expired";
        $cls  = 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 ring-red-200 dark:ring-red-900/50';
    } elseif ($daysLeft <= 30) {
        $icon = '⚠';
        $text = "$label: {$daysLeft}d left";
        $cls  = 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 ring-amber-200 dark:ring-amber-900/50';
    } else {
        $icon = '✓';
        $text = "$label: Valid";
        $cls  = 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 ring-emerald-200 dark:ring-emerald-900/50';
    }

    return "<span class='inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest ring-1 ring-inset {$cls}'>"
         . "<span aria-hidden='true'>{$icon}</span>"
         . htmlspecialchars($text)
         . "</span>";
}

// ─── API Call ─────────────────────────────────────────────────────────────────
$res = makeApiCall('/api/fuel/vehicles');
$vehicles = ($res && ($res['success'] ?? false)) ? $res['vehicles'] : [];

$rows  = [];
$today = new DateTime();

foreach ($vehicles as $row) {
    $taxExp  = new DateTime($row['tax_expiry_date'] ?? '0000-00-00');
    $insExp  = new DateTime($row['insurance_expiry_date'] ?? '0000-00-00');
    $doeExp  = new DateTime($row['doe_expiry_date'] ?? '0000-00-00');

    $row['tax_days'] = (int) $today->diff($taxExp)->days;
    $row['ins_days'] = (int) $today->diff($insExp)->days;
    $row['doe_days'] = (int) $today->diff($doeExp)->days;
    $row['tax_past'] = $today > $taxExp && ($row['tax_expiry_date'] ?? '0000-00-00') !== '0000-00-00';
    $row['ins_past'] = $today > $insExp && ($row['insurance_expiry_date'] ?? '0000-00-00') !== '0000-00-00';
    $row['doe_past'] = $today > $doeExp && ($row['doe_expiry_date'] ?? '0000-00-00') !== '0000-00-00';

    $rows[] = $row;
}
?>

<div id="vehicle-list-container" class="table-container rounded-2xl border border-slate-200 dark:border-slate-800">
    <table id="reminder" class="w-full text-sm">

        <thead>
            <tr class="table-header-row">
                <th class="px-6 py-4 text-left">Registration</th>
                <th class="px-6 py-4 text-left">Driver</th>
                <th class="px-6 py-4 text-left">Make / Model</th>
                <th class="px-6 py-4 text-left">Compliance Status</th>
                <th class="px-6 py-4 text-right">Actions</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
        <?php foreach ($rows as $row):
            $anyExpired = $row['tax_past'] || $row['ins_past'] || $row['doe_past'];
            $anySoon    = (!$row['tax_past'] && $row['tax_days'] <= 30)
                       || (!$row['ins_past'] && $row['ins_days'] <= 30)
                       || (!$row['doe_past'] && $row['doe_days'] <= 30);

            $accentClass = $anyExpired ? 'row-expired' : ($anySoon ? 'row-soon' : '');
            $rowBg = $anyExpired ? 'bg-red-50/50 dark:bg-red-900/10' : ($anySoon ? 'bg-amber-50/50 dark:bg-amber-900/10' : '');
        ?>
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors <?= $accentClass ?> <?= $rowBg ?> border-l-4 <?= $anyExpired ? 'border-l-red-500' : ($anySoon ? 'border-l-amber-500' : 'border-l-transparent') ?>">

                <!-- Plate -->
                <td class="px-6 py-4">
                    <span class="font-mono text-[10px] font-black uppercase tracking-widest bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200 rounded-lg px-2 py-1 border border-black/5 dark:border-white/5">
                        <?= htmlspecialchars($row['license_plate']) ?>
                    </span>
                </td>

                <!-- Driver -->
                <td class="px-6 py-4 font-bold text-slate-900 dark:text-slate-100">
                    <?= htmlspecialchars($row['user']['name'] ?? 'Unassigned') ?>
                </td>

                <!-- Make / Model -->
                <td class="px-6 py-4 text-slate-500 dark:text-slate-400 font-medium">
                    <?= htmlspecialchars($row['make_model']) ?>
                </td>

                <!-- Status Badges -->
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-2">
                        <?= getBadge('Tax',       $row['tax_past'], $row['tax_days']) ?>
                        <?= getBadge('Insurance', $row['ins_past'], $row['ins_days']) ?>
                        <?= getBadge('DOE',       $row['doe_past'], $row['doe_days']) ?>
                    </div>
                </td>

                <!-- Action -->
                <td class="px-6 py-4 text-right">
                    <div class="flex gap-3 justify-end">
                        <button type="button" data-vehicle-id="<?= (int) $row['vehicle_id'] ?>"
                           class="edit-vehicle-btn text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="edit_vehicle_docs.php?vehicle_id=<?= (int) $row['vehicle_id'] ?>"
                           class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-900 dark:hover:text-emerald-200 font-black uppercase text-[10px] tracking-widest transition-colors">
                            <i class="fas fa-file-invoice"></i> Docs
                        </a>
                    </div>
                </td>

            </tr>
        <?php endforeach; ?>
        </tbody>

    </table>
</div>

<style>
    /* Left accent border to flag row urgency at a glance */
    #reminder tbody tr {
        transition: all 0.2s;
    }
</style>
