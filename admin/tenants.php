<?php
$pageTitle = 'Tenant Manager';
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

if (!isTrackerSuperAdmin()) {
    header('Location: ../index.php');
    exit();
}

if (!trackerIsPrimaryApp()) {
    header('Location: index.php');
    exit();
}

include '../header.php';
include '../nav.php';

$tenantImpersonationActive = !empty($_SESSION['impersonation_active']);
$tenantImpersonationName = trim((string) ($_SESSION['impersonated_tenant_name'] ?? ''));
$tenantImpersonationSlug = trim((string) ($_SESSION['impersonated_tenant_slug'] ?? ($_SESSION['tenant_slug'] ?? '')));
$impersonatedUserName = trim((string) ($_SESSION['impersonated_user_name'] ?? ''));
$impersonatedUserEmail = trim((string) ($_SESSION['impersonated_user_email'] ?? ''));
?>

<div class="admin-shell space-y-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="heading-brand">Tenant Manager</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Provision tenants, assign users, and review tenant distribution.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="tenant_feature_diagnostic.php" class="admin-action admin-action-outline-warning">
                <i class="fas fa-stethoscope"></i> Feature Diagnostic
            </a>
            <a href="index.php" class="admin-action admin-action-outline-info">
                <i class="fas fa-cog"></i> Admin Home
            </a>
        </div>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-body flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="text-[10px] font-black uppercase tracking-widest text-indigo-500">Tenant Login Context</div>
                <?php if ($tenantImpersonationActive): ?>
                    <div class="mt-2 text-lg font-black text-gray-900 dark:text-white">
                        Impersonating <?php echo htmlspecialchars($impersonatedUserName !== '' ? $impersonatedUserName : $impersonatedUserEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="mt-1 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                        <?php echo htmlspecialchars($tenantImpersonationName !== '' ? $tenantImpersonationName : $tenantImpersonationSlug, ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($tenantImpersonationSlug !== ''): ?> • <?php echo htmlspecialchars($tenantImpersonationSlug, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                        <?php if ($impersonatedUserEmail !== ''): ?> • <?php echo htmlspecialchars($impersonatedUserEmail, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-2 text-sm font-bold text-gray-700 dark:text-slate-200">
                        No impersonation is active. Use a tenant row below to pick a user and log in as them.
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="../index.php" class="admin-action admin-action-primary">
                    <i class="fas fa-external-link-alt"></i> Open Tracker
                </a>
                <?php if ($tenantImpersonationActive): ?>
                    <button type="button" id="restoreTenantContextBtn" class="admin-action admin-action-outline-warning">
                        <i class="fas fa-undo"></i> Stop Impersonating
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-8">
        <section class="admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-layer-group text-indigo-400 mr-2"></i> Tenants</h3>
            </div>
            <div class="table-container">
                <table class="w-full text-sm">
                    <thead class="table-header-row">
                        <tr>
                            <th class="px-6 py-4 text-left">Tenant</th>
                            <th class="px-6 py-4 text-center">Users</th>
                            <th class="px-6 py-4 text-center">Categories</th>
                            <th class="px-6 py-4 text-center">Projects</th>
                            <th class="px-6 py-4 text-center">Leads</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tenantsBody" class="admin-table-body"></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 gap-8">
        <section class="admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-clone text-sky-400 mr-2"></i> One-Click Provisioning</h3>
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Creates tenant in API, provisions instance, and sets up admin user.</p>
            </div>
            <form id="provisionForm" class="admin-panel-body-lg space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="admin-label">Target Directory</label>
                        <input type="text" id="provTargetDir" class="admin-input" placeholder="e.g. trackers-acme" required>
                    </div>
                    <div>
                        <label class="admin-label">App Name</label>
                        <input type="text" id="provAppName" class="admin-input" placeholder="e.g. Acme Tracker" required>
                    </div>
                </div>
                <div>
                    <label class="admin-label">App URL</label>
                    <input type="url" id="provAppUrl" class="admin-input" placeholder="https://acme.webdesign-dublin.com" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b border-gray-100 dark:border-slate-800 pb-4 mb-4">
                    <div>
                        <label class="admin-label">Tenant Slug</label>
                        <input type="text" id="provTenantSlug" class="admin-input" placeholder="acme" required>
                    </div>
                    <div>
                        <label class="admin-label">Branch</label>
                        <input type="text" id="provBranch" class="admin-input" value="main" required>
                    </div>
                </div>

                <!-- Admin User Creation -->
                <div class="bg-indigo-50/30 dark:bg-indigo-900/10 p-4 rounded-2xl border border-indigo-100/50 dark:border-indigo-900/30 space-y-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-indigo-500">Initial Office Admin User</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="admin-label">Admin Email</label>
                            <input type="email" id="provAdminEmail" class="admin-input" placeholder="manager@client.com">
                        </div>
                        <div>
                            <label class="admin-label">Admin Name</label>
                            <input type="text" id="provAdminName" class="admin-input" placeholder="Office Manager">
                        </div>
                    </div>
                    <div>
                        <label class="admin-label">Admin Password</label>
                        <input type="password" id="provAdminPassword" class="admin-input" placeholder="Initial password for login">
                    </div>
                </div>

                <button type="submit" class="admin-action admin-action-info admin-action-lg w-full">Start One-Click Provisioning</button>
            </form>
        </section>
    </div>

    <section class="admin-panel admin-panel-body-lg space-y-6">
        <div class="section-header flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-table text-amber-400"></i>
                <h3>Tenant Report & Features</h3>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="admin-label">Tenant</label>
                <select id="reportTenantId" class="admin-select"></select>
            </div>
            <div>
                <label class="admin-label">Feature Toggles</label>
                <div class="grid grid-cols-1 gap-3" id="featureToggles">
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="toggleFuel" class="w-4 h-4 border-gray-300 rounded">
                        <span class="font-bold uppercase tracking-widest text-xs">Fuel & Vehicles</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="toggleToolInventory" class="w-4 h-4 border-gray-300 rounded">
                        <span class="font-bold uppercase tracking-widest text-xs">Tool Inventory</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="toggleHolidays" class="w-4 h-4 border-gray-300 rounded">
                        <span class="font-bold uppercase tracking-widest text-xs">Holidays</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="toggleTimesheets" class="w-4 h-4 border-gray-300 rounded">
                        <span class="font-bold uppercase tracking-widest text-xs">Timesheets</span>
                    </label>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" id="toggleTickets" class="w-4 h-4 border-gray-300 rounded">
                        <span class="font-bold uppercase tracking-widest text-xs">Tickets</span>
                    </label>
                </div>
                <button id="saveFeatureToggles" type="button" class="admin-action admin-action-info mt-4 w-full">Save Feature Toggles</button>
            </div>
        </div>
        <div id="tenantReport" class="overflow-x-auto"></div>
    </section>
</div>

<script>
const tenantHeaders = () => ({
    'Accept': 'application/json',
    'Authorization': `Bearer ${window.apiToken}`
});

const tenantJsonHeaders = () => ({
    ...tenantHeaders(),
    'Content-Type': 'application/json'
});

const featureToggleMapping = [
    { key: 'module_fuel_enabled', elementId: 'toggleFuel' },
    { key: 'module_tool_inventory_enabled', elementId: 'toggleToolInventory' },
    { key: 'module_holidays_enabled', elementId: 'toggleHolidays' },
    { key: 'module_timesheets_enabled', elementId: 'toggleTimesheets' },
    { key: 'module_tickets_enabled', elementId: 'toggleTickets' },
];

let tenantRows = [];
let tenantLookups = { users: [] };
const reportTenantStorageKey = 'tracker_admin_report_tenant_id';
const tenantReportIdRegex = /^\d+$/;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function swalConfig() {
    return typeof getSwalConfig === 'function' ? getSwalConfig() : {};
}

function normalizeTenantId(value) {
    const normalized = String(value || '').trim();
    if (!tenantReportIdRegex.test(normalized)) {
        return '';
    }
    return normalized;
}

function getPersistedReportTenantId() {
    const params = new URLSearchParams(window.location.search);
    const queryTenantId = normalizeTenantId(params.get('tenant_report_id'));
    if (queryTenantId) {
        return queryTenantId;
    }
    return normalizeTenantId(sessionStorage.getItem(reportTenantStorageKey));
}

function persistReportTenantId(tenantId) {
    const normalized = normalizeTenantId(tenantId);
    if (normalized) {
        sessionStorage.setItem(reportTenantStorageKey, normalized);
    } else {
        sessionStorage.removeItem(reportTenantStorageKey);
    }

    const url = new URL(window.location.href);
    if (normalized) {
        url.searchParams.set('tenant_report_id', normalized);
    } else {
        url.searchParams.delete('tenant_report_id');
    }
    window.history.replaceState({}, '', url.toString());
}

function isKnownTenantId(tenantId) {
    if (!tenantId) {
        return false;
    }
    return tenantRows.some((tenant) => String(tenant.id) === String(normalizeTenantId(tenantId)));
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('provisionForm').addEventListener('submit', provisionInstance);
    document.getElementById('saveFeatureToggles')?.addEventListener('click', saveFeatureToggles);
    document.getElementById('restoreTenantContextBtn')?.addEventListener('click', restoreTenantContext);
    document.getElementById('tenantsBody')?.addEventListener('click', handleTenantRowAction);
    document.getElementById('reportTenantId')?.addEventListener('change', (event) => {
        loadTenantReport(event.target.value);
    });
    await Promise.all([loadTenants(), loadLookups()]);
    const persistedTenantId = getPersistedReportTenantId();
    if (persistedTenantId && isKnownTenantId(persistedTenantId)) {
        const reportSelect = document.getElementById('reportTenantId');
        if (reportSelect) {
            reportSelect.value = persistedTenantId;
        }
        await loadTenantReport(persistedTenantId);
    } else {
        persistReportTenantId('');
        const reportSelect = document.getElementById('reportTenantId');
        if (reportSelect?.value && isKnownTenantId(reportSelect.value)) {
            await loadTenantReport(reportSelect.value);
        }
    }
});

async function apiJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
}

function handleTenantRowAction(event) {
    const button = event.target.closest('[data-tenant-action]');
    if (!button) {
        return;
    }

    const tenantId = normalizeTenantId(button.dataset.tenantId);
    if (!tenantId || !isKnownTenantId(tenantId)) {
        Swal.fire({ ...swalConfig(), icon: 'warning', title: 'Invalid tenant', text: 'Select a valid tenant row before continuing.' });
        return;
    }

    const tenant = tenantRows.find((row) => String(row.id) === tenantId);
    const action = button.dataset.tenantAction;

    if (action === 'impersonate') {
        openTenantImpersonation(tenantId, String(tenant?.slug || ''), String(tenant?.name || ''));
        return;
    }

    if (action === 'report') {
        loadTenantReport(tenantId);
        return;
    }

    if (action === 'delete' && tenant?.slug !== 'default') {
        deleteTenant(tenantId, String(tenant?.slug || ''));
    }
}

async function openTenantImpersonation(tenantId, tenantSlug, tenantName) {
    const tenantUsers = (tenantLookups.users || []).filter((user) => String(user.tenant_id || '') === String(tenantId));
    if (!tenantUsers.length) {
        Swal.fire({ ...swalConfig(), icon: 'warning', title: 'No Users', text: 'This tenant has no users available for impersonation.' });
        return;
    }

    tenantUsers.sort((a, b) => {
        const officeDelta = Number(!!b.is_office) - Number(!!a.is_office);
        if (officeDelta !== 0) {
            return officeDelta;
        }
        return String(a.name || '').localeCompare(String(b.name || ''));
    });

    const options = tenantUsers.reduce((map, user) => {
        const flags = [];
        if (user.is_office) flags.push('Office');
        if (user.is_member) flags.push('Worker');
        if (user.is_driver) flags.push('Driver');
        if (user.is_callout_driver) flags.push('Callout');
        if (user.is_subcontractor) flags.push('Sub');
        map[user.id] = `${user.name} (${user.email || 'no email'})${flags.length ? ' • ' + flags.join(', ') : ''}`;
        return map;
    }, {});

    const defaultUserId = tenantUsers.find((user) => user.is_office)?.id || tenantUsers[0].id;
    const result = await Swal.fire({
        ...swalConfig(),
        title: `Login As ${tenantName}`,
        input: 'select',
        inputOptions: options,
        inputValue: String(defaultUserId),
        inputPlaceholder: 'Select a user',
        showCancelButton: true,
        confirmButtonText: 'Impersonate User',
        inputValidator: (value) => value ? null : 'Select a user to continue.'
    });

    if (!result.isConfirmed) {
        return;
    }

    const selectedUser = tenantUsers.find((user) => String(user.id) === String(result.value));
    if (!selectedUser) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Invalid User', text: 'The selected user could not be found.' });
        return;
    }

    try {
        const result = await apiJson('tenant_login.php', {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify({
                action: 'impersonate',
                tenant_id: Number(tenantId),
                tenant_slug: tenantSlug,
                tenant_name: tenantName,
                user_id: Number(selectedUser.id)
            })
        });
        await Swal.fire({ ...swalConfig(), icon: 'success', title: 'Impersonation Started', text: result.message });
        window.location.href = result.redirect || '../index.php';
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Impersonation Failed', text: error.message });
    }
}

async function restoreTenantContext() {
    try {
        const result = await apiJson('tenant_login.php', {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify({ action: 'restore' })
        });
        await Swal.fire({ ...swalConfig(), icon: 'success', title: 'Tenant Context Restored', text: result.message });
        window.location.href = result.redirect || '../index.php';
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Restore Failed', text: error.message });
    }
}

async function loadTenants() {
    const data = await apiJson(`${window.laravelApiUrl}/api/tenants`, { headers: tenantHeaders() });
    tenantRows = data.data || [];
    renderTenants();
    populateTenantSelects();
    clearFeatureToggles();
}

async function loadLookups() {
    const data = await apiJson(`${window.laravelApiUrl}/api/tenants/lookups`, { headers: tenantHeaders() });
    tenantLookups = data.data || { users: [] };
}

function renderTenants() {
    const body = document.getElementById('tenantsBody');
    if (!tenantRows.length) {
        body.innerHTML = '<tr><td colspan="6" class="px-6 py-10 text-center text-gray-400 font-bold uppercase tracking-widest text-[10px]">No tenants found.</td></tr>';
        return;
    }

    body.innerHTML = tenantRows.map((tenant) => `
        <tr>
            <td class="px-6 py-4">
                <div class="font-black text-gray-900 dark:text-white">${escapeHtml(tenant.name)}</div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">${escapeHtml(tenant.slug)} • ${escapeHtml(tenant.status)}</div>
            </td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${escapeHtml(tenant.users_count)}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${escapeHtml(tenant.categories_count)}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${escapeHtml(tenant.projects_count)}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${escapeHtml(tenant.leads_count)}</td>
            <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                    <button type="button" data-tenant-action="impersonate" data-tenant-id="${escapeHtml(tenant.id)}" class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-emerald-600 hover:text-white transition-all">Login</button>
                    <button type="button" data-tenant-action="report" data-tenant-id="${escapeHtml(tenant.id)}" class="px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-gray-900 hover:text-white transition-all">Report</button>
                    ${tenant.slug !== 'default' ? `
                        <button type="button" data-tenant-action="delete" data-tenant-id="${escapeHtml(tenant.id)}" class="px-3 py-1.5 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-red-600 hover:text-white transition-all">Delete</button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

async function deleteTenant(tenantId, slug) {
    const { value: targetDir } = await Swal.fire({
        ...swalConfig(),
        title: 'Delete Tenant?',
        html: `This will delete database records and the physical directory.<br><br>Please enter the <b>directory name</b> to confirm (e.g. trackers-acme):`,
        input: 'text',
        inputPlaceholder: 'Directory name...',
        showCancelButton: true,
        confirmButtonText: 'Delete Everything',
        confirmButtonColor: '#ef4444',
        inputValidator: (value) => {
            if (!value) return 'You must provide the directory name!';
        }
    });

    if (!targetDir) return;

    const confirm2 = await Swal.fire({
        ...swalConfig(),
        title: 'Final Confirmation',
        text: `Are you absolutely sure you want to delete tenant ID ${tenantId} (${slug}) and the directory "${targetDir}"? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it',
        confirmButtonColor: '#ef4444'
    });

    if (!confirm2.isConfirmed) return;

    Swal.fire({
        ...swalConfig(),
        title: 'Deleting...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const result = await apiJson('delete_tenant_handler.php', {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify({
                tenant_id: tenantId,
                target_dir: targetDir
            })
        });
        await Swal.fire({ ...swalConfig(), icon: 'success', title: 'Deleted', text: result.message });
        await loadTenants();
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Deletion Failed', text: error.message });
    }
}

function populateTenantSelects() {
    const currentValues = {
        reportTenantId: document.getElementById('reportTenantId')?.value || ''
    };
    const options = tenantRows.map((tenant) => `<option value="${escapeHtml(tenant.id)}">${escapeHtml(tenant.name)} (${escapeHtml(tenant.slug)})</option>`).join('');
    ['reportTenantId'].forEach((id) => {
        const select = document.getElementById(id);
        if (select) {
            select.innerHTML = options;
            if (currentValues[id] && tenantRows.some((tenant) => String(tenant.id) === String(currentValues[id]))) {
                select.value = currentValues[id];
            }
        }
    });
}

async function loadTenantReport(explicitTenantId = null) {
    const tenantId = normalizeTenantId(explicitTenantId || document.getElementById('reportTenantId')?.value);
    if (!tenantId || !isKnownTenantId(tenantId)) {
        persistReportTenantId('');
        document.getElementById('tenantReport').innerHTML = '';
        Swal.fire({ ...swalConfig(), icon: 'warning', title: 'Select a tenant', text: 'Choose a tenant before loading the report.' });
        return;
    }

    document.getElementById('reportTenantId').value = tenantId;
    persistReportTenantId(tenantId);
    document.getElementById('tenantReport').innerHTML = `
        <div class="rounded-2xl border border-gray-200 dark:border-slate-800 bg-white dark:bg-slate-900/20 px-6 py-8 text-center">
            <div class="text-sm font-black text-gray-900 dark:text-white">Loading tenant report...</div>
            <div class="mt-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">Fetching distribution and feature data</div>
        </div>
    `;

    try {
        await loadFeatureSettings(tenantId);
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/report`, {
            headers: tenantHeaders()
        });
        const tenant = result.data?.tenant;
        const rows = result.data.distribution || [];
        if (!tenant) {
            throw new Error('Report response did not include tenant details.');
        }
        document.getElementById('tenantReport').innerHTML = `
            <div class="mb-4">
                <div class="font-black text-gray-900 dark:text-white">${escapeHtml(tenant.name)} (${escapeHtml(tenant.slug)})</div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400">${escapeHtml(tenant.status)}</div>
            </div>
            <table class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Table</th>
                        <th class="px-6 py-4 text-right">Rows</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
                    ${rows.map((row) => `
                        <tr>
                            <td class="px-6 py-4 font-bold text-gray-700 dark:text-slate-200">${escapeHtml(row.table)}</td>
                            <td class="px-6 py-4 text-right font-black text-indigo-600 dark:text-indigo-400">${escapeHtml(row.rows)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        document.getElementById('tenantReport').innerHTML = `
            <div class="rounded-2xl border border-rose-200 dark:border-rose-900/40 bg-rose-50 dark:bg-rose-950/20 px-6 py-8">
                <div class="text-sm font-black text-rose-900 dark:text-rose-100">Report failed to load</div>
                <div class="mt-2 text-xs text-rose-700 dark:text-rose-200">${escapeHtml(error.message || 'Unknown error')}</div>
            </div>
        `;
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Report Failed', text: error.message });
    }
}

async function loadFeatureSettings(tenantId) {
    const normalizedTenantId = normalizeTenantId(tenantId);
    if (!normalizedTenantId || !isKnownTenantId(normalizedTenantId)) {
        clearFeatureToggles();
        return;
    }

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${normalizedTenantId}/feature-settings`, {
            headers: tenantHeaders()
        });
        applyFeatureToggles(result.data || {});
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Could not load features', text: error.message });
    }
}

function applyFeatureToggles(settings = {}) {
    featureToggleMapping.forEach(({ key, elementId }) => {
        const checkbox = document.getElementById(elementId);
        if (!checkbox) {
            return;
        }
        checkbox.checked = Boolean(settings[key]);
    });
}

function clearFeatureToggles() {
    featureToggleMapping.forEach(({ elementId }) => {
        const checkbox = document.getElementById(elementId);
        if (checkbox) {
            checkbox.checked = false;
        }
    });
}

function getFeaturePayload() {
    const payload = {};
    featureToggleMapping.forEach(({ key, elementId }) => {
        const checkbox = document.getElementById(elementId);
        if (checkbox) {
            payload[key] = checkbox.checked;
        }
    });
    return payload;
}

async function saveFeatureToggles() {
    const tenantId = normalizeTenantId(document.getElementById('reportTenantId')?.value);
    if (!tenantId || !isKnownTenantId(tenantId)) {
        Swal.fire({ ...swalConfig(), icon: 'warning', title: 'Select a tenant', text: 'Choose a tenant before saving feature toggles.' });
        return;
    }

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/feature-settings`, {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify(getFeaturePayload())
        });
        persistReportTenantId(tenantId);
        await loadFeatureSettings(tenantId);
        await Swal.fire({
            ...swalConfig(),
            icon: 'success',
            title: 'Saved',
            text: (result.message || 'Features updated.') + ' Reloading to refresh the navigation.'
        });
        window.location.reload();
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Save Failed', text: error.message });
    }
}

async function provisionInstance(event) {
    event.preventDefault();
    const payload = {
        target_dir: document.getElementById('provTargetDir').value.trim(),
        app_name: document.getElementById('provAppName').value.trim(),
        app_url: document.getElementById('provAppUrl').value.trim(),
        tenant_slug: document.getElementById('provTenantSlug').value.trim(),
        branch: document.getElementById('provBranch').value.trim(),
        admin_email: document.getElementById('provAdminEmail').value.trim(),
        admin_name: document.getElementById('provAdminName').value.trim(),
        admin_password: document.getElementById('provAdminPassword').value
    };

    Swal.fire({
        ...swalConfig(),
        title: 'Provisioning...',
        text: 'Please wait while the new instance is being created.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const result = await apiJson('provision_handler.php', {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify(payload)
        });
        const stages = result.stages && typeof result.stages === 'object'
            ? Object.entries(result.stages).map(([key, stage]) => ({ key, ...stage }))
            : [];
        const stageHtml = stages.length
            ? `<div class="mt-4 space-y-2">${stages.map(stage => {
                const tone = stage.status === 'configured' || stage.status === 'created' || stage.status === 'existing'
                    ? 'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-100 dark:border-emerald-800'
                    : stage.status === 'partial' || stage.status === 'skipped'
                        ? 'bg-amber-50 text-amber-900 border-amber-200 dark:bg-amber-950/40 dark:text-amber-100 dark:border-amber-800'
                        : 'bg-rose-50 text-rose-900 border-rose-200 dark:bg-rose-950/40 dark:text-rose-100 dark:border-rose-800';
                return `<div class="rounded border px-3 py-2 ${tone}">
                    <div class="text-[10px] font-bold uppercase tracking-widest">${escapeHtml(stage.key)}</div>
                    <div class="mt-1 text-sm">${escapeHtml(stage.message || '')}</div>
                </div>`;
            }).join('')}</div>`
            : '';
        Swal.fire({
            ...swalConfig(),
            icon: 'success',
            title: 'Provisioning Complete',
            html: `<div class="text-left text-slate-900 dark:text-slate-100"><p class="text-sm text-slate-900 dark:text-slate-100">${escapeHtml(result.message || '')}</p>${stageHtml}<pre class="mt-4 p-4 bg-gray-100 text-slate-900 dark:bg-slate-800 dark:text-slate-100 rounded text-[10px] overflow-auto max-h-60">${escapeHtml(result.output || '')}</pre></div>`
        });
        document.getElementById('provisionForm').reset();
    } catch (error) {
        Swal.fire({
            ...swalConfig(),
            icon: 'error',
            title: 'Provisioning Failed',
            text: error.message
        });
    }
}
</script>

<?php include '../footer.php'; ?>
