<?php
$pageTitle = "My Profile";
require_once 'config.php';
require_once 'tracker_data.php';

if (!isTrackerAuthenticated()) {
    header('Location: oauth2callback.php');
    exit();
}

$flash = null;
$flashType = 'success';
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);
$superAdminEmail = trackerSuperAdminEmail();
$isProfileAdmin = (($_SESSION['email'] ?? '') === $superAdminEmail);
$requestedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$targetUserId = ($isProfileAdmin && $requestedUserId > 0) ? $requestedUserId : $sessionUserId;

if (!$isProfileAdmin && $requestedUserId > 0 && $requestedUserId !== $sessionUserId) {
    header('Location: profile.php');
    exit();
}

$profileRes = $targetUserId > 0 ? makeApiCall("/api/users/{$targetUserId}") : false;
$profileUser = ($profileRes && ($profileRes['success'] ?? false)) ? ($profileRes['user'] ?? []) : [];
$isClientUser = !empty($profileUser['client_details']) || !empty($profileUser['clientDetails']);

if ($isClientUser) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));

    if ($targetUserId <= 0) {
        $flash = 'Unable to resolve your account.';
        $flashType = 'error';
    } elseif ($name === '') {
        $flash = 'Name is required.';
        $flashType = 'error';
    } else {
        $updateRes = makeApiCall("/api/users/{$targetUserId}", [
            'name' => $name,
            'mobile' => $mobile,
        ], 'PATCH');

        if ($updateRes && ($updateRes['success'] ?? false)) {
            if ($targetUserId === $sessionUserId) {
                $_SESSION['user_name'] = $name;
            }
            $flash = $updateRes['message'] ?? 'Profile updated successfully.';
            $flashType = 'success';
            $profileRes = makeApiCall("/api/users/{$targetUserId}");
            $profileUser = ($profileRes && ($profileRes['success'] ?? false)) ? ($profileRes['user'] ?? []) : $profileUser;
        } else {
            $flash = $updateRes['message'] ?? 'Failed to update profile.';
            $flashType = 'error';
        }
    }
}

$name = $profileUser['name'] ?? ($_SESSION['user_name'] ?? 'User');
$email = $profileUser['email'] ?? ($_SESSION['email'] ?? '');
$mobile = $profileUser['mobile'] ?? '';
$status = $profileUser['status'] ?? 'active';
$roleId = (int) ($profileUser['role_id'] ?? ($_SESSION['role_id'] ?? 0));
$isOffice = !empty($profileUser['is_office']);
$isMember = !empty($profileUser['is_member']);
$isDriver = !empty($profileUser['is_driver']);
$isSubcontractor = !empty($profileUser['is_subcontractor']);
$isCalloutDriver = !empty($profileUser['is_callout_driver']);
$googleId = trim((string) ($profileUser['google_id'] ?? ($_SESSION['google_id'] ?? '')));
$userAuthId = $profileUser['user_auth_id'] ?? ($_SESSION['user_auth_id'] ?? null);
$isOwnProfile = $targetUserId === $sessionUserId;
$pageHeading = $isOwnProfile ? 'My Profile' : 'Staff Profile';
$pageIntro = $isOwnProfile
    ? 'Review your tracker account and update your own contact details.'
    : 'Review a staff account and update basic contact details.';
$backHref = $isOwnProfile ? 'index.php' : 'admin/index.php';
$backLabel = $isOwnProfile ? 'Back To Dashboard' : 'Back To Admin';

include 'header.php';
include 'nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand"><?php echo htmlspecialchars($pageHeading); ?></h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1"><?php echo htmlspecialchars($pageIntro); ?></p>
        </div>
        <div class="flex gap-3">
            <a href="<?php echo htmlspecialchars($backHref); ?>" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="mb-8 rounded-3xl border <?php echo $flashType === 'success' ? 'border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-100' : 'border-red-200 dark:border-red-900/40 bg-red-50 dark:bg-red-950/20 text-red-900 dark:text-red-100'; ?> p-5">
            <div class="text-[10px] font-black uppercase tracking-[0.25em] <?php echo $flashType === 'success' ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'; ?>">
                <?php echo $flashType === 'success' ? 'Profile Updated' : 'Update Failed'; ?>
            </div>
            <p class="mt-2 text-sm font-medium"><?php echo htmlspecialchars($flash); ?></p>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-[0.95fr_1.05fr] gap-6">
        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-id-badge text-indigo-400 mr-2"></i> Account Snapshot</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Current identity and tracker role markers.</p>
            </div>

            <div class="p-8 space-y-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Display Name</p>
                    <div class="text-2xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars($name); ?></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Email</div>
                        <div class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200 break-all"><?php echo htmlspecialchars($email ?: 'Not available'); ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Mobile</div>
                        <div class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($mobile ?: 'Not set'); ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Status</div>
                        <div class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200 uppercase"><?php echo htmlspecialchars($status); ?></div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 p-4">
                        <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">Role ID</div>
                        <div class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars((string) $roleId); ?></div>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Role Flags</p>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($isOffice): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-purple-50 text-purple-700 border border-purple-200">Office</span><?php endif; ?>
                        <?php if ($isMember): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-700 border border-indigo-200">Member</span><?php endif; ?>
                        <?php if ($isDriver): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-sky-50 text-sky-700 border border-sky-200">Driver</span><?php endif; ?>
                        <?php if ($isCalloutDriver): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-cyan-50 text-cyan-700 border border-cyan-200">Callout Driver</span><?php endif; ?>
                        <?php if ($isSubcontractor): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200">Subcontractor</span><?php endif; ?>
                        <?php if (!$isOffice && !$isMember && !$isDriver && !$isCalloutDriver && !$isSubcontractor): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200">No Flags</span><?php endif; ?>
                    </div>
                </div>

                <div class="rounded-2xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/20 p-5 space-y-3">
                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-300">Authentication</div>
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Google linked</span>
                        <span class="font-black text-slate-900 dark:text-white"><?php echo $googleId !== '' ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="flex justify-between gap-4 text-sm">
                        <span class="text-slate-500 dark:text-slate-400">User Auth ID</span>
                        <span class="font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($userAuthId ?? 'Not set')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-user-edit text-indigo-400 mr-2"></i> Update Profile</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400"><?php echo $isOwnProfile ? 'Edit your own tracker profile fields.' : 'Edit this staff member\'s basic profile fields.'; ?></p>
            </div>

            <form method="post" class="p-8 space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Display Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($email); ?>" readonly class="w-full p-4 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm font-bold text-slate-500 dark:text-slate-400 cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Mobile</label>
                    <input type="text" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                </div>

                <div class="rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-4 text-xs text-amber-900 dark:text-amber-100">
                    <?php echo $isOwnProfile ? 'This page only updates your own basic profile details. Permissions and account type are managed elsewhere.' : 'This page only updates basic profile details for this staff member. Permissions and account type are managed elsewhere.'; ?>
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-save"></i> Save Profile
                </button>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
