<?php
require_once __DIR__ . '/../config.php';
$hash = $_GET['h'] ?? '';
if (empty($hash)) {
    die("Invalid link provided.");
}
$pageTitle = "Work Order Details";
include_once __DIR__ . '/../header.php'; 
?>

<div class="bg-gray-50 dark:bg-slate-950 min-h-screen pb-20">
    <div class="max-w-xl mx-auto px-4 py-8">
        
        <!-- Header / Status -->
        <div id="loader" class="py-20 text-center">
            <div class="inline-block animate-spin w-10 h-10 border-4 border-indigo-200 border-t-indigo-600 rounded-full mb-4"></div>
            <p class="text-sm font-black uppercase tracking-widest text-gray-400">Loading work order...</p>
        </div>

        <div id="error-message" class="hidden py-20 text-center">
            <i class="fas fa-exclamation-triangle text-red-500 text-4xl mb-4"></i>
            <p class="text-lg font-bold text-red-600 dark:text-red-400">Task Not Found</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">The link may be invalid or has expired.</p>
        </div>

        <div id="task-content" class="hidden space-y-6">
            <!-- Task Info Card -->
            <div class="card-base p-6 border-none shadow-xl relative overflow-hidden">
                <div id="status-badge" class="absolute top-0 right-0 px-4 py-2 rounded-bl-2xl text-[10px] font-black uppercase tracking-widest"></div>
                
                <div class="mb-6">
                    <span class="text-xs font-black uppercase tracking-[0.2em] text-indigo-500 dark:text-indigo-400 mb-2 block">Work Order Ref</span>
                    <h1 id="task-po" class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter italic uppercase"></h1>
                </div>

                <div class="space-y-4">
                    <div>
                        <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-indigo-500"></i> Location
                        </h2>
                        <p id="task-location" class="text-sm font-bold text-gray-800 dark:text-gray-200 leading-relaxed"></p>
                    </div>

                    <div>
                        <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2 flex items-center gap-2">
                            <i class="fas fa-align-left text-indigo-500"></i> Requirements
                        </h2>
                        <div id="task-heading" class="p-4 bg-gray-50 dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-slate-800 text-sm font-medium italic text-gray-600 dark:text-gray-400"></div>
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    <a id="btn-directions" href="#" target="_blank" rel="noopener noreferrer" class="flex items-center justify-center gap-2 py-4 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 hover:bg-gray-50 transition-all shadow-sm">
                        <i class="fas fa-directions text-indigo-500"></i> Directions
                    </a>
                    <button id="btn-complete" onclick="markTaskComplete()" class="flex items-center justify-center gap-2 py-4 bg-indigo-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all shadow-lg shadow-indigo-500/20">
                        <i class="fas fa-check-circle"></i> Mark Done
                    </button>
                </div>
            </div>

            <!-- Upload Section -->
            <div class="card-base p-6 border-none shadow-lg">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4 flex items-center gap-2">
                    <i class="fas fa-camera text-indigo-500"></i> Upload Photos / Proof
                </h2>
                
                <div class="p-8 bg-indigo-50/50 dark:bg-indigo-900/10 border-2 border-dashed border-indigo-200 dark:border-indigo-800/50 rounded-3xl text-center relative group transition-all hover:border-indigo-400">
                    <label for="image-upload" class="cursor-pointer block">
                        <div class="w-16 h-16 bg-white dark:bg-slate-900 rounded-2xl shadow-sm flex items-center justify-center text-indigo-600 dark:text-indigo-400 mx-auto mb-4 group-hover:scale-110 transition-transform">
                            <i class="fas fa-cloud-upload-alt text-2xl"></i>
                        </div>
                        <p class="text-xs font-black uppercase tracking-widest text-indigo-900 dark:text-indigo-300">Snap or Pick Images</p>
                        <p class="text-[10px] text-gray-400 mt-1 italic">Completion photos, receipts, etc.</p>
                        <input type="file" id="image-upload" class="hidden" multiple accept="image/*" onchange="uploadFiles(this)">
                    </label>
                    <div id="upload-progress" class="hidden mt-6 h-2 w-full bg-gray-200 dark:bg-slate-800 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-600 transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <!-- Attachments -->
            <div id="attachments-section" class="hidden space-y-3">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 px-2 flex items-center gap-2">
                    <i class="fas fa-paperclip text-indigo-500"></i> Documents & Photos
                </h2>
                <div id="attachments-list" class="grid grid-cols-1 gap-2">
                    <!-- Files will be listed here -->
                </div>
            </div>

            <!-- Remarks -->
            <div class="card-base p-6 border-none shadow-lg">
                <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-4 flex items-center gap-2">
                    <i class="fas fa-comment-dots text-indigo-500"></i> Update Remarks
                </h2>
                <textarea id="remark-text" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-100 dark:border-slate-800 rounded-2xl text-sm font-medium focus:ring-2 focus:ring-indigo-500 outline-none transition-all dark:text-white" placeholder="Type your update here..."></textarea>
                <button onclick="submitRemark()" class="w-full mt-3 py-4 bg-gray-900 dark:bg-slate-800 text-white dark:text-gray-200 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all shadow-md">
                    Save Update
                </button>
                <div id="existing-remarks" class="mt-6 pt-6 border-t border-gray-100 dark:border-slate-800 text-[11px] text-gray-500 dark:text-gray-400 font-mono whitespace-pre-wrap leading-relaxed"></div>
            </div>

        </div>
    </div>
</div>

<script>
const taskHash = <?php echo json_encode($hash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const laravelApiUrl = '<?php echo $_ENV["LARAVEL_API_URL"]; ?>';
let currentTaskId = null;
let currentPO = '';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', function() {
    loadTaskDetails();
});

function loadTaskDetails() {
    fetch(`${laravelApiUrl}/api/public/task/${taskHash}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('loader').classList.add('hidden');
            if (!data.success) {
                document.getElementById('error-message').classList.remove('hidden');
                return;
            }

            const task = data.task;
            currentTaskId = task.id;
            currentPO = task.poNumber;

            // Fill content
            document.getElementById('task-po').textContent = task.poNumber || 'No PO';
            document.getElementById('task-location').textContent = (task.property ? task.property + ' - ' : '') + (task.location || 'N/A');
            document.getElementById('task-heading').textContent = task.task || 'No requirements provided.';
            document.getElementById('existing-remarks').textContent = task.remarks || '';
            
            if (task.location) {
                document.getElementById('btn-directions').href = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(task.location)}`;
            } else {
                document.getElementById('btn-directions').classList.add('hidden');
            }

            // Status Badge
            const badge = document.getElementById('status-badge');
            badge.textContent = task.status;
            if (task.status.toLowerCase() === 'completed') {
                badge.className = 'absolute top-0 right-0 px-4 py-2 rounded-bl-2xl text-[10px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400';
                document.getElementById('btn-complete').classList.add('hidden');
            } else {
                badge.className = 'absolute top-0 right-0 px-4 py-2 rounded-bl-2xl text-[10px] font-black uppercase tracking-widest bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-400';
            }

            document.getElementById('task-content').classList.remove('hidden');
            
            // Load Attachments
            loadAttachments(task.poNumber);
        })
        .catch(err => {
            document.getElementById('loader').classList.add('hidden');
            document.getElementById('error-message').classList.remove('hidden');
        });
}

function loadAttachments(po) {
    if (!po) return;
    fetch(`${window.appUrl}tracker_handler.php?action=get_attachments&po=${po}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('attachments-section');
            const list = document.getElementById('attachments-list');
            list.innerHTML = '';

            if (data.success && data.files.length > 0) {
                container.classList.remove('hidden');
                data.files.forEach(f => {
                    const link = f.FilePath || f.link || '#';
                    const name = f.DocumentName || f.name || 'Unknown File';
                    const date = f.UploadedDate || f.date || '';
                    const safeLink = encodeURI(link);
                    const safeName = escapeHtml(name);
                    const safeDate = escapeHtml(date);
                    
                    const isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(name);
                    const icon = isImg ? 'fa-image text-emerald-500' : 'fa-file-pdf text-red-500';

                    list.innerHTML += `
                        <a href="${safeLink}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-4 p-4 bg-white dark:bg-slate-900 border border-gray-100 dark:border-slate-800 rounded-2xl hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-all group shadow-sm">
                            <div class="w-10 h-10 bg-gray-50 dark:bg-slate-800 rounded-xl flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-bold text-gray-900 dark:text-white truncate text-xs italic tracking-tight">${safeName}</div>
                                <div class="text-[9px] text-gray-400 dark:text-gray-500 font-black uppercase tracking-widest mt-0.5">${safeDate}</div>
                            </div>
                            <i class="fas fa-external-link-alt text-gray-200 dark:text-gray-700 group-hover:text-indigo-400 transition-colors text-[10px]"></i>
                        </a>`;
                });
            }
        })
        .catch(() => {
            document.getElementById('attachments-section').classList.add('hidden');
        });
}

async function uploadFiles(input) {
    if (!input.files || input.files.length === 0) return;
    
    const progress = document.getElementById('upload-progress');
    const bar = progress.querySelector('div');
    progress.classList.remove('hidden');
    bar.style.width = '0%';

    const formData = new FormData();
    formData.append('action', 'worker_upload');
    formData.append('id', currentTaskId);

    const options = {
        maxSizeMB: 0.8,
        maxWidthOrHeight: 1920,
        useWebWorker: true
    };

    try {
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            if (file.type.startsWith('image/')) {
                const compressedFile = await imageCompression(file, options);
                formData.append('images[]', compressedFile, file.name);
            } else {
                formData.append('images[]', file);
            }
            bar.style.width = ((i + 1) / input.files.length * 30) + '%';
        }

        fetch(`${window.appUrl}tracker_handler.php`, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                bar.style.width = '100%';
                Toast.fire({ icon: 'success', title: 'Upload successful' });
                setTimeout(() => {
                    progress.classList.add('hidden');
                    loadAttachments(currentPO);
                }, 1000);
            } else {
                Swal.fire('Upload Failed', data.message, 'error');
                progress.classList.add('hidden');
            }
        })
        .catch(() => {
            progress.classList.add('hidden');
            Swal.fire('Upload Failed', 'Server connection failed during upload.', 'error');
        });
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'Compression or Upload failed', 'error');
        progress.classList.add('hidden');
    }
}

function submitRemark() {
    const remark = document.getElementById('remark-text').value;
    if (!remark.trim()) return;

    fetch(`${laravelApiUrl}/api/public/tasks/${currentTaskId}/remark`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ hash: taskHash, remark: remark, is_task_hash: true })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('remark-text').value = '';
            Toast.fire({ icon: 'success', title: 'Remark added' });
            loadTaskDetails();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Failed to save remark.', 'error');
    });
}

function markTaskComplete() {
    if (!confirm('Mark this job as completed?')) return;

    fetch(`${laravelApiUrl}/api/public/tasks/${currentTaskId}/complete`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ hash: taskHash, is_task_hash: true })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadTaskDetails();
            Swal.fire('Success', 'Work Order marked as completed!', 'success');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(() => {
        Swal.fire('Error', 'Failed to update work order status.', 'error');
    });
}
</script>

<?php include_once __DIR__ . '/../footer.php'; ?>
