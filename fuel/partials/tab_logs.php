<div id="tab-logs" class="tab-pane space-y-8">
    <div class="card-base border-none">
        <div class="section-header !bg-slate-800 dark:!bg-slate-950/70">
            <h3>
                <i class="fas fa-clipboard-check text-emerald-300"></i> Daily Vehicle Check
            </h3>
        </div>
        <div class="p-8 space-y-8">
            <p class="fuel-section-copy">Complete one check per vehicle each day before first use. Failed items are saved and automatically logged as a defect.</p>
            <div id="dailyCheckStatusBoard" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4"></div>
            <form id="dailyCheckForm" action="save_daily_vehicle_check.php" method="post" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="fuel-label">Vehicle *</label>
                        <select id="dailyCheckVehicleId" name="vehicle_id" class="fuel-control fuel-control-indigo" required>
                            <option value="">Select Vehicle</option>
                            <?php renderFuelVehicleOptions($vRes ?? []); ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="fuel-label">Notes</label>
                        <input id="dailyCheckNotes" type="text" name="notes" class="fuel-control fuel-control-indigo" placeholder="Optional notes about the vehicle condition">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <?php foreach (fuelDailyCheckItems() as $itemKey => $itemLabel): ?>
                        <div class="rounded-2xl border border-gray-100 dark:border-slate-800 p-4 bg-gray-50 dark:bg-slate-950/60">
                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($itemLabel) ?></div>
                            <div class="mt-3 flex gap-3">
                                <label class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">
                                    <input type="radio" name="checks[<?= htmlspecialchars($itemKey) ?>]" value="pass" required>
                                    Pass
                                </label>
                                <label class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-red-600 dark:text-red-400">
                                    <input type="radio" name="checks[<?= htmlspecialchars($itemKey) ?>]" value="fail" required>
                                    Fail
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="fuel-action-btn fuel-action-btn-indigo w-full md:w-auto px-8 py-4 shadow-xl" type="submit">
                    <i class="fas fa-shield-check text-emerald-300"></i> Save Daily Check
                </button>
            </form>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-plus-circle text-emerald-400"></i> New Log Entry
            </h3>
        </div>
        <div class="p-8">
            <p class="fuel-section-copy mb-6">Capture the trip, mileage, and receipt in one pass. Vehicle selection will try to fill the assigned driver and last mileage automatically.</p>
            <form id="addFuelLogForm" action="insert_fuel_log_secure.php" method="POST" onsubmit="return validateMileage()" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                    <div>
                        <label class="fuel-label flex items-center gap-2">
                            Vehicle *
                            <i class="fas fa-info-circle text-indigo-400 cursor-help" onclick="showAutopopulateInfo()"></i>
                        </label>
                        <select class="fuel-control fuel-control-indigo" name="vehicle_id" id="vehicle_id_add" required>
                            <option value="">Select Vehicle</option>
                            <?php renderFuelVehicleOptions($vRes ?? []); ?>
                        </select>
                    </div>
                    <div>
                        <label class="fuel-label">Driver *</label>
                        <select class="fuel-control fuel-control-indigo" name="user_id" id="user_id" required>
                            <?php renderFuelDriverOptions($driversOnlyRes ?? []); ?>
                        </select>
                    </div>
                    <div>
                        <label class="fuel-label">Date *</label>
                        <input class="fuel-control fuel-control-indigo" type="date" name="date" id="date" required>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                    <div>
                        <label class="fuel-label">Start Mileage</label>
                        <input class="fuel-control fuel-control-indigo" type="number" step="0.01" id="start_mileage" name="start_mileage" required>
                    </div>
                    <div>
                        <label class="fuel-label">Finish Mileage</label>
                        <input class="fuel-control fuel-control-indigo" type="number" step="0.01" name="finish_mileage" id="finish_mileage" required>
                    </div>
                    <div>
                        <label class="fuel-label">Fuel Amount (L)</label>
                        <input class="fuel-control fuel-control-indigo" type="number" step="0.01" name="fuel_amount" id="fuel_amount" required>
                    </div>
                </div>
                <div class="flex flex-col md:flex-row gap-8 items-end">
                    <div class="flex-grow w-full">
                        <label class="fuel-label">Mileage/Receipt Image</label>
                        <div class="relative group">
                            <input type="file" accept="image/*" name="image_file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="w-full py-6 bg-indigo-50 dark:bg-indigo-900/20 border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 rounded-2xl text-center group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/30 transition-all">
                                <i class="fas fa-camera text-indigo-400 text-2xl mb-1"></i>
                                <p class="text-xs font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest">Click to upload photo</p>
                            </div>
                        </div>
                    </div>
                    <button class="fuel-action-btn fuel-action-btn-indigo w-full md:w-64 py-5 shadow-2xl" type="submit">
                        <i class="fas fa-check-circle text-emerald-400"></i> Add Log Entry
                    </button>
                </div>
                <div id="dailyCheckFuelGateNotice" class="hidden mt-4 rounded-2xl border px-4 py-3 text-xs font-bold"></div>
                <div id="error-message" class="mt-4 text-[10px] font-black text-red-500 uppercase tracking-widest ml-1"></div>
            </form>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-history text-indigo-400"></i> Log History
            </h3>
            <div class="flex items-center gap-4">
                <select id="vehicleSelect" class="bg-white/10 text-white border-white/20 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest focus:bg-white focus:text-gray-900 outline-none transition-all">
                    <?php renderFuelVehicleOptions($vRes ?? [], true); ?>
                </select>
            </div>
        </div>
        <div class="table-container">
            <table class="w-full text-sm fuel-table-compact" id="logTable2">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-left">Driver</th>
                        <th class="px-6 py-4 text-left">Plate</th>
                        <th class="px-6 py-4 text-right">Start Mi</th>
                        <th class="px-6 py-4 text-right">Finish Mi</th>
                        <th class="px-6 py-4 text-right">Fuel (L)</th>
                        <th class="px-6 py-4 text-center">Receipt</th>
                        <th class="px-6 py-4 text-right">Km Traveled</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
            </table>
        </div>
        <div id="logCountsDiv" class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800"></div>
    </div>
</div>
