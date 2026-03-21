<?php
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../tracker_data.php"; // For makeApiCall

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit;
}

include '../header.php' ;
include '../nav.php' ;

$vehicleId = $_GET['vehicle_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_info'])) {
    $vId = $_POST['vehicle_id'];
    $data = [
        'tax_disk_ref' => $_POST['tax_disk_ref'] ?? null,
        'tax_issue_date' => $_POST['tax_issue_date'] ?? null,
        'tax_expiry_date' => $_POST['tax_expiry_date'] ?? null,
        'insurance_disk_ref' => $_POST['insurance_disk_ref'] ?? null,
        'insurance_issue_date' => $_POST['insurance_issue_date'] ?? null,
        'insurance_expiry_date' => $_POST['insurance_expiry_date'] ?? null,
        'doe_disk_ref' => $_POST['doe_disk_ref'] ?? null,
        'doe_issue_date' => $_POST['doe_issue_date'] ?? null,
        'doe_expiry_date' => $_POST['doe_expiry_date'] ?? null,
    ];

    $response = makeApiCall("/api/fuel/vehicles/{$vId}", $data, 'PATCH');

    if ($response && ($response['success'] ?? false)) {
        echo "<script>Swal.fire({ icon:'success', title:'Success', text:'Vehicle documents updated.', timer: 2000, showConfirmButton: false }).then(() => window.location.href = 'index.php');</script>";
    } else {
        $msg = $response['message'] ?? 'Error updating documents.';
        echo "<script>Swal.fire({ icon:'error', title:'Error', text:'$msg' });</script>";
    }
}

// Fetch vehicle via API
$vehicle = null;
if ($vehicleId) {
    $res = makeApiCall("/api/fuel/vehicles/{$vehicleId}");
    if ($res && ($res['success'] ?? false)) {
        $vehicle = $res['vehicle'];
    }
}

if (!$vehicle) {
    echo "<div class='max-w-4xl mx-auto px-4 py-20 text-center'><div class='card-base p-12'><i class='fas fa-exclamation-triangle text-red-500 text-4xl mb-4'></i><p class='text-gray-500 font-bold uppercase tracking-widest'>Vehicle not found.</p><a href='index.php' class='btn-primary mt-6 inline-flex'>Back to Fleet</a></div></div>";
    include "../footer.php";
    exit;
}
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="heading-brand">Vehicle Document Registry</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Manage compliance, tax, and insurance for fleet units.</p>
        </div>
        <a href="index.php" class="btn-secondary py-2 px-4 shadow-none">
            <i class="fas fa-arrow-left"></i> Back to Fleet
        </a>
    </div>

    <div class="card-base border-none">
        <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white">
            <h2 class="text-xl font-black uppercase italic tracking-tighter flex items-center gap-3">
                <i class="fas fa-file-contract text-indigo-400"></i> Registration: <?= htmlspecialchars($vehicle['license_plate']) ?>
            </h2>
            <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20"><?= htmlspecialchars($vehicle['make_model']) ?></span>
        </div>
        
        <div class="p-8">
            <form method="post" class="space-y-10">
                <input type="hidden" name="vehicle_id" value="<?= htmlspecialchars($vehicleId) ?>">
                
                <!-- Tax Section -->
                <div class="bg-indigo-50/50 dark:bg-indigo-950/20 p-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/30">
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-receipt"></i> Road Tax Compliance
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Tax Disk Ref</label>
                            <input type="text" name="tax_disk_ref" value="<?= htmlspecialchars($vehicle['tax_disk_ref'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" placeholder="REF-000000">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Issue Date</label>
                            <input type="date" name="tax_issue_date" value="<?= htmlspecialchars($vehicle['tax_issue_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Expiry Date</label>
                            <input type="date" name="tax_expiry_date" value="<?= htmlspecialchars($vehicle['tax_expiry_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                    </div>
                </div>

                <!-- Insurance Section -->
                <div class="bg-emerald-50/50 dark:bg-emerald-950/20 p-8 rounded-3xl border border-emerald-100 dark:border-emerald-900/30">
                    <h4 class="text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-shield-alt"></i> Insurance Coverage
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Policy / Disk Ref</label>
                            <input type="text" name="insurance_disk_ref" value="<?= htmlspecialchars($vehicle['insurance_disk_ref'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white" placeholder="POL-000000">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Effective Date</label>
                            <input type="date" name="insurance_issue_date" value="<?= htmlspecialchars($vehicle['insurance_issue_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Renewal Date</label>
                            <input type="date" name="insurance_expiry_date" value="<?= htmlspecialchars($vehicle['insurance_expiry_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                    </div>
                </div>

                <!-- DOE Section -->
                <div class="bg-amber-50/50 dark:bg-amber-950/20 p-8 rounded-3xl border border-amber-100 dark:border-amber-900/30">
                    <h4 class="text-xs font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 mb-6 flex items-center gap-2">
                        <i class="fas fa-check-double"></i> CVRT / DOE Verification
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Certificate Ref</label>
                            <input type="text" name="doe_disk_ref" value="<?= htmlspecialchars($vehicle['doe_disk_ref'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-amber-500 outline-none transition-all text-sm font-bold dark:text-white" placeholder="CVRT-000000">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Test Date</label>
                            <input type="date" name="doe_issue_date" value="<?= htmlspecialchars($vehicle['doe_issue_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-amber-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Next Test Due</label>
                            <input type="date" name="doe_expiry_date" value="<?= htmlspecialchars($vehicle['doe_expiry_date'] ?? '') ?>" class="w-full p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-amber-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="pt-6 flex flex-col md:flex-row gap-4">
                    <button type="submit" name="update_service_info" class="btn-primary flex-1 py-5 text-sm tracking-widest active:scale-[0.98]">
                        <i class="fas fa-save text-emerald-400"></i> Sync Document Registry
                    </button>
                    <a href="index.php" class="btn-secondary py-5 px-12 text-sm tracking-widest text-gray-400">
                        Discard Changes
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../footer.php"; ?>
