/**
 * OpsMan – Tasks JS
 * Handles tasks list page and employee-portal task loading.
 */

let taskPage = 1;
const taskPerPage = 20;
let taskFilters = {};

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }

    const userInfoEl = document.getElementById('userInfo');
    if (userInfoEl) userInfoEl.textContent = user.username;

    // Hide "New Task" button for field employees
    const createBtn = document.getElementById('createTaskBtn');
    if (createBtn && user.role === 'field_employee') {
        createBtn.classList.add('hidden');
    }

    if (document.getElementById('tasksBody')) {
        loadTasks();
        loadEmployeesForSelect();
    }
});

// ── Load Tasks ────────────────────────────────────────────────────────

async function loadTasks() {
    const body = document.getElementById('tasksBody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="8" class="text-center">Loading…</td></tr>';

    const qs = new URLSearchParams({
        page: taskPage,
        per_page: taskPerPage,
        ...taskFilters,
    });

    try {
        const res = await api(`/api/tasks.php?${qs}`);
        if (!res.data?.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No tasks found.</td></tr>';
            return;
        }

        body.innerHTML = res.data.map(t => `
            <tr>
                <td>${t.id}</td>
                <td>
                    <a href="#" onclick="viewTask(${t.id})" class="task-title-link">
                        ${t.title}
                    </a>
                </td>
                <td><span class="badge badge-info">${t.task_type.replace(/_/g, ' ')}</span></td>
                <td>${t.assigned_to_name || '<span class="text-muted">Unassigned</span>'}</td>
                <td>${priorityBadge(t.priority)}</td>
                <td>${statusBadge(t.status)}</td>
                <td class="${isOverdue(t.deadline) && t.status !== 'completed' ? 'text-danger' : ''}">
                    ${formatDateShort(t.deadline)}
                </td>
                <td>
                    <button class="btn btn-xs btn-outline" onclick="viewTask(${t.id})">View</button>
                    ${canManage() ? `<button class="btn btn-xs btn-primary" onclick="editTask(${t.id})">Edit</button>` : ''}
                </td>
            </tr>
        `).join('');

        renderPagination(res.pagination, 'tasksPagination', p => { taskPage = p; loadTasks(); });
    } catch (e) {
        body.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${e.message}</td></tr>`;
    }
}

function applyFilters() {
    taskPage = 1;
    taskFilters = {
        status:    document.getElementById('filterStatus')?.value  || '',
        priority:  document.getElementById('filterPriority')?.value || '',
        task_type: document.getElementById('filterType')?.value    || '',
        search:    document.getElementById('filterSearch')?.value  || '',
    };
    // Remove empty keys
    Object.keys(taskFilters).forEach(k => { if (!taskFilters[k]) delete taskFilters[k]; });
    loadTasks();
}

// ── Employee portal: my tasks ─────────────────────────────────────────

async function loadMyTasks() {
    const list = document.getElementById('myTasksList');
    if (!list) return;
    list.innerHTML = '<div class="loading-spinner">Loading…</div>';

    try {
        const res = await api('/api/tasks.php?page=1&per_page=50');
        const count = document.getElementById('taskCount');
        if (count) count.textContent = res.pagination?.total || 0;

        if (!res.data?.length) {
            list.innerHTML = '<p class="text-center text-muted">No tasks assigned.</p>';
            return;
        }

        list.innerHTML = res.data.map(t => `
            <div class="task-card-mobile priority-${t.priority}" onclick="viewTaskMobile(${t.id})">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem">
                    <strong>${t.title}</strong>
                    ${statusBadge(t.status)}
                </div>
                <div style="font-size:.8rem;color:#6c757d;margin-top:.25rem">
                    ${t.task_type.replace(/_/g, ' ')} · ${priorityBadge(t.priority)}
                </div>
                ${t.location ? `<div style="font-size:.8rem;color:#6c757d">📍 ${t.location}</div>` : ''}
                <div style="font-size:.8rem;color:#6c757d">⏰ ${formatDateShort(t.deadline)}</div>
            </div>
        `).join('');
    } catch (e) {
        list.innerHTML = `<p class="text-center text-danger">${e.message}</p>`;
    }
}

async function loadMyTasksForSelect() {
    try {
        const res = await api('/api/tasks.php?status=assigned&per_page=50');
        const selects = ['checkinTaskSelect', 'reportTaskSelect'];
        selects.forEach(id => {
            const sel = document.getElementById(id);
            if (!sel) return;
            sel.innerHTML = '<option value="">Select a task</option>' +
                (res.data || []).map(t => `<option value="${t.id}">${t.title}</option>`).join('');
        });
    } catch (e) {
        console.error('Failed to load tasks for select', e);
    }
}

function viewTaskMobile(id) {
    viewTask(id);
}

// ── Task Detail / Modal ───────────────────────────────────────────────

async function viewTask(id) {
    try {
        const res = await api(`/api/tasks.php?id=${id}`);
        const t   = res.data;

        const modal = document.getElementById('taskDetailModal');
        const title = document.getElementById('taskDetailTitle');
        const body  = document.getElementById('taskDetailBody');

        if (!modal) return;
        title.textContent = t.title;
        body.innerHTML = `
            <div class="detail-grid">
                <div><strong>Type:</strong> <span class="badge badge-info">${t.task_type.replace(/_/g,' ')}</span></div>
                <div><strong>Status:</strong> ${statusBadge(t.status)}</div>
                <div><strong>Priority:</strong> ${priorityBadge(t.priority)}</div>
                <div><strong>Deadline:</strong> <span class="${isOverdue(t.deadline) ? 'text-danger' : ''}">${formatDate(t.deadline)}</span></div>
                <div><strong>Assigned To:</strong> ${t.assigned_to_name || 'Unassigned'}</div>
                <div><strong>Assigned By:</strong> ${t.assigned_by_name || '–'}</div>
                <div><strong>Location:</strong> ${t.location || '–'}</div>
                <div><strong>Shipment Ref:</strong> ${t.shipment_ref || '–'}</div>
                <div><strong>Created:</strong> ${formatDate(t.created_at)}</div>
            </div>
            ${t.description ? `<div style="margin-top:1rem"><strong>Description:</strong><p style="margin-top:.25rem;font-size:.875rem">${t.description}</p></div>` : ''}
            ${canManage() ? `
            <div class="modal-actions" style="margin-top:1rem;padding-top:.75rem;border-top:1px solid #e2e8f0">
                <select id="statusUpdate-${t.id}" class="form-control form-control-sm" style="width:auto">
                    <option value="">Update Status…</option>
                    <option value="pending">Pending</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="overdue">Overdue</option>
                </select>
                <button class="btn btn-sm btn-primary" onclick="updateStatus(${t.id})">Update</button>
            </div>` : ''}
        `;
        modal.classList.remove('hidden');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function updateStatus(taskId) {
    const sel = document.getElementById(`statusUpdate-${taskId}`);
    if (!sel?.value) { showToast('Select a status', 'warning'); return; }
    try {
        await api(`/api/tasks.php?id=${taskId}&action=update-status`, 'PUT', { status: sel.value });
        showToast('Status updated', 'success');
        closeModal('taskDetailModal');
        if (typeof loadTasks === 'function') loadTasks();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── Create / Edit ─────────────────────────────────────────────────────

function openCreateTaskModal() {
    resetTaskForm();
    document.getElementById('taskModalTitle').textContent = 'Create Task';
    document.getElementById('taskModal').classList.remove('hidden');
}

async function editTask(id) {
    try {
        const res = await api(`/api/tasks.php?id=${id}`);
        const t   = res.data;
        resetTaskForm();
        document.getElementById('taskModalTitle').textContent = 'Edit Task';
        document.getElementById('taskId').value          = t.id;
        document.getElementById('taskTitle').value        = t.title;
        document.getElementById('taskPriority').value     = t.priority;
        document.getElementById('taskType').value         = t.task_type;
        document.getElementById('taskAssignTo').value     = t.assigned_to || '';
        document.getElementById('taskLocation').value     = t.location    || '';
        document.getElementById('taskShipmentRef').value  = t.shipment_ref || '';
        document.getElementById('taskDescription').value  = t.description  || '';
        if (t.deadline) {
            document.getElementById('taskDeadline').value = t.deadline.replace(' ', 'T').slice(0, 16);
        }
        document.getElementById('taskModal').classList.remove('hidden');
    } catch (e) {
        showToast(e.message, 'error');
    }
}

function resetTaskForm() {
    const form = document.getElementById('taskForm');
    if (form) form.reset();
    const idEl = document.getElementById('taskId');
    if (idEl) idEl.value = '';
}

async function loadEmployeesForSelect() {
    try {
        const res = await api('/api/employees.php?per_page=100');
        const sel = document.getElementById('taskAssignTo');
        if (!sel) return;
        sel.innerHTML = '<option value="">Unassigned</option>' +
            (res.data || []).map(e => `<option value="${e.id}">${e.full_name}</option>`).join('');
    } catch { /* non-critical */ }
}

document.addEventListener('submit', async (e) => {
    if (e.target.id !== 'taskForm') return;
    e.preventDefault();

    const id       = document.getElementById('taskId').value;
    const isEdit   = !!id;
    const deadline = document.getElementById('taskDeadline').value;
    const payload  = {
        title:        document.getElementById('taskTitle').value,
        task_type:    document.getElementById('taskType').value,
        priority:     document.getElementById('taskPriority').value,
        assigned_to:  document.getElementById('taskAssignTo').value   || null,
        location:     document.getElementById('taskLocation').value    || null,
        shipment_ref: document.getElementById('taskShipmentRef').value || null,
        description:  document.getElementById('taskDescription').value || null,
        deadline:     deadline ? deadline.replace('T', ' ') + ':00' : null,
    };

    try {
        if (isEdit) {
            await api(`/api/tasks.php?id=${id}`, 'PUT', payload);
            showToast('Task updated', 'success');
        } else {
            await api('/api/tasks.php', 'POST', payload);
            showToast('Task created', 'success');
        }
        closeModal('taskModal');
        loadTasks();
    } catch (err) {
        showToast(err.message, 'error');
    }
});

// ── Helpers ───────────────────────────────────────────────────────────

function canManage() {
    const user = getStoredUser();
    return user && ['admin', 'operations_manager'].includes(user.role);
}
