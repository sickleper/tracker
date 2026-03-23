const ProposalSubmissionModule = (function() {
    let isSaving = false;

    function normalizeTaxes(value) {
        if (Array.isArray(value)) {
            return value.filter(Boolean).join(',');
        }
        if (value === null || value === undefined) {
            return '';
        }
        return value;
    }

    function saveProposal() {
        if (isSaving) {
            return;
        }

        const formData = $('#proposalForm').serializeArray();
        const data = {};
        
        // Convert serializeArray to object
        formData.forEach(item => {
            if (item.name.includes('[]')) {
                const name = item.name.replace('[]', '');
                // Handle nested items array manually if needed, but for simple multiple selects:
                if (!data[name]) data[name] = [];
                data[name].push(item.value);
            } else {
                data[item.name] = item.value;
            }
        });

        // Structure items correctly (as we have names like items[0][item_name])
        // serializeArray doesn't nest objects well, so we re-extract items
        const items = [];
        $('.proposal-item-row').each(function(index) {
            const taxesInput = $(this).find('.item-taxes').val();
            items.push({
                item_name: $(this).find('.item-name').val(),
                quantity: $(this).find('.item-qty').val(),
                unit_price: $(this).find('.item-price').val(),
                amount: $(this).find('.item-total').val(),
                item_summary: $(this).find('.item-summary').val(),
                taxes: normalizeTaxes(taxesInput)
            });
        });

        if (!items.length) {
            Swal.fire('Error', 'Add at least one proposal item before saving.', 'error');
            return;
        }

        const hasBlankItems = items.some(item => !String(item.item_name || '').trim());
        if (hasBlankItems) {
            Swal.fire('Error', 'Every proposal item needs a name.', 'error');
            return;
        }

        data.items = items;
        data.sub_total = parseFloat($('#sub-total-display').text().replace('€', '').replace(',', ''));
        data.total = parseFloat($('#total-display').text().replace('€', '').replace(',', ''));
        data.valid_till = new Date(new Date().getTime() + (30 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0]; // 30 days default

        if (!data.description || !String(data.description).trim()) {
            Swal.fire('Error', 'Project overview is required.', 'error');
            return;
        }

        isSaving = true;
        const saveButton = $('#saveProposal');
        const originalButtonHtml = saveButton.html();
        saveButton.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin text-emerald-400"></i> Saving...');

        Swal.fire({
            title: 'Saving Proposal...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'proposal_handler.php',
            type: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Proposal Created!',
                        text: 'Redirecting to review...',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'proposal_view.php?id=' + response.data.id;
                    });
                } else {
                    Swal.fire('Error', response.message || 'Failed to save', 'error');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error';
                Swal.fire('Error', msg, 'error');
            },
            complete: function() {
                isSaving = false;
                saveButton.prop('disabled', false).html(originalButtonHtml);
            }
        });
    }

    return {
        saveProposal: saveProposal
    };
})();
