<?php
$pageTitle = 'API Integration Guide';
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

if (!isTrackerSuperAdmin()) {
    header('Location: index.php');
    exit();
}

$apiBaseUrl = rtrim((string) ($laravelApiUrl ?? ($_ENV['LARAVEL_API_URL'] ?? '')), '/');
$appUrl = rtrim((string) (trackerAppUrl() ?: ''), '/');
$tenantSlug = trackerTenantSlug();
$scrambleDocsToken = trim((string) ($_ENV['SCRAMBLE_DOCS_TOKEN'] ?? ''));
$scrambleDocsQuery = $scrambleDocsToken !== '' ? '?docs_token=' . rawurlencode($scrambleDocsToken) : '';
$liveDocsUrl = $apiBaseUrl !== '' ? $apiBaseUrl . '/docs/api' . $scrambleDocsQuery : '';
$jsonSpecUrl = $apiBaseUrl !== '' ? $apiBaseUrl . '/docs/api.json' . $scrambleDocsQuery : '';
$bootstrapExample = [
    'app_name' => 'Acme Tracker',
    'app_url' => 'https://acme.example.com',
    'laravel_api_url' => 'https://api.example.com',
    'default_tenant' => 'acme',
    'is_primary_app' => false,
];
$loginExample = [
    'email' => 'office@acme.example.com',
    'password' => 'your-password',
    'tenant_slug' => 'acme',
];
$googleLoginExample = [
    'email' => 'office@acme.example.com',
    'name' => 'Acme Office',
    'google_id' => 'google-oauth-sub',
    'tenant_slug' => 'acme',
];
$authenticatedHeaders = [
    'Accept: application/json',
    'Authorization: Bearer {token}',
    'X-Tenant-Slug: acme',
];
$publicHeaders = [
    'Accept: application/json',
    'X-Tenant-Slug: acme',
];
$routeGroups = [
    [
        'title' => 'Auth',
        'scope' => 'Tenant-aware login. Use the tenant app slug on login and keep sending it on authenticated requests.',
        'routes' => [
            'POST /api/login',
            'POST /api/google-auth-login',
            'POST /api/logout',
            'GET /api/user',
        ],
    ],
    [
        'title' => 'Leads And Reminders',
        'scope' => 'Lead CRUD, follow-ups, category lookup, conversion to client/project, reminder feed.',
        'routes' => [
            'GET /api/leads',
            'POST /api/leads',
            'GET /api/leads/{id}',
            'PATCH /api/leads/{id}',
            'DELETE /api/leads/{id}',
            'POST /api/leads/{id}/followup',
            'DELETE /api/leads/{id}/followup',
            'POST /api/leads/{id}/convert',
            'POST /api/leads/{id}/convert-to-project',
            'GET /api/reminders',
        ],
    ],
    [
        'title' => 'Proposals And Projects',
        'scope' => 'Proposal CRUD, templates, taxes, project CRUD, milestones, members.',
        'routes' => [
            'GET /api/proposals',
            'POST /api/proposals',
            'GET /api/proposals/{id}',
            'GET /api/proposals/templates',
            'POST /api/proposals/templates',
            'GET /api/projects',
            'POST /api/projects',
            'GET /api/projects/{id}',
            'PATCH /api/projects/{id}',
            'GET /api/projects/milestones',
            'POST /api/projects/members',
        ],
    ],
    [
        'title' => 'Operations Modules',
        'scope' => 'Tickets, holidays, timesheets/attendance, fuel, tool inventory, Twilio, settings.',
        'routes' => [
            'GET /api/tickets',
            'GET /api/leaves',
            'GET /api/holidays',
            'POST /api/attendances/clock-in',
            'GET /api/fuel/logs',
            'GET /api/fuel/vehicles',
            'GET /api/tools',
            'GET /api/tools/lookups',
            'GET /api/settings',
            'POST /api/settings',
            'GET /api/twilio/numbers',
            'POST /api/twilio/assignments',
        ],
    ],
    [
        'title' => 'Public Tenant-Aware Routes',
        'scope' => 'Public endpoints resolve tenant by explicit slug/id, portal hash, project category, or request host.',
        'routes' => [
            'GET /api/public/leads/categories',
            'POST /api/public/leads',
            'GET /api/public/booking/slots',
            'GET /api/public/clients/by-hash/{hash}',
            'GET /api/public/proposals/by-hash/{hash}',
            'POST /api/public/proposals/by-hash/{hash}/accept',
            'GET /api/public/task/{hash}',
        ],
    ],
    [
        'title' => 'Primary-App-Only Operations',
        'scope' => 'Use only from the main admin app. Do not expose these flows on tenant apps.',
        'routes' => [
            'GET /api/tenants',
            'POST /api/tenants',
            'POST /api/tenants/{tenantId}/assign-user',
            'POST /api/tenants/{tenantId}/move-category',
            'POST /api/tenants/{tenantId}/sync-derived',
            'GET /api/tenants/{tenantId}/report',
            'GET /api/tenants/{tenantId}/feature-settings',
            'POST /api/tenants/{tenantId}/feature-settings',
            'POST /api/admin/impersonate',
            'POST /api/admin/stop-impersonation',
            'POST /api/maintenance/clear-cache',
            'POST /api/maintenance/clear-route-cache',
            'POST /api/maintenance/clear-config-cache',
            'POST /api/maintenance/optimize-clear',
            'POST /api/maintenance/clear-sessions',
            'POST /api/maintenance/clear-logs',
        ],
    ],
];

include '../header.php';
include '../nav.php';
?>

<div class="admin-shell space-y-8">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div>
            <h1 class="heading-brand">API Integration Guide</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">
                Reference for building a tenant-locked tracker app against the shared Laravel API.
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="index.php" class="admin-action admin-action-outline-sky">
                <i class="fas fa-arrow-left"></i> Back To Admin
            </a>
            <?php if ($liveDocsUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($liveDocsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="admin-action admin-action-outline-success">
                <i class="fas fa-up-right-from-square"></i> Open OpenAPI UI
            </a>
            <?php endif; ?>
            <?php if ($jsonSpecUrl !== ''): ?>
            <a href="<?php echo htmlspecialchars($jsonSpecUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="admin-action admin-action-outline-info">
                <i class="fas fa-code"></i> Open JSON Spec
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="admin-panel admin-panel-body-lg xl:col-span-2 space-y-6">
            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Integration Model</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <div class="admin-chip">
                        Each tracker app is single-tenant and must be bootstrapped with one fixed tenant slug.
                    </div>
                    <div class="admin-chip">
                        The Laravel API and database are shared, so tenant isolation must be enforced server-side on every request.
                    </div>
                    <div class="admin-chip">
                        Tenant apps should send the tenant slug on login and on authenticated API calls.
                    </div>
                    <div class="admin-chip">
                        Tenant manager, impersonation, and shared maintenance flows belong on the primary app only.
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Bootstrap Config</h3>
                <p class="mt-3 text-sm font-semibold text-gray-600 dark:text-slate-400">
                    Each tracker install should keep its local bootstrap file at
                    <span class="font-black text-gray-800 dark:text-slate-200"><?php echo htmlspecialchars(TRACKER_BOOTSTRAP_CONFIG_PATH, ENT_QUOTES, 'UTF-8'); ?></span>.
                </p>
                <pre class="mt-4 rounded-3xl bg-slate-950 text-slate-100 p-5 overflow-x-auto text-xs font-mono"><?php echo htmlspecialchars(json_encode($bootstrapExample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Authentication Rules</h3>
                <div class="mt-4 space-y-4 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <p>Use bearer-token authentication. Log in through the tenant app and send the same tenant slug on subsequent authenticated requests.</p>
                    <p>Normal users may only log into the tenant app that matches their assigned tenant. The superadmin email may log in across apps, but the API still resolves an effective tenant context for the session.</p>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">POST /api/login</p>
                        <pre class="rounded-3xl bg-slate-950 text-slate-100 p-5 overflow-x-auto text-xs font-mono"><?php echo htmlspecialchars(json_encode($loginExample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">POST /api/google-auth-login</p>
                        <pre class="rounded-3xl bg-slate-950 text-slate-100 p-5 overflow-x-auto text-xs font-mono"><?php echo htmlspecialchars(json_encode($googleLoginExample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Required Headers</h3>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Authenticated Requests</span>
                        <pre class="bg-transparent p-0 m-0 text-xs font-mono text-gray-700 dark:text-slate-200"><?php echo htmlspecialchars(implode("\n", $authenticatedHeaders), ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Public Tenant-Aware Requests</span>
                        <pre class="bg-transparent p-0 m-0 text-xs font-mono text-gray-700 dark:text-slate-200"><?php echo htmlspecialchars(implode("\n", $publicHeaders), ENT_QUOTES, 'UTF-8'); ?></pre>
                    </div>
                </div>
                <p class="mt-4 text-sm font-semibold text-gray-600 dark:text-slate-400">
                    Public routes can also resolve tenant context from portal hashes, project category IDs, or request host, but explicit tenant headers are the safest integration pattern.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Frontend Request Pattern</h3>
                <pre class="mt-4 rounded-3xl bg-slate-950 text-slate-100 p-5 overflow-x-auto text-xs font-mono"><?php echo htmlspecialchars(<<<'JS'
const apiBase = 'https://api.example.com';
const token = auth.token;
const tenantSlug = 'acme';

async function apiJson(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
    'X-Tenant-Slug': tenantSlug,
    ...(options.headers || {}),
  };

  const res = await fetch(`${apiBase}${path}`, {
    ...options,
    headers,
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.message || `HTTP ${res.status}`);
  }

  return data;
}
JS, ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Route Groups</h3>
                <div class="mt-4 space-y-4">
                    <?php foreach ($routeGroups as $group): ?>
                        <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5">
                            <div class="flex flex-col gap-2">
                                <h4 class="text-xs font-black uppercase tracking-widest text-slate-900 dark:text-white"><?php echo htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p class="text-sm font-semibold text-gray-600 dark:text-slate-400"><?php echo htmlspecialchars($group['scope'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-3">
                                <?php foreach ($group['routes'] as $route): ?>
                                    <div class="admin-chip text-xs font-mono text-gray-700 dark:text-slate-200"><?php echo htmlspecialchars($route, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Build Checklist</h3>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <div class="admin-chip">Lock the app to one tenant slug in local bootstrap config.</div>
                    <div class="admin-chip">Send <span class="font-mono">X-Tenant-Slug</span> on every authenticated request.</div>
                    <div class="admin-chip">Store bearer token securely and clear it on logout or impersonation restore.</div>
                    <div class="admin-chip">Treat maintenance, tenant manager, and impersonation flows as primary-app-only.</div>
                    <div class="admin-chip">Do not assume browser-open reminders; server-side jobs handle WhatsApp follow-up sends.</div>
                    <div class="admin-chip">Audit any new API endpoint for tenant scoping before deploying it to all tenant apps.</div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="admin-panel admin-panel-body-lg">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Current Runtime</h3>
                <div class="mt-4 space-y-3 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Tracker App URL</span>
                        <span class="break-all"><?php echo htmlspecialchars($appUrl !== '' ? $appUrl : 'Not resolved', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Laravel API URL</span>
                        <span class="break-all"><?php echo htmlspecialchars($apiBaseUrl !== '' ? $apiBaseUrl : 'Not resolved', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">OpenAPI UI</span>
                        <span class="break-all"><?php echo htmlspecialchars($liveDocsUrl !== '' ? $liveDocsUrl : 'Not resolved', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Default Tenant Slug</span>
                        <span class="break-all"><?php echo htmlspecialchars($tenantSlug !== '' ? $tenantSlug : 'Not resolved', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="admin-chip">
                        <span class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">App Mode</span>
                        <span><?php echo trackerIsPrimaryApp() ? 'Primary App' : 'Tenant App'; ?></span>
                    </div>
                </div>
            </div>

            <div class="admin-panel admin-panel-body-lg">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Source Files</h3>
                <div class="mt-4 space-y-3 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <div class="admin-chip">Tracker bootstrap: <span class="font-mono"><?php echo htmlspecialchars(TRACKER_BOOTSTRAP_CONFIG_PATH, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="admin-chip">Tenant bootstrap script: <span class="font-mono"><?php echo htmlspecialchars(TRACKER_REPO_DIR . '/setup_tenant_app.sh', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="admin-chip">Tenant clone provisioner: <span class="font-mono"><?php echo htmlspecialchars(TRACKER_REPO_DIR . '/provision_tenant_clone.sh', ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="admin-chip">API routes: <span class="font-mono">/home/api/laravel_api/routes/api.php</span></div>
                    <div class="admin-chip">Tenant auth: <span class="font-mono">/home/api/laravel_api/app/Http/Controllers/AuthController.php</span></div>
                    <div class="admin-chip">Authenticated tenant middleware: <span class="font-mono">/home/api/laravel_api/app/Http/Middleware/ResolveTenant.php</span></div>
                    <div class="admin-chip">Public tenant middleware: <span class="font-mono">/home/api/laravel_api/app/Http/Middleware/ResolvePublicTenant.php</span></div>
                    <div class="admin-chip">Scramble config: <span class="font-mono">/home/api/laravel_api/config/scramble.php</span></div>
                </div>
            </div>

            <div class="admin-panel admin-panel-body-lg">
                <h3 class="text-sm font-black uppercase tracking-widest text-indigo-500">Notes</h3>
                <div class="mt-4 space-y-3 text-sm font-semibold text-gray-700 dark:text-slate-300">
                    <p>This page is a hand-maintained integration guide, not a generated OpenAPI spec.</p>
                    <p>Live OpenAPI docs now come from Scramble at <span class="font-mono">/docs/api</span> and <span class="font-mono">/docs/api.json</span> on the Laravel API app.</p>
                    <p>If you set <span class="font-mono">SCRAMBLE_DOCS_TOKEN</span> in the API environment, the guide links above will append it as <span class="font-mono">docs_token</span>.</p>
                </div>
            </div>
        </div>
    </div>
</div>
