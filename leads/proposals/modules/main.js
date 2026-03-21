$(document).ready(function() {
    
    // Initialize if template data exists
    if (window.templateData && window.templateData.items) {
        window.templateData.items.forEach(item => {
            ItemManagementModule.addItem({
                item_name: item.item_name,
                quantity: item.quantity,
                unit_price: item.unit_price,
                item_summary: item.item_summary,
                taxes: item.taxes ? JSON.parse(item.taxes) : []
            });
        });
    }

    // Event Listeners
    $('#add-item').on('click', function() {
        ItemManagementModule.addItem();
    });

    $('#discount, #discount_type').on('input change', function() {
        CalculationModule.calculateTotals();
    });

    $('#saveProposal').on('click', function() {
        ProposalSubmissionModule.saveProposal();
    });

    // Initial calculation
    CalculationModule.calculateTotals();
});
