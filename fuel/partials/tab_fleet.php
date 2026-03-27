<div id="tab-fleet" class="tab-pane hidden space-y-8">
    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-car text-indigo-400"></i> Vehicles & Compliance
            </h3>
        </div>
        <div class="p-6">
            <?php include __DIR__ . '/../vehicles_list.php'; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-plus-circle text-indigo-400 mr-2"></i> Add Vehicle</h3>
            </div>
            <div class="p-8">
                        <p class="fuel-section-copy mb-6">Add a registration and model to make the vehicle available for logs, documents, and reporting.</p>
                        <form id="addVehicleForm" action="add_vehicle.php" method="post" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="fuel-label">License Plate</label>
                                    <input required class="fuel-control fuel-control-indigo" type="text" name="license_plate" placeholder="151-D-12345">
                                </div>
                                <div>
                                    <label class="fuel-label">Make & Model</label>
                                    <input required class="fuel-control fuel-control-indigo" type="text" name="make_model" placeholder="Ford Transit">
                                </div>
                            </div>
                            <button class="fuel-action-btn fuel-action-btn-indigo w-full py-4 shadow-2xl" type="submit">
                                <i class="fas fa-save text-emerald-400"></i> Save to Fleet
                            </button>
                        </form>
            </div>
        </div>

        <div class="card-base border-none">
            <div class="section-header">
                <h3><i class="fas fa-user-plus text-emerald-400 mr-2"></i> Add Driver</h3>
            </div>
            <div class="p-8">
                        <p class="fuel-section-copy mb-6">Create a team user here first, then use the driver and callout toggles below to control availability.</p>
                        <form id="addUserForm" action="add_user.php" method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="fuel-label">Full Name</label>
                                    <input type="text" class="fuel-control fuel-control-indigo" name="name" required placeholder="John Smith">
                                </div>
                                <div>
                                    <label class="fuel-label">Email Address</label>
                                    <input type="email" class="fuel-control fuel-control-indigo" name="email" required placeholder="email@example.com">
                                </div>
                            </div>
                            <div>
                                <label class="fuel-label">Phone Number</label>
                                <input type="text" class="fuel-control fuel-control-indigo" name="mobile" placeholder="087 123 4567">
                            </div>
                            <button type="submit" class="fuel-action-btn fuel-action-btn-indigo w-full py-4 shadow-2xl">
                                <i class="fas fa-id-card text-indigo-200"></i> Add Driver System
                            </button>
                        </form>
            </div>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-users text-indigo-400"></i> Driver Registry
            </h3>
        </div>
        <div class="table-container">
            <table class="w-full text-sm fuel-table-compact">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">Name</th>
                        <th class="px-6 py-4 text-left">Email</th>
                        <th class="px-6 py-4 text-center">Driver Status</th>
                        <th class="px-6 py-4 text-center">Callout</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="driverRegistryBody" class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
            </table>
        </div>
    </div>
</div>
