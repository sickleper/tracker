<?php
$pageTitle = "Admin Profile";
require_once '../config.php';
require_once '../tracker_data.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

if (!isTrackerSuperAdmin()) {
    header('Location: ../index.php');
    exit();
}

$flash = null;
$flashType = 'success';

$currentUserRes = makeApiCall('/api/user');
$currentUser = is_array($currentUserRes) ? $currentUserRes : [];
$currentUserId = (int) ($currentUser['id'] ?? ($_SESSION['user_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $mobile = trim((string) ($_POST['mobile'] ?? ''));
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $passwordChangeRequested = ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '');

    if ($currentUserId <= 0) {
        $flash = 'Unable to resolve the current admin account.';
        $flashType = 'error';
    } elseif ($name === '') {
        $flash = 'Name is required.';
        $flashType = 'error';
    } elseif ($passwordChangeRequested && $currentPassword === '') {
        $flash = 'Current password is required to change your password.';
        $flashType = 'error';
    } elseif ($passwordChangeRequested && strlen($newPassword) < 8) {
        $flash = 'New password must be at least 8 characters.';
        $flashType = 'error';
    } elseif ($passwordChangeRequested && $newPassword !== $confirmPassword) {
        $flash = 'New password and confirmation do not match.';
        $flashType = 'error';
    } else {
        $payload = [
            'name' => $name,
            'mobile' => $mobile,
        ];

        if ($passwordChangeRequested) {
            $loginUrl = rtrim($_ENV['LARAVEL_API_URL'] ?? '', '/') . '/api/login';
            $loginPayload = json_encode([
                'email' => $_SESSION['email'] ?? '',
                'password' => $currentPassword,
                'tenant_slug' => trackerTenantSlug(),
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $loginUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPayload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Tenant-Slug: ' . trackerTenantSlug(),
                'Content-Length: ' . strlen($loginPayload),
            ]);
            $loginResponse = curl_exec($ch);
            $loginHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $loginData = json_decode((string) $loginResponse, true);
            if ($loginHttpCode !== 200 || !isset($loginData['token'])) {
                $flash = $loginData['message'] ?? 'Current password is incorrect.';
                $flashType = 'error';
            } else {
                $payload['password'] = $newPassword;
            }
        }

        if ($flashType !== 'error') {
            $updateRes = makeApiCall("/api/users/{$currentUserId}", $payload, 'PATCH');

            if ($updateRes && ($updateRes['success'] ?? false)) {
                $_SESSION['user_name'] = $name;
                $flash = $passwordChangeRequested
                    ? 'Profile and password updated successfully.'
                    : ($updateRes['message'] ?? 'Profile updated successfully.');
                $flashType = 'success';
                $currentUserRes = makeApiCall('/api/user');
                $currentUser = is_array($currentUserRes) ? $currentUserRes : [];
            } else {
                $flash = $updateRes['message'] ?? 'Failed to update profile.';
                $flashType = 'error';
            }
        }
    }
}

$name = $currentUser['name'] ?? ($_SESSION['user_name'] ?? 'Admin');
$email = $currentUser['email'] ?? ($_SESSION['email'] ?? '');
$mobile = $currentUser['mobile'] ?? '';
$status = $currentUser['status'] ?? 'active';
$roleId = (int) ($currentUser['role_id'] ?? ($_SESSION['role_id'] ?? 0));
$isOffice = !empty($currentUser['is_office']);
$isMember = !empty($currentUser['is_member']);
$isDriver = !empty($currentUser['is_driver']);
$isSubcontractor = !empty($currentUser['is_subcontractor']);
$googleId = trim((string) ($currentUser['google_id'] ?? ($_SESSION['google_id'] ?? '')));
$userAuthId = $currentUser['user_auth_id'] ?? ($_SESSION['user_auth_id'] ?? null);

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Admin Profile</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Review your admin account and update your contact details.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back To Admin
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
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Current admin identity and access markers.</p>
            </div>

            <div class="p-8 space-y-6">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Admin Name</p>
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
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-700 border border-emerald-200">Super Admin</span>
                        <?php if ($isOffice): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-purple-50 text-purple-700 border border-purple-200">Office</span><?php endif; ?>
                        <?php if ($isMember): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-700 border border-indigo-200">Member</span><?php endif; ?>
                        <?php if ($isDriver): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-sky-50 text-sky-700 border border-sky-200">Driver</span><?php endif; ?>
                        <?php if ($isSubcontractor): ?><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-amber-50 text-amber-700 border border-amber-200">Subcontractor</span><?php endif; ?>
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
                    <p class="text-xs text-indigo-900 dark:text-indigo-100">Email stays read-only here because admin access is currently tied to the configured super-admin email.</p>
                </div>
            </div>
        </div>

        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-user-edit text-indigo-400 mr-2"></i> Update Profile</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Edit safe self-service fields for the admin account.</p>
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

                <div class="pt-2 border-t border-gray-100 dark:border-slate-800">
                    <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 mb-4">Change Password</h4>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Current Password</label>
                            <input type="password" name="current_password" minlength="8" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" autocomplete="current-password">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">New Password</label>
                            <input type="password" name="new_password" minlength="8" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" autocomplete="new-password">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="8" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-4 text-xs text-amber-900 dark:text-amber-100">
                    Email, permissions, and admin identity are not edited here. To change your password, enter your current password and a new one of at least 8 characters.
                </div>

                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-2xl font-black uppercase tracking-widest transition-all shadow-xl active:scale-95 flex items-center justify-center gap-3">
                    <i class="fas fa-save"></i> Save Profile
                </button>
            </form>
        </div>
    </div>
</div>

<?php include '../footer.php'; ?>
