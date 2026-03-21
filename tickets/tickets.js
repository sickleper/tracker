// --- Modal Management ---
function closeModal(modalId) {
    $('#' + modalId).addClass('hidden');
    $('body').removeClass('overflow-hidden');
}

function filterTickets(status) {
    // Update tab classes
    const activeClass = 'border-indigo-500 text-indigo-600 dark:text-indigo-400';
    const inactiveClass = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-slate-400 dark:hover:text-slate-200';
    
    $('#tab-active, #tab-closed, #tab-archived').removeClass(activeClass + ' border-indigo-500 text-indigo-600 dark:text-indigo-400').addClass(inactiveClass);
    $(`#tab-${status}`).addClass(activeClass).removeClass(inactiveClass);
    
    // Show loading
    $('#ticket-table-container').html('<div class="flex justify-center p-20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500"></i></div>');
    
    // Fetch filtered view
    $.get('show_ticket.php', { status_filter: status }, function(html) {
        $('#ticket-table-container').html(html);
    }).fail(function() {
        $('#ticket-table-container').html('<div class="p-12 text-center"><i class="fas fa-wifi text-red-500 text-3xl mb-4"></i><p class="text-red-500 font-bold uppercase tracking-widest text-xs">Failed to load tickets</p></div>');
        Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error!', text:'Could not load the selected ticket view.' });
    });
}

function openCreateTicketModal() {
    $('#createTicketModal').removeClass('hidden');
    $('body').addClass('overflow-hidden');
}

function initTicketsTable() {
    if ($.fn.DataTable.isDataTable('#ticketsDataTable')) {
        $('#ticketsDataTable').DataTable().destroy();
    }
    $('#ticketsDataTable').DataTable({
        "order": [[ 6, "desc" ]], 
        "pageLength": 25,
        "responsive": true,
        "autoWidth": false,
        "dom": 'rtp',
        "columnDefs": [
            { "targets": [1, 8], "orderable": false }
        ]
    });
}

// Function to determine SweetAlert2 config based on dark mode
function getSwalConfig() {
    const isDark = $('html').hasClass('dark');
    return {
        background: isDark ? '#0f172a' : '#ffffff',
        color: isDark ? '#f8fafc' : '#1e293b',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#ef4444'
    };
}

$(document).ready(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var ticket_Id = urlParams.get('ticketid');

    if (ticket_Id) {
        loadTicketDetails(ticket_Id);
    }

    function loadTicketDetails(ticket_Id) {
        $.ajax({
            url: 'fetch_ticket_details.php',
            method: 'POST',
            data: {ticketId: ticket_Id},
            success: function(response) {
                $('#ticketModalLabel').html('🎫 Ticket #' + ticket_Id);
                if (response.success && response.data) {
                    $('#ticketModal .modal-body').html(displayTodoDetails(response.data, response.currentUserId));
                    const replyForm = document.getElementById('replyForm');
                    if (replyForm) {
                        replyForm.addEventListener('submit', handleReplySubmit);
                    }
                } else {
                    $('#ticketModal .modal-body').html('<p class="text-red-500 font-bold p-6 text-center">' + (response.error || 'Could not load ticket details.') + '</p>');
                }
                $('#ticketModal').removeClass('hidden');
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error!', text:'Error fetching ticket details' });
            }
        });
    }

    // Handle Create Ticket Form Submission
    $('#createTicketForm').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalBtnHtml = submitBtn.html();
        
        // Basic validation
        const title = form.find('input[name="title"]').val().trim();
        const message = form.find('textarea[name="message"]').val().trim();
        
        if (!title || !message) {
            Swal.fire({ ...getSwalConfig(), icon:'warning', title:'Required!', text:'Please fill in both subject and description.' });
            return;
        }

        // UI Feedback
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');

        const formData = {
            title: title,
            message: message,
            priority: form.find('input[name="priority"]:checked').val(),
            category_id: form.find('select[name="category_id"]').val(),
            label_id: form.find('select[name="label_id"]').val()
        };

        $.ajax({
            url: 'create_ticket.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        ...getSwalConfig(),
                        title: 'Success!',
                        text: 'Your ticket has been created.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // Reload to see the new ticket in the list
                    });
                } else {
                    Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error!', text:response.error || 'Failed to create ticket.' });
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon:'error', title:'Error!', text:'An error occurred during submission.' });
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
            }
        });
    });
});

function handleReplySubmit(event) {
    event.preventDefault();
    const form = event.target;
    const ticketId = form.dataset.ticketId;
    const messageTextarea = form.querySelector('#replyMessage');
    const message = messageTextarea.value.trim();

    if (!message) return;

    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonHtml = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Posting...';

    $.ajax({
        url: 'add_ticket_reply.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ ticketId: ticketId, message: message }),
        success: function(response) {
            if (response.status === "success") {
                Swal.fire({ ...getSwalConfig(), title: 'Success!', text: 'Reply posted.', icon: 'success', timer: 1500, showConfirmButton: false });
                addReplyToUI(response.reply);
                messageTextarea.value = '';
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: response.message || 'Failed to post reply.' });
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to post reply.';
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: msg });
        },
        complete: function() {
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHtml;
        }
    });
}

function addReplyToUI(reply) {
    const repliesSection = document.querySelector('.replies-section');
    const noReplies = repliesSection.querySelector('.italic');
    if (noReplies) repliesSection.innerHTML = '<h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6 flex items-center gap-2"><i class="fas fa-comments text-indigo-500"></i> Conversation History</h4>';

    const newReplyHtml = `
        <div class="mb-4 p-4 rounded-2xl border bg-indigo-50 dark:bg-indigo-900/30 border-indigo-100 dark:border-indigo-900/50 ml-12 animate-fade-in transition-all">
            <div class="flex justify-between items-center mb-2">
                <strong class="text-sm text-gray-900 dark:text-white">${escapeHtml(reply.user_name || 'You')}</strong>
                <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">${reply.updated_at}</span>
            </div>
            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">${escapeHtml(reply.message)}</div>
        </div>
    `;
    repliesSection.insertAdjacentHTML('beforeend', newReplyHtml);
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return unsafe;
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

$(document).on('click', '.ticketbtn', function() {
    const ticketId = $(this).attr('id');
    $('#ticketModalLabel').html('<i class="fas fa-ticket-alt text-indigo-400"></i> Ticket Details: #' + ticketId);
    $('#ticketModal .modal-body').html('<div class="flex justify-center p-20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500"></i></div>');
    $('#ticketModal').removeClass('hidden');
    $('body').addClass('overflow-hidden');

    $.ajax({
        url: 'fetch_ticket_details.php',
        method: 'POST',
        data: {ticketId: ticketId},
        success: function(response) {
            if (response.success && response.data) {
                $('#ticketModal .modal-body').html(displayTodoDetails(response.data, response.currentUserId, response.allUsers));
                document.getElementById('replyForm').addEventListener('submit', handleReplySubmit);
            } else {
                $('#ticketModal .modal-body').html('<div class="p-12 text-center"><i class="fas fa-exclamation-triangle text-red-500 text-3xl mb-4"></i><p class="text-red-500 font-bold">' + (response.error || 'Could not load ticket details.') + '</p></div>');
            }
        },
        error: function() {
            $('#ticketModal .modal-body').html('<div class="p-12 text-center"><i class="fas fa-wifi text-red-500 text-3xl mb-4"></i><p class="text-red-500 font-bold">Network error while fetching ticket details</p></div>');
        }
    });
});

function deleteTicket(ticketId) {
    Swal.fire({
        ...getSwalConfig(),
        title: 'Delete Ticket?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'delete_ticket.php',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ ticketId: ticketId }),
                success: function(response) {
                    if (response.status === "success") {
                        Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Deleted!', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                    } else {
                        Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: response.message || 'Failed to delete.' });
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to delete ticket.';
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: msg });
                }
            });
        }
    });
}

function updateAssignment(ticketId, userId) {
    Swal.fire({ ...getSwalConfig(), title: 'Syncing...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

    $.ajax({
        url: 'update_ticket.php', 
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ ticketId: ticketId, assigned_to: userId }),
        success: function(response) {
            if (response.success) {
                Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Assigned', timer: 1000, showConfirmButton: false });
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: response.error || 'Failed to update.' });
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to update assignment.';
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: msg });
        }
    });
}

function quickUpdateStatus(ticketId, newStatus) {
    Swal.fire({ ...getSwalConfig(), title: 'Updating...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

    $.ajax({
        url: 'update_ticket.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ ticketId: ticketId, status: newStatus }),
        success: function(response) {
            if (response.success) {
                Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Success', text: 'Status is now ' + newStatus, timer: 1500, showConfirmButton: false }).then(() => location.reload());
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: response.error || 'Failed to update.' });
            }
        },
        error: function(xhr) {
            const msg = xhr.responseJSON ? xhr.responseJSON.error : 'Failed to update ticket status.';
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: msg });
        }
    });
}

function displayTodoDetails(todo, loggedInUserId, allUsers = []) {
    currentUserId = loggedInUserId;
    const { id, subject, message, status, priority, reply, requester, assigned_to } = todo;
    const replies = Array.isArray(reply) ? reply : [];

    const isActive = ['open', 'pending', 'resolved'].includes(status.toLowerCase());
    const isClosed = status.toLowerCase() === 'closed';
    const isArchived = status.toLowerCase() === 'archived';

    let actionButtons = '';
    if (isActive) {
        actionButtons = `
            <button onclick="quickUpdateStatus(${id}, 'closed')" class="px-4 py-2 bg-red-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-700 transition-all shadow-md">
                <i class="fas fa-check-circle mr-1"></i> Close Ticket
            </button>
            <button onclick="quickUpdateStatus(${id}, 'archived')" class="px-4 py-2 bg-gray-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-700 transition-all shadow-md">
                <i class="fas fa-archive mr-1"></i> Archive
            </button>
        `;
    } else if (isClosed || isArchived) {
        actionButtons = `
            <button onclick="quickUpdateStatus(${id}, 'open')" class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-700 transition-all shadow-md">
                <i class="fas fa-undo mr-1"></i> Reopen Ticket
            </button>
        `;
    }

    // User assignment HTML
    let userOptions = '<option value="" class="dark:bg-slate-900">Unassigned</option>';
    if (allUsers && allUsers.length > 0) {
        userOptions += allUsers.map(u => `<option value="${u.id}" class="dark:bg-slate-900" ${u.id == assigned_to ? 'selected' : ''}>${escapeHtml(u.name)}</option>`).join('');
    }

    const assignmentHtml = `
        <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-950 rounded-xl border border-gray-100 dark:border-slate-800">
            <span class="text-[9px] font-black uppercase tracking-widest text-gray-400">Assign To:</span>
            <select onchange="updateAssignment(${id}, this.value)" class="bg-transparent border-none text-[11px] font-bold text-indigo-600 dark:text-indigo-400 outline-none focus:ring-0 cursor-pointer p-0">
                ${userOptions}
            </select>
        </div>
    `;

    let repliesHtml = replies.map(r => {
        const isCurrentUser = r.user_id == currentUserId;
        const replyClass = isCurrentUser ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-100 dark:border-indigo-900/50' : 'bg-gray-50 dark:bg-slate-950 border-gray-100 dark:border-slate-800';
        const alignClass = isCurrentUser ? 'ml-12' : 'mr-12';

        return `
            <div class="mb-4 p-4 rounded-2xl border ${replyClass} ${alignClass} transition-all">
                <div class="flex justify-between items-center mb-2">
                    <strong class="text-sm text-gray-900 dark:text-gray-100">${escapeHtml(r.user_name || 'User ' + r.user_id)}</strong>
                    <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">${r.updated_at}</span>
                </div>
                <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">${escapeHtml(r.message)}</div>
            </div>
        `;
    }).join('');

    return `
        <div class="space-y-6">
            <div class="bg-gray-900 p-6 rounded-2xl shadow-xl mb-6 border border-white/5">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 border-b border-white/10 pb-6">
                    <h3 class="text-xl font-black text-white italic uppercase tracking-tighter">${escapeHtml(subject)}</h3>
                    <div class="flex flex-wrap gap-2">
                        ${actionButtons}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <span class="px-2 py-1 bg-white/10 rounded text-[9px] font-black uppercase tracking-widest border border-white/20 text-white">Status: ${escapeHtml(status)}</span>
                    <span class="px-2 py-1 bg-white/10 rounded text-[9px] font-black uppercase tracking-widest border border-white/20 text-white">Priority: ${escapeHtml(priority)}</span>
                    <span class="px-2 py-1 bg-white/10 rounded text-[9px] font-black uppercase tracking-widest border border-white/20 text-white">From: ${escapeHtml(requester?.name || 'N/A')}</span>
                    ${assignmentHtml}
                </div>
                ${message ? `<div class="mt-6 p-4 bg-white/5 rounded-xl border border-white/5 text-sm text-gray-300 leading-relaxed italic font-medium">"${escapeHtml(message)}"</div>` : ''}
            </div>
            
            <div class="replies-section px-2">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6 flex items-center gap-2">
                    <i class="fas fa-comments text-indigo-500"></i> Conversation History
                </h4>
                ${repliesHtml || '<div class="text-center py-12"><i class="fas fa-ghost text-3xl text-gray-100 dark:text-gray-800 mb-3 block"></i><p class="text-gray-400 italic text-sm">No replies yet.</p></div>'}
            </div>

            <div class="pt-8 border-t border-gray-100 dark:border-slate-800">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4 ml-1">Post a Reply</h4>
                <form id="replyForm" data-ticket-id="${id}" class="space-y-4">
                    <textarea id="replyMessage" name="message" rows="4" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm dark:text-gray-200 font-medium" placeholder="Type your message here..."></textarea>
                    <button type="submit" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-600 transition-all active:scale-95 shadow-xl flex items-center justify-center gap-2">
                        <i class="fas fa-reply"></i> Submit Reply
                    </button>
                </form>
            </div>
        </div>
    `;
}
