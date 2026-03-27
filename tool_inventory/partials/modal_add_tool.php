<div id="addToolModal" class="fixed inset-0 z-[150] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="card-base w-full max-w-2xl overflow-hidden border-none shadow-2xl">
            <div class="section-header">
                <h3><i class="fas fa-tools text-indigo-400"></i> Create Master Tool</h3>
                <button onclick="closeModal('addToolModal')" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white"><i class="fas fa-times"></i></button>
            </div>
            <form id="addToolForm" class="p-8 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Tool Name *</label>
                        <input type="text" name="toolName" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Category *</label>
                        <select name="toolTypeID" required class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                            <?php foreach ($toolTypes as $type): ?>
                                <option value="<?= $type['ToolTypeID'] ?>"><?= htmlspecialchars($type['ToolTypeName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Serial Number</label>
                        <input type="text" name="serialNumber" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Market Value (EUR)</label>
                        <input type="text" name="value" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500 transition-all" value="0.00">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Applicable Trades</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($toolTrades as $trade): ?>
                            <label class="flex items-center gap-2 px-4 py-2 bg-gray-50 dark:bg-slate-950 border border-transparent dark:border-slate-800 rounded-xl cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all group">
                                <input type="checkbox" name="toolTradeID[]" value="<?= $trade['TradeID'] ?>" class="rounded text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400"><?= htmlspecialchars($trade['TradeName']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4 -mx-8 -mb-8">
                    <button type="button" onclick="closeModal('addToolModal')" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-900 rounded-2xl transition-all">Cancel</button>
                    <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-xl active:scale-[0.98] flex items-center justify-center gap-2">
                        <i class="fas fa-save text-indigo-200"></i> Save Master Tool
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
