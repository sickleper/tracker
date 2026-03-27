<div id="tab-reports" class="tab-pane hidden space-y-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="card-base h-full border-none p-0">
            <div class="section-header"><h3><i class="fas fa-tachometer-alt text-indigo-400"></i> Efficiency Trend</h3></div>
            <div class="p-8 h-80 relative">
                <div id="mplChartState" class="fuel-panel-state fuel-panel-state-muted absolute inset-8">
                    <i class="fas fa-chart-line text-2xl"></i>
                    <span>Loading efficiency report...</span>
                </div>
                <canvas id="mplChart"></canvas>
            </div>
        </div>
        <div class="card-base h-full border-none p-0">
            <div class="section-header flex items-center justify-between">
                <h3><i class="fas fa-wave-square text-red-400"></i> Anomalies</h3>
                <div class="flex gap-2 flex-wrap justify-end">
                    <select id="yearFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-3 py-1 text-[10px] font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900"></select>
                    <select id="monthFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-3 py-1 text-[10px] font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900"></select>
                    <select id="vehicleFilter" class="bg-white/10 text-white border-white/20 rounded-xl px-3 py-1 text-[10px] font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900">
                        <?php renderFuelVehicleOptions($vRes ?? [], true); ?>
                    </select>
                </div>
            </div>
            <div class="p-8 h-80 relative">
                <div id="anomaliesChartState" class="fuel-panel-state fuel-panel-state-muted absolute inset-8">
                    <i class="fas fa-chart-area text-2xl"></i>
                    <span>Loading anomaly report...</span>
                </div>
                <canvas id="anomaliesChart"></canvas>
            </div>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header flex items-center justify-between">
            <h3><i class="fas fa-chart-line text-indigo-300 mr-2"></i> Weekly Performance</h3>
            <select id="vehicleSelectReport" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest outline-none transition-all focus:bg-white focus:text-gray-900">
                <?php renderFuelVehicleOptions($vRes ?? [], true, 'Filter Registration'); ?>
            </select>
        </div>
        <div class="table-container relative">
            <div id="resultsTableState" class="fuel-panel-state fuel-panel-state-muted absolute inset-0 z-10 bg-white/90 dark:bg-slate-950/90">
                <i class="fas fa-table text-2xl"></i>
                <span>Open reports to load weekly performance...</span>
            </div>
            <table id="resultsTable" class="w-full text-sm fuel-table-compact">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4">Driver</th>
                        <th class="px-6 py-4">Registration</th>
                        <th class="px-6 py-4 text-right">Total Km</th>
                        <th class="px-6 py-4 text-right">Total Liters</th>
                        <th class="px-6 py-4 text-center">Period</th>
                        <th class="px-6 py-4 text-right">KPL</th>
                        <th class="px-6 py-4 text-right">MPG</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
            </table>
        </div>
    </div>
</div>
