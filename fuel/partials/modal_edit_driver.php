<div id="editDriverModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <form id="editDriverForm" action="update_user.php" method="post" class="card-base w-full max-w-lg overflow-hidden border-none shadow-2xl">
            <div class="bg-gray-900 p-6 flex items-center justify-between text-white">
                <h5 class="text-lg font-black uppercase italic tracking-wider flex items-center gap-3"><i class="fas fa-user-edit text-indigo-400"></i> Edit Driver Account</h5>
                <button type="button" onclick="closeModal('editDriverModal')" class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 transition-all text-white flex items-center justify-center"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-8 space-y-6">
                <input type="hidden" name="id" id="edit_driver_id">
                <div>
                    <label class="fuel-label">Full Name</label>
                    <input required type="text" class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_driver_name" name="name">
                </div>
                <div>
                    <label class="fuel-label">Email Address</label>
                    <input required type="email" class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_driver_email" name="email">
                </div>
                <div>
                    <label class="fuel-label">Phone Number</label>
                    <input type="text" class="fuel-control fuel-control-modal fuel-control-indigo" id="edit_driver_mobile" name="mobile">
                </div>
                <div>
                    <label class="fuel-label">Fleet Callout Availability</label>
                    <select name="is_callout_driver" id="edit_is_callout_driver" class="fuel-control fuel-control-modal fuel-control-indigo">
                        <option value="0">Regular Fleet Driver</option>
                        <option value="1">Primary Callout Specialist</option>
                    </select>
                    <p class="mt-3 text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-tight italic leading-relaxed">Only Specialists' leave will trigger schedule blackouts in the lead booking console.</p>
                </div>
            </div>
            <div class="p-6 bg-gray-50 dark:bg-slate-950 border-t border-gray-100 dark:border-slate-800 flex gap-4">
                <button type="button" onclick="closeModal('editDriverModal')" class="fuel-action-btn fuel-action-btn-cancel flex-1 py-4 text-xs">Cancel</button>
                <button type="submit" class="fuel-action-btn fuel-action-btn-indigo flex-1 py-4 shadow-lg">
                    <i class="fas fa-save text-indigo-200"></i> Update Profile
                </button>
            </div>
        </form>
    </div>
</div>
