/**
 * OpsMan – Alerts JS
 */

let alertPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }

    const userInfoEl = document.getElementById('userInfo');
    if (userInfoEl) userInfoEl.textContent = user.username;

    loadAlerts();
});

async function loadAlerts() {
    const body = document.getElementById('alertsBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="6" class="text-center">Loading…</td></tr>';

    const qs = new URLSearchParams({ page: alertPage, per_page: 20 });
    const severity = document.getElementById('filterSeverity')?.value;
    const isRead = document.getElementById('filterRead')?.value;
    if (severity) qs.set('severity', severity);
    if (isRead !== undefined && isRead !== '') qs.set('is_read', isRead);

    try {
        const res = await api(`/api/alerts.php?${qs}`);
        const items = res.data || [];

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No alerts found.</td></tr>';
            return;
        }

        body.innerHTML = items.map(a => `
            <tr class="${a.is_read ? '' : 'font-bold'}">
                <td>${a.id}</td>
                <td>${a.title}</td>
                <td>${a.message}</td>
                <td><span class="badge ${a.severity === 'critical' ? 'badge-danger' : a.severity === 'warning' ? 'badge-warning' : 'badge-info'}">${a.severity}</span></td>
                <td>${formatDate(a.created_at)}</td>
                <td>
                    ${!a.is_read ? `<button class="btn btn-xs btn-outline" onclick="markRead(${a.id})">Mark Read</button>` : '<span class="text-muted">Read</span>'}
                </td>
            </tr>
        `).join('');

        if (res.pagination) {
            renderPagination(res.pagination, 'alertsPagination', p => { alertPage = p; loadAlerts(); });
        }
    } catch (e) {
        body.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${e.message}</td></tr>`;
    }
}

function applyFilters() {
    alertPage = 1;
    loadAlerts();
}

async function markRead(id) {
    try {
        await api(`/api/alerts.php?id=${id}&action=read`, 'PUT');
        showToast('Alert marked as read', 'success');
        loadAlerts();
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function markAllRead() {
    try {
        await api('/api/alerts.php?action=read-all', 'PUT');
        showToast('All alerts marked as read', 'success');
        loadAlerts();
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}
