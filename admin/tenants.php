<?php
$pageTitle = 'Tenant Manager';
require_once '../config.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$superAdminEmail = trackerSuperAdminEmail();
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    header('Location: ../index.php');
    exit();
}

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="heading-brand">Tenant Manager</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Create tenants, move users and categories, and review tenant distribution.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-cog"></i> Admin Home
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <section class="card-base border-none xl:col-span-1">
            <div class="section-header">
                <h3><i class="fas fa-building text-indigo-400 mr-2"></i> Create Tenant</h3>
            </div>
            <form id="tenantForm" class="p-8 space-y-6">
                <input type="hidden" id="tenantId">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Name</label>
                    <input type="text" id="tenantName" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Slug</label>
                    <input type="text" id="tenantSlug" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" required>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Status</label>
                    <select id="tenantStatus" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-6 py-4 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-xl active:scale-95">Save Tenant</button>
                    <button type="button" id="tenantResetBtn" class="px-6 py-4 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-slate-300 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-slate-700 transition-all">Reset</button>
                </div>
            </form>
        </section>

        <section class="card-base border-none xl:col-span-2">
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
                    <tbody id="tenantsBody" class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
        <section class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-user-plus text-emerald-400 mr-2"></i> Assign User</h3>
            </div>
            <form id="assignUserForm" class="p-8 space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">User</label>
                    <select id="assignUserId" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500"></select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Target Tenant</label>
                    <select id="assignTenantId" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500"></select>
                </div>
                <label class="flex items-center gap-3 text-xs font-bold text-gray-600 dark:text-slate-300">
                    <input type="checkbox" id="assignCascade" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                    Cascade common user-owned rows
                </label>
                <button type="submit" class="w-full px-6 py-4 bg-emerald-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-xl active:scale-95">Assign User To Tenant</button>
            </form>
        </section>

        <section class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-project-diagram text-sky-400 mr-2"></i> Move Category</h3>
            </div>
            <form id="moveCategoryForm" class="p-8 space-y-6">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category</label>
                    <select id="moveCategoryId" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-sky-500"></select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Target Tenant</label>
                    <select id="moveTenantId" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-sky-500"></select>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <button type="button" id="dryRunBtn" class="px-6 py-4 bg-white dark:bg-slate-900 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-sky-50 dark:hover:bg-slate-800 transition-all">Dry Run</button>
                    <button type="submit" class="px-6 py-4 bg-sky-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-sky-700 transition-all shadow-xl active:scale-95">Move Category</button>
                </div>
            </form>
        </section>
    </div>

    <section class="card-base border-none space-y-6">
        <div class="section-header flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fas fa-table text-amber-400"></i>
                <h3>Tenant Report & Features</h3>
            </div>
            <div class="flex gap-2">
                <button id="syncTenantBtn" type="button" class="px-5 py-2 bg-amber-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 transition-all shadow-xl active:scale-95">Sync Derived Data</button>
                <button id="loadReportBtn" type="button" class="px-5 py-2 bg-gray-900 dark:bg-slate-700 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-black dark:hover:bg-slate-600 transition-all shadow-xl active:scale-95">Load Report</button>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Tenant</label>
                <select id="reportTenantId" class="w-full px-4 py-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-amber-500"></select>
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Feature Toggles</label>
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
                <button id="saveFeatureToggles" type="button" class="mt-4 w-full px-5 py-3 bg-sky-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-sky-700 transition-all shadow-xl active:scale-95">Save Feature Toggles</button>
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

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('tenantResetBtn').addEventListener('click', resetTenantForm);
    document.getElementById('tenantForm').addEventListener('submit', saveTenant);
    document.getElementById('assignUserForm').addEventListener('submit', assignUserToTenant);
    document.getElementById('moveCategoryForm').addEventListener('submit', (event) => moveCategoryToTenant(event, false));
    document.getElementById('dryRunBtn').addEventListener('click', (event) => moveCategoryToTenant(event, true));
    document.getElementById('loadReportBtn').addEventListener('click', loadTenantReport);
    document.getElementById('syncTenantBtn').addEventListener('click', syncTenantData);
    document.getElementById('saveFeatureToggles')?.addEventListener('click', saveFeatureToggles);
    document.getElementById('reportTenantId')?.addEventListener('change', (event) => {
        loadFeatureSettings(event.target.value);
    });
    await Promise.all([loadTenants(), loadLookups()]);
});

async function apiJson(url, options = {}) {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
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
                    <button type="button" onclick="editTenant(${tenant.id})" class="px-3 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-indigo-600 hover:text-white transition-all">Edit</button>
                    <button type="button" onclick="loadTenantReport(${tenant.id})" class="px-3 py-1.5 bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-slate-200 font-black uppercase text-[9px] tracking-widest rounded-lg hover:bg-gray-900 hover:text-white transition-all">Report</button>
                </div>
            </td>
        </tr>
    `).join('');
}

function populateTenantSelects() {
    const options = tenantRows.map((tenant) => `<option value="${tenant.id}">${escapeHtml(tenant.name)} (${escapeHtml(tenant.slug)})</option>`).join('');
    ['assignTenantId', 'moveTenantId', 'reportTenantId'].forEach((id) => {
        const select = document.getElementById(id);
        if (select) {
            select.innerHTML = options;
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
        return;
    }

    document.getElementById('reportTenantId').value = tenantId;

    try {
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
        Swal.fire({ ...swalConfig(), icon: 'success', title: 'Saved', text: result.message || 'Features updated.' });
        await loadFeatureSettings(tenantId);
    } catch (error) {
        Swal.fire({ ...swalConfig(), icon: 'error', title: 'Save Failed', text: error.message });
    }
}
</script>

<?php include '../footer.php'; ?>
