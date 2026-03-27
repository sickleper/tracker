<div id="editFuelLogModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <form id="updatelog" action="update_fuel_log.php" method="post" enctype="multipart/form-data" class="card-base w-full max-w-2xl overflow-hidden border-none shadow-2xl">
            <div class="section-header !bg-emerald-600 dark:!bg-emerald-900/40">
                <h3><i class="fas fa-gas-pump mr-2 text-emerald-200"></i> Refine Fuel Entry</h3>
                <button type="button" onclick="closeModal('editFuelLogModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8">
                <input type="hidden" id="log_id" name="log_id" required>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="fuel-label">Vehicle</label>
                        <select class="fuel-control fuel-control-modal fuel-control-emerald" name="vehicle_id" id="editFuelLogModal_vehicle_id">
                            <?php renderFuelVehicleOptions($vRes ?? []); ?>
                        </select>
                    </div>
                    <div>
                        <label class="fuel-label">Driver</label>
                        <select class="fuel-control fuel-control-modal fuel-control-emerald" name="user_id" id="editFuelLogModal_user_id">
                            <?php renderFuelDriverOptions($driversOnlyRes ?? [], false); ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div><label class="fuel-label">Date</label><input class="fuel-control fuel-control-modal fuel-control-emerald" type="date" name="date" required></div>
                    <div><label class="fuel-label">Start Mi</label><input class="fuel-control fuel-control-modal fuel-control-emerald" type="text" name="start_mileage" required></div>
                    <div><label class="fuel-label">Finish Mi</label><input class="fuel-control fuel-control-modal fuel-control-emerald" type="text" name="finish_mileage" required></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                    <div><label class="fuel-label">Fuel (L)</label><input class="fuel-control fuel-control-modal fuel-control-emerald" type="text" name="fuel_amount" required></div>
                    <div class="pb-2">
                        <label class="fuel-label">New Odo/Receipt Photo</label>
                        <input type="file" class="text-[10px] text-gray-500 font-bold uppercase cursor-pointer" name="image_file">
                    </div>
                </div>
                <div class="mt-8 p-6 bg-gray-50 dark:bg-slate-950 rounded-2xl text-center border border-gray-100 dark:border-slate-800">
                    <label class="fuel-label mb-4 ml-0">Current Verification Attachment</label>
                    <img id="current_image" class="mx-auto max-h-48 rounded-xl shadow-lg border border-black/5 dark:border-white/5 transition-all" src="uploads/1200px-Jeep_Odometer.jpg">
                </div>
            </div>
            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                <button type="button" onclick="closeModal('editFuelLogModal')" class="fuel-action-btn fuel-action-btn-cancel flex-1 py-4 text-xs">Cancel</button>
                <button type="submit" class="fuel-action-btn fuel-action-btn-emerald flex-1 py-4 shadow-lg">
                    <i class="fas fa-check-circle text-emerald-200"></i> Update Verified Entry
                </button>
            </div>
        </form>
    </div>
</div>
