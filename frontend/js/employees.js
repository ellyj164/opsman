/**
 * OpsMan – Employees JS
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee' || user.role === 'field_agent') {
        window.location.href = 'employee-portal.html'; return;
    }
    document.getElementById('userInfo').textContent = user.username;

    if (!['admin', 'operations_manager'].includes(user.role)) {
        const btn = document.getElementById('createBtn');
        if (btn) btn.classList.add('hidden');
    }

    loadEmployees();
});

async function loadEmployees(page = 1) {
    currentPage = page;
    const search = document.getElementById('filterSearch')?.value || '';
    const dept = document.getElementById('filterDepartment')?.value || '';
    try {
        const params = new URLSearchParams({ page, per_page: 20 });
        if (search) params.set('search', search);
        if (dept) params.set('department', dept);
        const res = await api(`/api/employees.php?${params}`);
        renderTable(res.data || []);
        if (res.pagination) {
            renderPagination(res.pagination, 'paginationBar', loadEmployees);
        }
    } catch (e) {
        showToast('Error loading employees: ' + e.message, 'error');
    }
}

function applyFilters() {
    loadEmployees(1);
}

function renderTable(items) {
    const tbody = document.getElementById('employeesBody');
    if (!tbody) return;
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No employees found</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(e => `
        <tr>
            <td>${e.employee_code}</td>
            <td><strong>${e.full_name}</strong></td>
            <td>${e.department || '–'}</td>
            <td>${e.phone || '–'}</td>
            <td>${e.email || '–'}</td>
            <td><span class="badge ${e.is_active ? 'badge-success' : 'badge-secondary'}">${e.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="viewEmployee(${e.id})">View</button>
                <button class="btn btn-sm btn-outline" onclick="editEmployee(${e.id})">Edit</button>
            </td>
        </tr>
    `).join('');
}

async function viewEmployee(id) {
    try {
        const res = await api(`/api/employees.php?id=${id}`);
        const e = res.data;
        const modal = document.getElementById('detailModal');
        const body = document.getElementById('detailBody');
        if (!modal || !body) return;
        body.innerHTML = `
            <div class="detail-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem .75rem;font-size:.875rem">
                <div><strong>Name:</strong> ${e.full_name}</div>
                <div><strong>Code:</strong> ${e.employee_code}</div>
                <div><strong>Department:</strong> ${e.department || '–'}</div>
                <div><strong>Phone:</strong> ${e.phone || '–'}</div>
                <div><strong>Email:</strong> ${e.email || '–'}</div>
                <div><strong>Address:</strong> ${e.address || '–'}</div>
                <div><strong>Role:</strong> ${e.role || '–'}</div>
                <div><strong>Status:</strong> ${e.is_active ? 'Active' : 'Inactive'}</div>
            </div>
        `;
        modal.classList.remove('hidden');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function editEmployee(id) {
    // Placeholder for edit modal
    showToast('Edit functionality coming soon', 'info');
}
