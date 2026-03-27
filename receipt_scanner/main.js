const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const queueStatus = document.getElementById('queueStatus');
const queueList = document.getElementById('queueList');
const errorDiv = document.getElementById('error');
const errorMessage = document.getElementById('errorMessage');
const receiptsList = document.getElementById('receiptsList');

const mileageDropZone = document.getElementById('mileageDropZone');
const mileageFileInput = document.getElementById('mileageFileInput');
const mileageQueueStatus = document.getElementById('mileageQueueStatus');
const mileageQueueList = document.getElementById('mileageQueueList');
const mileageList = document.getElementById('mileageList');
const vehicleReg = document.getElementById('vehicleReg');
const fuelLogCaptureForm = document.getElementById('fuelLogCaptureForm');

let uploadQueue = [];
let mileageUploadQueue = [];
let isProcessing = false;
let isProcessingMileage = false;
let allReceipts = [];
let filteredReceipts = [];

// Tab switching
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
    window.setTimeout(notifyParentOfHeight, 50);
}

// File Upload Handlers (Receipts)
dropZone.addEventListener('click', () => fileInput.click());
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
});
fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

// File Upload Handlers (Mileage)
mileageDropZone.addEventListener('click', () => mileageFileInput.click());
mileageDropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    mileageDropZone.classList.add('drag-over');
});
mileageDropZone.addEventListener('dragleave', () => mileageDropZone.classList.remove('drag-over'));
mileageDropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    mileageDropZone.classList.remove('drag-over');
    handleMileageFiles(e.dataTransfer.files);
});
mileageFileInput.addEventListener('change', (e) => handleMileageFiles(e.target.files));

function handleFiles(files) {
    if (!files || files.length === 0) return;

    const allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];

    for (let file of files) {
        if (!allowed_types.includes(file.type)) {
            showError(`Skipped "${file.name}" - Invalid file type. Only JPEG, PNG, and PDF allowed.`);
            continue;
        }

        if (file.size > 10 * 1024 * 1024) {
            showError(`Skipped "${file.name}" - File size exceeds 10MB limit.`);
            continue;
        }

        uploadQueue.push({
            file: file,
            id: Date.now() + Math.random(),
            status: 'queued',
            progress: 0
        });
    }

    if (uploadQueue.length > 0) {
        queueStatus.classList.add('show');
        updateQueueDisplay();
        processQueue();
    }

    fileInput.value = '';
}

// 1. Add better validation for vehicle registration
async function handleMileageFiles(files) {
    if (!files || files.length === 0) return;

    const vehicleRegValue = vehicleReg.value.trim();

    // Better validation for Irish vehicle registration
    if (!vehicleRegValue) {
        showError('Please enter a vehicle registration number first.');
        return;
    }

    // Optional: Validate Irish reg format (e.g., 24D1024, 211D12345)
    const irishRegPattern = /^[0-9]{2,3}[A-Z]{1,2}[0-9]{1,6}$/i;
    if (!irishRegPattern.test(vehicleRegValue)) {
        const result = await Swal.fire({
            title: 'Unusual Registration Format',
            text: `"${vehicleRegValue}" doesn't match typical Irish registration format. Continue anyway?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'No, cancel',
            customClass: {
                popup: 'swal-custom-container',
                title: 'swal-custom-title',
                content: 'swal-custom-content',
                confirmButton: 'swal-custom-confirm-button',
                cancelButton: 'swal-custom-cancel-button'
            }
        });
        if (!result.isConfirmed) {
            return; // Stop processing if user cancels
        }
    }

    const allowed_types = ['image/jpeg', 'image/png'];

    for (let file of files) {
        if (!allowed_types.includes(file.type)) {
            showError(`Skipped "${file.name}" - Invalid file type. Only JPEG and PNG allowed for mileage photos.`);
            continue;
        }

        if (file.size > 10 * 1024 * 1024) {
            showError(`Skipped "${file.name}" - File size exceeds 10MB limit.`);
            continue;
        }

        mileageUploadQueue.push({
            file: file,
            id: Date.now() + Math.random(),
            status: 'queued',
            vehicle_reg: vehicleRegValue
        });
    }

    if (mileageUploadQueue.length > 0) {
        mileageQueueStatus.classList.add('show');
        updateMileageQueueDisplay();
        processMileageQueue();
    }

    mileageFileInput.value = '';
}


function updateQueueDisplay() {
    queueList.innerHTML = uploadQueue.map(item => `
        <div class="queue-item ${item.status}">
            <div>
                <div class="queue-item-name">${escapeHtml(item.file.name)}</div>
                <div class="queue-item-status">
                    ${item.status === 'processing' ? '<span class="spinner-small"></span>Processing...' :
        item.status === 'completed' ? '✓ Completed' :
            item.status === 'failed' ? '✗ Failed: ' + (item.error || 'Unknown error') :
                'Queued'}
                </div>
            </div>
        </div>
    `).join('');
}

function updateMileageQueueDisplay() {
    mileageQueueList.innerHTML = mileageUploadQueue.map(item => `
        <div class="queue-item ${item.status}">
            <div>
                <div class="queue-item-name">${escapeHtml(item.file.name)} (${escapeHtml(item.vehicle_reg)})</div>
                <div class="queue-item-status">
                    ${item.status === 'processing' ? '<span class="spinner-small"></span>Processing...' :
        item.status === 'completed' ? '✓ Completed' :
            item.status === 'failed' ? '✗ Failed: ' + (item.error || 'Unknown error') :
                'Queued'}
                </div>
            </div>
        </div>
    `).join('');
}

async function processQueue() {
    if (isProcessing || uploadQueue.length === 0) return;
    isProcessing = true;

    for (let item of uploadQueue) {
        if (item.status !== 'queued') continue;
        item.status = 'processing';
        updateQueueDisplay();

        try {
            await uploadReceipt(item);
            item.status = 'completed';
        } catch (error) {
            item.status = 'failed';
            item.error = error.message;
        }

        updateQueueDisplay();
        await new Promise(resolve => setTimeout(resolve, 1000));
    }

    isProcessing = false;
    loadReceipts();

    setTimeout(() => {
        uploadQueue = uploadQueue.filter(item => item.status !== 'completed');
        if (uploadQueue.length === 0) {
            queueStatus.classList.remove('show');
        } else {
            updateQueueDisplay();
        }
    }, 3000);
}

async function processMileageQueue() {
    if (isProcessingMileage || mileageUploadQueue.length === 0) return;
    isProcessingMileage = true;

    for (let item of mileageUploadQueue) {
        if (item.status !== 'queued') continue;
        item.status = 'processing';
        updateMileageQueueDisplay();

        try {
            await uploadMileage(item);
            item.status = 'completed';
        } catch (error) {
            item.status = 'failed';
            item.error = error.message;
        }

        updateMileageQueueDisplay();
        await new Promise(resolve => setTimeout(resolve, 1000));
    }

    isProcessingMileage = false;
    loadMileage();

    setTimeout(() => {
        mileageUploadQueue = mileageUploadQueue.filter(item => item.status !== 'completed');
        if (mileageUploadQueue.length === 0) {
            mileageQueueStatus.classList.remove('show');
        } else {
            updateMileageQueueDisplay();
        }
    }, 3000);
}

function uploadReceipt(item) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('receipt', item.file);
        const userEmail = document.getElementById('userEmailDisplay')?.textContent || 'admin@energyretrofitireland.ie';
        formData.append('user_email', userEmail);
        const projectAddress = document.getElementById('projectAddress')?.value;
        if (projectAddress) {
            formData.append('project_address', projectAddress);
        }

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data);
                } else {
                    reject(new Error(data.error || 'Upload failed'));
                }
            })
            .catch(error => {
                reject(new Error('Upload failed: ' + error.message));
            });
    });
}

function uploadMileage(item) {
    return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('mileage_photo', item.file);
        formData.append('vehicle_reg', item.vehicle_reg);
        const userEmail = document.getElementById('userEmailDisplay')?.textContent || 'admin@energyretrofitireland.ie';
        formData.append('user_email', userEmail);

        fetch('', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data);
                } else {
                    reject(new Error(data.error || 'Upload failed'));
                }
            })
            .catch(error => {
                reject(new Error('Upload failed: ' + error.message));
            });
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: message,
        customClass: {
            popup: 'swal-custom-container',
            title: 'swal-custom-title',
            content: 'swal-custom-content',
            confirmButton: 'swal-custom-confirm-button'
        }
    });
}

function bindFuelLogCaptureForm() {
    if (!fuelLogCaptureForm) return;

    fuelLogCaptureForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(fuelLogCaptureForm);

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Failed to create fuel log');
            }

            await Swal.fire({
                icon: 'success',
                title: 'Fuel Log Added',
                text: 'The scanner created a real fuel log entry for the fleet module.',
                confirmButtonText: 'Open Fuel Logs'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.top.location.href = '../fuel/index.php#logs';
                }
            });

            fuelLogCaptureForm.reset();
            loadReceipts();
            loadMileage();
        } catch (error) {
            showError(error.message || 'Failed to create fuel log');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Receipt List Functions
function loadReceipts(receiptsToRender = null) {
    fetch('?action=fetch')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.receipts.length > 0) {
                allReceipts = data.receipts; // Always store all fetched receipts
                renderReceipts(receiptsToRender || allReceipts); // Render filtered or all
            } else {
                allReceipts = [];
                receiptsList.innerHTML = '<p class="scanner-empty-state">No receipts yet. Upload your first receipt!</p>';
                document.getElementById('statTotal').textContent = '0';
                document.getElementById('statConstruction').textContent = '0';
                document.getElementById('statFuel').textContent = '0';
                document.getElementById('statOther').textContent = '0';
                window.setTimeout(notifyParentOfHeight, 50);
            }
        })
        .catch(error => {
            receiptsList.innerHTML = '<p class="scanner-empty-state scanner-empty-state-error">Error loading receipts</p>';
            window.setTimeout(notifyParentOfHeight, 50);
        });
}

function renderReceipts(receiptsToRender) {
    if (receiptsToRender.length > 0) {
        let stats = {
            total: 0,
            construction: 0,
            fuel: 0,
            other: 0
        };

        receiptsToRender.forEach(receipt => {
            stats.total++;
            if (receipt.category === 'Construction') {
                stats.construction++;
            } else if (receipt.category === 'Fuel') {
                stats.fuel++;
            } else {
                stats.other++;
            }
        });

        receiptsList.innerHTML = receiptsToRender.map(receipt => `
                            <div class="receipt-card receipt-card-compact" id="receipt-${receipt.id}">
                                <div class="receipt-header receipt-header-compact">
                                    <div class="receipt-primary">
                                        <span class="merchant">${escapeHtml(receipt.merchant_name)}</span>
                                        <div class="receipt-meta-line">
                                            <span class="receipt-meta-chip"><i class="fas fa-calendar-alt"></i> ${receipt.transaction_date}</span>
                                            ${receipt.project_address ? `<span class="receipt-meta-chip"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(receipt.project_address)}</span>` : ''}
                                        </div>
                                    </div>
                                    <span class="amount">€${parseFloat(receipt.total_amount).toFixed(2)}</span>
                                </div>
                                <div class="receipt-details receipt-details-compact">
                                    <div class="receipt-badge-row">
                                        <span class="badge ${receipt.category === 'Construction' ? 'badge-construction' : receipt.category === 'Fuel' ? 'badge-fuel' : 'badge-category'}">${escapeHtml(receipt.category)}</span>
                                        <span class="badge badge-payment">${escapeHtml(receipt.payment_method)}</span>
                                    </div>
                                    <div class="receipt-confidence"><i class="fas fa-chart-bar"></i> ${(receipt.confidence_score * 100).toFixed(0)}% confidence</div>
                                </div>
                                <div class="receipt-actions receipt-actions-compact">
                                    <button class="receipt-action-btn view-btn" onclick="viewReceiptDetails('${receipt.id}')">Details</button>
                                    <button class="receipt-action-btn view-image-btn" onclick="viewReceiptImageModal('${escapeHtml(receipt.receipt_image)}')">View</button>
                                    <button class="receipt-action-btn delete-btn" onclick="deleteReceipt('${receipt.id}')">Delete</button>
                                </div>
                            </div>
        `).join('');

        document.getElementById('statTotal').textContent = stats.total;
        document.getElementById('statConstruction').textContent = stats.construction;
        document.getElementById('statFuel').textContent = stats.fuel;
        document.getElementById('statOther').textContent = stats.other;
    } else {
        receiptsList.innerHTML = '<p class="scanner-empty-state">No receipts found matching your criteria.</p>';
        document.getElementById('statTotal').textContent = '0';
        document.getElementById('statConstruction').textContent = '0';
        document.getElementById('statFuel').textContent = '0';
        document.getElementById('statOther').textContent = '0';
    }
    window.setTimeout(notifyParentOfHeight, 50);
}

function loadMileage() {
    fetch('?action=fetch_mileage')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.mileage.length > 0) {
                // Sort by created_at descending (newest first)
                const sortedMileage = data.mileage.sort((a, b) => {
                    return new Date(b.created_at) - new Date(a.created_at);
                });

                mileageList.innerHTML = sortedMileage.map(m => {
                    const createdDate = new Date(m.created_at);
                    const dateStr = createdDate.toLocaleDateString('en-IE', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                    const timeStr = createdDate.toLocaleTimeString('en-IE', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    // Format mileage with thousands separator
                    const formattedMileage = parseInt(m.mileage).toLocaleString('en-IE');

                    return `
                    <div class="mileage-card mileage-card-compact" id="mileage-${m.id}">
                        <div class="mileage-header mileage-header-compact">
                            <div class="mileage-primary">
                                <span class="vehicle-reg"><i class="fas fa-car"></i> ${escapeHtml(m.vehicle_reg)}</span>
                                <div class="mileage-meta-line">
                                    <span class="mileage-meta-chip"><i class="fas fa-calendar-alt"></i> ${dateStr} at ${timeStr}</span>
                                    <span class="mileage-meta-chip"><i class="fas fa-user"></i> ${escapeHtml(m.uploaded_by)}</span>
                                </div>
                            </div>
                            <span class="mileage">
                                ${formattedMileage} <span class="mileage-unit">km</span>
                            </span>
                        </div>
                        <div class="mileage-actions mileage-actions-compact">
                            <button class="receipt-action-btn view-btn" onclick="viewMileageImage('${escapeHtml(m.mileage_image)}')">View Image</button>
                            <button class="receipt-action-btn delete-btn" onclick="deleteMileage('${m.id}')">Delete</button>
                        </div>
                    </div>
                `;
                }).join('');
            } else {
                mileageList.innerHTML = `
                    <div class="scanner-empty-state scanner-empty-state-large">
                        <div class="scanner-empty-icon"><i class="fas fa-car"></i></div>
                        <p class="scanner-empty-title">No mileage logs yet</p>
                        <p class="scanner-empty-copy">Upload a photo of your vehicle's odometer to get started</p>
                    </div>
                `;
            }
            window.setTimeout(notifyParentOfHeight, 50);
        })
        .catch(error => {
            console.error('Error loading mileage:', error);
            mileageList.innerHTML = '<p class="scanner-empty-state scanner-empty-state-error">Error loading mileage logs</p>';
            window.setTimeout(notifyParentOfHeight, 50);
        });
}

function setDetailsModalMode(mode) {
    const modal = document.getElementById('detailsModal');
    if (!modal) return;

    modal.classList.remove('details-modal-image', 'details-modal-record');
    modal.classList.add(mode === 'image' ? 'details-modal-image' : 'details-modal-record');
}

// 3. Add function to view mileage image in modal
function viewMileageImage(imageFileName) {
    const modal = document.getElementById('detailsModal');
    const modalBody = document.getElementById('modalBody');
    setDetailsModalMode('image');

    modalBody.innerHTML = `
        <div class="scanner-image-modal-body">
            <img src="uploads/${escapeHtml(imageFileName)}" 
                 alt="Mileage Photo"
                 class="scanner-modal-image">
            <div class="scanner-image-modal-actions">
                <a href="uploads/${escapeHtml(imageFileName)}" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="receipt-action-btn view-btn">
                    Open in New Tab
                </a>
            </div>
        </div>
    `;

    modal.classList.add('show');
}

function viewReceiptImageModal(imageFileName) {
    const modal = document.getElementById('detailsModal');
    const modalBody = document.getElementById('modalBody');
    setDetailsModalMode('image');

    modalBody.innerHTML = `
        <div class="scanner-image-modal-body">
            <img src="uploads/${escapeHtml(imageFileName)}"
                 alt="Receipt Image"
                 class="scanner-modal-image">
            <div class="scanner-image-modal-actions">
                <a href="uploads/${escapeHtml(imageFileName)}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="receipt-action-btn view-btn">
                    Open in New Tab
                </a>
            </div>
        </div>
    `;

    modal.classList.add('show');
}

async function deleteReceipt(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
            popup: 'swal-custom-container',
            title: 'swal-custom-title',
            content: 'swal-custom-content',
            confirmButton: 'swal-custom-confirm-button',
            cancelButton: 'swal-custom-cancel-button'
        }
    });

    if (!result.isConfirmed) {
        return;
    }

    const receiptCard = document.getElementById(`receipt-${id}`);
    if (receiptCard) receiptCard.style.opacity = '0.5';

    const modal = document.getElementById('detailsModal');
    if (modal && modal.classList.contains('show')) closeModal();

    fetch(`?action=delete&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire(
                    'Deleted!',
                    'Your receipt has been deleted.',
                    'success'
                );
                if (receiptCard) {
                    receiptCard.style.transition = 'all 0.3s';
                    receiptCard.style.transform = 'translateX(-100%)';
                    receiptCard.style.opacity = '0';
                    setTimeout(() => loadReceipts(), 300);
                }
            } else {
                showError('Error deleting receipt: ' + (data.error || 'Unknown error'));
                if (receiptCard) {
                    receiptCard.style.opacity = '1';
                }
            }
        })
        .catch(error => {
            showError('Delete error: ' + error.message);
            if (receiptCard) {
                receiptCard.style.opacity = '1';
            }
        });
}

// 4. Enhanced delete with better feedback
async function deleteMileage(id) {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!',
        customClass: {
            popup: 'swal-custom-container',
            title: 'swal-custom-title',
            content: 'swal-custom-content',
            confirmButton: 'swal-custom-confirm-button',
            cancelButton: 'swal-custom-cancel-button'
        }
    });

    if (!result.isConfirmed) {
        return;
    }

    const mileageCard = document.getElementById(`mileage-${id}`);
    if (mileageCard) {
        mileageCard.style.opacity = '0.5';
        mileageCard.style.pointerEvents = 'none';
    }

    fetch(`?action=delete_mileage&id=${encodeURIComponent(id)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire(
                    'Deleted!',
                    'Your mileage log has been deleted.',
                    'success'
                );
                if (mileageCard) {
                    mileageCard.style.transition = 'all 0.3s ease';
                    mileageCard.style.transform = 'translateX(-100%)';
                    mileageCard.style.opacity = '0';
                    setTimeout(() => loadMileage(), 300);
                } else {
                    loadMileage();
                }
            } else {
                showError('Error deleting mileage: ' + (data.error || 'Unknown error'));
                if (mileageCard) {
                    mileageCard.style.opacity = '1';
                    mileageCard.style.pointerEvents = 'auto';
                }
            }
        })
        .catch(error => {
            showError('Delete error: ' + error.message);
            if (mileageCard) {
                mileageCard.style.opacity = '1';
                mileageCard.style.pointerEvents = 'auto';
            }
        });
}

function viewReceiptDetails(id) {
    fetch(`?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                setDetailsModalMode('record');
                const receipt = data.receipt;
                const modalBody = document.getElementById('modalBody');

                let itemsHtml = 'No items found.';
                if (receipt.items && receipt.items.length > 0) {
                    itemsHtml = `
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${receipt.items.map(item => `
                                <tr>
                                    <td>${escapeHtml(item.name)}</td>
                                    <td>${escapeHtml(item.quantity)}</td>
                                    <td>${escapeHtml(item.price)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                }

                modalBody.innerHTML = `
                <div class="modal-section">
                    <h3>Details</h3>
                    <div class="modal-field">
                        <div class="modal-label">Merchant</div>
                        <div class="modal-value">${escapeHtml(receipt.merchant_name)}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">Date</div>
                        <div class="modal-value">${escapeHtml(receipt.transaction_date)}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">Total</div>
                        <div class="modal-value">${escapeHtml(receipt.currency)} ${parseFloat(receipt.total_amount).toFixed(2)}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">Category</div>
                        <div class="modal-value"><span class="badge ${receipt.category === 'Construction' ? 'badge-construction' : receipt.category === 'Fuel' ? 'badge-fuel' : 'badge-category'}">${escapeHtml(receipt.category)}</span></div>
                    </div>
                </div>
                <div class="modal-section">
                    <h3>Additional Info</h3>
                    <div class="modal-field">
                        <div class="modal-label">Subtotal (ex VAT)</div>
                        <div class="modal-value">${receipt.subtotal ? '€' + parseFloat(receipt.subtotal).toFixed(2) : 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">VAT</div>
                        <div class="modal-value">${receipt.tax_amount ? '€' + parseFloat(receipt.tax_amount).toFixed(2) : 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">Tip</div>
                        <div class="modal-value">${receipt.tip_amount ? '€' + parseFloat(receipt.tip_amount).toFixed(2) : 'N/A'}</div>
                    </div>
                    <div class="modal-field">
                        <div class="modal-label">Payment Method</div>
                        <div class="modal-value">${escapeHtml(receipt.payment_method)}</div>
                    </div>
                    ${receipt.project_address ? `
                    <div class="modal-field">
                        <div class="modal-label">Project Address</div>
                        <div class="modal-value">${escapeHtml(receipt.project_address)}</div>
                    </div>
                    ` : ''}
                    ${receipt.gps_latitude && receipt.gps_longitude ? `
                    <div class="modal-field">
                        <div class="modal-label">GPS Location</div>
                        <div class="modal-value">
                            <a href="https://www.google.com/maps/search/?api=1&query=${receipt.gps_latitude},${receipt.gps_longitude}" target="_blank" rel="noopener noreferrer">
                                ${receipt.gps_latitude}, ${receipt.gps_longitude} <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                    ` : ''}
                </div>
                <div class="modal-section modal-section-span">
                    <h3>Items Details</h3>
                    ${itemsHtml}
                </div>
                ${receipt.fuel_type ? `
                <div class="modal-section modal-section-span modal-section-stack modal-section-fuel">
                    <h3 class="modal-section-fuel-title">Fuel Details</h3>
                    <div class="modal-field">
                        <div class="modal-label">Fuel Type</div>
                        <div class="modal-value">${escapeHtml(receipt.fuel_type)}</div>
                    </div>
                    ${receipt.litres ? `<div class="modal-field">
                        <div class="modal-label">Litres</div>
                        <div class="modal-value">${parseFloat(receipt.litres).toFixed(2)} L</div>
                    </div>` : ''}
                    ${receipt.price_per_litre ? `<div class="modal-field">
                        <div class="modal-label">Price per Litre</div>
                        <div class="modal-value">€${parseFloat(receipt.price_per_litre).toFixed(2)}/L</div>
                    </div>` : ''}
                </div>
                ` : ''}
                <div class="modal-section modal-section-span modal-section-stack">
                    <h3>Raw OCR Text</h3>
                    <div class="ocr-text">${escapeHtml(receipt.raw_ocr_text)}</div>
                </div>
            `;
                document.getElementById('detailsModal').classList.add('show');
            } else {
                showError('Error: ' + (data.error || 'Failed to fetch receipt details'));
            }
        })
        .catch(error => {
            showError('Error fetching receipt details: ' + error.message);
        });
}

function closeModal() {
    setDetailsModalMode('record');
    document.getElementById('detailsModal').classList.remove('show');
}

// Filter and Export Functions
function openFilterModal() {
    document.getElementById('filterModal').classList.add('show');
    const today = new Date();
    const twoYearsAgo = new Date(today.getFullYear() - 2, today.getMonth(), today.getDate()); // Two years ago from today
    document.getElementById('filterStartDate').valueAsDate = twoYearsAgo;
    document.getElementById('filterEndDate').valueAsDate = today;
}

function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('show');
}

function closeSummaryModal() {
    document.getElementById('summaryModal').classList.remove('show');
}

function applyFilter() {
    const startDate = document.getElementById('filterStartDate').value;
    const endDate = document.getElementById('filterEndDate').value;
    const category = document.getElementById('filterCategory').value;

    if (!startDate || !endDate) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Dates',
            text: 'Please select both start and end dates.',
            customClass: {
                popup: 'swal-custom-container',
                title: 'swal-custom-title',
                content: 'swal-custom-content',
                confirmButton: 'swal-custom-confirm-button'
            }
        });
        return;
    }

    filteredReceipts = allReceipts.filter(receipt => {
        const receiptDate = receipt.transaction_date;
        const matchesDate = receiptDate >= startDate && receiptDate <= endDate;
        const matchesCategory = !category || receipt.category === category;
        return matchesDate && matchesCategory;
    });

    closeFilterModal();
    displayDailySummary(); // This will show the summary of filtered receipts
    renderReceipts(filteredReceipts); // Update the main receipts list with filtered results
}

function resetFilter() {
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    document.getElementById('filterCategory').value = '';
    filteredReceipts = []; // Clear filtered receipts
    renderReceipts(allReceipts); // Display all receipts again
    closeFilterModal(); // Close the filter modal after resetting
}

function displayDailySummary() {
    if (filteredReceipts.length === 0) {
        alert('No receipts found for the selected date range');
        return;
    }

    const dailyGroups = {};
    filteredReceipts.forEach(receipt => {
        if (!dailyGroups[receipt.transaction_date]) {
            dailyGroups[receipt.transaction_date] = [];
        }
        dailyGroups[receipt.transaction_date].push(receipt);
    });

    const sortedDates = Object.keys(dailyGroups).sort();

    let summaryHtml = '';
    let grandTotal = 0;

    sortedDates.forEach(date => {
        const receipts = dailyGroups[date];
        const dailyTotal = receipts.reduce((sum, r) => sum + parseFloat(r.total_amount), 0);
        grandTotal += dailyTotal;

        const dateObj = new Date(date);
        const dayName = dateObj.toLocaleDateString('en-IE', { weekday: 'long', month: 'short', day: 'numeric' });

        summaryHtml += `
            <div class="daily-summary">
                <div class="daily-summary-date">${dayName}</div>
                <div class="daily-summary-info">
                    <div class="daily-summary-item">Items: ${receipts.length}</div>
                    <div class="daily-summary-item">Categories: ${[...new Set(receipts.map(r => r.category))].join(', ')}</div>
                    <div class="daily-summary-total">€${dailyTotal.toFixed(2)}</div>
                </div>
                <div class="daily-summary-list">
                    ${receipts.map(r => `${escapeHtml(r.merchant_name)} - €${parseFloat(r.total_amount).toFixed(2)}`).join('<br>')}
                </div>
            </div>
        `;
    });

    summaryHtml += `
        <div class="daily-summary daily-summary-period">
            <div class="daily-summary-date">Period Total</div>
            <div class="daily-summary-period-total">€${grandTotal.toFixed(2)}</div>
            <div class="daily-summary-period-meta">
                ${sortedDates.length} days | ${filteredReceipts.length} receipts
            </div>
        </div>
    `;

    document.getElementById('summaryContent').innerHTML = summaryHtml;
    document.getElementById('summaryModal').classList.add('show');
}



// 6. Add stats display for mileage (optional enhancement)
function displayMileageStats() {
    fetch('?action=fetch_mileage')
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.mileage.length === 0) return;

            const vehicles = {};
            data.mileage.forEach(m => {
                if (!vehicles[m.vehicle_reg]) {
                    vehicles[m.vehicle_reg] = [];
                }
                vehicles[m.vehicle_reg].push({
                    mileage: parseInt(m.mileage),
                    date: new Date(m.created_at)
                });
            });

            // Calculate distance traveled per vehicle
            Object.keys(vehicles).forEach(reg => {
                const readings = vehicles[reg].sort((a, b) => a.date - b.date);
                if (readings.length >= 2) {
                    const first = readings[0];
                    const last = readings[readings.length - 1];
                    const distance = last.mileage - first.mileage;
                    console.log(`${reg}: ${distance} km traveled`);
                }
            });
        });
}
// 5. Add export mileage to CSV function
function exportMileageToCSV() {
    fetch('?action=fetch_mileage')
        .then(response => response.json())
        .then(data => {
            if (!data.success || data.mileage.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Data',
                    text: 'No mileage data to export.',
                    customClass: {
                        popup: 'swal-custom-container',
                        title: 'swal-custom-title',
                        content: 'swal-custom-content',
                        confirmButton: 'swal-custom-confirm-button'
                    }
                });
                return;
            }

            let csv = 'Date,Time,Vehicle Registration,Mileage (km),Uploaded By\n';

            data.mileage
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
                .forEach(m => {
                    const date = new Date(m.created_at);
                    const dateStr = date.toLocaleDateString('en-IE');
                    const timeStr = date.toLocaleTimeString('en-IE');

                    csv += `"${dateStr}","${timeStr}","${m.vehicle_reg}",${m.mileage},"${m.uploaded_by}"\n`;
                });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `mileage_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            Swal.fire({
                icon: 'success',
                title: 'Export Successful!',
                text: 'Mileage data exported to CSV.',
                customClass: {
                    popup: 'swal-custom-container',
                    title: 'swal-custom-title',
                    content: 'swal-custom-content',
                    confirmButton: 'swal-custom-confirm-button'
                }
            });
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Export Error',
                text: 'Failed to export mileage data: ' + error.message,
                customClass: {
                    popup: 'swal-custom-container',
                    title: 'swal-custom-title',
                    content: 'swal-custom-content',
                    confirmButton: 'swal-custom-confirm-button'
                }
            });
        });
}

function exportToCSV() {
    if (filteredReceipts.length === 0) {
        Swal.fire({
            icon: 'info',
            title: 'No Data',
            text: 'No receipts to export.',
            customClass: {
                popup: 'swal-custom-container',
                title: 'swal-custom-title',
                content: 'swal-custom-content',
                confirmButton: 'swal-custom-confirm-button'
            }
        });
        return;
    }

    const dailyGroups = {};
    filteredReceipts.forEach(receipt => {
        if (!dailyGroups[receipt.transaction_date]) {
            dailyGroups[receipt.transaction_date] = [];
        }
        dailyGroups[receipt.transaction_date].push(receipt);
    });

    const sortedDates = Object.keys(dailyGroups).sort();

    let csv = 'Date,Merchant,Category,Amount (EUR),Payment Method,Confidence,Project Address,GPS Latitude,GPS Longitude\n'; // Added GPS Latitude, GPS Longitude

    let grandTotal = 0;
    sortedDates.forEach(date => {
        const receipts = dailyGroups[date];
        const dailyTotal = receipts.reduce((sum, r) => sum + parseFloat(r.total_amount), 0);
        grandTotal += dailyTotal;

        receipts.forEach(receipt => {
            csv += `"${receipt.transaction_date}","${receipt.merchant_name}","${receipt.category}",${receipt.total_amount},"${receipt.payment_method}",${(receipt.confidence_score * 100).toFixed(0)}%,"${escapeHtml(receipt.project_address || '')}","${receipt.gps_latitude || ''}","${receipt.gps_longitude || ''}"\n`; // Added GPS Latitude, GPS Longitude
        });

        csv += `"${date} - DAILY TOTAL","","",${dailyTotal.toFixed(2)},"","","","",""\n`; // Added empty fields for Project Address, GPS Latitude, GPS Longitude
        csv += '\n';
    });

    csv += `"PERIOD TOTAL","","",${grandTotal.toFixed(2)},"","","","",""\n`; // Added empty fields for Project Address, GPS Latitude, GPS Longitude

    const element = document.createElement('a');
    const file = new Blob([csv], { type: 'text/csv' });
    element.href = URL.createObjectURL(file);
    element.download = `receipts_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(element);
    element.click();
    document.body.removeChild(element);

    Swal.fire({
        icon: 'success',
        title: 'Export Successful!',
        text: 'Receipt data exported to CSV.',
        customClass: {
            popup: 'swal-custom-container',
            title: 'swal-custom-title',
            content: 'swal-custom-content',
            confirmButton: 'swal-custom-confirm-button'
        }
    });
}

function openInstructionsModal(type) {
    const modal = document.getElementById('instructionsModal');
    const body = document.getElementById('instructionsBody');

    if (type === 'receipts') {
        body.innerHTML = `
            <h3>Tips for Clear Receipt Photos:</h3>
            <ul>
                <li><strong>Good Lighting:</strong> Use bright, even light. Avoid shadows.</li>
                <li><strong>Flat Surface:</strong> Place the receipt on a flat, dark surface.</li>
                <li><strong>Full View:</strong> Capture the entire receipt, from top to bottom.</li>
                <li><strong>Sharp Focus:</strong> Make sure the text is clear and not blurry.</li>
                <li><strong>No Crumples:</strong> Smooth out any folds or wrinkles.</li>
            </ul>
        `;
    } else if (type === 'mileage') {
        body.innerHTML = `
            <h3>Tips for Clear Mileage Photos:</h3>
            <ul>
                <li><strong>Clear View:</strong> Ensure the odometer is clean and easy to read.</li>
                <li><strong>No Glare:</strong> Avoid reflections on the dashboard screen.</li>
                <li><strong>Include Context:</strong> Capture some of the surrounding dashboard for context.</li>
                <li><strong>Sharp Focus:</strong> Make sure the numbers are sharp and not blurry.</li>
            </ul>
        `;
    }

    modal.classList.add('show');
}

function closeInstructionsModal() {
    document.getElementById('instructionsModal').classList.remove('show');
}

function notifyParentOfHeight() {
    const body = document.body;
    const html = document.documentElement;
    const height = Math.max(
        body ? body.scrollHeight : 0,
        body ? body.offsetHeight : 0,
        html ? html.clientHeight : 0,
        html ? html.scrollHeight : 0,
        html ? html.offsetHeight : 0
    );

    if (window.parent && window.parent !== window) {
        window.parent.postMessage({
            type: 'receipt_scanner_height',
            height
        }, window.location.origin);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const receiptsGuideBtn = document.getElementById('receipts-guide-btn');
    if (receiptsGuideBtn) {
        receiptsGuideBtn.addEventListener('click', function() {
            openInstructionsModal('receipts');
        });
    }

    const mileageGuideBtn = document.getElementById('mileage-guide-btn');
    if (mileageGuideBtn) {
        mileageGuideBtn.addEventListener('click', function() {
            openInstructionsModal('mileage');
        });
    }

    const clearProjectAddressBtn = document.getElementById('clearProjectAddressBtn');
    const projectAddressInput = document.getElementById('projectAddress');

    if (clearProjectAddressBtn && projectAddressInput) {
        clearProjectAddressBtn.addEventListener('click', function() {
            projectAddressInput.value = '';
            projectAddressInput.focus(); // Optional: keep focus on the input
        });
    }

    bindFuelLogCaptureForm();
    notifyParentOfHeight();
    window.setTimeout(notifyParentOfHeight, 300);
    window.setTimeout(notifyParentOfHeight, 1000);
});

// Initialize
loadReceipts();
loadMileage();
document.getElementsByClassName('tab-link')[0].click();
window.addEventListener('load', notifyParentOfHeight);
window.addEventListener('resize', notifyParentOfHeight);
