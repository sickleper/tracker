<div id="editVehicleModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <form id="editVehicleForm" action="update_vehicle.php" method="post" class="card-base w-full max-w-lg overflow-hidden border-none shadow-2xl">
            <div class="section-header">
                <h3><i class="fas fa-car mr-2 text-indigo-400"></i> Modify Vehicle Core</h3>
                <button type="button" onclick="closeModal('editVehicleModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8 space-y-6">
                <input type="hidden" name="vehicle_id" id="vehicle_update_id">
                <div>
                    <label class="fuel-label">License Plate</label>
                    <input required type="text" class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_license_plate" name="license_plate">
                </div>
                <div>
                    <label class="fuel-label">Make & Model</label>
                    <input required type="text" class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_make_model" name="make_model">
                </div>
                <div>
                    <label class="fuel-label">Default Assigned Driver</label>
                    <select required class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_user_id" name="user_id">
                        <?php renderFuelDriverOptions($driversOnlyRes ?? []); ?>
                    </select>
                </div>
            </div>
            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                <button type="button" onclick="closeModal('editVehicleModal')" class="fuel-action-btn fuel-action-btn-cancel flex-1 py-4 text-xs">Cancel</button>
                <button type="submit" class="fuel-action-btn fuel-action-btn-indigo flex-1 py-4 shadow-lg">Save Update</button>
            </div>
        </form>
    </div>
</div>
