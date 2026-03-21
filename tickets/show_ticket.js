let currentUserId = null;

function closeModal(modalId) {
    $('#' + modalId).addClass('hidden');
    $('body').removeClass('overflow-hidden');
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

$(document).on('click', '.type-badge', function() {
    const badge = $(this);
    const ticketId = badge.data('ticket-id');
    const currentTypeId = badge.data('current-type-id');

    $.ajax({
        url: 'get_ticket_types.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const typeOptions = response.types;

                Swal.fire({
                    ...getSwalConfig(),
                    title: 'Update Ticket Type',
                    input: 'select',
                    inputOptions: typeOptions,
                    inputValue: currentTypeId,
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    showLoaderOnConfirm: true,
                    preConfirm: (newTypeId) => {
                        if (!newTypeId) {
                            Swal.showValidationMessage('Please select a type');
                            return false;
                        }
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: 'update_ticket.php',
                                method: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ ticketId: ticketId, label_id: newTypeId })
                            }).done(response => {
                                if (response.success) {
                                    const newTypeName = typeOptions[newTypeId];
                                    badge.text(newTypeName);
                                    badge.data('current-type-id', newTypeId);
                                    resolve();
                                } else {
                                    reject('Failed to update type: ' + (response.error || 'Unknown error'));
                                }
                            }).fail(jqXHR => {
                                reject('An error occurred while updating the type.');
                            });
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Updated!', text: 'Ticket type updated successfully.' });
                    }
                }).catch((error) => {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: error });
                });
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: 'Could not fetch ticket types.' });
            }
        }
    });
});

$(document).on('click', '.group-badge', function() {
    const badge = $(this);
    const ticketId = badge.data('ticket-id');
    const currentGroupId = badge.data('current-group-id');

    $.ajax({
        url: 'get_ticket_groups.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const groupOptions = response.groups;

                Swal.fire({
                    ...getSwalConfig(),
                    title: 'Update Ticket Group',
                    input: 'select',
                    inputOptions: groupOptions,
                    inputValue: currentGroupId,
                    showCancelButton: true,
                    confirmButtonText: 'Update',
                    showLoaderOnConfirm: true,
                    preConfirm: (newGroupId) => {
                        if (!newGroupId) {
                            Swal.showValidationMessage('Please select a group');
                            return false;
                        }
                        return new Promise((resolve, reject) => {
                            $.ajax({
                                url: 'update_ticket.php',
                                method: 'POST',
                                contentType: 'application/json',
                                data: JSON.stringify({ ticketId: ticketId, category_id: newGroupId })
                            }).done(response => {
                                if (response.success) {
                                    const newGroupName = groupOptions[newGroupId];
                                    badge.text(newGroupName);
                                    badge.data('current-group-id', newGroupId);
                                    resolve();
                                } else {
                                    reject('Failed to update group: ' + (response.error || 'Unknown error'));
                                }
                            }).fail(jqXHR => {
                                reject('An error occurred while updating the group.');
                            });
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Updated!', text: 'Ticket group updated successfully.' });
                    }
                }).catch((error) => {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: error });
                });
            } else {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: 'Could not fetch ticket groups.' });
            }
        }
    });
});

$(document).on('click', '.status-badge', function() {
    const badge = $(this);
    const ticketId = badge.data('ticket-id');
    const currentStatus = badge.data('current-status');

    Swal.fire({
        ...getSwalConfig(),
        title: 'Update Ticket Status',
        input: 'select',
        inputOptions: {
            'open': 'Open',
            'pending': 'Pending',
            'resolved': 'Resolved',
            'closed': 'Closed',
            'archived': 'Archived'
        },
        inputValue: currentStatus,
        showCancelButton: true,
        confirmButtonText: 'Update',
        showLoaderOnConfirm: true,
        preConfirm: (newStatus) => {
            if (!newStatus) {
                Swal.showValidationMessage('Please select a status');
                return false;
            }
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'update_ticket.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ ticketId: ticketId, status: newStatus })
                }).done(response => {
                    if (response.success) {
                        badge.text(newStatus);
                        const statusColors = {
                            'open': 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900/50',
                            'pending': 'bg-yellow-100 dark:bg-amber-900/30 text-yellow-700 dark:text-amber-400 border-yellow-200 dark:border-amber-900/50',
                            'resolved': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-900/50',
                            'closed': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-900/50'
                        };
                        badge.removeClass('bg-emerald-100 text-emerald-700 bg-yellow-100 text-yellow-700 bg-blue-100 text-blue-700 bg-red-100 text-red-700 dark:bg-emerald-900/30 dark:bg-amber-900/30 dark:bg-blue-900/30 dark:bg-red-900/30');
                        badge.addClass(statusColors[newStatus] || 'bg-gray-100 dark:bg-slate-800 text-gray-600 dark:text-gray-400');
                        badge.data('current-status', newStatus);
                        resolve();
                    } else {
                        reject('Failed to update status: ' + (response.error || 'Unknown error'));
                    }
                }).fail(jqXHR => {
                    reject('An error occurred while updating the status.');
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Updated!', text: 'Ticket status updated successfully.' });
        }
    }).catch((error) => {
        Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: error });
    });
});

$(document).on('click', '.priority-badge', function() {
    const badge = $(this);
    const ticketId = badge.data('ticket-id');
    const currentPriority = badge.data('current-priority');

    Swal.fire({
        ...getSwalConfig(),
        title: 'Update Ticket Priority',
        input: 'select',
        inputOptions: {
            'low': 'Low',
            'medium': 'Medium',
            'high': 'High',
            'urgent': 'Urgent'
        },
        inputValue: currentPriority,
        showCancelButton: true,
        confirmButtonText: 'Update',
        showLoaderOnConfirm: true,
        preConfirm: (newPriority) => {
            if (!newPriority) {
                Swal.showValidationMessage('Please select a priority');
                return false;
            }
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'update_ticket.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ ticketId: ticketId, priority: newPriority })
                }).done(response => {
                    if (response.success) {
                        badge.text(newPriority.charAt(0).toUpperCase() + newPriority.slice(1));
                        const priorityColors = {
                            'low': 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border-blue-100 dark:border-blue-900/50',
                            'medium': 'bg-gray-100 dark:bg-slate-800 text-gray-700 dark:text-gray-400 border-gray-200 dark:border-slate-700',
                            'high': 'bg-orange-50 dark:bg-amber-900/20 text-orange-700 dark:text-amber-400 border-orange-200 dark:border-amber-900/50',
                            'urgent': 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200 dark:border-red-900/50'
                        };
                        badge.removeClass('bg-blue-50 text-blue-700 bg-gray-100 text-gray-700 bg-orange-50 text-orange-700 bg-red-50 text-red-700 dark:bg-blue-900/20 dark:bg-slate-800 dark:bg-amber-900/20 dark:bg-red-900/20');
                        badge.addClass(priorityColors[newPriority] || 'bg-gray-50 text-gray-500');
                        badge.data('current-priority', newPriority);
                        resolve();
                    } else {
                        reject('Failed to update priority: ' + (response.error || 'Unknown error'));
                    }
                }).fail(jqXHR => {
                    reject('An error occurred while updating the priority.');
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Updated!', text: 'Ticket priority updated successfully.' });
        }
    }).catch((error) => {
        Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error!', text: error });
    });
});

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
        }
    });
}
