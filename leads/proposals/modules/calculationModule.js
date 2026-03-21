const CalculationModule = (function() {
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function calculateTotals() {
        let subTotal = 0;
        let taxTotal = 0;

        $('.proposal-item-row').each(function() {
            const qty = parseFloat($(this).find('.item-qty').val()) || 0;
            const price = parseFloat($(this).find('.item-price').val()) || 0;
            const rowTotal = qty * price;
            
            $(this).find('.item-total').val(rowTotal.toFixed(2));
            subTotal += rowTotal;

            // Calculate tax for this row
            const taxIds = $(this).find('.item-taxes').val() || [];
            taxIds.forEach(id => {
                const tax = window.taxesData.find(t => t.id == id);
                if (tax) {
                    taxTotal += rowTotal * (tax.rate_percent / 100);
                }
            });
        });

        const discountValue = parseFloat($('#discount').val()) || 0;
        const discountType = $('#discount_type').val();
        let totalDiscount = 0;

        if (discountType === 'percent') {
            totalDiscount = subTotal * (discountValue / 100);
        } else {
            totalDiscount = discountValue;
        }

        const finalTotal = Math.max(0, subTotal + taxTotal - totalDiscount);

        $('#sub-total-display').text('€' + subTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#tax-total-display').text('€' + taxTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#total-display').text('€' + finalTotal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        
        updatePreviewItems();
    }

    function updatePreviewItems() {
        let html = `
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="py-2 text-left font-black uppercase text-[10px] text-gray-400">Description</th>
                        <th class="py-2 text-right font-black uppercase text-[10px] text-gray-400">Qty</th>
                        <th class="py-2 text-right font-black uppercase text-[10px] text-gray-400">Price</th>
                        <th class="py-2 text-right font-black uppercase text-[10px] text-gray-400">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">`;

        $('.proposal-item-row').each(function() {
            const name = $(this).find('.item-name').val() || 'New Item';
            const qty = $(this).find('.item-qty').val() || 0;
            const price = $(this).find('.item-price').val() || 0;
            const total = qty * price;

            html += `
                <tr>
                    <td class="py-3 text-gray-900 font-medium">${escapeHtml(name)}</td>
                    <td class="py-3 text-right text-gray-600">${escapeHtml(qty)}</td>
                    <td class="py-3 text-right text-gray-600">€${parseFloat(price).toFixed(2)}</td>
                    <td class="py-3 text-right text-gray-900 font-bold">€${total.toFixed(2)}</td>
                </tr>`;
        });

        const subTotalText = $('#sub-total-display').text();
        const taxTotalText = $('#tax-total-display').text();
        const totalText = $('#total-display').text();

        html += `
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-100">
                        <td colspan="3" class="py-4 text-right text-[10px] font-black uppercase text-gray-400">Sub Total</td>
                        <td class="py-4 text-right font-bold text-gray-900">${subTotalText}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="py-1 text-right text-[10px] font-black uppercase text-gray-400">Tax</td>
                        <td class="py-1 text-right font-bold text-gray-900">${taxTotalText}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="py-2 text-right text-[10px] font-black uppercase text-indigo-600">Final Total</td>
                        <td class="py-2 text-right font-black text-xl text-indigo-600">${totalText}</td>
                    </tr>
                </tfoot>
            </table>`;

        $('#preview-items-table').html(html);
    }

    return {
        calculateTotals: calculateTotals
    };
})();
