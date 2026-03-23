let leadsTable = null;
let mapInstance = null;
let markerInstance = null;
let followUpPicker = null;

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function safeJsString(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

// --- Modal Management ---

function closeLeadModal() {
    $('#leadModal').addClass('hidden');
    $('body').removeClass('overflow-hidden');
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
}

function closeFollowUpModal() {
    $('#followUpModal').addClass('hidden');
    $('body').removeClass('overflow-hidden');
    if (followUpPicker) followUpPicker.destroy();
}

// --- Lead CRUD ---

// Function to determine SweetAlert2 theme based on dark mode
function getSwalTheme() {
    return $('html').hasClass('dark') ? 'dark' : 'default';
}

function openAddLeadModal() {
    $('#leadModalLabel').html('<span>🎯</span> Create New Lead');
    $('#leadModalBody').html(renderLeadForm());
    $('#leadModal').removeClass('hidden');
    $('body').addClass('overflow-hidden');
    setTimeout(() => initLeafletMap(), 300);
}

function openEditLeadModal(id) {
    $('#leadModalLabel').html('<span>🎯</span> Edit Lead #' + id);
    $('#leadModalBody').html('<div class="flex justify-center p-12"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i></div>');
    $('#leadModal').removeClass('hidden');
    $('body').addClass('overflow-hidden');

    $.ajax({
        url: 'leads_handler.php',
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + window.apiToken },
        data: { action: 'get_lead', id: id },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.success) {
                $('#leadModalBody').html(renderLeadForm(res.data));
                setTimeout(() => initLeafletMap(res.data.latlng), 300);
            } else {
                $('#leadModalBody').html('<p class="text-red-500 font-bold p-6 text-center">' + (res.message || 'Failed to load lead.') + '</p>');
            }
        }
    });
}

function renderLeadForm(data = null) {
    const isEdit = data !== null;
    return `
        <form id="leadForm" class="space-y-6">
            <input type="hidden" name="action" value="${isEdit ? 'update_lead' : 'create_lead'}">
            ${isEdit ? `<input type="hidden" name="id" value="${data.id}">` : ''}
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Client Name *</label>
                    <input type="text" name="client_name" required value="${data?.client_name || ''}" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Email Address</label>
                    <input type="email" name="client_email" value="${data?.client_email || ''}" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Category / Brand *</label>
                    <select name="category_id" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        <option value="">Select Category</option>
                        ${(window.leadCategories || []).map(cat => `
                            <option value="${escapeHtml(cat.id)}" ${data?.category_id == cat.id ? 'selected' : ''}>${escapeHtml(cat.category_name)}</option>
                        `).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Eircode (Lookup)</label>
                    <div class="flex gap-2">
                        <input type="text" id="eircodeInput" class="flex-grow p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold uppercase dark:text-white" placeholder="D24...">
                        <button type="button" onclick="lookupEircode()" class="px-4 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 transition-all shadow-md">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Mobile Number</label>
                    <input type="text" name="mobile" value="${data?.mobile || ''}" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Company Name</label>
                    <input type="text" name="company_name" value="${data?.company_name || ''}" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Address (Manual or from Lookup)</label>
                    <input type="text" id="addressInput" name="address" value="${data?.address || ''}" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Coordinates (Lat, Lng)</label>
                    <input type="text" id="latlngInput" name="latlng" value="${data?.latlng || ''}" readonly class="w-full p-4 bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl text-sm font-bold text-gray-500 dark:text-gray-400">
                </div>
            </div>

            <input type="hidden" name="city" id="cityInput" value="${data?.city || ''}">
            <input type="hidden" name="state" id="stateInput" value="${data?.state || ''}">
            <input type="hidden" name="country" id="countryInput" value="${data?.country || ''}">
            <input type="hidden" name="postal_code" id="postalCodeInput" value="${data?.postal_code || ''}">

            <div id="modalMap" class="w-full h-64 rounded-2xl border border-gray-200 dark:border-slate-800 shadow-inner z-0"></div>

            <div>
                <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2">Message / Requirements</label>
                <textarea name="message" rows="4" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm leading-relaxed dark:text-gray-300">${data?.message || ''}</textarea>
            </div>

            <div class="flex items-center gap-3 p-4 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-100 dark:border-indigo-900/30">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="next_follow_up" value="yes" class="sr-only peer" ${data?.next_follow_up === 'yes' ? 'checked' : ''}>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                </label>
                <div>
                    <span class="block text-xs font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-400">Requires Follow Up?</span>
                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase">Toggle this to keep the lead in your active queue</span>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <button type="submit" class="flex-1 py-4 bg-gray-900 dark:bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-gray-800 dark:hover:bg-indigo-700 transition-all active:scale-[0.98] shadow-lg">
                    ${isEdit ? 'Update Lead' : 'Create Lead'}
                </button>
                <button type="button" onclick="closeLeadModal()" class="px-8 py-4 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-300 rounded-2xl font-black uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-slate-700 transition-all">
                    Cancel
                </button>
            </div>
        </form>
    `;
}

// --- Leaflet Map ---

function initLeafletMap(latlng = null) {
    const dublin = [53.3498, -6.2603];
    let pos = dublin;

    if (latlng) {
        const parts = latlng.split(',');
        if (parts.length === 2) {
            pos = [parseFloat(parts[0]), parseFloat(parts[1])];
        }
    }

    if (mapInstance) {
        mapInstance.remove();
    }

    mapInstance = L.map('modalMap').setView(pos, 15);

    // Always use light tiles for maximum visibility
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(mapInstance);

    markerInstance = L.marker(pos, { draggable: true }).addTo(mapInstance);

    markerInstance.on('dragend', function(e) {
        const p = markerInstance.getLatLng();
        $('#latlngInput').val(`${p.lat.toFixed(6)},${p.lng.toFixed(6)}`);
    });
}

// --- Follow Up Management ---

function openFollowUpModal(id, name) {
    $('#followUpLeadId').val(id);
    $('#followUpLeadName').text(name);
    $('#followUpForm')[0].reset();
    $('#reminderOptions').addClass('hidden');
    $('#clearFollowUpBtn').addClass('hidden');

    if (followUpPicker) {
        followUpPicker.destroy();
        followUpPicker = null;
    }
    
    $('#followUpModal').removeClass('hidden');
    $('body').addClass('overflow-hidden');

    $.ajax({
        url: 'leads_handler.php',
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + window.apiToken },
        data: { action: 'get_lead', id: id },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.success && res.data.follow_ups && res.data.follow_ups.length > 0) {
                const fu = res.data.follow_ups[0];
                $('#clearFollowUpBtn').removeClass('hidden');
                $('#followUpRemark').val(fu.remark || '');
                $('#sendReminderSelect').val(fu.send_reminder || 'no').trigger('change');
                if (fu.send_reminder === 'yes') {
                    $('input[name="remind_time"]').val(fu.remind_time || 1);
                    $('select[name="remind_type"]').val(normalizeRemindType(fu.remind_type));
                }
                initFollowUpPicker(fu.next_follow_up_date);
            } else {
                initFollowUpPicker();
            }
        }
    });
}

function normalizeRemindType(value) {
    const normalized = String(value || 'hour').toLowerCase();
    if (normalized.includes('minute')) return 'minute';
    if (normalized.includes('day')) return 'day';
    return 'hour';
}

function initFollowUpPicker(defaultDate = null) {
    const parsedDefaultDate = defaultDate ? new Date(defaultDate) : null;
    const hasValidDefaultDate = parsedDefaultDate instanceof Date && !isNaN(parsedDefaultDate.getTime());
    const minDate = hasValidDefaultDate && parsedDefaultDate < new Date() ? null : 'today';

    followUpPicker = flatpickr("#followUpDateTime", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        defaultDate: defaultDate || new Date(),
        minDate: minDate,
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            // Check if this day is one of the callout days
            const dayIndex = dayElem.dateObj.getDay();
            if (window.calloutDays.includes(dayIndex)) {
                dayElem.classList.add("callout-day-highlight");
                dayElem.title = "Official Callout Day";
            }
        },
        onChange: function(selectedDates, dateStr, instance) {
            const date = selectedDates[0];
            if (date && !window.calloutDays.includes(date.getDay())) {
                Swal.fire({
                    title: 'Non-Callout Day',
                    text: 'You have selected a day that is not configured as a standard callout day. Continue anyway?',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    theme: getSwalTheme()
                });
            }
        }
    });
}

function clearFollowUp() {
    const id = $('#followUpLeadId').val();
    Swal.fire({
        title: 'Clear Follow Up?',
        text: "This will remove the scheduled follow-up for this lead.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        theme: getSwalTheme(),
        customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: { action: 'delete_followup', id: id },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ icon:'success', title:'Cleared!', text:'Follow up removed.', theme: getSwalTheme() });
                        closeFollowUpModal();
                        refreshLeads();
                    }
                }
            });
        }
    });
}

// --- Status Management ---

function updateLeadStatus(id, newStatus, followUp = null) {
    const data = { action: 'update_lead', id: id };
    if (newStatus !== null) data.status_id = newStatus;
    if (followUp !== null) data.next_follow_up = followUp;

    $.ajax({
        url: 'leads_handler.php',
        method: 'POST',
        data: data,
        success: function(res) {
            if (res.success) {
                if (followUp !== null) refreshLeads();
            } else {
                Swal.fire({ icon:'error', title:'Error!', text:res.message, theme: getSwalTheme() });
            }
        }
    });
}

// --- Helpers & Utilities ---

function lookupEircode() {
    const eircode = $('#eircodeInput').val().trim();
    if (!eircode) {
        Swal.fire({ icon:'error', title:'Error', text:'Please enter an Eircode', theme: getSwalTheme() });
        return;
    }

    const btn = $('button[onclick="lookupEircode()"]');
    const originalIcon = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.getJSON(`../query_address.php?eircode=${eircode}`, function(data) {
        btn.prop('disabled', false).html(originalIcon);
        if (data.error) {
            Swal.fire({ icon:'error', title:'Error', text:data.error, theme: getSwalTheme() });
        } else {
            if (data.address) $('#addressInput').val(data.address);
            if (data.city) $('#cityInput').val(data.city);
            if (data.postcode) $('#postalCodeInput').val(data.postcode);
            if (data.coordinates) {
                const pos = [parseFloat(data.coordinates.lat), parseFloat(data.coordinates.lng)];
                $('#latlngInput').val(`${pos[0]},${pos[1]}`);
                if (mapInstance && markerInstance) {
                    mapInstance.setView(pos, 15);
                    markerInstance.setLatLng(pos);
                }
            }
            Swal.fire({ title: 'Address Found!', text: data.address, icon: 'success', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
        }
    }).fail(function() {
        btn.prop('disabled', false).html(originalIcon);
        Swal.fire({ icon:'error', title:'Error', text:'Failed to query address service', theme: getSwalTheme() });
    });
}

function convertToProject(id) {
    Swal.fire({
        title: 'Convert to Full Project?',
        text: "This will create a permanent Client account and generate a new Project entry for detailed operations.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Convert Now',
        confirmButtonColor: '#10b981',
        theme: getSwalTheme(),
        customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Converting...', allowOutsideClick: false, theme: getSwalTheme(), didOpen: () => Swal.showLoading() });

            // 1. Trigger the API conversion (Lead -> Client -> Project)
            $.post(`leads_handler.php`, { action: 'convert_lead_to_project', id: id }, function(res) {
                if (res.success) {
                    const alreadyConverted = !!res.already_converted;
                    Swal.fire({
                        icon: 'success',
                        title: alreadyConverted ? 'Project Already Exists' : 'Conversion Successful',
                        text: alreadyConverted
                            ? 'This lead is already linked to Project #' + res.project_id
                            : 'Lead converted to Project #' + res.project_id,
                        theme: getSwalTheme()
                    }).then(() => {
                        window.location.href = `../projects/view.php?id=${res.project_id}`;
                    });
                } else {
                    Swal.fire({ icon:'error', title:'Conversion Failed', text: res.message, theme: getSwalTheme() });
                }
            }, 'json');
        }
    });
}

function viewEmails(email) {
    if (!email) return;
    Swal.fire({
        title: `<span class="heading-brand text-xl">📧 Recent Emails: ${email}</span>`,
        html: '<div id="email-list-container" class="text-left py-4"><div class="flex justify-center"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i></div></div>',
        width: '600px',
        showConfirmButton: false,
        theme: getSwalTheme(),
        customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' }
    });

    $.getJSON(`../ajax_gmail_orders.php?action=search&q=${encodeURIComponent(email)}`, function(res) {
        if (res.success && res.emails.length > 0) {
            let html = '<div class="space-y-3">';
            res.emails.forEach(em => {
                html += `
                    <div class="p-4 bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all cursor-pointer group">
                        <div class="text-[10px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest mb-1">${escapeHtml(em.date)}</div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">${escapeHtml(em.subject)}</div>
                    </div>
                `;
            });
            html += '</div>';
            $('#email-list-container').html(html);
        } else {
            $('#email-list-container').html('<div class="py-12 text-center text-gray-400 dark:text-gray-600 font-bold italic">No recent emails found.</div>');
        }
    });
}

function refreshLeads() {
    if (leadsTable) leadsTable.ajax.reload();
}

function deleteLead(id) {
    Swal.fire({
        title: 'Delete Lead?',
        text: "This action is irreversible.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it',
        theme: getSwalTheme(),
        customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('leads_handler.php', { action: 'delete_lead', id: id }, function(response) {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.success) {
                    Swal.fire({ icon:'success', title:'Deleted!', text:'Lead removed.', theme: getSwalTheme() });
                    refreshLeads();
                } else {
                    Swal.fire({ icon:'error', title:'Error!', text:res.message, theme: getSwalTheme() });
                }
            });
        }
    });
}

$(document).ready(function() {
    leadsTable = $('#leadsTable').DataTable({
        ajax: {
            url: 'leads_handler.php',
            type: 'POST',
            data: function(d) {
                d.action = 'list_leads';
                d.category_id = $('#categoryFilter').val();
                d.search_val = $('#leadSearch').val();
            },
            dataSrc: function(json) {
                $('#totalLeadsCount').text(json.data ? json.data.length : 0);
                return json.data || [];
            }
        },
        columns: [
            { 
                data: null,
                width: '20%',
                render: (row) => {
                    const date = moment(row.created_at).format('DD/MM/YYYY HH:mm');
                    const msg = escapeHtml(row.message || 'No requirements provided.').replace(/\n/g, '<br>');
                    return `<div class="flex flex-col gap-1">
                        <span class="text-[10px] font-black text-indigo-500 dark:text-indigo-400 uppercase tracking-widest">${date}</span>
                        <div class="text-xs text-gray-700 dark:text-gray-300 line-clamp-3 leading-relaxed">${msg}</div>
                    </div>`;
                }
            },
            { 
                data: null,
                render: (row) => `
                    <div class="flex flex-col">
                        <span class="font-bold text-gray-900 dark:text-white">${escapeHtml(row.client_name || 'N/A')}</span>
                        <span class="text-[10px] text-gray-400 dark:text-gray-500 uppercase font-black tracking-widest">${escapeHtml(row.address || 'No Address')}</span>
                    </div>
                `
            },
            {
                data: null,
                render: (row) => {
                    const safeEmail = escapeHtml(row.client_email || '');
                    const safeMobile = escapeHtml(row.mobile || '');
                    const safeEmailJs = safeJsString(row.client_email || '');
                    return `
                    <div class="flex flex-col gap-1">
                        ${row.client_email ? `
                            <div class="flex items-center gap-2">
                                <a href="mailto:${safeEmail}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium"><i class="fas fa-envelope mr-1 opacity-50"></i> email</a>
                                <button onclick="viewEmails('${safeEmailJs}')" class="text-[9px] bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-1.5 rounded font-black uppercase hover:bg-indigo-600 hover:text-white transition-all">Inbox</button>
                            </div>
                        ` : ''}
                        ${row.mobile ? `<a href="tel:${safeMobile}" class="badge bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold"><i class="fa fa-phone mr-1"></i> ${safeMobile}</a>` : ''}
                    </div>
                `;
                }
            },
            {
                data: 'status_id',
                render: (data, type, row) => `
                    <select onchange="updateLeadStatus(${row.id}, this.value)" class="p-2 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-lg text-[10px] font-black uppercase tracking-wider outline-none focus:ring-2 focus:ring-indigo-500 transition-all dark:text-gray-300">
                        <option value="1" ${data == 1 ? 'selected' : ''}>Pending Visit</option>
                        <option value="2" ${data == 2 ? 'selected' : ''}>Completed Visit</option>
                        <option value="3" ${data == 3 ? 'selected' : ''}>Reschedule Visit</option>
                    </select>
                `
            },
            {
                data: null,
                render: (row) => `<span class="px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-400 text-[10px] font-black rounded-lg uppercase tracking-wider">${escapeHtml(row.category?.category_name || 'General')}</span>`
            },
            {
                data: 'next_follow_up',
                render: (data, type, row) => `
                    <div class="flex items-center justify-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer" ${data === 'yes' ? 'checked' : ''} onchange="updateLeadStatus(${row.id}, null, this.checked ? 'yes' : 'no')">
                            <div class="toggle-bg peer"></div>
                        </label>
                    </div>
                `
            },
            {
                data: null,
                className: 'text-center',
                render: (row) => {
                    const projectAction = row.project && row.project.id
                        ? `<a href="../projects/view.php?id=${row.project.id}" class="p-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 rounded-xl transition-all" title="Open Project"><i class="fas fa-briefcase"></i></a>`
                        : `<button onclick="convertToProject(${row.id})" class="p-2 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/30 rounded-xl transition-all" title="Convert to Project"><i class="fas fa-check-double"></i></button>`;

                    return `
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="openSummary(${row.id})" class="p-2 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-xl transition-all" title="View Summary"><i class="fas fa-id-card"></i></button>
                            <a href="proposals/proposal_form.php?lead_id=${row.id}" class="p-2 text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-xl transition-all" title="Create Proposal"><i class="fas fa-file-invoice"></i></a>
                            ${projectAction}
                            <button onclick="openEditLeadModal(${row.id})" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-xl transition-all" title="Edit Lead"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteLead(${row.id})" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/20 rounded-xl transition-all" title="Delete Lead"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    `;
                }
            },
            {
                data: null,
                render: (row) => {
                    const fu = (row.follow_ups && row.follow_ups.length > 0) ? row.follow_ups[0] : null;
                    const safeName = safeJsString(row.client_name || '');
                    if (!fu) return `<button onclick="openFollowUpModal(${row.id}, '${safeName}')" class="px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500 text-[9px] font-black rounded-lg uppercase tracking-wider hover:bg-indigo-600 hover:text-white transition-all"><i class="fa fa-bell mr-1"></i> Reminder</button>`;
                    
                    const date = moment(fu.next_follow_up_date);
                    const isPast = date.isBefore(moment());
                    const colorClass = isPast ? 'bg-red-600' : 'bg-green-600';
                    
                    return `<button onclick="openFollowUpModal(${row.id}, '${safeName}')" class="px-2 py-1 ${colorClass} text-white text-[9px] font-black rounded-lg uppercase tracking-wider hover:opacity-80 transition-all">
                        <i class="fa ${isPast ? 'fa-exclamation-triangle' : 'fa-calendar-check'} mr-1"></i> ${date.format('DD/MM/YY HH:mm')}
                    </button>`;
                }
            },
            {
                data: null,
                render: (row) => {
                    const fu = (row.follow_ups && row.follow_ups.length > 0) ? row.follow_ups[0] : null;
                    if (!fu) return '--';
                    
                    const date = moment(fu.next_follow_up_date);
                    const now = moment();
                    if (date.isBefore(now)) {
                        const diff = moment.duration(now.diff(date));
                        return `<span class="text-red-500 font-bold text-[10px]">⚠️ ${diff.days() > 0 ? diff.days() + 'd ' : ''}${diff.hours()}h ago</span>`;
                    } else {
                        const diff = moment.duration(date.diff(now));
                        return `<span class="text-indigo-500 dark:text-indigo-400 font-bold text-[10px]">${diff.days() > 0 ? diff.days() + 'd ' : ''}${diff.hours()}h left</span>`;
                    }
                }
            }
        ],
        order: [[0, 'desc']],
        responsive: true,
        autoWidth: false,
        dom: 'rtp',
        pageLength: 50,
        rowCallback: function(row, data) {
            const fu = (data.follow_ups && data.follow_ups.length > 0) ? data.follow_ups[0] : null;
            if (fu) {
                const date = moment(fu.next_follow_up_date);
                if (date.isBefore(moment())) {
                    $(row).addClass('bg-red-50/30 dark:bg-red-950/10');
                    $(row).css('border-left', '4px solid #ef4444');
                } else if (date.diff(moment(), 'hours') <= 24) {
                    $(row).addClass('bg-amber-50/30 dark:bg-amber-950/10');
                    $(row).css('border-left', '4px solid #f59e0b');
                }
            }
        }
    });

    // Handle Filters
    $('#leadSearch').on('keyup', _.debounce(() => refreshLeads(), 300));
    $('#categoryFilter').on('change', () => refreshLeads());

    // Reminder toggle
    $(document).on('change', '#sendReminderSelect', function() {
        if ($(this).val() === 'yes') $('#reminderOptions').removeClass('hidden');
        else $('#reminderOptions').addClass('hidden');
    });

    // Handle Follow Up Submit
    $('#followUpForm').on('submit', function(e) {
        e.preventDefault();
        const id = $('#followUpLeadId').val();
        const data = {};
        $(this).serializeArray().forEach(item => data[item.name] = item.value);

        if ((data.send_reminder || 'no') !== 'yes') {
            data.remind_time = '';
            data.remind_type = '';
        } else {
            data.remind_type = normalizeRemindType(data.remind_type);
        }

        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            data: { action: 'save_followup', id: id, ...data },
            success: function(response) {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: 'Follow up scheduled.', timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                    closeFollowUpModal();
                    refreshLeads();
                } else {
                    Swal.fire({ icon:'error', title:'Error!', text:res.message, theme: getSwalTheme() });
                }
            }
        });
    });

    // Handle Lead Form Submit
    $(document).on('submit', '#leadForm', function(e) {
        e.preventDefault();
        const form = $(this);
        const data = {};
        form.serializeArray().forEach(item => data[item.name] = item.value);

        $.ajax({
            url: 'leads_handler.php',
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + window.apiToken },
            data: data,
            success: function(response) {
                const res = (typeof response === 'string') ? JSON.parse(response) : response;
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Success!', text: res.message, timer: 1500, showConfirmButton: false, theme: getSwalTheme() });
                    closeLeadModal();
                    refreshLeads();
                } else {
                    Swal.fire({ icon:'error', title:'Error!', text:res.message, theme: getSwalTheme() });
                }
            }
        });
    });
});
