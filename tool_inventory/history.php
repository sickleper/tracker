<?php 
$pageTitle = "Inventory History";
require_once '../config.php';
require_once '../tracker_data.php';

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

// Fetch vehicles via API
$vRes = makeApiCall('/api/fuel/vehicles');
$vehicles = ($vRes && ($vRes['success'] ?? false)) ? $vRes['vehicles'] : [];

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="card-base border-none mb-8">
        <div class="section-header">
            <h3>
                <i class="fas fa-history text-indigo-400 mr-2"></i> Inventory Stocktake History
            </h3>
            <button onclick="window.location.href='index.php'" class="bg-white/10 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-white/20 transition-all">
                <i class="fas fa-warehouse mr-1"></i> Back to Inventory
            </button>
        </div>
        <div class="p-8">
            <div class="max-w-md">
                <label for="vehicleSelect" class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Vehicle to View History</label>
                <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" id="vehicleSelect">
                    <option value="">Choose a van...</option>
                    <?php foreach ($vehicles as $v) echo "<option value='".htmlspecialchars($v['vehicle_id'])."'>".htmlspecialchars($v['license_plate'])." - ".htmlspecialchars($v['make_model'])."</option>"; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- History Dates List -->
        <div class="lg:col-span-4">
            <div class="card-base border-none h-full">
                <div class="section-header !bg-gray-100 dark:!bg-slate-800 !text-gray-900 dark:!text-white border-b border-gray-200 dark:border-slate-700">
                    <h4 class="font-black uppercase text-xs tracking-widest">Available Check Dates</h4>
                </div>
                <div id="historyList" class="p-0 max-h-[600px] overflow-y-auto custom-scrollbar">
                    <div class="text-center py-12 text-gray-300 dark:text-gray-700 italic text-sm font-medium">Select a vehicle first</div>
                </div>
            </div>
        </div>

        <!-- Details Table -->
        <div class="lg:col-span-8">
            <div class="card-base border-none min-h-[400px]">
                <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60">
                    <h4 class="text-white font-black uppercase text-xs tracking-widest">Verified Inventory Details</h4>
                    <div id="selectedDateHeader" class="text-indigo-200 font-black text-xs uppercase tracking-tighter"></div>
                </div>
                <div id="inventoryDetails" class="p-0 overflow-x-auto">
                    <div class="text-center py-20 text-gray-300 dark:text-gray-700 italic text-sm font-medium">Select a date from the list</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    $('#vehicleSelect').change(function() {
        const vid = $(this).val();
        if (vid) {
            loadInventoryHistory(vid);
            $('#inventoryDetails').html('<div class="text-center py-20 text-gray-300 dark:text-gray-700 italic text-sm font-medium">Select a date from the list</div>');
            $('#selectedDateHeader').text('');
        } else {
            $('#historyList').html('<div class="text-center py-12 text-gray-300 dark:text-gray-700 italic text-sm font-medium">Select a vehicle first</div>');
            $('#inventoryDetails').html('');
        }
    });

    function loadInventoryHistory(vid) {
        $('#historyList').html('<div class="flex justify-center py-12"><i class="fas fa-circle-notch fa-spin text-2xl text-indigo-500"></i></div>');
        $.getJSON(`fetch_inventory_history.php?vehicle_id=${vid}`, function(data) {
            if (data.length > 0) {
                let html = '<div class="divide-y divide-gray-100 dark:divide-slate-800 bg-white dark:bg-slate-900/20">';
                data.forEach(item => {
                    html += `
                        <button class="w-full text-left p-6 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all flex items-center justify-between group history-item" data-date="${item.CheckDate}">
                            <div>
                                <div class="text-sm font-black text-gray-900 dark:text-gray-100">${moment(item.CheckDate).format('Do MMMM YYYY')}</div>
                                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-600 uppercase tracking-widest">${moment(item.CheckDate).fromNow()}</div>
                            </div>
                            <i class="fas fa-chevron-right text-gray-200 dark:text-gray-700 group-hover:text-indigo-500 transition-colors"></i>
                        </button>`;
                });
                html += '</div>';
                $('#historyList').html(html);
            } else {
                $('#historyList').html('<div class="text-center py-12 text-gray-400 dark:text-gray-600 italic text-sm font-medium">No checks recorded for this van.</div>');
            }
        });
    }

    $(document).on('click', '.history-item', function() {
        $('.history-item').removeClass('bg-indigo-50 dark:bg-indigo-900/30 border-l-4 border-indigo-500');
        $(this).addClass('bg-indigo-50 dark:bg-indigo-900/30 border-l-4 border-indigo-500');
        
        const vid = $('#vehicleSelect').val();
        const date = $(this).data('date');
        $('#selectedDateHeader').text(moment(date).format('MMMM Do, YYYY'));
        loadInventoryDetails(vid, date);
    });

    function loadInventoryDetails(vid, date) {
        $('#inventoryDetails').html('<div class="flex justify-center py-20"><i class="fas fa-circle-notch fa-spin text-3xl text-indigo-500"></i></div>');
        $.getJSON(`fetch_inventory_details.php?vehicle_id=${vid}&check_date=${date}`, function(data) {
            if (data.length > 0) {
                let html = `
                    <div class="table-container">
                        <table class="w-full text-sm">
                            <thead class="table-header-row">
                                <tr>
                                    <th class="px-6 py-4 text-left">Tool Name</th>
                                    <th class="px-6 py-4 text-left">Category</th>
                                    <th class="px-6 py-4 text-right">Qty</th>
                                    <th class="px-6 py-4 text-right">Value</th>
                                    <th class="px-6 py-4 text-center">Image</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20">`;
                
                data.forEach(item => {
                    html += `
                        <tr class="table-row-hover">
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">${item.ToolName}</td>
                            <td class="px-6 py-4 text-[10px] font-black uppercase text-gray-400 dark:text-gray-500">${item.ToolTypeID || 'N/A'}</td>
                            <td class="px-6 py-4 text-right font-black text-indigo-600 dark:text-indigo-400">${item.Quantity}</td>
                            <td class="px-6 py-4 text-right font-mono dark:text-gray-300">€${parseFloat(item.Value || 0).toFixed(2)}</td>
                            <td class="px-6 py-4 text-center">
                                ${item.ImageURL ? `<img src="${item.ImageURL}" class="h-10 w-10 object-cover rounded-lg mx-auto shadow-sm cursor-pointer hover:scale-110 transition-transform" onclick="Swal.fire({imageUrl: '${item.ImageURL}', showConfirmButton: false})">` : '<i class="fas fa-image text-gray-200 dark:text-slate-800"></i>'}
                            </td>
                        </tr>`;
                });
                
                html += '</tbody></table></div>';
                $('#inventoryDetails').html(html);
            } else {
                $('#inventoryDetails').html('<div class="text-center py-20 text-gray-400 dark:text-gray-600 italic font-medium">No details found.</div>');
            }
        });
    }
});
</script>

</body>
</html>
