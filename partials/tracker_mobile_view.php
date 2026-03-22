<!-- MOBILE CARD VIEW -->
<div class="mobile-view text-left">
    <div class="space-y-4">
        <?php if (empty($workOrders)): ?>
            <div class="card-base p-12 text-center text-gray-500">
                <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                <p class="font-black uppercase tracking-widest text-xs">No work orders found</p>
            </div>
        <?php else: ?>
            <?php foreach ($workOrders as $wo): 
                $borderColor = 'border-indigo-600';
                if (strtolower($wo['priority']) === 'urgent') $borderColor = 'border-red-600';
                elseif (strtolower($wo['priority']) === 'high') $borderColor = 'border-orange-500';
            ?>
            <div class="card-base border-l-4 <?php echo $borderColor; ?> flex flex-col"
                 style="background-color: <?php echo $wo['rowColor'] ?: 'var(--card-bg)'; ?>;">
                
                <!-- Card Header -->
                <div class="p-4 border-b border-gray-100 dark:border-slate-700">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">#<?php echo $wo['id']; ?></span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest <?php echo getPriorityColor($wo['priority']); ?>">
                                    <?php echo $wo['priority']; ?>
                                </span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest <?php echo getStatusColor($wo['status']); ?>">
                                    <?php echo $wo['status']; ?>
                                </span>
                            </div>
                            <h3 class="font-black text-base italic uppercase tracking-tighter <?php echo ($wo['poNumber'] === '000000' || empty($wo['poNumber'])) ? 'text-red-600' : 'text-gray-900 dark:text-white'; ?>">
                                PO: <?php echo htmlspecialchars($wo['poNumber']); ?>
                                <?php if ($wo['fileCount'] > 0): ?>
                                    <button onclick="showFiles('<?php echo $wo['id']; ?>', '<?php echo urlencode($wo['poNumber']); ?>')" class="inline-flex items-center gap-1 px-2 py-0.5 ml-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg border border-indigo-100 dark:border-indigo-800 font-black text-[9px] uppercase tracking-widest">
                                        <i class="fas fa-paperclip"></i> <?php echo $wo['fileCount']; ?>
                                    </button>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="openSummary(<?php echo $wo['id']; ?>)" class="w-10 h-10 flex items-center justify-center bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-xl transition-all"><i class="fas fa-id-card"></i></button>
                            <button onclick="toggleDetails(<?php echo $wo['id']; ?>)" class="w-10 h-10 flex items-center justify-center bg-gray-50 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-xl transition-all">
                                <i class="fas fa-chevron-down chevron-rotate" id="chevron-<?php echo $wo['id']; ?>"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="p-4 space-y-3">
                    <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                        <strong class="text-gray-900 dark:text-white font-black uppercase text-[10px] tracking-widest mr-1">Task:</strong> 
                        <?php echo nl2br(htmlspecialchars($wo['task'])); ?>
                    </div>
                    <?php if ($wo['property']): ?>
                    <div class="text-xs text-indigo-700 dark:text-indigo-400 font-black uppercase tracking-wider flex items-start gap-2">
                        <i class="fas fa-building mt-0.5 opacity-50"></i> 
                        <button onclick="editClient(<?php echo $wo['clientId']; ?>)" class="hover:underline focus:outline-none text-left">
                            <?php echo htmlspecialchars($wo['property']); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php if ($wo['location']): ?>
                    <div class="text-[11px] text-gray-500 dark:text-gray-400 italic flex items-start gap-2 leading-relaxed">
                        <i class="fas fa-map-marker-alt mt-0.5 opacity-50"></i> <span><?php echo htmlspecialchars($wo['location']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                        <div class="flex-1 flex items-center gap-3 bg-gray-50 dark:bg-slate-900/50 p-2 rounded-xl border border-gray-100 dark:border-slate-800">
                            <i class="fas fa-user-ninja text-indigo-600 dark:text-indigo-400 ml-1"></i>
                            <select onchange="updateCell(<?php echo $wo['id']; ?>, 'assignedTo', this.value)" class="bg-transparent border-none p-0 focus:ring-0 text-xs font-bold w-full text-gray-700 dark:text-gray-200">
                                <option value="" class="dark:bg-slate-900">Unassigned</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" class="dark:bg-slate-900" <?php echo ($wo['assignedTo'] == $u['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name']) . (!empty($u['is_subcontractor']) ? ' (Sub)' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="wa-btn-container-mobile-<?php echo $wo['id']; ?>" class="<?php echo empty($wo['assignedTo']) ? 'hidden' : ''; ?> ml-3">
                            <button onclick="sendJobWhatsApp(<?php echo $wo['id']; ?>)" class="w-10 h-10 bg-emerald-500 text-white rounded-xl shadow-lg hover:bg-emerald-600 flex items-center justify-center transition-all active:scale-95">
                                <i class="fab fa-whatsapp text-lg"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Details (Hidden) -->
                <div id="details-<?php echo $wo['id']; ?>" class="hidden p-4 bg-gray-50 dark:bg-slate-900/30 border-t border-gray-200 dark:border-slate-700 space-y-4 text-xs font-medium">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1"><span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Contact</span> <span class="text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($wo['contact'] ?: 'N/A'); ?></span></div>
                        <div class="space-y-1"><span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Property Code</span> <span class="text-gray-900 dark:text-gray-200 uppercase font-bold"><?php echo htmlspecialchars($wo['propertyCode'] ?: 'N/A'); ?></span></div>
                        <div class="space-y-1"><span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Date Booked</span> <span class="text-blue-600 dark:text-blue-400 font-bold"><?php echo ($wo['dateBooked'] && $wo['dateBooked'] !== '0000-00-00') ? $wo['dateBooked'] : 'N/A'; ?></span></div>
                        <div class="space-y-1">
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Assigned Tag</span> 
                            <select <?php echo $isSubcontractorView ? 'disabled' : ''; ?> 
                                    onchange="updateCell(<?php echo $wo['id']; ?>, 'tags', this.value)" 
                                    class="bg-transparent border-none p-0 focus:ring-0 text-xs font-black uppercase tracking-widest <?php echo $isSubcontractorView ? 'text-gray-400' : 'text-indigo-600 dark:text-indigo-400'; ?>">
                                <option value="" class="dark:bg-slate-900">None</option>
                                <?php foreach ($subcontractors as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>" class="dark:bg-slate-900" <?php echo (isset($wo['tags']) && $wo['tags'] == $sub['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1"><span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Next Visit</span> <span class="text-orange-600 dark:text-orange-400 font-bold"><?php echo ($wo['nextVisit'] && $wo['nextVisit'] !== '0000-00-00') ? $wo['nextVisit'] : 'N/A'; ?></span></div>
                        <div class="space-y-1"><span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Invoiced</span> <span class="font-black uppercase tracking-tighter <?php echo $wo['invoiceSent'] === 'Yes' ? 'text-emerald-600' : 'text-gray-400'; ?>"><?php echo $wo['invoiceSent']; ?></span></div>
                    </div>
                    
                    <?php if ($wo['remarks']): ?>
                    <div class="p-3 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 rounded-xl">
                        <span class="text-[9px] font-black uppercase tracking-widest text-red-400 block mb-1">Internal Remarks</span>
                        <div class="text-red-600 dark:text-red-400 font-bold italic"><?php echo nl2br(htmlspecialchars($wo['remarks'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-3 gap-2 pt-2">
                        <a href="tracker_form.php?id=<?php echo $wo['id']; ?>" class="flex flex-col items-center gap-1 p-3 bg-indigo-600 text-white rounded-2xl transition-all active:scale-95">
                            <i class="fas fa-edit"></i> <span class="text-[9px] font-black uppercase tracking-widest">Edit</span>
                        </a>
                        <?php if ((strtolower($wo['status']) === 'completed' || strtolower($wo['status']) === 'closed') && empty($wo['invoiceNo'])): ?>
                            <button onclick="generateXeroInvoice(<?php echo $wo['id']; ?>)" class="flex flex-col items-center gap-1 p-3 bg-emerald-600 text-white rounded-2xl transition-all active:scale-95">
                                <i class="fas fa-file-invoice-dollar"></i> <span class="text-[9px] font-black uppercase tracking-widest">Inv</span>
                            </button>
                        <?php else: ?>
                            <button onclick="showHistory(<?php echo $wo['id']; ?>)" class="flex flex-col items-center gap-1 p-3 bg-gray-600 text-white rounded-2xl transition-all active:scale-95">
                                <i class="fas fa-history"></i> <span class="text-[9px] font-black uppercase tracking-widest">Logs</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (!in_array(strtolower($wo['status']), ['completed', 'closed', 'cancelled'])): ?>
                            <button onclick="if(confirm('Mark job as completed?')) updateCell(<?php echo $wo['id']; ?>, 'status', 'completed')" class="flex flex-col items-center gap-1 p-3 bg-emerald-500 text-white rounded-2xl shadow-emerald-200/50 shadow-lg transition-all active:scale-95 font-black uppercase">
                                <i class="fas fa-check-circle"></i> <span class="text-[9px] tracking-widest">Done</span>
                            </button>
                        <?php else: ?>
                            <button onclick="showHistory(<?php echo $wo['id']; ?>)" class="flex flex-col items-center gap-1 p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-2xl transition-all active:scale-95">
                                <i class="fas fa-history"></i> <span class="text-[9px] font-black uppercase tracking-widest">Hist</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Card Footer -->
                <div class="px-4 py-3 bg-gray-50 dark:bg-slate-800/50 flex justify-between items-center">
                    <div class="text-xs text-gray-500 dark:text-gray-400">ID: <?php echo $wo['id']; ?></div>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($wo['assignedUser']) && !empty($wo['assignedUser']['hash'])): ?>
                            <a href="<?php echo htmlspecialchars(trackerAppUrl() . '/public/public_view.php?hash=' . $wo['assignedUser']['hash']); ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-800 text-sm" title="Open Public Link"><i class="fas fa-link"></i></a>
                        <?php endif; ?>
                        <button onclick="openSummary(<?php echo $wo['id']; ?>)" class="text-blue-600 hover:text-blue-800 text-sm" title="View Summary"><i class="fas fa-eye"></i></button>
                        <button onclick="showHistory(<?php echo $wo['id']; ?>)" class="text-gray-500 hover:text-gray-700 text-sm" title="View History"><i class="fas fa-history"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
