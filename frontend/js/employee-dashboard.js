/**
 * OpsMan – Employee Dashboard JS
 * Loads aggregated dashboard data for the current employee.
 */

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }

    const userInfoEl = document.getElementById('userInfo');
    if (userInfoEl) userInfoEl.textContent = user.username;

    loadDashboard();
    loadEmployeesForTransfer();

    // Auto-refresh every 60 seconds
    setInterval(loadDashboard, 60000);
});

// ── Load Dashboard ────────────────────────────────────────────────────

async function loadDashboard() {
    try {
        const res = await api('/api/employee_dashboard.php');
        const d   = res.data;

        // Stats
        document.getElementById('statPoints').textContent    = d.points?.total ?? 0;
        document.getElementById('statRank').textContent      = d.points?.rank ? `#${d.points.rank}` : '–';
        document.getElementById('statCompleted').textContent  = d.tasks?.completed?.length ?? 0;
        document.getElementById('statPending').textContent    = d.tasks?.pending?.length ?? 0;
        document.getElementById('statActive').textContent     = d.tasks?.active?.length ?? 0;
        document.getElementById('statSteps').textContent      = d.steps?.active?.length ?? 0;

        // Notifications badge
        const badge = document.getElementById('notifBadge');
        const unread = d.notifications?.unread_count ?? 0;
        if (unread > 0) {
            badge.textContent = unread;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }

        // Render sections
        renderActiveSteps(d.steps?.active || []);
        renderMyTasks([...(d.tasks?.active || []), ...(d.tasks?.pending || [])]);
        renderCompletedTasks(d.tasks?.completed || []);
        renderPointsHistory(d.points?.breakdown || []);
        renderNotifications(d.notifications?.items || []);
        renderAiInsights(d.ai_insights);
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── Active Steps ──────────────────────────────────────────────────────

function renderActiveSteps(steps) {
    const body = document.getElementById('activeStepsBody');
    if (!steps.length) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No active workflow steps.</td></tr>';
        return;
    }
    body.innerHTML = steps.map(s => `
        <tr>
            <td>${s.task_title || ''} <span class="text-muted text-sm">${s.task_ref || ''}</span></td>
            <td><strong>${s.step_name}</strong></td>
            <td>${statusBadge(s.status)}</td>
            <td>
                <button class="btn btn-xs btn-success" onclick="completeStep(${s.id})">✅ Complete</button>
                <button class="btn btn-xs btn-outline" onclick="openTransfer(${s.id})" title="Transfer next step after completing">🔄 Transfer</button>
            </td>
        </tr>
    `).join('');
}

// ── My Tasks ──────────────────────────────────────────────────────────

function renderMyTasks(tasks) {
    const body = document.getElementById('myTasksBody');
    if (!tasks.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No active or pending tasks.</td></tr>';
        return;
    }
    body.innerHTML = tasks.map(t => `
        <tr>
            <td>${t.ref || '–'}</td>
            <td>${t.title}</td>
            <td><span class="badge badge-info">${(t.task_type || '').replace(/_/g, ' ')}</span></td>
            <td>${priorityBadge(t.priority)}</td>
            <td>${statusBadge(t.status)}</td>
            <td class="${isOverdue(t.deadline) && t.status !== 'completed' ? 'text-danger' : ''}">${formatDateShort(t.deadline)}</td>
        </tr>
    `).join('');
}

// ── Completed Tasks ───────────────────────────────────────────────────

function renderCompletedTasks(tasks) {
    const body = document.getElementById('completedTasksBody');
    if (!tasks.length) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No completed tasks yet.</td></tr>';
        return;
    }
    body.innerHTML = tasks.map(t => `
        <tr>
            <td>${t.ref || '–'}</td>
            <td>${t.title}</td>
            <td><span class="badge badge-info">${(t.task_type || '').replace(/_/g, ' ')}</span></td>
            <td>${formatDate(t.updated_at)}</td>
        </tr>
    `).join('');
}

// ── Points History ────────────────────────────────────────────────────

function renderPointsHistory(points) {
    const body = document.getElementById('pointsBody');
    if (!points.length) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No points earned yet.</td></tr>';
        return;
    }
    body.innerHTML = points.map(p => `
        <tr>
            <td>${p.step_name || '–'}</td>
            <td>${p.task_title || p.task_ref || '–'}</td>
            <td><span class="badge badge-success">+${p.points}</span></td>
            <td>${formatDate(p.created_at)}</td>
        </tr>
    `).join('');
}

// ── Notifications ─────────────────────────────────────────────────────

let notifPanelOpen = false;

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    notifPanelOpen = !notifPanelOpen;
    panel.classList.toggle('hidden', !notifPanelOpen);
}

function renderNotifications(items) {
    const list = document.getElementById('notifList');
    if (!items.length) {
        list.innerHTML = '<p class="text-center text-muted" style="padding:1rem;">No notifications.</p>';
        return;
    }
    list.innerHTML = items.map(n => `
        <div class="alert-item ${n.read_status == 0 ? 'unread' : ''}" onclick="markNotifRead(${n.id})">
            <span class="alert-icon">${getNotifIcon(n.type)}</span>
            <div class="alert-content">
                <div class="alert-message">${n.message}</div>
                <div class="alert-meta">
                    <span class="text-sm text-muted">${formatDate(n.created_at)}</span>
                    <span class="badge badge-secondary">${n.type}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function getNotifIcon(type) {
    const icons = {
        task_assigned: '📋', task_transferred: '🔄', task_approved: '✅',
        step_completed: '🏅', info: 'ℹ️', task_created: '📝',
    };
    return icons[type] || '🔔';
}

async function markNotifRead(id) {
    try {
        await api(`/api/notifications.php?id=${id}&action=read`, 'PUT');
        loadDashboard();
    } catch (e) {
        console.error(e);
    }
}

async function markAllNotifRead() {
    try {
        await api('/api/notifications.php?action=read-all', 'PUT');
        showToast('All notifications marked as read', 'success');
        loadDashboard();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── Step Actions ──────────────────────────────────────────────────────

async function completeStep(stepId) {
    try {
        await api(`/api/task_steps.php?action=complete&id=${stepId}`, 'POST');
        showToast('Step completed! Points awarded.', 'success');
        loadDashboard();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

function openTransfer(stepId) {
    document.getElementById('transferStepId').value = stepId;
    document.getElementById('transferModal').classList.remove('hidden');
}

async function doTransfer() {
    const stepId = document.getElementById('transferStepId').value;
    const empId  = document.getElementById('transferEmployee').value;
    if (!empId) { showToast('Select an employee', 'warning'); return; }

    try {
        await api('/api/task_steps.php?action=transfer', 'POST', {
            from_step_id: parseInt(stepId),
            to_employee_id: parseInt(empId),
        });
        showToast('Step transferred successfully!', 'success');
        closeModal('transferModal');
        loadDashboard();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function loadEmployeesForTransfer() {
    try {
        const res = await api('/api/employees.php?per_page=100');
        const sel = document.getElementById('transferEmployee');
        if (!sel) return;
        sel.innerHTML = '<option value="">Select employee…</option>' +
            (res.data || []).map(e => `<option value="${e.id}">${e.full_name}</option>`).join('');
    } catch { /* non-critical */ }
}

// ── AI Insights ───────────────────────────────────────────────────────

function renderAiInsights(data) {
    const el = document.getElementById('aiInsightsContent');
    if (!data) {
        el.innerHTML = '<p class="text-muted">AI service is not available. Insights will appear once the service is running.</p>';
        return;
    }

    let html = '';
    if (data.points_awarded !== undefined) {
        html += `<div class="insight-item">
            <span>🎯</span>
            <div><strong>${data.points_awarded} AI points</strong> — ${data.reason || 'Based on performance analysis'}</div>
        </div>`;
    }
    if (data.insights && data.insights.length) {
        data.insights.forEach(i => {
            html += `<div class="insight-item"><span>💡</span><div>${i}</div></div>`;
        });
    }
    if (data.total_tasks !== undefined) {
        html += `<div class="insight-item">
            <span>📊</span>
            <div>Total: ${data.total_tasks} tasks | Completed: ${data.completed} | Overdue: ${data.overdue}</div>
        </div>`;
    }
    if (!html) {
        html = '<p class="text-muted">No AI insights available yet.</p>';
    }
    el.innerHTML = html;
}
