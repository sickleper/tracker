<!-- Weather Forecast Modal -->
<div id="weather-forecast-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden flex items-center justify-center p-0 md:p-4">
    <div class="w-full max-w-4xl h-full md:h-[90vh] flex flex-col relative bg-white dark:bg-slate-900 md:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white">
            <h3 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-cloud-sun text-yellow-400"></i> Weather Forecast & Assignment
            </h3>
            <button onclick="closeWeatherForecastModal()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="weather-forecast-content" class="flex-1 overflow-y-auto custom-scrollbar min-h-[400px]">
            <!-- Content loaded via AJAX from partials/weather_forecast_content.php -->
        </div>
    </div>
</div>

<!-- History Modal -->
<div id="history-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 w-full max-w-2xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white">
            <h3 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-history text-indigo-400"></i> Change History
            </h3>
            <button onclick="closeHistory()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="history-content" class="p-0 overflow-y-auto flex-1 custom-scrollbar"></div>
        <div class="bg-gray-50 dark:bg-slate-950 p-6 border-t border-gray-100 dark:border-slate-800 flex justify-end">
            <button type="button" onclick="closeHistory()" class="px-8 py-3 bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-slate-700 transition-all active:scale-95 shadow-sm">Close View</button>
        </div>
    </div>
</div>

<!-- Summary Modal -->
<div id="summary-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden flex items-center justify-center p-0 md:p-4">
    <div class="w-full max-w-2xl h-full md:h-auto md:max-h-[95vh] flex flex-col relative bg-white dark:bg-slate-900 md:rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-gray-900 dark:bg-black p-6 flex items-center justify-between text-white">
            <h3 class="text-lg font-black italic uppercase tracking-wider flex items-center gap-3">
                <i class="fas fa-file-alt text-indigo-400"></i> Job Summary
            </h3>
            <button onclick="closeSummary()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="summary-content" class="p-0 overflow-y-auto flex-1 custom-scrollbar min-h-[400px]">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Help/Guide Modal -->
<div id="help-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[9999] hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 w-full max-w-4xl max-h-[90vh] flex flex-col rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800" onclick="event.stopPropagation()">
        <div class="bg-indigo-600 p-6 flex justify-between items-center text-white">
            <h3 class="text-xl font-black italic uppercase tracking-tighter flex items-center gap-3">
                <i class="fas fa-book-open text-indigo-200"></i> User Guide & Documentation
            </h3>
            <button onclick="closeHelp()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-all text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-8 overflow-y-auto flex-1 custom-scrollbar text-left text-gray-800 dark:text-gray-200">
            <?php if (file_exists('partials/help_content.php')) include 'partials/help_content.php'; ?>
        </div>
        <div class="bg-gray-50 dark:bg-slate-950 p-6 border-t border-gray-100 dark:border-slate-800 flex justify-end">
            <button type="button" onclick="closeHelp()" class="px-8 py-3 bg-gray-200 dark:bg-slate-800 text-gray-700 dark:text-gray-300 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-slate-700 transition-all active:scale-95 shadow-sm">Got it</button>
        </div>
    </div>
</div>

<!-- Client Modal -->
<div id="client-modal" class="hidden fixed inset-0 z-[150] overflow-y-auto text-center bg-black/50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="inline-block align-bottom bg-white dark:bg-slate-900 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-200 dark:border-slate-800">
            <div id="client-modal-content" class="p-6">
                <!-- Client Details Form -->
                <form id="client-details-form" class="space-y-6">
                    <input type="hidden" name="id" id="client-id-input">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Client Name</label>
                            <input type="text" name="name" id="client-name-input" readonly class="w-full p-3 bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl text-sm font-bold text-gray-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Contact Email</label>
                            <input type="email" name="email" id="client-email-input" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Invoice Email</label>
                            <input type="email" name="invoice_email" id="client-invoice-email-input" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Mobile</label>
                            <input type="text" name="mobile" id="client-mobile-input" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Default Address</label>
                        <textarea name="address" id="client-address-input" rows="2" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1 ml-1">Google Sheet ID</label>
                        <input type="text" name="spreadsheet_id" id="client-spreadsheet-input" class="w-full p-3 bg-white dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="flex gap-3 pt-4 border-t border-gray-100 dark:border-slate-800">
                        <button type="button" onclick="closeClientModal()" class="flex-1 py-3 bg-gray-100 dark:bg-slate-800 text-gray-500 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-200">Cancel</button>
                        <button type="button" onclick="syncNow()" class="flex-1 py-3 bg-emerald-100 text-emerald-600 rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-emerald-600 hover:text-white transition-all">Full Sync</button>
                        <button type="submit" class="flex-[2] py-3 bg-indigo-600 text-white rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-indigo-700 shadow-lg">Save Client Details</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
