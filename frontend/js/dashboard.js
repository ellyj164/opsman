/**
 * OpsMan – Dashboard JS
 * Loads stats, initialises Leaflet map, renders Chart.js charts,
 * and auto-refreshes every 30 seconds.
 */

let map = null;
let employeeMarkers = [];
let statusChart = null;
let refreshInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee') { window.location.href = 'employee-portal.html'; return; }

    document.getElementById('userInfo').textContent = user.username;

    initMap();
    loadDashboard();

    // Auto-refresh every 30 seconds
    refreshInterval = setInterval(loadDashboard, 30000);
});

// ── Main Load ─────────────────────────────────────────────────────────

async function loadDashboard() {
    try {
        const res = await api('/api/dashboard.php');
        renderStats(res.data.stats);
        renderRecentTasks(res.data.recent_tasks);
        renderAlerts(res.data.alerts);
        renderStatusChart(res.data.stats.task_status_chart);
        document.getElementById('lastRefresh').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
        showToast('Dashboard load error: ' + e.message, 'error');
    }

    refreshMap();
}

// ── Stats ─────────────────────────────────────────────────────────────

function renderStats(stats) {
    if (!stats) return;
    document.getElementById('statActive').textContent    = stats.active_tasks    ?? '–';
    document.getElementById('statCompleted').textContent = stats.completed_today ?? '–';
    document.getElementById('statOverdue').textContent   = stats.overdue_tasks   ?? '–';
    document.getElementById('statEmployees').textContent = stats.total_employees ?? '–';

    const alertBadge = document.getElementById('alertBadge');
    if (alertBadge) alertBadge.textContent = stats.pending_alerts ?? 0;
}

// ── Map ───────────────────────────────────────────────────────────────

function initMap() {
    if (!window.L) return;
    map = L.map('map').setView([20, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map);
}

async function refreshMap() {
    if (!map || !window.L) return;
    try {
        const res = await api('/api/dashboard.php?action=employee-locations');
        const locations = res.data || [];

        // Clear existing markers
        employeeMarkers.forEach(m => map.removeLayer(m));
        employeeMarkers = [];

        if (!locations.length) return;

        const bounds = [];
        locations.forEach(loc => {
            const marker = L.marker([loc.latitude, loc.longitude])
                .bindPopup(`
                    <strong>${loc.full_name}</strong><br>
                    ${loc.employee_code}<br>
                    ${loc.current_task ? '📋 ' + loc.current_task + '<br>' : ''}
                    🕒 ${formatDate(loc.logged_at)}
                `)
                .addTo(map);
            employeeMarkers.push(marker);
            bounds.push([loc.latitude, loc.longitude]);
        });

        if (bounds.length) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 12 });
        }
    } catch (e) {
        console.warn('Map refresh error:', e.message);
    }
}

// ── Recent Tasks ──────────────────────────────────────────────────────

function renderRecentTasks(tasks) {
    const body = document.getElementById('recentTasksBody');
    if (!body || !tasks) return;

    if (!tasks.length) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No recent tasks</td></tr>';
        return;
    }

    body.innerHTML = tasks.map(t => `
        <tr>
            <td>${t.title}</td>
            <td><span class="badge badge-info" style="font-size:.7rem">${t.task_type.replace(/_/g,' ')}</span></td>
            <td>${t.assigned_to_name || '<span class="text-muted">Unassigned</span>'}</td>
            <td>${priorityBadge(t.priority)}</td>
            <td>${statusBadge(t.status)}</td>
            <td class="${isOverdue(t.deadline) && t.status !== 'completed' ? 'text-danger' : ''}">
                ${formatDateShort(t.deadline)}
            </td>
        </tr>
    `).join('');
}

// ── Alerts ────────────────────────────────────────────────────────────

function renderAlerts(alerts) {
    const list = document.getElementById('alertsList');
    if (!list) return;

    if (!alerts?.length) {
        list.innerHTML = '<p class="text-center text-muted" style="padding:1rem">No unread alerts</p>';
        return;
    }

    const icons = { critical: '🔴', warning: '🟡', info: 'ℹ️' };
    list.innerHTML = alerts.map(a => `
        <div class="alert-item ${a.is_read ? '' : 'unread'} severity-${a.severity}">
            <div class="alert-icon">${icons[a.severity] || 'ℹ️'}</div>
            <div class="alert-content">
                <div class="alert-title">${a.title}</div>
                <div class="alert-message">${a.message}</div>
                <div class="alert-meta">
                    <span class="badge ${a.severity === 'critical' ? 'badge-danger' : a.severity === 'warning' ? 'badge-warning' : 'badge-info'}">${a.severity}</span>
                    <span class="text-muted">${formatDate(a.created_at)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

// ── Status Chart ──────────────────────────────────────────────────────

function renderStatusChart(statusMap) {
    const ctx = document.getElementById('statusChart');
    if (!ctx || !window.Chart || !statusMap) return;

    const labels = Object.keys(statusMap).map(s => s.replace(/_/g, ' '));
    const data   = Object.values(statusMap);
    const colors = {
        pending:     '#6c757d',
        assigned:    '#4285f4',
        in_progress: '#1a73e8',
        completed:   '#34a853',
        overdue:     '#ea4335',
    };
    const backgroundColors = Object.keys(statusMap).map(s => colors[s] || '#ccc');

    if (statusChart) statusChart.destroy();

    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, backgroundColor: backgroundColors, borderWidth: 2, borderColor: '#fff' }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } },
            },
        },
    });
}
