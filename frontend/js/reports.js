/**
 * OpsMan – Reports JS
 * Handles the reports list/detail page.
 */

let reportPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }

    const userInfoEl = document.getElementById('userInfo');
    if (userInfoEl) userInfoEl.textContent = user.username;

    loadReports();

    // Populate employee filter (managers only)
    if (user.role !== 'field_employee') {
        loadEmployeeFilter();
    }
});

async function loadReports() {
    const body = document.getElementById('reportsBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="8" class="text-center">Loading…</td></tr>';

    const qs = new URLSearchParams({ page: reportPage, per_page: 20 });
    const empId    = document.getElementById('filterEmployee')?.value;
    const status   = document.getElementById('filterStatus')?.value;
    const dateFrom = document.getElementById('filterDateFrom')?.value;
    const dateTo   = document.getElementById('filterDateTo')?.value;
    if (empId)    qs.set('employee_id', empId);
    if (status)   qs.set('status',      status);
    if (dateFrom) qs.set('date_from',   dateFrom);
    if (dateTo)   qs.set('date_to',     dateTo + ' 23:59:59');

    try {
        const res = await api(`/api/reports.php?${qs}`);
        if (!res.data?.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No reports found.</td></tr>';
            return;
        }

        body.innerHTML = res.data.map(r => `
            <tr>
                <td>${r.id}</td>
                <td>${r.task_title || '–'}</td>
                <td>${r.employee_name || '–'}</td>
                <td>${r.check_in_time ? formatDate(r.check_in_time) : '<span class="text-muted">–</span>'}</td>
                <td>${r.check_out_time ? formatDate(r.check_out_time) : '<span class="text-muted">–</span>'}</td>
                <td>${statusBadge(r.status)}</td>
                <td>${formatDateShort(r.created_at)}</td>
                <td>
                    <button class="btn btn-xs btn-outline" onclick="viewReport(${r.id})">View</button>
                </td>
            </tr>
        `).join('');

        renderPagination(res.pagination, 'reportsPagination', p => { reportPage = p; loadReports(); });
    } catch (e) {
        body.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${e.message}</td></tr>`;
    }
}

async function viewReport(id) {
    try {
        const res = await api(`/api/reports.php?id=${id}`);
        const r   = res.data;
        const modal = document.getElementById('reportDetailModal');
        const body  = document.getElementById('reportDetailBody');

        body.innerHTML = `
            <div class="detail-grid" style="grid-template-columns:1fr 1fr;gap:.5rem .75rem;font-size:.875rem">
                <div><strong>Task:</strong> ${r.task_title}</div>
                <div><strong>Employee:</strong> ${r.employee_name}</div>
                <div><strong>Status:</strong> ${statusBadge(r.status)}</div>
                <div><strong>Check-in:</strong> ${r.check_in_time ? formatDate(r.check_in_time) : '–'}</div>
                <div><strong>Check-out:</strong> ${r.check_out_time ? formatDate(r.check_out_time) : '–'}</div>
                ${r.check_in_lat ? `<div><strong>Check-in location:</strong> ${r.check_in_lat}, ${r.check_in_lng}</div>` : ''}
                ${r.check_out_lat ? `<div><strong>Check-out location:</strong> ${r.check_out_lat}, ${r.check_out_lng}</div>` : ''}
                <div><strong>Created:</strong> ${formatDate(r.created_at)}</div>
            </div>
            ${r.notes ? `<div style="margin-top:1rem"><strong>Notes:</strong><p style="margin-top:.25rem;font-size:.875rem">${r.notes}</p></div>` : ''}
            ${r.observations ? `<div style="margin-top:.75rem"><strong>Observations:</strong><p style="margin-top:.25rem;font-size:.875rem">${r.observations}</p></div>` : ''}
        `;
        modal.classList.remove('hidden');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function loadEmployeeFilter() {
    try {
        const res = await api('/api/employees.php?per_page=100');
        const sel = document.getElementById('filterEmployee');
        if (!sel) return;
        sel.innerHTML = '<option value="">All Employees</option>' +
            (res.data || []).map(e => `<option value="${e.id}">${e.full_name}</option>`).join('');
    } catch { /* non-critical */ }
}
