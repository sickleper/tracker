const ItemManagementModule = (function() {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function addItem(data = {}) {
        const index = $('.proposal-item-row').length;
        
        // Default to VAT 13.5% (ID: 2) if it exists and no taxes provided
        if (!data.taxes || data.taxes.length === 0) {
            data.taxes = [2]; 
        }

        const taxOptions = window.taxesData.map(tax => 
            `<option value="${tax.id}" ${data.taxes && data.taxes.includes(tax.id) ? 'selected' : ''}>${escapeHtml(tax.tax_name)} (${escapeHtml(tax.rate_percent)}%)</option>`
        ).join('');

        const html = `
            <div class="proposal-item-row p-6 bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 shadow-sm space-y-4 relative group animate-slide-down">
                <button type="button" class="remove-item absolute -top-2 -right-2 w-8 h-8 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-all shadow-lg flex items-center justify-center hover:bg-red-600 active:scale-90">
                    <i class="fas fa-times text-xs"></i>
                </button>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-6">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Item Name / Description</label>
                        <input type="text" name="items[${index}][item_name]" class="item-name w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white" value="${escapeHtml(data.item_name || '')}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Qty</label>
                        <input type="number" name="items[${index}][quantity]" class="item-qty w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white" value="${data.quantity || 1}" step="0.01" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Unit Price (€)</label>
                        <input type="number" name="items[${index}][unit_price]" class="item-price w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white" value="${data.unit_price || 0}" step="0.01" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Total</label>
                        <input type="text" class="item-total w-full p-3 bg-gray-100 dark:bg-slate-800 border-none rounded-xl text-sm font-black text-indigo-600 dark:text-indigo-400" value="0.00" readonly>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-8">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Summary (Optional)</label>
                        <textarea name="items[${index}][item_summary]" class="item-summary w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-xs dark:text-gray-300" rows="1">${escapeHtml(data.item_summary || '')}</textarea>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-[9px] font-black uppercase text-gray-400 mb-1 ml-1">Taxes</label>
                        <select name="items[${index}][taxes][]" class="item-taxes w-full p-3 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white" multiple>
                            ${taxOptions}
                        </select>
                    </div>
                </div>
            </div>`;

        $('#proposal-items').append(html);
        CalculationModule.calculateTotals();
    }

    $(document).on('click', '.remove-item', function() {
        $(this).closest('.proposal-item-row').remove();
        CalculationModule.calculateTotals();
    });

    $(document).on('input', '.item-qty, .item-price, .item-name', function() {
        CalculationModule.calculateTotals();
    });

    $(document).on('change', '.item-taxes', function() {
        CalculationModule.calculateTotals();
    });

    return {
        addItem: addItem
    };
})();
