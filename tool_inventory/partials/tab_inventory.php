<div id="tab-inventory" class="tab-pane hidden space-y-8">
    <div class="card-base border-none">
        <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60 flex-wrap gap-4">
            <h3>
                <i class="fas fa-boxes text-indigo-300"></i> Van Audit Table
            </h3>
            <div class="flex flex-wrap items-center gap-4">
                <select id="vehicleFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest focus:bg-white focus:text-gray-900 outline-none transition-all">
                    <option value="" class="text-gray-900">Select Vehicle to Audit</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= htmlspecialchars($v['vehicle_id']) ?>" class="text-gray-900"><?= htmlspecialchars($v['license_plate']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button id="saveInventoryLogButton" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-md active:scale-95">
                    <i class="fas fa-clipboard-check mr-1"></i> Verify Current Stock
                </button>
            </div>
        </div>
        <div class="p-6">
            <div id="lastInventoryCheckTime" class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 ml-1"></div>
            <div class="table-container">
                <table id="inventoryTable" class="w-full text-sm">
                    <thead class="table-header-row">
                        <tr>
                            <th class="px-6 py-4 text-left">ID</th>
                            <th class="px-6 py-4 text-left">Tool Name</th>
                            <th class="px-6 py-4 text-center">Type</th>
                            <th class="px-6 py-4 text-center">Qty</th>
                            <th class="px-6 py-4 text-center">Condition</th>
                            <th class="px-6 py-4 text-right">Value (EUR)</th>
                            <th class="px-6 py-4 text-left">Remarks</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
