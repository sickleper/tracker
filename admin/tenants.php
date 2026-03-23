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
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Create tenants, move users and categories, and review tenant distribution.</p>
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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <section class="admin-panel xl:col-span-1">
            <div class="section-header">
                <h3><i class="fas fa-building text-indigo-400 mr-2"></i> Create Tenant</h3>
            </div>
            <form id="tenantForm" class="admin-panel-body-lg space-y-6">
                <input type="hidden" id="tenantId">
                <div>
                    <label class="admin-label">Name</label>
                    <input type="text" id="tenantName" class="admin-input" required>
                </div>
                <div>
                    <label class="admin-label">Slug</label>
                    <input type="text" id="tenantSlug" class="admin-input" required>
                </div>
                <div>
                    <label class="admin-label">Status</label>
                    <select id="tenantStatus" class="admin-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="admin-action admin-action-primary admin-action-lg flex-1">Save Tenant</button>
                    <button type="button" id="tenantResetBtn" class="admin-action admin-action-muted admin-action-lg">Reset</button>
                </div>
            </form>
        </section>

        <section class="admin-panel xl:col-span-2">
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

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <section class="admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-user-plus text-emerald-400 mr-2"></i> Assign User</h3>
            </div>
            <form id="assignUserForm" class="admin-panel-body-lg space-y-6">
                <div>
                    <label class="admin-label">User</label>
                    <select id="assignUserId" class="admin-select"></select>
                </div>
                <div>
                    <label class="admin-label">Target Tenant</label>
                    <select id="assignTenantId" class="admin-select"></select>
                </div>
                <label class="flex items-center gap-3 text-xs font-bold text-gray-600 dark:text-slate-300">
                    <input type="checkbox" id="assignCascade" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    Cascade common user-owned rows
                </label>
                <button type="submit" class="admin-action admin-action-success admin-action-lg w-full">Assign User To Tenant</button>
            </form>
        </section>

        <section class="admin-panel">
            <div class="section-header">
                <h3><i class="fas fa-project-diagram text-sky-400 mr-2"></i> Move Category</h3>
            </div>
            <form id="moveCategoryForm" class="admin-panel-body-lg space-y-6">
                <div>
                    <label class="admin-label">Category</label>
                    <select id="moveCategoryId" class="admin-select"></select>
                </div>
                <div>
                    <label class="admin-label">Target Tenant</label>
                    <select id="moveTenantId" class="admin-select"></select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <button type="button" id="dryRunBtn" class="admin-action admin-action-outline-sky admin-action-lg">Dry Run</button>
                    <button type="submit" class="admin-action admin-action-info admin-action-lg">Move Category</button>
                </div>
            </form>
        </section>
    </div>

    <section class="admin-panel admin-panel-body-lg space-y-6">
        <div class="section-header flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-table text-amber-400"></i>
                <h3>Tenant Report & Features</h3>
            </div>
            <div class="flex gap-2">
                <button id="syncTenantBtn" type="button" class="admin-action admin-action-warning admin-action-sm">Sync Derived Data</button>
                <button id="loadReportBtn" type="button" class="admin-action admin-action-dark admin-action-sm">Load Report</button>
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
let tenantLookups = { users: [], categories: [] };
const reportTenantStorageKey = 'tracker_admin_report_tenant_id';

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

function getPersistedReportTenantId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('tenant_report_id') || sessionStorage.getItem(reportTenantStorageKey) || '';
}

function persistReportTenantId(tenantId) {
    const normalized = String(tenantId || '').trim();
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

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('tenantResetBtn').addEventListener('click', resetTenantForm);
    document.getElementById('tenantForm').addEventListener('submit', saveTenant);
    document.getElementById('assignUserForm').addEventListener('submit', assignUserToTenant);
    document.getElementById('moveCategoryForm').addEventListener('submit', (event) => moveCategoryToTenant(event, false));
    document.getElementById('dryRunBtn').addEventListener('click', (event) => moveCategoryToTenant(event, true));
    document.getElementById('loadReportBtn').addEventListener('click', loadTenantReport);
    document.getElementById('syncTenantBtn').addEventListener('click', syncTenantData);
    document.getElementById('saveFeatureToggles')?.addEventListener('click', saveFeatureToggles);
    document.getElementById('restoreTenantContextBtn')?.addEventListener('click', restoreTenantContext);
    document.getElementById('reportTenantId')?.addEventListener('change', (event) => {
        loadTenantReport(event.target.value);
    });
    await Promise.all([loadTenants(), loadLookups()]);
    const persistedTenantId = getPersistedReportTenantId();
    if (persistedTenantId) {
        const reportSelect = document.getElementById('reportTenantId');
        if (reportSelect) {
            reportSelect.value = persistedTenantId;
        }
        await loadTenantReport(persistedTenantId);
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
    const reportSelect = document.getElementById('reportTenantId');
    if (reportSelect && reportSelect.value) {
        await Promise.all([
            loadFeatureSettings(reportSelect.value),
            loadTenantReport(reportSelect.value)
        ]);
    } else {
        clearFeatureToggles();
    }
}

async function loadLookups() {
    const data = await apiJson(`${window.laravelApiUrl}/api/tenants/lookups`, { headers: tenantHeaders() });
    tenantLookups = data.data || { users: [], categories: [] };
    populateLookupSelects();
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
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${tenant.users_count}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${tenant.categories_count}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${tenant.projects_count}</td>
            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-slate-200">${tenant.leads_count}</td>
            <td class="px-6 py-4 text-right">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="openTenantImpersonation(${tenant.id}, '${escapeHtml(tenant.slug)}', '${escapeHtml(tenant.name)}')" class="px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-emerald-600 hover:text-white transition-all">Login</button>
                    <button type="button" onclick="editTenant(${tenant.id})" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-indigo-600 hover:text-white transition-all">Edit</button>
                    <button type="button" onclick="loadTenantReport(${tenant.id})" class="px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-gray-900 hover:text-white transition-all">Report</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function populateTenantSelects() {
    const currentValues = {
        assignTenantId: document.getElementById('assignTenantId')?.value || '',
        moveTenantId: document.getElementById('moveTenantId')?.value || '',
        reportTenantId: document.getElementById('reportTenantId')?.value || ''
    };
    const options = tenantRows.map((tenant) => `<option value="${tenant.id}">${escapeHtml(tenant.name)} (${escapeHtml(tenant.slug)})</option>`).join('');
    ['assignTenantId', 'moveTenantId', 'reportTenantId'].forEach((id) => {
        const select = document.getElementById(id);
        if (select) {
            select.innerHTML = options;
            if (currentValues[id] && tenantRows.some((tenant) => String(tenant.id) === String(currentValues[id]))) {
                select.value = currentValues[id];
            }
        }
    });
}

function populateLookupSelects() {
    document.getElementById('assignUserId').innerHTML = tenantLookups.users.map((user) => {
        const tenantLabel = user.tenant_id ? `Tenant ${user.tenant_id}` : 'No Tenant';
        return `<option value="${user.id}">${escapeHtml(user.name)} (${escapeHtml(user.email || '')}) • ${tenantLabel}</option>`;
    }).join('');

    document.getElementById('moveCategoryId').innerHTML = tenantLookups.categories.map((category) => {
        const tenantLabel = category.tenant_id ? `Tenant ${category.tenant_id}` : 'No Tenant';
        return `<option value="${category.id}">${escapeHtml(category.category_name)} • ${tenantLabel}</option>`;
    }).join('');
}

function editTenant(id) {
    const tenant = tenantRows.find((row) => Number(row.id) === Number(id));
    if (!tenant) {
        return;
    }

    document.getElementById('tenantId').value = tenant.id;
    document.getElementById('tenantName').value = tenant.name || '';
    document.getElementById('tenantSlug').value = tenant.slug || '';
    document.getElementById('tenantStatus').value = tenant.status || 'active';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetTenantForm() {
    document.getElementById('tenantForm').reset();
    document.getElementById('tenantId').value = '';
    document.getElementById('tenantStatus').value = 'active';
}

async function saveTenant(event) {
    event.preventDefault();
    const tenantId = document.getElementById('tenantId').value;
    const payload = {
        name: document.getElementById('tenantName').value.trim(),
        slug: document.getElementById('tenantSlug').value.trim(),
        status: document.getElementById('tenantStatus').value
    };

    const url = tenantId ? `${window.laravelApiUrl}/api/tenants/${tenantId}` : `${window.laravelApiUrl}/api/tenants`;
    const method = tenantId ? 'PATCH' : 'POST';

    try {
        const result = await apiJson(url, {
            method,
            headers: tenantJsonHeaders(),
            body: JSON.stringify(payload)
        });
        Swal.fire({ ...swalConfig(), icon: 'success', title: 'Saved', text: result.message || 'Tenant saved.' });
        resetTenantForm();
        await loadTenants();
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Save Failed', text: error.message });
    }
}

async function assignUserToTenant(event) {
    event.preventDefault();
    const tenantId = document.getElementById('assignTenantId').value;
    const payload = {
        user_id: Number(document.getElementById('assignUserId').value),
        cascade: document.getElementById('assignCascade').checked
    };

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/assign-user`, {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify(payload)
        });
        Swal.fire({ ...swalConfig(), icon: 'success', title: 'User Assigned', text: result.message });
        await Promise.all([loadTenants(), loadLookups()]);
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Assignment Failed', text: error.message });
    }
}

async function moveCategoryToTenant(event, dryRun) {
    event.preventDefault();
    const tenantId = document.getElementById('moveTenantId').value;
    const payload = {
        category_id: Number(document.getElementById('moveCategoryId').value),
        dry_run: dryRun
    };

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/move-category`, {
            method: 'POST',
            headers: tenantJsonHeaders(),
            body: JSON.stringify(payload)
        });
        Swal.fire({ ...swalConfig(), icon: 'success', title: dryRun ? 'Dry Run Complete' : 'Category Moved', text: result.message });
        if (!dryRun) {
            await Promise.all([loadTenants(), loadLookups()]);
        }
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: dryRun ? 'Dry Run Failed' : 'Move Failed', text: error.message });
    }
}

async function syncTenantData() {
    const tenantId = document.getElementById('reportTenantId').value;
    if (!tenantId) {
        return;
    }

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/sync-derived`, {
            method: 'POST',
            headers: tenantHeaders()
        });
        Swal.fire({ ...swalConfig(), icon: 'success', title: 'Sync Complete', text: result.message });
        await loadTenantReport(tenantId);
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Sync Failed', text: error.message });
    }
}

async function loadTenantReport(explicitTenantId = null) {
    const tenantId = explicitTenantId || document.getElementById('reportTenantId').value;
    if (!tenantId) {
        persistReportTenantId('');
        return;
    }

    document.getElementById('reportTenantId').value = tenantId;
    persistReportTenantId(tenantId);

    try {
        await loadFeatureSettings(tenantId);
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/report`, {
            headers: tenantHeaders()
        });
        const tenant = result.data.tenant;
        const rows = result.data.distribution || [];
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
                            <td class="px-6 py-4 text-right font-black text-indigo-600 dark:text-indigo-400">${row.rows}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Report Failed', text: error.message });
    }
}

async function loadFeatureSettings(tenantId) {
    if (!tenantId) {
        clearFeatureToggles();
        return;
    }

    try {
        const result = await apiJson(`${window.laravelApiUrl}/api/tenants/${tenantId}/feature-settings`, {
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
    const tenantId = document.getElementById('reportTenantId').value;
    if (!tenantId) {
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
</script>

<?php include '../footer.php'; ?>
