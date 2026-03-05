/**
 * OpsMan – Points / Leaderboard JS
 */

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }

    const userInfoEl = document.getElementById('userInfo');
    if (userInfoEl) userInfoEl.textContent = user.username;

    loadLeaderboard();
});

// ── Leaderboard ───────────────────────────────────────────────────────

async function loadLeaderboard() {
    const body  = document.getElementById('leaderboardBody');
    body.innerHTML = '<tr><td colspan="7" class="text-center">Loading…</td></tr>';

    const month = document.getElementById('filterMonth')?.value || '';
    const qs = month ? `?month=${month}` : '';

    try {
        const res = await api(`/api/points.php${qs}`);
        const data = res.data || [];

        if (!data.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No points data available.</td></tr>';
            return;
        }

        body.innerHTML = data.map(row => {
            const rankIcon = row.rank === 1 ? '🥇' : row.rank === 2 ? '🥈' : row.rank === 3 ? '🥉' : `#${row.rank}`;
            return `
                <tr>
                    <td><strong>${rankIcon}</strong></td>
                    <td>${row.full_name}</td>
                    <td>${row.employee_code}</td>
                    <td>${row.department || '–'}</td>
                    <td><span class="badge badge-success">${row.total_points}</span></td>
                    <td>${row.tasks_scored}</td>
                    <td>
                        <button class="btn btn-xs btn-outline" onclick="viewEmployeeDetail(${row.employee_id}, '${escapeHtml(row.full_name)}')">View Detail</button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        body.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${e.message}</td></tr>`;
    }
}

function clearFilter() {
    const monthInput = document.getElementById('filterMonth');
    if (monthInput) monthInput.value = '';
    loadLeaderboard();
}

// ── Employee Detail ───────────────────────────────────────────────────

async function viewEmployeeDetail(empId, empName) {
    const panel = document.getElementById('empDetailPanel');
    panel.classList.remove('hidden');
    document.getElementById('empDetailTitle').textContent = `Points Detail: ${empName}`;

    try {
        // Load employee points summary
        const [pointsRes, contribRes] = await Promise.all([
            api(`/api/points.php?action=employee&id=${empId}`),
            api(`/api/points.php?action=step-contribution&id=${empId}`),
        ]);

        const pData = pointsRes.data || {};
        const statsEl = document.getElementById('empDetailStats');
        statsEl.innerHTML = `
            <div class="stat-card stat-blue" style="min-width:auto;">
                <div class="stat-content">
                    <div class="stat-value">${pData.total_points ?? 0}</div>
                    <div class="stat-label">Total Points</div>
                </div>
            </div>
            <div class="stat-card stat-purple" style="min-width:auto;">
                <div class="stat-content">
                    <div class="stat-value">#${pData.rank ?? '–'}</div>
                    <div class="stat-label">Rank</div>
                </div>
            </div>
        `;

        // Step contribution
        const contrib = contribRes.data || [];
        const contribBody = document.getElementById('stepContribBody');
        if (!contrib.length) {
            contribBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No step contribution data.</td></tr>';
        } else {
            contribBody.innerHTML = contrib.map(c => `
                <tr>
                    <td>${c.step_name}</td>
                    <td><span class="badge badge-success">${c.total_points}</span></td>
                    <td>${c.times_completed}</td>
                </tr>
            `).join('');
        }

        // Scroll to panel
        panel.scrollIntoView({ behavior: 'smooth' });
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── Helpers ───────────────────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
