<?php
$pageTitle = 'Tenant Feature Diagnostic';
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

if (!trackerIsPrimaryApp()) {
    header('Location: ../index.php');
    exit();
}

if (!isTrackerSuperAdmin() && empty($_SESSION['impersonation_active'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../api_helper.php';
require_once '../nav.php';

$moduleKeys = [
    'module_fuel_enabled',
    'module_tool_inventory_enabled',
    'module_holidays_enabled',
    'module_timesheets_enabled',
    'module_tickets_enabled',
];

$resolvedTenantSlug = trackerTenantSlug();
$apiToken = getTrackerApiToken();
$rawSettings = makeApiCall('/api/settings');
$rawUser = makeApiCall('/api/user');
$rawProposals = makeApiCall('/api/proposals');
$settingsByKey = [];
$moduleApiValues = [];
$runtimeUser = [];
$proposalSummary = [
    'count' => 0,
    'tenant_ids' => [],
    'sample' => [],
];

if (is_array($rawSettings) && ($rawSettings['success'] ?? false)) {
    foreach (($rawSettings['data'] ?? []) as $group => $items) {
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            $key = $item['key'] ?? null;
            if (!is_string($key) || $key === '') {
                continue;
            }
            $settingsByKey[$key] = $item['value'] ?? null;
        }
    }
}

foreach ($moduleKeys as $key) {
    $moduleApiValues[$key] = $settingsByKey[$key] ?? null;
}

if (is_array($rawUser)) {
    $runtimeUser = is_array($rawUser['data'] ?? null) ? $rawUser['data'] : $rawUser;
}

if (is_array($rawProposals) && ($rawProposals['success'] ?? false) && is_array($rawProposals['data'] ?? null)) {
    $proposalSummary['count'] = count($rawProposals['data']);
    foreach ($rawProposals['data'] as $proposal) {
        $tenantId = $proposal['tenant_id'] ?? null;
        if ($tenantId !== null && $tenantId !== '') {
            $proposalSummary['tenant_ids'][(string) $tenantId] = true;
        }
        if (count($proposalSummary['sample']) < 10) {
            $proposalSummary['sample'][] = [
                'id' => $proposal['id'] ?? null,
                'tenant_id' => $tenantId,
                'status' => $proposal['status'] ?? null,
                'lead_name' => $proposal['lead']['client_name'] ?? null,
                'lead_email' => $proposal['lead']['client_email'] ?? null,
                'created_at' => $proposal['created_at'] ?? null,
            ];
        }
    }
    $proposalSummary['tenant_ids'] = array_keys($proposalSummary['tenant_ids']);
}

include '../header.php';
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="heading-brand">Tenant Feature Diagnostic</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Inspect the tenant resolution and feature values PHP sees at runtime.</p>
        </div>
        <div class="flex gap-3">
            <?php if (trackerCanUseTenantAdminTools()): ?>
            <a href="tenants.php" class="bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-building"></i> Tenant Manager
            </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="card-base border-none">
        <div class="section-header">
            <h3><i class="fas fa-fingerprint text-indigo-400 mr-2"></i> Tenant Resolution</h3>
        </div>
        <div class="p-8 overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php
                    $resolutionRows = [
                        'Resolved trackerTenantSlug()' => $resolvedTenantSlug,
                        'Session tenant_id' => $_SESSION['tenant_id'] ?? null,
                        'Session tenant_slug' => $_SESSION['tenant_slug'] ?? null,
                        'Server TENANT_SLUG' => $_SERVER['TENANT_SLUG'] ?? null,
                        'Header X-Tenant-Slug' => $_SERVER['HTTP_X_TENANT_SLUG'] ?? null,
                        'Env TENANT_SLUG' => $_ENV['TENANT_SLUG'] ?? null,
                        'GET tenant_slug' => $_GET['tenant_slug'] ?? null,
                        'POST tenant_slug' => $_POST['tenant_slug'] ?? null,
                        'API token present' => $apiToken ? 'yes' : 'no',
                    ];
                    foreach ($resolutionRows as $label => $value):
                    ?>
                    <tr>
                        <td class="px-4 py-3 font-black uppercase tracking-widest text-[10px] text-gray-400"><?php echo htmlspecialchars($label); ?></td>
                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($value ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-base border-none">
        <div class="section-header">
            <h3><i class="fas fa-user-shield text-sky-400 mr-2"></i> Auth Runtime</h3>
        </div>
        <div class="p-8 overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php
                    $authRows = [
                        'Session email' => $_SESSION['email'] ?? null,
                        'Session user_id' => $_SESSION['user_id'] ?? null,
                        'Session is_office' => !empty($_SESSION['is_office']) ? 'true' : 'false',
                        'Session impersonation_active' => !empty($_SESSION['impersonation_active']) ? 'true' : 'false',
                        'API /user email' => $runtimeUser['email'] ?? null,
                        'API /user id' => $runtimeUser['id'] ?? $runtimeUser['user_id'] ?? null,
                        'API /user tenant_id' => $runtimeUser['tenant_id'] ?? null,
                        'API /user is_office' => !empty($runtimeUser['is_office']) ? 'true' : 'false',
                    ];
                    foreach ($authRows as $label => $value):
                    ?>
                    <tr>
                        <td class="px-4 py-3 font-black uppercase tracking-widest text-[10px] text-gray-400"><?php echo htmlspecialchars($label); ?></td>
                        <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($value ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-base border-none">
        <div class="section-header">
            <h3><i class="fas fa-file-invoice text-rose-400 mr-2"></i> Proposal Runtime Check</h3>
        </div>
        <div class="p-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900/30 p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">API `/api/proposals` Count</div>
                    <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) $proposalSummary['count']); ?></div>
                </div>
                <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900/30 p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">Tenant IDs Seen</div>
                    <div class="mt-2 text-sm font-mono text-gray-900 dark:text-white"><?php echo htmlspecialchars(implode(', ', $proposalSummary['tenant_ids'])); ?></div>
                </div>
                <div class="rounded-2xl border border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900/30 p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">Resolved Tenant Slug</div>
                    <div class="mt-2 text-sm font-mono text-gray-900 dark:text-white"><?php echo htmlspecialchars($resolvedTenantSlug); ?></div>
                </div>
            </div>
            <pre class="w-full overflow-auto rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs leading-relaxed"><?php echo htmlspecialchars(json_encode($proposalSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>
    </section>

    <section class="card-base border-none">
        <div class="section-header">
            <h3><i class="fas fa-toggle-on text-emerald-400 mr-2"></i> Runtime Feature Checks</h3>
        </div>
        <div class="p-8 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-4 py-3 text-left">Key</th>
                        <th class="px-4 py-3 text-left">`gs()` Value</th>
                        <th class="px-4 py-3 text-left">`featureEnabled()`</th>
                        <th class="px-4 py-3 text-left">Raw `/api/settings` Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                    <?php foreach ($moduleKeys as $key): ?>
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-gray-900 dark:text-white"><?php echo htmlspecialchars($key); ?></td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-slate-300"><?php echo htmlspecialchars((string) gs($key, '')); ?></td>
                        <td class="px-4 py-3 font-black text-xs <?php echo featureEnabled($key) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'; ?>">
                            <?php echo featureEnabled($key) ? 'true' : 'false'; ?>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-slate-300"><?php echo htmlspecialchars((string) ($moduleApiValues[$key] ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card-base border-none">
        <div class="section-header">
            <h3><i class="fas fa-code text-amber-400 mr-2"></i> Raw Settings API Status</h3>
        </div>
        <div class="p-8 space-y-4">
            <div class="text-xs font-black uppercase tracking-widest text-gray-400">
                Success: <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) (($rawSettings['success'] ?? false) ? 'true' : 'false')); ?></span>
            </div>
            <div class="text-xs font-black uppercase tracking-widest text-gray-400">
                Message: <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars((string) ($rawSettings['message'] ?? '')); ?></span>
            </div>
            <pre class="w-full overflow-auto rounded-2xl bg-slate-950 text-slate-100 p-4 text-xs leading-relaxed"><?php echo htmlspecialchars(json_encode($rawSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        </div>
    </section>
</div>

<?php include '../footer.php'; ?>
