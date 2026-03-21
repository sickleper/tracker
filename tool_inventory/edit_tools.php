<?php 
$pageTitle = "Manage Master Tools";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// Fetch lookups via API
$lookupRes = makeApiCall('/api/tools/lookups');
$toolTypes = ($lookupRes && ($lookupRes['success'] ?? false)) ? $lookupRes['types'] : [];
$toolTrades = ($lookupRes && ($lookupRes['success'] ?? false)) ? $lookupRes['trades'] : [];

include '../header.php';
include '../nav.php';
?>

<!-- Lightbox2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css" rel="stylesheet" />
<!-- Lightbox2 JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/js/lightbox.min.js"></script>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-tools text-indigo-400 mr-2"></i> Master Tool Registry
            </h3>
            <button onclick="window.location.href='index.php'" class="bg-white/10 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-white/20 transition-all">
                <i class="fas fa-arrow-left mr-1"></i> Back to Inventory
            </button>
        </div>
        <div class="table-container">
            <table id="toolsTable" class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">Tool Name</th>
                        <th class="px-6 py-4 text-left">Type</th>
                        <th class="px-6 py-4 text-left">Serial Number</th>
                        <th class="px-6 py-4 text-left">Purchase Date</th>
                        <th class="px-6 py-4 text-right">Value (€)</th>
                        <th class="px-6 py-4 text-left">Trades</th>
                        <th class="px-6 py-4 text-center">Image</th>
                        <th class="px-6 py-4 text-left">Van Assignment</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Tool Modal -->
<div id="editToolModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="card-base w-full max-w-2xl overflow-hidden border-none shadow-2xl">
            <div class="section-header">
                <h3><i class="fas fa-edit mr-2 text-indigo-400"></i> Edit Master Tool</h3>
                <button onclick="closeModal('editToolModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <form id="editToolForm" action="update_tool.php" method="POST" class="p-8 space-y-6">
                <input type="hidden" name="toolID" id="editToolID">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Tool Name *</label>
                        <input type="text" name="toolName" id="editToolName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category *</label>
                        <select name="toolTypeID" id="editToolTypeID" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php foreach($toolTypes as $type) echo "<option value='{$type['ToolTypeID']}'>".htmlspecialchars($type['ToolTypeName'])."</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Serial Number</label>
                        <input type="text" name="serialNumber" id="editSerialNumber" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Market Value (€)</label>
                        <input type="text" name="value" id="editValue" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Applicable Trades</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($toolTrades as $trade): ?>
                            <label class="flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all">
                                <input type="checkbox" name="toolTradeID[]" value="<?php echo $trade['TradeID']; ?>" class="trade-checkbox rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($trade['TradeName']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4 -mx-8 -mb-8">
                    <button type="button" onclick="closeModal('editToolModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                    <button type="submit" class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save text-indigo-200"></i> Update Master Tool
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const table = $('#toolsTable').DataTable({
        ajax: 'fetch_tools_table.php',
        columns: [
            { data: 'ToolID', className: 'px-6 py-4 text-xs font-mono text-gray-400' },
            { data: 'ToolName', className: 'px-6 py-4 font-bold text-gray-900 dark:text-gray-100' },
            { data: 'ToolTypeName', className: 'px-6 py-4 text-[10px] font-black uppercase text-gray-400' },
            { data: 'SerialNumber', className: 'px-6 py-4 text-xs font-mono text-gray-500' },
            { data: 'PurchaseDate', className: 'px-6 py-4 text-xs text-gray-500' },
            { data: 'Value', className: 'px-6 py-4 text-right font-black text-emerald-600 dark:text-emerald-400', render: (d) => `€${parseFloat(d).toFixed(2)}` },
            { data: 'Trades', className: 'px-6 py-4 text-xs text-gray-500 dark:text-gray-400' },
            { 
                data: 'ImageURL', 
                className: 'px-6 py-4 text-center',
                render: (d) => d ? `<a href="${d}" data-lightbox="tools"><img src="${d}" class="h-10 w-10 object-cover rounded-lg shadow-sm mx-auto hover:scale-110 transition-transform"></a>` : '<i class="fas fa-image text-gray-200 dark:text-slate-800"></i>'
            },
            { 
                data: 'assignedVehicle',
                className: 'px-6 py-4',
                render: (d) => d !== 'Not Assigned' ? `<span class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-[9px] font-black rounded-lg uppercase tracking-wider border border-green-200 dark:border-green-800/50">${d}</span>` : `<span class="px-2 py-1 bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-gray-500 text-[9px] font-black rounded-lg uppercase tracking-wider border border-gray-200 dark:border-slate-700">Available</span>`
            },
            {
                data: 'ToolID',
                className: 'px-6 py-4 text-right',
                render: (d, t, r) => `
                    <div class="flex justify-end gap-3">
                        <button onclick="editTool(${d})" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 dark:hover:text-indigo-200 font-black uppercase text-[10px] tracking-widest transition-colors">Edit</button>
                        ${r.assignedVehicle === 'Not Assigned' ? `<button onclick="deleteTool(${d}, '${r.ToolName.replace(/'/g, "\\'")}')" class="text-red-400 hover:text-red-600 font-black uppercase text-[10px] tracking-widest transition-colors">Delete</button>` : ''}
                    </div>
                `
            }
        ],
        responsive: true,
        dom: 'ftip',
        pageLength: 25
    });

    window.editTool = function(id) {
        $.getJSON('fetch_tool.php', { tool_id: id }, function(data) {
            $('#editToolID').val(data.ToolID);
            $('#editToolName').val(data.ToolName);
            $('#editToolTypeID').val(data.ToolTypeID);
            $('#editSerialNumber').val(data.SerialNumber);
            $('#editValue').val(data.Value);
            
            // Set checkboxes for trades
            $('.trade-checkbox').prop('checked', false);
            if (data.Trades) {
                const tradeIds = data.Trades.split(',');
                tradeIds.forEach(tid => {
                    $(`.trade-checkbox[value="${tid}"]`).prop('checked', true);
                });
            }
            openModal('editToolModal');
        });
    };

    window.deleteTool = function(id, name) {
        Swal.fire({
            title: 'Delete Master Tool?',
            text: `Are you sure you want to delete ${name}? This will remove it from the system entirely.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('delete_tool.php', { id: id }, function(res) {
                    if (res.success) {
                        Swal.fire('Deleted!', 'Tool has been removed.', 'success');
                        table.ajax.reload();
                    }
                });
            }
        });
    };

    $('#editToolForm').submit(function(e) {
        e.preventDefault();
        $.ajax({
            url: 'update_tool.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon:'success', title:'Updated!', text:'Master tool details saved.', timer:1500, showConfirmButton:false });
                    closeModal('editToolModal');
                    table.ajax.reload();
                }
            }
        });
    });

    window.openModal = function(id) { $(`#${id}`).removeClass('hidden'); };
    window.closeModal = function(id) { $(`#${id}`).addClass('hidden'); };
});
</script>

<style>
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #4f46e5 !important;
        color: white !important;
        border: none !important;
        border-radius: 12px !important;
        font-weight: 900 !important;
        font-size: 10px !important;
        padding: 5px 12px !important;
    }
</style>

</body>
</html>
