<script>
        // Immediately remove 'msg' from URL to prevent 403 errors on sort/refresh
        (function() {
            const url = new URL(window.location);
            if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        })();

        // Handle Session Messages from PHP
        const sessionMsg = "<?php echo $sessionMsg ?? ''; ?>";
        const sheetSync = <?php echo isset($_SESSION['sheet_sync']) ? ($_SESSION['sheet_sync'] ? 'true' : 'false') : 'null'; ?>;
        <?php unset($_SESSION['sheet_sync']); ?>

        if (sessionMsg === 'updated') {
            let text = 'The order has been updated successfully.';
            if (sheetSync === true) text += ' (Drive Sheet Updated)';
            Swal.fire({ title: 'Updated!', text: text, icon: 'success', timer: 2000, showConfirmButton: false });
        } else if (sessionMsg === 'created') {
            let text = 'The new order has been created.';
            if (sheetSync === true) text += ' (Drive Sheet Updated)';
            Swal.fire({ title: 'Created!', text: text, icon: 'success', timer: 2000, showConfirmButton: false });
        }

        // Helper for Toasts
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        const indicator = document.getElementById('saving-indicator');
        
        function showSaving() { 
            if (indicator) indicator.classList.remove('hidden'); 
        }
        
        function hideSaving() { 
            if (indicator) indicator.classList.add('hidden'); 
        }

        function showSaved() {
            if (indicator) {
                indicator.innerHTML = '<i class="fas fa-check"></i> Saved!';
                setTimeout(() => { 
                    indicator.classList.add('hidden'); 
                    indicator.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Auto-saving...';
                }, 2000);
            }
        }

        function toggleDetails(id) {
            const details = document.getElementById('details-' + id);
            const chevron = document.getElementById('chevron-' + id);
            if (details) details.classList.toggle('hidden');
            if (chevron) chevron.classList.toggle('rotated');
        }

        function openSummary(id) {
            const isLead = window.location.pathname.indexOf('/leads/') !== -1;
            const typeParam = isLead ? '&type=lead' : '&type=task';
            const trimmedAppUrl = (window.appUrl || '').replace(/\/$/, '');
            const origin = trimmedAppUrl || window.location.origin || (window.location.protocol + '//' + window.location.host);
            const ajaxUrl = `${origin}/job_summary.php?id=${id}${typeParam}&ajax=1`;
            
            const modal = document.getElementById('summary-modal');
            const content = document.getElementById('summary-content');
            
            content.innerHTML = '<div class="flex flex-col items-center justify-center py-32"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Summary...</p></div>';
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');

            fetch(ajaxUrl)
                .then(r => r.text())
                .then(html => {
                    content.innerHTML = html;
                    content.style.opacity = '0';
                    setTimeout(() => {
                        content.style.transition = 'opacity 0.3s ease';
                        content.style.opacity = '1';
                    }, 10);
                })
                .catch(e => {
                    content.innerHTML = '<div class="p-12 text-center text-red-500 font-bold uppercase tracking-widest">Failed to load summary.</div>';
                });
        }

        function closeSummary() {
            document.getElementById('summary-modal')?.classList.add('hidden');
            document.getElementById('summary-content').innerHTML = '';
            document.body.classList.remove('overflow-hidden');
        }

        function openHelp() {
            document.getElementById('help-modal')?.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeHelp() {
            document.getElementById('help-modal')?.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function editClient(id) {
            if (!id) {
                Swal.fire('Info', 'No client ID associated with this job.', 'info');
                return;
            }
            showSaving();
            fetch(window.appUrl + `get_client_details.php?id=${id}`)
                .then(r => r.json())
                .then(data => {
                    hideSaving();
                    if (data.success) {
                        const c = data.client;
                        const setVal = (id, val) => {
                            const el = document.getElementById(id);
                            if (el) el.value = val || '';
                        };

                        setVal('client-id-input', c.id);
                        setVal('client-name-input', c.name);
                        setVal('client-email-input', c.email);
                        setVal('client-invoice-email-input', c.invoice_email);
                        setVal('client-mobile-input', c.mobile);
                        setVal('client-address-input', c.address);
                        setVal('client-spreadsheet-input', c.spreadsheet_id);

                        document.getElementById('client-modal')?.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
        }

        function closeClientModal() {
            document.getElementById('client-modal')?.classList.add('hidden');
            const form = document.getElementById('client-details-form');
            if (form) form.reset();
            document.body.classList.remove('overflow-hidden');
        }

        function syncNow() {
            const clientId = document.getElementById('client-id-input').value;
            if (!clientId) return;

            Swal.fire({
                title: 'Sync to Google Sheet?',
                text: "Replace Google Sheet data with current database records?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                confirmButtonText: 'Yes, sync now'
            }).then((result) => {
                if (result.isConfirmed) {
                    showSaving();
                    fetch(window.appUrl + 'tracker_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'full_sync', id: clientId })
                    })
                    .then(r => r.json())
                    .then(data => {
                        hideSaving();
                        if (data.success) {
                            Swal.fire('Synced!', data.message, 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
                }
            });
        }

        document.getElementById('client-details-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const clientId = formData.get('id');
            showSaving();
            fetch(window.appUrl + 'save_client_details.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                hideSaving();
                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Client Updated' });
                    closeClientModal();
                    setTimeout(() => {
                        window.location.href = window.appUrl + `index.php?client_filter=${clientId}`;
                    }, 1000);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
        });

        function setRowColor(id, color) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.style.backgroundColor = color;
            updateCell(id, 'rowColor', color);
        }

        function updateCell(id, field, value) {
            showSaving();
            fetch(window.appUrl + 'tracker_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'update', id: id, field: field, value: value })
            })
            .then(r => r.json()).then(d => { 
                if(d.success) {
                    showSaved();
                    if (d.sheet_sync) Toast.fire({ icon: 'success', title: 'Drive Sheet Updated' });
                    
                    if (field === 'assignedTo') {
                        const containers = document.querySelectorAll(`[id^="wa-btn-container-${id}"], [id^="wa-btn-container-mobile-${id}"]`);
                        containers.forEach(container => {
                            if (value.trim() !== "") container.classList.remove('hidden');
                            else container.classList.add('hidden');
                        });
                    }

                    if (field === 'status') {
                        const lowVal = value.toLowerCase();
                        if (lowVal === 'completed' || lowVal === 'closed' || lowVal === 'cancelled') {
                            location.reload();
                        }
                    }
                } else {
                    Swal.fire('Error', d.message, 'error');
                }
            })
            .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
        }

        function updateBadgeStyle(select, type) {
            const val = select.value.toLowerCase();
            const parent = select.parentElement;
            if (type === 'priority') parent.className = 'px-1 py-2 text-center transition-all ' + getPriorityStyle(val);
            else if (type === 'status') parent.className = 'px-1 py-2 text-center transition-all ' + getStatusStyle(val);
            else if (type === 'yesno') parent.className = 'inline-block px-2 py-0.5 rounded-full border text-[9px] font-bold uppercase transition-all ' + getYesNoStyle(val);
        }

        function getYesNoStyle(v) { 
            const val = v.toLowerCase();
            if (val === 'paid') return 'bg-blue-600 text-white border-blue-700';
            if (val === 'yes') return 'bg-green-600 text-white border-green-700';
            if (val === 'drafted') return 'bg-orange-400 text-white border-orange-500';
            if (val === 'not required') return 'bg-gray-400 text-white border-gray-500';
            return 'bg-gray-100 text-gray-400 border-gray-200';
        }
        
        function getPriorityStyle(p) {
            switch(p.toLowerCase()){
                case 'emergency': return 'bg-red-900 text-white border-red-950 animate-pulse font-bold';
                case 'urgent': return 'bg-red-600 text-white border-red-700';
                case 'high': return 'bg-orange-500 text-white border-orange-600';
                case 'medium': return 'bg-teal-600 text-white border-teal-700';
                case 'low': return 'bg-green-600 text-white border-green-700';
                default: return 'bg-gray-600 text-white border-gray-700';
            }
        }
        
        function getStatusStyle(s) {
            switch(s.toLowerCase()){
                case 'cancelled': return 'bg-[#4e342e] text-white border-[#3e2723]';
                case 'closed': return 'bg-gray-600 text-white border-gray-700';
                case 'completed': return 'bg-green-600 text-white border-green-700';
                case 'pending': return 'bg-[#ff0000] text-white font-bold border-red-700';
                case 'in progress': return 'bg-blue-600 text-white border-blue-700';
                case 'on hold': return 'bg-orange-600 text-white border-orange-700';
                case 'open': case 'incomplete': return 'bg-purple-600 text-white border-purple-700';
                default: return 'bg-gray-600 text-white border-gray-700';
            }
        }

        function deleteOrder(id) {
            Swal.fire({
                title: 'Delete?', text: "You can't undo this!", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#4f46e5', cancelButtonColor: '#ef4444'
            }).then((result) => {
                if (result.isConfirmed) {
                    showSaving();
                    fetch(window.appUrl + 'tracker_handler.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'delete', id: id }) })
                    .then(r => r.json()).then(d => { hideSaving(); if(d.success) { location.reload(); } else Swal.fire('Error', d.message, 'error'); })
                    .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
                }
            });
        }

        function showHistory(id) {
            document.getElementById('history-modal')?.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            const content = document.getElementById('history-content');
            if (content) {
                content.innerHTML = '<div class="flex justify-center p-12"><i class="fas fa-circle-notch fa-spin fa-2x text-indigo-500"></i></div>';
                fetch(window.appUrl + `tracker_handler.php?action=get_history&id=${id}`).then(r => r.json()).then(data => {
                    if (data.success && data.history.length > 0) {
                        let h = '<div class="overflow-x-auto"><table class="w-full text-sm text-left"><thead><tr class="table-header-row"><th class="px-4 py-3">Field</th><th class="px-4 py-3">Old Value</th><th class="px-4 py-3">New Value</th><th class="px-4 py-3">By</th><th class="px-4 py-3 text-center">Date</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-slate-800">';
                        data.history.forEach(row => { 
                            h += `<tr class="table-row-hover text-[11px]">
                                <td class="px-4 py-3 font-black text-gray-900 dark:text-indigo-400 uppercase tracking-tighter">${row.field_name}</td>
                                <td class="px-4 py-3 text-gray-400 dark:text-gray-500 line-through italic">${row.old_value||'NULL'}</td>
                                <td class="px-4 py-3 text-indigo-600 dark:text-indigo-300 font-black">${row.new_value||'NULL'}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 font-bold">@ ${row.user_name||'System'}</td>
                                <td class="px-4 py-3 text-gray-400 dark:text-gray-500 text-[10px] whitespace-nowrap text-center">${row.changed_at}</td>
                            </tr>`; 
                        });
                        content.innerHTML = h + '</tbody></table></div>';
                    } else content.innerHTML = '<div class="text-center p-20 text-gray-400 font-black uppercase tracking-widest text-xs">No change history recorded for this job</div>';
                }).catch(e => { content.innerHTML = '<div class="p-12 text-center text-red-500">Error loading history</div>'; });
            }
        }

        function closeHistory() { 
            document.getElementById('history-modal')?.classList.add('hidden'); 
            document.body.classList.remove('overflow-hidden');
        }

        function showFiles(taskId, po) {
            fetch(window.appUrl + `tracker_handler.php?action=get_attachments&po=${po}`)
                .then(r => r.json())
                .then(data => {
                    let html = '<div class="text-left space-y-3 p-2">';
                    html += `
                        <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-900/20 border-2 border-dashed border-indigo-200 dark:border-indigo-800 rounded-2xl text-center">
                            <label for="file-upload-${taskId}" class="cursor-pointer">
                                <i class="fas fa-cloud-upload-alt text-3xl text-indigo-500 mb-2"></i>
                                <p class="text-xs font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-400">Upload New Images</p>
                                <p class="text-[10px] text-gray-500 mt-1 italic">Click to browse or drag and drop</p>
                                <input type="file" id="file-upload-${taskId}" class="hidden" multiple accept="image/*" onchange="handleFileUpload(${taskId}, '${po}', this)">
                            </label>
                            <div id="upload-progress-${taskId}" class="hidden mt-3 h-1.5 w-full bg-indigo-100 dark:bg-indigo-950 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-600 transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>`;

                    if (data.success && data.files.length > 0) {
                        data.files.forEach(f => {
                            const link = f.FilePath || f.link || '#';
                            const name = f.DocumentName || f.name || 'Unknown File';
                            const date = f.UploadedDate || f.date || '';
                            html += `<a href="${link}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all group shadow-sm">
                                <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-900/50 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                    <i class="fas fa-file-pdf text-lg"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-black text-gray-900 dark:text-white truncate text-sm italic tracking-tight">${name}</div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-black uppercase tracking-widest">${date} (Drive)</div>
                                </div>
                                <i class="fas fa-external-link-alt text-gray-300 dark:text-gray-700 group-hover:text-indigo-400 transition-colors"></i>
                            </a>`;
                        });
                    } else if (!data.success) {
                        html += `<div class="p-8 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">Error loading files: ${data.message}</div>`;
                    } else {
                        html += `<div class="p-8 text-center text-gray-400 font-bold uppercase tracking-widest text-xs">No attachments found for this PO</div>`;
                    }
                    html += '</div>';

                    Swal.fire({ 
                        title: `<span class="heading-brand text-xl">📂 Attachments - PO ${po}</span>`,
                        html: html, 
                        showCloseButton: true, 
                        showConfirmButton: false, 
                        width: '500px',
                        background: 'var(--card-bg)',
                        color: 'var(--text-main)',
                        customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' }
                    });
                })
                .catch(e => { Swal.fire('Error', 'Failed to load attachments', 'error'); });
        }

        async function handleFileUpload(taskId, po, input) {
            if (!input.files || input.files.length === 0) return;
            const progressBar = document.getElementById(`upload-progress-${taskId}`);
            const progressFill = progressBar.querySelector('div');
            progressBar.classList.remove('hidden');
            progressFill.style.width = '0%';
            
            const formData = new FormData();
            formData.append('action', 'worker_upload');
            formData.append('id', taskId);

            const options = { maxSizeMB: 0.8, maxWidthOrHeight: 1920, useWebWorker: true };

            try {
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    if (file.type.startsWith('image/')) {
                        const compressedFile = await imageCompression(file, options);
                        formData.append('images[]', compressedFile, file.name);
                    } else {
                        formData.append('images[]', file);
                    }
                    progressFill.style.width = ((i + 1) / input.files.length * 30) + '%';
                }

                const response = await fetch(window.appUrl + 'tracker_handler.php', { method: 'POST', body: formData });
                const data = await response.json();

                if (data.success) {
                    progressFill.style.width = '100%';
                    Toast.fire({ icon: 'success', title: data.message });
                    setTimeout(() => showFiles(taskId, po), 1000);
                } else {
                    Swal.fire('Upload Failed', data.message, 'error');
                    progressBar.classList.add('hidden');
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'Compression or Upload failed', 'error');
                progressBar.classList.add('hidden');
            }
        }

        function exportCSV() {
            const currentParams = new URLSearchParams(window.location.search);
            const exportParams = new URLSearchParams();
            exportParams.set('action', 'export');
            const allowedFilters = ['search', 'property_filter', 'status_filter', 'priority_filter', 'invoice_filter', 'client_filter', 'show_closed', 'date_filter', 'sort', 'order'];
            allowedFilters.forEach(key => { if (currentParams.has(key)) exportParams.set(key, currentParams.get(key)); });
            window.location.href = window.appUrl + 'tracker_handler.php?' + exportParams.toString();
        }

        function filterExtractedPdfData(description) {
            if (!description) return '';
            const regex = /(\r?\n\r?\n--- Extracted Content:.*?---\r?\n[\s\S]*?)(?=\r?\n\r?\n--- Extracted Content:|$)/g;
            let match, extractedContent = '';
            while ((match = regex.exec(description)) !== null) {
                extractedContent += match[1].trim() + '\n\n'; 
            }
            return extractedContent.trim();
        }

        async function xeroInvoiceFlow(config) {
            const { previewUrl, previewBody, createUrl, getCreateBody, title, isBulk } = config;
            showSaving();
            try {
                const response = await fetch(previewUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: previewBody });
                const data = await response.json();
                hideSaving();
                if (!data.success) { Swal.fire('Error', data.message, 'error'); return; }

                const inv = data.invoice;
                let previewHtml = '';
                if (isBulk) {
                    const itemsHtml = inv.items.map((item, idx) => `<div class="mb-4 pb-4 border-b border-indigo-100 dark:border-indigo-900/30 last:border-0 last:pb-0"><p class="font-black text-indigo-600 dark:text-indigo-400 mb-1 uppercase text-[10px] tracking-widest">Job #${idx+1}</p><pre class="whitespace-pre-wrap font-sans text-gray-700 dark:text-gray-300 text-[11px] leading-relaxed italic">${item.description}</pre></div>`).join('');
                    previewHtml = `<div class="text-left text-xs space-y-4 max-h-[60vh] overflow-y-auto p-2"><div class="bg-gray-50 dark:bg-slate-900 p-4 rounded-2xl border border-gray-100 dark:border-slate-800"><p class="mb-1"><strong class="text-[10px] font-black uppercase text-gray-400">Client:</strong> <span class="font-bold text-gray-900 dark:text-white">${inv.contact}</span></p><p class="mb-1"><strong class="text-[10px] font-black uppercase text-gray-400">Total Items:</strong> <span class="font-black text-indigo-600 dark:text-indigo-400">${inv.items.length}</span></p></div><div><label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Group Reference</label><input type="text" id="bulk-custom-ref" class="w-full p-4 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-black dark:text-white focus:ring-2 focus:ring-indigo-500" value="${inv.reference}"></div><div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-2xl border border-indigo-100 dark:border-indigo-900/50">${itemsHtml}</div></div>`;
                } else {
                    const filteredDesc = filterExtractedPdfData(inv.description);
                    previewHtml = `<div class="text-left text-xs space-y-4 max-h-[60vh] overflow-y-auto p-2"><div class="bg-gray-50 dark:bg-slate-900 p-4 rounded-2xl border border-gray-100 dark:border-slate-800"><p class="mb-1"><strong class="text-[10px] font-black uppercase text-gray-400">Billing Contact:</strong> <span class="font-bold text-gray-900 dark:text-white">${inv.contact}</span></p><p class="mb-1"><strong class="text-[10px] font-black uppercase text-gray-400">Email:</strong> <span class="font-medium text-indigo-600 dark:text-indigo-400">${inv.email}</span></p><p><strong class="text-[10px] font-black uppercase text-gray-400">Reference:</strong> <span class="font-mono font-bold text-gray-700 dark:text-gray-300">${inv.reference}</span></p></div><div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-2xl border border-indigo-100 dark:border-indigo-900/50"><p class="font-black text-indigo-700 dark:text-indigo-400 mb-2 uppercase tracking-widest text-[10px]">Description Preview</p><pre class="whitespace-pre-wrap font-sans text-gray-700 dark:text-gray-300 leading-relaxed text-[11px] italic">${filteredDesc || 'No specific PDF data extracted.'}</pre></div><p class="text-[10px] text-amber-600 dark:text-amber-400 font-black uppercase text-center bg-amber-50 dark:bg-amber-950/20 py-2 rounded-lg">Pricing will be set manually in Xero</p></div>`;
                }

                const { isConfirmed, value: customRef } = await Swal.fire({
                    title: title, html: previewHtml, background: 'var(--card-bg)', color: 'var(--text-main)', showCancelButton: true, confirmButtonColor: '#10b981', confirmButtonText: isBulk ? 'Generate Bulk Draft' : 'Create Draft', customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' },
                    preConfirm: () => { if (isBulk) { const refInput = document.getElementById('bulk-custom-ref'); return refInput ? refInput.value : ''; } return ''; }
                });

                if (isConfirmed) {
                    showSaving();
                    const createResponse = await fetch(createUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: getCreateBody(customRef) });
                    const finalData = await createResponse.json();
                    hideSaving();
                    if (finalData.success) { Swal.fire('Success!', finalData.message + (finalData.invoice_no ? ' Invoice No: ' + finalData.invoice_no : ''), 'success'); setTimeout(() => location.reload(), 2000); }
                    else Swal.fire('Error', finalData.message, 'error');
                }
            } catch (e) { hideSaving(); console.error(e); Swal.fire('Error', 'Xero operation failed', 'error'); }
        }

        function generateXeroInvoice(id) {
            xeroInvoiceFlow({ previewUrl: window.appUrl + '../xero/generate_invoice.php', previewBody: new URLSearchParams({ id: id, preview: 1 }), createUrl: window.appUrl + '../xero/generate_invoice.php', getCreateBody: () => new URLSearchParams({ id: id }), title: `<span class="heading-brand text-xl">📄 Draft Invoice</span>`, isBulk: false });
        }

        function generateBulkXeroInvoice() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length === 0) return;
            const items = Array.from(checked).map(cb => ({ id: cb.value, status: cb.getAttribute('data-status'), client: cb.getAttribute('data-client') }));
            if (items.some(item => !['completed', 'closed'].includes(item.status))) { Swal.fire('Error', 'Only "Completed" or "Closed" jobs can be added to a bulk invoice.', 'error'); return; }
            const firstClient = items[0].client;
            if (!firstClient || items.some(item => item.client !== firstClient)) { Swal.fire('Error', 'All selected jobs must belong to the same Billing Client.', 'error'); return; }
            const ids = items.map(item => item.id);
            const previewBody = new URLSearchParams(); ids.forEach(id => previewBody.append('ids[]', id)); previewBody.append('preview', 1);
            xeroInvoiceFlow({ previewUrl: window.appUrl + '../xero/generate_bulk_invoice.php', previewBody: previewBody, createUrl: window.appUrl + '../xero/generate_bulk_invoice.php', getCreateBody: (customRef) => { const body = new URLSearchParams(); ids.forEach(id => body.append('ids[]', id)); body.append('custom_reference', customRef); return body; }, title: `<span class="heading-brand text-xl">📦 Bulk Group Invoice</span>`, isBulk: true });
        }

        async function resolveWhatsAppJoinNumber() {
            const fallbackNumber = <?php echo json_encode($GLOBALS['whatsapp_join_number'] ?? $_ENV['WHATSAPP_JOIN_NUMBER'] ?? ''); ?>;

            if (!window.laravelApiUrl || !window.apiToken) {
                return fallbackNumber;
            }

            try {
                const response = await fetch(`${window.laravelApiUrl}/api/settings`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${window.apiToken}`
                    }
                });

                const payload = await response.json();
                if (!response.ok || !payload || payload.success !== true || !payload.data || typeof payload.data !== 'object') {
                    return fallbackNumber;
                }

                for (const groupItems of Object.values(payload.data)) {
                    if (!Array.isArray(groupItems)) continue;
                    const match = groupItems.find(item => item && item.key === 'whatsapp_join_number');
                    if (match && typeof match.value === 'string' && match.value.trim() !== '') {
                        return match.value;
                    }
                }
            } catch (error) {
                console.error('Failed to resolve tenant WhatsApp join number.', error);
            }

            return fallbackNumber;
        }

        window.showJoinQR = async function() {
            const rawJoinNumber = await resolveWhatsAppJoinNumber();
            const joinNumber = String(rawJoinNumber || '').replace(/[^\d+]/g, '');
            const waNumber = joinNumber.replace(/^\+/, '');

            if (!joinNumber) {
                Swal.fire('Missing Setting', 'WhatsApp join number is not configured.', 'warning');
                return;
            }
            if (!/^\d{7,15}$/.test(waNumber)) {
                Swal.fire('Invalid Setting', 'WhatsApp join number is not in a valid format. Use an international number with country code.', 'warning');
                return;
            }

            const joinUrl = `https://wa.me/${waNumber}?text=${encodeURIComponent('Start Job Tracker')}`;
            const displayNumber = joinNumber.startsWith('+') ? joinNumber : `+${waNumber}`;

            Swal.fire({
                title: `<span class="heading-brand text-xl">📱 WhatsApp Sync</span>`,
                html: `
                    <div class="flex flex-col items-center p-4">
                        <div class="bg-white dark:bg-white/90 p-6 rounded-3xl mb-6 shadow-hard">
                            <div id="whatsapp-join-qr" class="w-48 h-48 flex items-center justify-center"></div>
                        </div>
                        <p class="text-sm font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest mb-2">Scan to Initialize</p>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Configured Number</p>
                        <p class="text-sm font-mono font-bold text-gray-900 dark:text-white mb-4">${displayNumber}</p>
                        <a href="${joinUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-4 py-2 rounded-2xl bg-emerald-600 text-white text-[10px] font-black uppercase tracking-widest hover:bg-emerald-700 transition-all mb-4">Open WhatsApp Link</a>
                        <p class="text-xs text-gray-500 dark:text-gray-400 italic text-center leading-relaxed">Send the pre-filled message to receive your daily automated job assignments.</p>
                    </div>
                `,
                background: 'var(--card-bg)',
                color: 'var(--text-main)',
                showCloseButton: true,
                showConfirmButton: false,
                width: '400px',
                customClass: { popup: 'rounded-3xl border border-gray-100 dark:border-slate-800 shadow-2xl' },
                didOpen: () => {
                    const qrContainer = document.getElementById('whatsapp-join-qr');
                    if (qrContainer && typeof QRCode !== 'undefined') {
                        qrContainer.innerHTML = '';
                        new QRCode(qrContainer, {
                            text: joinUrl,
                            width: 192,
                            height: 192,
                            colorDark: '#111827',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    }
                }
            });
        }

        function sendJobWhatsApp(id) {
            Swal.fire({ title: 'Send Job via WhatsApp?', text: "This will send the details to the assigned user's WhatsApp.", icon: 'question', showCancelButton: true, confirmButtonColor: '#25D366', cancelButtonColor: '#d33', confirmButtonText: 'Yes, send it!' }).then((result) => {
                if (result.isConfirmed) {
                    showSaving();
                    fetch(`${window.laravelApiUrl}/api/tasks/${id}/send-whatsapp`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + (window.apiToken || ''),
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.json()).then(data => { hideSaving(); if (data.success) { Swal.fire('Sent!', data.message, 'success'); location.reload(); } else Swal.fire('Error', data.message || 'Failed to send WhatsApp message.', 'error'); })
                    .catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
                }
            });
        }

        function toggleSelectAll(source) {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = source.checked);
            updateBulkState();
        }

        function updateBulkState() {
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            const bar = document.getElementById('bulk-action-bar');
            const countSpan = document.getElementById('selected-count');
            if (checkedCount > 0) { if (bar) bar.classList.remove('hidden'); if (countSpan) countSpan.innerText = checkedCount; }
            else if (bar) bar.classList.add('hidden');
        }

        function toggleWeatherDependency(id, element) {
            showSaving();
            let isCurrentlyDependent = element.getAttribute('data-weather-dependent') === '1';
            if (!element.hasAttribute('data-weather-dependent')) isCurrentlyDependent = element.classList.contains('text-yellow-500');
            const newStatus = isCurrentlyDependent ? 0 : 1;
            fetch(window.appUrl + 'update_weather_dependency.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ id: id, is_weather_dependent: newStatus }) })
            .then(r => r.json()).then(data => {
                hideSaving();
                if (data.success) {
                    element.setAttribute('data-weather-dependent', newStatus);
                    if (newStatus === 1) { element.classList.remove('text-gray-400', 'hover:text-gray-600'); element.classList.add('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100'); element.setAttribute('title', 'Weather Dependent (Click to make not dependent)'); }
                    else { element.classList.remove('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100'); element.classList.add('text-gray-400', 'hover:text-gray-600'); element.setAttribute('title', 'Not Weather Dependent (Click to make dependent)'); }
                    Toast.fire({ icon: 'success', title: data.message });
                } else Swal.fire('Error', data.message, 'error');
            }).catch(e => { hideSaving(); Swal.fire('Error', 'Network error', 'error'); });
        }

        function openWeatherForecastModal(taskId) {
            const modal = document.getElementById('weather-forecast-modal');
            const content = document.getElementById('weather-forecast-content');
            if (!modal || !content) return;
            content.innerHTML = '<div class="flex flex-col items-center justify-center py-32"><div class="w-12 h-12 border-4 border-indigo-600/20 border-t-indigo-600 rounded-full animate-spin mb-4"></div><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Opening Forecast Tool...</p></div>';
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            fetch(window.appUrl + 'partials/weather_forecast_content.php?task_id=' + taskId).then(r => r.text()).then(html => {
                content.innerHTML = html;
                content.querySelectorAll('script').forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });
            }).catch(e => { content.innerHTML = '<div class="p-12 text-center text-red-500 font-bold">Failed to load forecast tool.</div>'; });
        }

        function closeWeatherForecastModal() {
            document.getElementById('weather-forecast-modal')?.classList.add('hidden');
            if (document.getElementById('weather-forecast-content')) document.getElementById('weather-forecast-content').innerHTML = '';
            document.body.classList.remove('overflow-hidden');
        }

        window.addEventListener('message', function(event) {
            if (event.origin !== window.location.origin) return;
            if (event.data && event.data.type === 'weather_date_assigned') {
                const { taskId, date: assignedDate } = event.data;
                const taskRow = document.querySelector(`tr[data-id="${taskId}"]`);
                if (taskRow) {
                    const nextVisitInput = taskRow.querySelector('input[onchange*="nextVisit"]');
                    if (nextVisitInput) {
                        nextVisitInput.value = assignedDate;
                        updateCell(taskId, 'nextVisit', assignedDate); 
                        nextVisitInput.classList.add('bg-green-100', 'text-green-800', 'font-bold');
                        setTimeout(() => nextVisitInput.classList.remove('bg-green-100', 'text-green-800', 'font-bold'), 5000);
                    }
                    const weatherIconBtn = taskRow.querySelector('.weather-icon-btn');
                    if (weatherIconBtn) { weatherIconBtn.classList.remove('text-gray-400', 'hover:text-gray-600'); weatherIconBtn.classList.add('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100'); weatherIconBtn.setAttribute('title', 'Weather Dependent (Click to open forecast)'); }
                }
                closeWeatherForecastModal();
                Toast.fire({ icon: 'success', title: 'Task date updated successfully!' });
            } else if (event.data && event.data.type === 'weather_dependent_toggled') {
                const { taskId, isDependent } = event.data;
                const taskRow = document.querySelector(`tr[data-id="${taskId}"]`);
                if (taskRow) {
                    const btn = taskRow.querySelector('.weather-icon-btn');
                    if (btn) {
                        if (isDependent) { btn.classList.remove('text-gray-400', 'hover:text-gray-600'); btn.classList.add('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100'); btn.setAttribute('title', 'Weather Dependent (Click to open forecast)'); }
                        else { btn.classList.remove('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100'); btn.classList.add('text-gray-400', 'hover:text-gray-600'); btn.setAttribute('title', 'Not Weather Dependent (Click to open forecast)'); }
                    }
                }
            } else if (event.data === 'close_weather_modal') closeWeatherForecastModal();
        });

        $(document).ready(function() {
            $(".datepicker").flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true,
                onClose: function(selectedDates, dateStr, instance) {
                    instance.element.dispatchEvent(new Event('change'));
                }
            });
        });
    </script>
