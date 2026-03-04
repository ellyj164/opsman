/**
 * OpsMan – File Uploads JS
 * Handles file selection, preview, and upload to backend.
 */

const pendingFiles = [];   // Files waiting to be uploaded after report creation

// ── File Selection ────────────────────────────────────────────────────

function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    const preview = document.getElementById('filePreview');
    if (!preview) return;

    files.forEach(file => {
        if (!validateFileClient(file)) return;
        pendingFiles.push(file);
        appendPreview(file, preview);
    });
}

function validateFileClient(file) {
    const maxSize = 10 * 1024 * 1024; // 10 MB
    const allowed = ['image/jpeg','image/png','image/gif','application/pdf',
                     'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (file.size > maxSize) {
        showToast(`${file.name}: File exceeds 10 MB limit`, 'error');
        return false;
    }
    if (!allowed.includes(file.type)) {
        showToast(`${file.name}: File type not allowed`, 'error');
        return false;
    }
    return true;
}

function appendPreview(file, container) {
    const item = document.createElement('div');
    item.className = 'file-preview-item';
    item.dataset.filename = file.name;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            item.innerHTML = `
                <img src="${e.target.result}" alt="${file.name}" title="${file.name}">
                <button class="remove-file" onclick="removeFile('${file.name}', this.parentElement)">✕</button>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        item.innerHTML = `
            <div style="width:60px;height:60px;border:1px solid #e2e8f0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.7rem;text-align:center;padding:.25rem;color:#6c757d">
                📄<br>${file.name.slice(-12)}
            </div>
            <button class="remove-file" onclick="removeFile('${file.name}', this.parentElement)">✕</button>
        `;
    }

    container.appendChild(item);
}

function removeFile(name, element) {
    const idx = pendingFiles.findIndex(f => f.name === name);
    if (idx !== -1) pendingFiles.splice(idx, 1);
    element?.remove();
}

// ── Upload ────────────────────────────────────────────────────────────

/**
 * Upload all pending files for a given report ID.
 * Called after the report has been created.
 */
async function uploadPendingFiles(reportId) {
    if (!pendingFiles.length) return;

    for (const file of pendingFiles) {
        await uploadFile(file, reportId);
    }
    pendingFiles.length = 0;
}

/**
 * Upload a single file.
 */
async function uploadFile(file, reportId) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('report_id', reportId);

    const token = getToken();
    const headers = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch(API_BASE_URL + '/uploads.php', {
            method: 'POST',
            headers,
            body: formData,
        });
        const data = await res.json();
        if (!data.success) {
            showToast(`Upload failed: ${data.error}`, 'error');
        } else {
            showToast(`Uploaded: ${file.name}`, 'success', 2000);
        }
    } catch (e) {
        showToast(`Upload error: ${e.message}`, 'error');
    }
}

// ── Camera Capture ────────────────────────────────────────────────────

function captureFromCamera() {
    const input = document.getElementById('fileInput');
    if (input) {
        input.setAttribute('capture', 'environment');
        input.setAttribute('accept', 'image/*');
        input.click();
        input.removeAttribute('capture');
    }
}
