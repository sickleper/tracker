<div id="tab-assign" class="tab-pane hidden space-y-8">
    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-plus-circle text-emerald-400"></i> Assign Tool to Van
            </h3>
            <button onclick="openModal('addToolModal')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all shadow-lg active:scale-95">
                <i class="fas fa-tools mr-1"></i> Add Master Tool
            </button>
        </div>
        <div class="p-8">
            <form id="assignToolForm" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Van *</label>
                        <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="vehicle_id" id="vehicle_id" required>
                            <option value="">Select a van</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= htmlspecialchars($v['vehicle_id']) ?>"><?= htmlspecialchars($v['license_plate']) ?> - <?= htmlspecialchars($v['make_model']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Select Tool *</label>
                        <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="tool_id" id="tool_id" required>
                            <option value="">Select a tool</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Quantity *</label>
                        <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="number" name="quantity" value="1" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Condition</label>
                        <select class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" name="condition" required>
                            <option value="New">New</option>
                            <option value="Good" selected>Good</option>
                            <option value="Fair">Fair</option>
                            <option value="Poor">Poor</option>
                            <option value="Broken">Broken</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Price/Value (EUR)</label>
                        <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="text" name="price" id="price" value="0.00">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Internal Remarks</label>
                        <input class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white" type="text" name="remarks" placeholder="Optional notes...">
                    </div>
                </div>
                <div id="tool_info" class="mb-6"></div>
                <button class="w-full py-5 bg-indigo-600 dark:bg-indigo-700 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95 shadow-2xl flex items-center justify-center gap-3" type="submit">
                    <i class="fas fa-check-circle text-emerald-400"></i> Complete Asset Assignment
                </button>
            </form>
        </div>
    </div>
</div>
