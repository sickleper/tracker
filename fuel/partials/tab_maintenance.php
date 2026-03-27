<div id="tab-maintenance" class="tab-pane hidden space-y-8">
    <div class="card-base border-none">
        <div class="section-header !bg-indigo-700 dark:!bg-indigo-950/60">
            <h3>
                <i class="fas fa-bell text-amber-300"></i> Compliance Reminders
            </h3>
        </div>
        <div class="p-6">
            <p class="fuel-section-copy mb-4">Upcoming and overdue fleet documents and service dates, using the same reminder feed shown in the main notification bell.</p>
            <div id="vehicleReminderSummaryPanel"></div>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header !bg-amber-600 dark:!bg-amber-900/40">
            <h3>
                <i class="fas fa-tools text-amber-200"></i> Service Schedule
            </h3>
        </div>
        <div id="serviceSchedulePanel" class="p-6">
            <?php include __DIR__ . '/../service_intervals.php'; ?>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header !bg-slate-800 dark:!bg-slate-950/70">
            <h3>
                <i class="fas fa-clock-rotate-left text-indigo-300"></i> Maintenance History
            </h3>
        </div>
        <div class="p-6">
            <p class="fuel-section-copy mb-4">Every completed service entry is recorded here with date, mileage, interval, and notes.</p>
            <div id="maintenanceHistoryPanel"></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div class="lg:col-span-5">
            <div class="card-base h-full border-none">
                <div class="section-header !bg-red-700 dark:!bg-red-950/60">
                    <h3>
                        <i class="fas fa-exclamation-triangle text-red-300"></i> Report Defect
                    </h3>
                </div>
                <div class="p-8">
                    <form action="save_defect.php" method="post" id="defectForm" class="space-y-6">
                        <div>
                            <label class="fuel-label">Vehicle *</label>
                            <select class="fuel-control fuel-control-red" name="vehicle_id" required>
                                <option value="">Select Vehicle</option>
                                <?php renderFuelVehicleOptions($vRes ?? []); ?>
                            </select>
                        </div>
                        <div>
                            <label class="fuel-label">Details *</label>
                            <textarea class="fuel-control fuel-control-red fuel-control-textarea" name="defect_details" rows="4" placeholder="Describe the issue..." required></textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="fuel-label">Severity *</label>
                                <select class="fuel-control fuel-control-red" name="severity" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-3 text-xs font-black uppercase tracking-widest text-red-600 dark:text-red-300">
                                    <input type="checkbox" name="off_road" value="1" class="h-4 w-4 rounded border-red-300 text-red-600 focus:ring-red-500">
                                    Mark Vehicle Off Road
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="fuel-label">Notes</label>
                            <textarea class="fuel-control fuel-control-red fuel-control-textarea" name="notes" rows="3" placeholder="Optional notes for follow-up, workshop, or driver handover"></textarea>
                        </div>
                        <button type="submit" class="fuel-action-btn fuel-action-btn-red w-full py-4 shadow-xl">
                            <i class="fas fa-paper-plane text-red-200"></i> Submit Defect Report
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="lg:col-span-7">
            <div class="card-base h-full border-none">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-list text-gray-400 dark:text-gray-500"></i> Active Defect Logs
                    </h3>
                </div>
                <div class="p-6">
                    <div id="defectsRow" class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar"></div>
                </div>
            </div>
        </div>
    </div>
</div>
