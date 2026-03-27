<div id="vehicle-list-container" class="table-container rounded-2xl border border-slate-200 dark:border-slate-800">
    <table id="reminder" class="w-full text-sm">

        <thead>
            <tr class="table-header-row">
                <th class="px-6 py-4 text-left">Registration</th>
                <th class="px-6 py-4 text-left">Driver</th>
                <th class="px-6 py-4 text-left">Make / Model</th>
                <th class="px-6 py-4 text-left">Compliance Status</th>
                <th class="px-6 py-4 text-right">Actions</th>
            </tr>
        </thead>

        <tbody id="vehicleRegistryBody" class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900/20">
            <tr>
                <td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">Loading fleet overview...</td>
            </tr>
        </tbody>

    </table>
</div>

<style>
    #reminder tbody tr {
        transition: all 0.2s;
    }
</style>
