/**
 * OpsMan – Core Application JS
 * Shared utilities used across all pages.
 */

const API_BASE_URL = (function () {
    const loc = window.location;
    const pathParts = loc.pathname.split('/');
    // Remove the filename (e.g., dashboard.html)
    pathParts.pop();
    // Remove 'frontend' directory if present
    if (pathParts[pathParts.length - 1] === 'frontend') {
        pathParts.pop();
    }
    return loc.origin + pathParts.join('/') + '/backend';
}());

// ── Auth helpers ──────────────────────────────────────────────────────

function getToken() {
    return localStorage.getItem('opsman_token');
}

function getStoredUser() {
    try {
        return JSON.parse(localStorage.getItem('opsman_user') || 'null');
    } catch {
        return null;
    }
}

function setAuth(token, user) {
    localStorage.setItem('opsman_token', token);
    localStorage.setItem('opsman_user', JSON.stringify(user));
}

function clearAuth() {
    localStorage.removeItem('opsman_token');
    localStorage.removeItem('opsman_user');
}

// ── API helper ────────────────────────────────────────────────────────

/**
 * Make an authenticated JSON request.
 * @param {string} path   Endpoint path (relative to API_BASE_URL or full URL)
 * @param {string} method HTTP method
 * @param {object} body   Request body (optional)
 * @returns {Promise<object>} Parsed JSON response
 */
async function api(path, method = 'GET', body = null) {
    const url = path.startsWith('http') ? path : API_BASE_URL + path;
    const headers = { 'Content-Type': 'application/json' };
    const token = getToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const opts = { method, headers };
    if (body && method !== 'GET') {
        opts.body = JSON.stringify(body);
    }

    const res = await fetch(url, opts);
    const rawText = await res.text();
    let data;
    try {
        data = JSON.parse(rawText);
    } catch (e) {
        console.error('API JSON parse failed for', url, '- Status:', res.status, '- Raw response:', rawText);
        data = { success: false, error: 'Invalid server response' };
    }

    if (!res.ok || data.success === false) {
        const msg = data.error || `Request failed (${res.status})`;
        if (res.status === 401) {
            clearAuth();
            window.location.href = 'index.html';
        }
        throw new Error(msg);
    }

    return data;
}

// ── Toast Notifications ───────────────────────────────────────────────

function showToast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(120%)';
        toast.style.transition = 'all .3s ease';
        setTimeout(() => toast.remove(), 350);
    }, duration);
}

// ── Loading States ────────────────────────────────────────────────────

function setLoading(btnId, loading = true) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    const spinner = btn.querySelector('.spinner');
    const text    = btn.querySelector('[id$=BtnText]');
    if (spinner) spinner.classList.toggle('hidden', !loading);
    if (text)    text.classList.toggle('hidden',    loading);
}

// ── Date / Time Utilities ─────────────────────────────────────────────

function formatDate(dateStr) {
    if (!dateStr) return '–';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '–';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function isOverdue(deadlineStr) {
    if (!deadlineStr) return false;
    return new Date(deadlineStr) < new Date();
}

// ── Badge Helpers ─────────────────────────────────────────────────────

function priorityBadge(priority) {
    const map = { low: 'badge-secondary', medium: 'badge-info', high: 'badge-warning', urgent: 'badge-danger' };
    return `<span class="badge ${map[priority] || 'badge-secondary'}">${priority}</span>`;
}

function statusBadge(status) {
    const map = {
        pending: 'badge-secondary', assigned: 'badge-info',
        in_progress: 'badge-primary', completed: 'badge-success', overdue: 'badge-danger',
        draft: 'badge-secondary', submitted: 'badge-info', reviewed: 'badge-success',
        active: 'badge-primary',
    };
    return `<span class="badge ${map[status] || 'badge-secondary'}">${status.replace(/_/g, ' ')}</span>`;
}

// ── Pagination ────────────────────────────────────────────────────────

function renderPagination(pagination, containerId, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container || !pagination) return;

    const { page, total_pages } = pagination;
    if (total_pages <= 1) { container.innerHTML = ''; return; }

    let html = '';
    if (page > 1) {
        html += `<button class="page-btn" onclick="(${onPageChange.toString()})(${page - 1})">‹ Prev</button>`;
    }

    const start = Math.max(1, page - 2);
    const end   = Math.min(total_pages, page + 2);
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="(${onPageChange.toString()})(${i})">${i}</button>`;
    }

    if (page < total_pages) {
        html += `<button class="page-btn" onclick="(${onPageChange.toString()})(${page + 1})">Next ›</button>`;
    }
    container.innerHTML = html;
}

// ── Modal Helpers ─────────────────────────────────────────────────────

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
}

// ── Sidebar toggle ────────────────────────────────────────────────────

function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('open');
}

// ── Logout ────────────────────────────────────────────────────────────

async function logout() {
    try {
        await api('/api/auth.php?action=logout', 'POST');
    } catch {
        // ignore – clear local state anyway
    }
    clearAuth();
    window.location.href = 'index.html';
}
