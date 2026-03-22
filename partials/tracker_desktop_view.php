<!-- DESKTOP TABLE VIEW -->
        <div class="desktop-view card-base relative min-h-[80vh] flex flex-col border-none">
            <div id="saving-indicator" class="hidden fixed top-4 right-4 z-[100] bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-900 px-6 py-3 rounded-full shadow-2xl flex items-center gap-3 text-indigo-600 dark:text-indigo-400 font-bold">
                <i class="fas fa-circle-notch fa-spin"></i> Auto-saving...
            </div>

            <div class="table-container flex-grow min-h-[400px]">
                <table class="w-full text-left border-collapse table-auto relative">
                    <thead class="table-header-row text-center sticky top-0 z-10 shadow-md">
                        <tr>
                            <th class="px-2 py-3 text-center"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)" class="cursor-pointer"></th>
                            <th class="px-2 py-3"><a href="<?php echo sortLink('id', $sort, $order); ?>" class="flex justify-center items-center text-center">ID <?php echo sortIcon('id', $sort, $order); ?></a></th>
                            <th class="px-2 py-3"><a href="<?php echo sortLink('poNumber', $sort, $order); ?>" class="flex justify-center items-center text-center">PO Number <?php echo sortIcon('poNumber', $sort, $order); ?></a></th>
                            <th class="px-2 py-3" style="min-width: 180px;"><a href="<?php echo sortLink('task', $sort, $order); ?>" class="flex justify-center items-center text-center">Task/Job <?php echo sortIcon('task', $sort, $order); ?></a></th>
                            <th class="px-2 py-3"><a href="<?php echo sortLink('clientName', $sort, $order); ?>" class="flex justify-center items-center text-center">Client <?php echo sortIcon('clientName', $sort, $order); ?></a></th>
                            <th class="px-2 py-3"><a href="<?php echo sortLink('contact', $sort, $order); ?>" class="flex justify-center items-center text-center">Contact <?php echo sortIcon('contact', $sort, $order); ?></a></th>
                            <th class="px-2 py-3"><a href="<?php echo sortLink('propertyCode', $sort, $order); ?>" class="flex justify-center items-center text-center">Property Code <?php echo sortIcon('propertyCode', $sort, $order); ?></a></th>
                            <th class="px-2 py-3" style="min-width: 200px;">Project/Location/Site Details</th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('inv_sent', $sort, $order); ?>" class="flex justify-center items-center text-center">Inv Status <?php echo sortIcon('inv_sent', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center">Inv No</th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('assignedTo', $sort, $order); ?>" class="flex justify-center items-center text-center">Assigned <?php echo sortIcon('assignedTo', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('priority', $sort, $order); ?>" class="flex justify-center items-center text-center">Priority <?php echo sortIcon('priority', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('openingDate', $sort, $order); ?>" class="flex justify-center items-center text-center">Open Date <?php echo sortIcon('openingDate', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('dateBooked', $sort, $order); ?>" class="flex justify-center items-center text-center text-blue-200">Booked <?php echo sortIcon('dateBooked', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('nextVisit', $sort, $order); ?>" class="flex justify-center items-center text-center text-orange-200">Next <?php echo sortIcon('nextVisit', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('is_weather_dependent', $sort, $order); ?>" class="flex justify-center items-center text-center">Weather <?php echo sortIcon('is_weather_dependent', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center"><a href="<?php echo sortLink('status', $sort, $order); ?>" class="flex justify-center items-center text-center">Status <?php echo sortIcon('status', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center" style="min-width: 150px;">Remarks</th>
                            <th class="px-2 py-3 text-center">Tag</th>
                            <th class="px-2 py-3 text-center text-center"><a href="<?php echo sortLink('certSent', $sort, $order); ?>" class="flex justify-center items-center text-center text-blue-200">Cert <?php echo sortIcon('certSent', $sort, $order); ?></a></th>
                            <th class="px-2 py-3 text-center text-green-200">WA Sent</th>
                            <th class="px-2 py-3 text-center">Files</th>
                            <th class="px-2 py-3 text-center">Link</th>
                            <th class="px-2 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-700 text-[11px] text-center">
                        <?php 
                        $rowCount = count($workOrders);
                        foreach ($workOrders as $index => $wo): 
                            // Debugging assignedUser
                            echo "<script>console.log('Work Order ID: " . $wo['id'] . "', " . json_encode($wo['assignedUser'] ?? null) . ");</script>";

                            $opDate = ($wo['openingDate'] && $wo['openingDate'] !== '0000-00-00') ? $wo['openingDate'] : '';
                            $bkDate = ($wo['dateBooked'] && $wo['dateBooked'] !== '0000-00-00') ? $wo['dateBooked'] : '';
                            $nxDate = ($wo['nextVisit'] && $wo['nextVisit'] !== '0000-00-00') ? $wo['nextVisit'] : '';
                            
                            // Determine if dropdown should open upwards (last 3 rows if total rows > 3)
                            $dropUp = ($index > $rowCount - 4 && $rowCount > 3);
                        ?>
                        <tr class="table-row-hover" data-id="<?php echo $wo['id']; ?>" style="background-color: <?php echo $wo['rowColor'] ? $wo['rowColor'] : 'transparent'; ?>;">
                            <td class="px-1 py-2 text-center">
                                <?php if (in_array(strtolower($wo['status']), ['completed', 'closed'])): ?>
                                    <input type="checkbox" class="row-checkbox cursor-pointer" value="<?php echo $wo['id']; ?>" data-status="<?php echo strtolower($wo['status']); ?>" data-client="<?php echo htmlspecialchars($wo['invoice_contact'] ?? ''); ?>" onchange="updateBulkState()">
                                <?php endif; ?>
                            </td>
                            <td class="px-1 py-2 text-center text-gray-400 font-bold"><?php echo $wo['id']; ?></td>
                            <td class="px-1 py-2 font-bold text-center transition-all <?php echo ($wo['poNumber'] === '000000' || empty($wo['poNumber']) ? 'bg-red-600 text-white' : ''); ?>">
                                <input type="text" value="<?php echo htmlspecialchars($wo['poNumber']); ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'poNumber', this.value); updatePOStyle(this); this.blur();" class="bg-transparent border-none p-0 w-full focus:ring-0 text-center dark:text-white">
                            </td>
                            <td class="px-1 py-2 text-center">
                                <textarea onchange="updateCell(<?php echo $wo['id']; ?>, 'task', this.value)" class="bg-transparent border-none p-0 w-full focus:ring-0 leading-tight resize-none text-center dark:text-gray-300" rows="2"><?php echo htmlspecialchars($wo['task']); ?></textarea>
                            </td>
                            <td class="px-1 py-2 text-center font-bold text-indigo-600 dark:text-indigo-400">
                                <button onclick="editClient(<?php echo $wo['clientId']; ?>)" class="hover:underline focus:outline-none">
                                    <?php echo htmlspecialchars($wo['clientName'] ?: $wo['property'] ?: '---'); ?>
                                </button>
                            </td>
                            <td class="px-1 py-2 text-center"><input type="tel" value="<?php echo htmlspecialchars($wo['contact']); ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'contact', this.value)" class="bg-transparent border-none p-0 w-full focus:ring-0 text-center dark:text-gray-300"></td>
                            <td class="px-1 py-2 text-center"><input type="text" value="<?php echo htmlspecialchars($wo['propertyCode']); ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'propertyCode', this.value)" class="bg-transparent border-none p-0 w-full focus:ring-0 text-center uppercase dark:text-gray-300"></td>
                            <td class="px-1 py-2 text-center">
                                <textarea onchange="updateCell(<?php echo $wo['id']; ?>, 'location', this.value)" class="bg-transparent border-none p-0 w-full focus:ring-0 leading-tight resize-none italic text-gray-500 dark:text-gray-400 text-center text-[10px]" rows="2"><?php echo htmlspecialchars($wo['location']); ?></textarea>
                            </td>
                            <td class="px-1 py-2 text-center">
                                <div class="inline-block px-2 py-0.5 rounded-full border text-[9px] font-bold uppercase transition-all <?php echo getYesNoColor($wo['invoiceSent']); ?>">
                                    <select onchange="updateCell(<?php echo $wo['id']; ?>, 'invoiceSent', this.value); updateBadgeStyle(this, 'yesno');" class="bg-transparent border-none p-0 focus:ring-0 cursor-pointer text-center">
                                        <?php foreach ($yesNoOptions as $o): ?><option value="<?php echo $o; ?>" <?php echo $wo['invoiceSent']==$o?'selected':'';?>><?php echo $o;?></option><?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                            <td class="px-1 py-2 text-center font-medium">
                                <div class="flex flex-col items-center gap-1">
                                    <input type="text" value="<?php echo htmlspecialchars($wo['invoiceNo'] ?? ''); ?>" 
                                           onchange="updateCell(<?php echo $wo['id']; ?>, 'invoiceNo', this.value)" 
                                           class="bg-transparent border-none p-0 w-full focus:ring-0 text-center text-gray-600 dark:text-gray-400 font-medium"
                                           placeholder="...">
                                    <?php if (!empty($wo['xero_invoice_id'])): ?>
                                        <a href="https://go.xero.com/AccountsReceivable/View.aspx?InvoiceID=<?php echo htmlspecialchars($wo['xero_invoice_id']); ?>" 
                                           target="_blank" rel="noopener noreferrer" class="text-[9px] text-blue-600 dark:text-blue-400 hover:underline font-bold" title="Open in Xero">
                                            <i class="fas fa-external-link-alt"></i> Xero
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-1 py-2 text-center">
                                <div class="flex items-center gap-1 justify-center">
                                    <select onchange="const publicBaseUrl = <?php echo json_encode(trackerAppUrl()); ?>; const newUrl = this.options[this.selectedIndex].dataset.hash && publicBaseUrl ? publicBaseUrl + '/public/public_view.php?hash=' + this.options[this.selectedIndex].dataset.hash : '#'; updateCell(<?php echo $wo['id']; ?>, 'assignedTo', this.value); this.closest('tr').querySelector('.public-link-cell a').href = newUrl;" class="bg-transparent border-none p-0 focus:ring-0 text-center text-[10px] w-full dark:text-gray-200">
                                        <option value="" class="dark:bg-slate-900">Unassigned</option>
                                        <?php foreach ($users as $u): ?>
                                            <option value="<?php echo $u['id']; ?>" class="dark:bg-slate-900" <?php echo (($wo['assignedTo'] ?? $wo['assigned_to'] ?? 0) == $u['id']) ? 'selected' : ''; ?> data-hash="<?php echo htmlspecialchars($u['hash'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($u['name']) . (!empty($u['is_subcontractor']) ? ' (Sub)' : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="wa-btn-container-<?php echo $wo['id']; ?>" class="<?php echo empty($wo['assignedTo'] ?? $wo['assigned_to'] ?? '') ? 'hidden' : ''; ?>">
                                        <button onclick="sendJobWhatsApp(<?php echo $wo['id']; ?>)" class="text-green-500 hover:text-green-700 ml-1" title="Send WhatsApp Details"><i class="fab fa-whatsapp"></i></button>
                                    </div>
                                </div>
                            </td>
                                        <td class="px-1 py-2 text-center transition-all <?php echo getPriorityColor($wo['priority']); ?>">
                                        <select onchange="updateCell(<?php echo $wo['id']; ?>, 'priority', this.value); updateBadgeStyle(this, 'priority'); this.blur();" class="bg-transparent border-none p-0 w-full text-center focus:ring-0 cursor-pointer text-[9px] font-bold uppercase">
                                        <?php foreach ($priorityOptions as $o): 
                                        $bg = ''; $txt = '';
                                        switch(strtolower($o)) {
                                            case 'urgent': $bg='#dc2626'; $txt='#ffffff'; break;
                                            case 'high': $bg='#f97316'; $txt='#ffffff'; break;
                                            case 'medium': $bg='#0d9488'; $txt='#ffffff'; break;
                                            case 'low': $bg='#16a34a'; $txt='#ffffff'; break;
                                            default: $bg='#4b5563'; $txt='#ffffff';
                                        }
                                        ?>
                                        <option value="<?php echo $o; ?>" style="background-color:<?php echo $bg; ?>; color:<?php echo $txt; ?>;" <?php echo (strtolower($wo['priority']) == strtolower($o)) ? 'selected' : ''; ?>><?php echo $o;?></option>
                                        <?php endforeach; ?>
                                        </select>
                                        </td>
                                        <td class="px-1 py-2 text-center">
                                        <input type="text" value="<?= $opDate ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'openingDate', this.value)" class="datepicker bg-transparent border-none p-0 text-[9px] w-full text-center">
                                        </td>
                                        <td class="px-1 py-2 text-center">
                                        <input type="text" value="<?= $bkDate ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'dateBooked', this.value)" class="datepicker bg-transparent border-none p-0 text-[9px] text-blue-600 font-bold w-full text-center">
                                        </td>
                                        <td class="px-1 py-2 text-center">
                                        <input type="text" value="<?= $nxDate ?>" onchange="updateCell(<?php echo $wo['id']; ?>, 'nextVisit', this.value)" class="datepicker bg-transparent border-none p-0 text-[9px] text-orange-600 font-bold w-full text-center">
                                        </td>
                                        <td class="px-1 py-2 text-center">
                                        <button onclick="openWeatherForecastModal(<?php echo $wo['id']; ?>)" 
                                        class="weather-icon-btn text-lg p-1 rounded-full transition-colors duration-200 
                                               <?php echo $wo['is_weather_dependent'] ? 'text-yellow-500 hover:text-yellow-700 bg-yellow-100' : 'text-gray-400 hover:text-gray-600'; ?>"
                                        title="<?php echo $wo['is_weather_dependent'] ? 'Weather Dependent (Click to open forecast)' : 'Not Weather Dependent (Click to open forecast)'; ?>">
                                        <i class="fas fa-cloud-showers-heavy"></i>
                                        </button>
                                        </td>
                                        <td class="px-1 py-2 text-center transition-all <?php echo getStatusColor($wo['status']); ?>">
                                        <select onchange="updateCell(<?php echo $wo['id']; ?>, 'status', this.value); updateBadgeStyle(this, 'status');" class="bg-transparent border-none p-0 w-full text-center focus:ring-0 cursor-pointer text-[9px] font-bold uppercase">
                                        <?php foreach ($statusOptions as $o): ?><option value="<?php echo $o; ?>" <?php echo (strtolower($wo['status']) == strtolower($o)) ? 'selected' : ''; ?>><?php echo $o; ?></option><?php endforeach; ?>
                                        </select>
                                        </td>
                                        <td class="px-1 py-2 text-center"><textarea onchange="updateCell(<?php echo $wo['id']; ?>, 'remarks', this.value)" class="bg-transparent border-none p-0 w-full focus:ring-0 leading-tight resize-none text-[10px] text-center text-red-600 font-bold" rows="2"><?php echo htmlspecialchars($wo['remarks']); ?></textarea></td>
                                        <td class="px-1 py-2 text-center <?php echo !empty($wo['tags']) ? 'bg-green-100' : ''; ?>">
                                        <select <?php echo $isSubcontractorView ? 'disabled' : ''; ?> 
                                        onchange="updateCell(<?php echo $wo['id']; ?>, 'tags', this.value); if(this.value) { this.parentElement.classList.add('bg-green-100'); } else { this.parentElement.classList.remove('bg-green-100'); }" 
                                        class="bg-transparent border-none p-0 focus:ring-0 text-center text-[10px] w-full font-bold <?php echo $isSubcontractorView ? 'text-gray-400 cursor-not-allowed' : 'text-gray-600 cursor-pointer'; ?>">
                                        <option value="">None</option>
                                        <?php foreach ($subcontractors as $sub): ?>
                                        <option value="<?php echo $sub['id']; ?>" <?php echo (isset($wo['tags']) && $wo['tags'] == $sub['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sub['name']); ?></option>
                                        <?php endforeach; ?>
                                        </select>
                                        </td>
                                        <td class="px-1 py-2 text-center transition-all <?php echo getYesNoColor($wo['certSent']); ?>">
                                        <select onchange="updateCell(<?php echo $wo['id']; ?>, 'certSent', this.value); updateBadgeStyle(this, 'yesno');" class="bg-transparent border-none p-0 focus:ring-0 cursor-pointer text-center w-full text-[9px] font-bold uppercase">
                                        <?php foreach ($yesNoOptions as $o): ?><option value="<?php echo $o; ?>" <?php echo $wo['certSent']==$o?'selected':'';?>><?php echo $o;?></option><?php endforeach; ?>
                                        </select>
                                        </td>
                                        <td class="px-1 py-2 text-center transition-all <?php echo getYesNoColor($wo['whatsappSent']); ?>">
                                        <select onchange="updateCell(<?php echo $wo['id']; ?>, 'whatsappSent', this.value); updateBadgeStyle(this, 'yesno');" class="bg-transparent border-none p-0 focus:ring-0 cursor-pointer text-center w-full text-[9px] font-bold uppercase">
                                        <?php foreach ($yesNoOptions as $o): ?><option value="<?php echo $o; ?>" <?php echo $wo['whatsappSent']==$o?'selected':'';?>><?php echo $o;?></option><?php endforeach; ?>
                                        </select>
                                        </td>
                                        <td class="px-1 py-2 text-center">
                                        <?php if ($wo['fileCount'] > 0): ?>
                                        <button onclick="showFiles('<?php echo $wo['id']; ?>', '<?php echo urlencode($wo['poNumber']); ?>')" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded border border-indigo-200 font-bold hover:bg-indigo-100 transition-colors text-[9px]"><i class="fas fa-paperclip"></i> <?php echo $wo['fileCount']; ?></button>
                                        <?php else: ?><span class="text-gray-300 font-bold text-center text-[10px]">0</span><?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center public-link-cell">
                                            <?php if (!empty($wo['assignedUser']) && !empty($wo['assignedUser']['hash'])): ?>
                                                <a href="<?php echo htmlspecialchars(trackerAppUrl() . '/public/public_view.php?hash=' . $wo['assignedUser']['hash']); ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:text-indigo-900" title="Open Public Link"><i class="fas fa-link"></i></a>
                                            <?php endif; ?>
                                        </td>                            <td class="px-1 py-2 text-center whitespace-nowrap">
                                <div class="relative inline-block text-left group/action">
                                    <button class="p-2 text-gray-400 hover:text-indigo-600 transition-colors">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    
                                    <div class="hidden group-hover/action:block absolute right-0 <?php echo $dropUp ? 'bottom-0 mb-8' : 'top-0 mt-8'; ?> w-48 bg-white border border-gray-200 rounded-lg shadow-2xl z-[100] py-2">
                                        <!-- Row Color Picker -->
                                        <div class="px-4 py-2 border-b border-gray-50 flex items-center justify-between mb-1">
                                            <span class="text-[9px] font-bold text-gray-400 uppercase">Row Color</span>
                                            <div class="flex gap-1">
                                                <button onclick="setRowColor(<?php echo $wo['id']; ?>, '')" class="w-3 h-3 rounded-full border border-gray-200 hover:scale-125 transition-transform flex items-center justify-center bg-white" title="Remove Color">
                                                    <i class="fas fa-times text-[6px] text-gray-400"></i>
                                                </button>
                                                <?php foreach(['#ffffff','#fee2e2','#dcfce7','#fef9c3','#dbeafe'] as $c): ?>
                                                    <button onclick="setRowColor(<?php echo $wo['id']; ?>, '<?php echo $c; ?>')" class="w-3 h-3 rounded-full border border-gray-200 hover:scale-125 transition-transform" style="background-color:<?php echo $c;?>"></button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <button onclick="openSummary(<?php echo $wo['id']; ?>)" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-3 transition-colors">
                                            <i class="fas fa-id-card w-4 text-blue-400"></i> <span class="text-[11px] font-medium">Job Summary</span>
                                        </button>

                                        <?php if ((strtolower($wo['status']) === 'completed' || strtolower($wo['status']) === 'closed') && empty($wo['invoiceNo'])): ?>
                                            <button onclick="generateXeroInvoice(<?php echo $wo['id']; ?>)" class="w-full text-left px-4 py-2 text-green-600 hover:bg-green-50 flex items-center gap-3 transition-colors">
                                                <i class="fas fa-file-invoice-dollar w-4"></i> <span class="text-[11px] font-bold">Generate Xero</span>
                                            </button>
                                        <?php endif; ?>

                                        <button onclick="showHistory(<?php echo $wo['id']; ?>)" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-3 transition-colors">
                                            <i class="fas fa-history w-4 text-gray-400"></i> <span class="text-[11px] font-medium">History</span>
                                        </button>

                                        <a href="tracker_form.php?id=<?php echo $wo['id']; ?>" class="w-full text-left px-4 py-2 text-indigo-600 hover:bg-indigo-50 flex items-center gap-3 transition-colors no-underline">
                                            <i class="fas fa-edit w-4"></i> <span class="text-[11px] font-medium">Edit Order</span>
                                        </a>

                                        <div class="border-t border-gray-100 mt-1 pt-1">
                                            <button onclick="deleteOrder(<?php echo $wo['id']; ?>)" class="w-full text-left px-4 py-2 text-red-600 hover:bg-red-50 flex items-center gap-3 transition-colors">
                                                <i class="fas fa-trash-alt w-4"></i> <span class="text-[11px] font-medium">Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Action Bar -->
            <div id="bulk-action-bar" class="hidden absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-full shadow-2xl z-[50] flex items-center gap-4">
                <span class="text-xs font-bold"><span id="selected-count">0</span> Selected</span>
                <div class="h-4 w-px bg-gray-600"></div>
                <button onclick="generateBulkXeroInvoice()" class="text-xs font-bold text-green-400 hover:text-green-300 flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar"></i> Create Group Invoice
                </button>
            </div>

            <!-- Pagination Footer -->
            <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex flex-col md:flex-row items-center justify-between gap-4 text-center">
                <div class="text-[10px] text-gray-500 uppercase font-bold text-center w-full md:w-auto">Showing <?php echo $offset + 1; ?>-<?php echo min($offset+$limit, $totalRecords); ?> of <?php echo $totalRecords; ?> records</div>
                <div class="flex gap-1 justify-center items-center mx-auto">
                    <?php if ($page > 1): ?><a href="?page=<?php echo $page-1;?>&<?php echo http_build_query(['search'=>$search,'sort'=>$sort,'order'=>$order]);?>" class="px-2 py-1 bg-white border rounded text-xs hover:bg-gray-100">Prev</a><?php endif; ?>
                    <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
                        <a href="?page=<?php echo $i;?>&<?php echo http_build_query(['search'=>$search,'sort'=>$sort,'order'=>$order]);?>" class="px-2 py-1 border rounded text-xs <?php echo $page==$i?'bg-indigo-600 text-white border-indigo-600':'bg-white text-gray-700 hover:bg-gray-100';?> text-center min-w-[24px]"><?php echo $i;?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1;?>&<?php echo http_build_query(['search'=>$search,'sort'=>$sort,'order'=>$order]);?>" class="px-2 py-1 bg-white border rounded text-xs hover:bg-gray-100">Next</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
