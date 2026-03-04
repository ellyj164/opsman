/**
 * OpsMan – Analytics JS
 * Loads performance metrics, delay analysis, and AI insights.
 */

let perfChart  = null;
let delayChart = null;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee') { window.location.href = 'employee-portal.html'; return; }

    document.getElementById('userInfo').textContent = user.username;
    loadAllAnalytics();
});

async function loadAllAnalytics() {
    await Promise.allSettled([
        loadPerformance(),
        loadDelays(),
        loadAiInsights(),
        loadBottlenecks(),
    ]);
}

// ── Performance ───────────────────────────────────────────────────────

async function loadPerformance() {
    try {
        const res  = await api('/api/analytics.php?action=performance');
        const data = res.data || [];
        renderPerfChart(data);
        renderPerfTable(data);
    } catch (e) {
        document.getElementById('perfTableBody').innerHTML =
            `<tr><td colspan="7" class="text-center text-danger">${e.message}</td></tr>`;
    }
}

function renderPerfChart(data) {
    const ctx = document.getElementById('perfChart');
    if (!ctx || !window.Chart) return;

    const labels    = data.map(d => d.full_name);
    const completed = data.map(d => parseInt(d.completed) || 0);
    const overdue   = data.map(d => parseInt(d.overdue)   || 0);

    if (perfChart) perfChart.destroy();
    perfChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Completed', data: completed, backgroundColor: '#34a853' },
                { label: 'Overdue',   data: overdue,   backgroundColor: '#ea4335' },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { x: { stacked: false }, y: { beginAtZero: true } },
        },
    });
}

function renderPerfTable(data) {
    const body = document.getElementById('perfTableBody');
    if (!body) return;

    if (!data.length) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No data</td></tr>';
        return;
    }

    body.innerHTML = data.map(d => {
        const rate = d.total_tasks > 0
            ? Math.round((d.completed / d.total_tasks) * 100)
            : 100;
        const scoreClass = d.performance_score >= 90 ? 'score-high' : d.performance_score >= 70 ? 'score-med' : 'score-low';
        return `
            <tr>
                <td>${d.full_name}</td>
                <td>${d.department || '–'}</td>
                <td>${d.total_tasks || 0}</td>
                <td>${d.completed  || 0}</td>
                <td>${d.overdue    || 0}</td>
                <td>
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill" style="width:${rate}%;background:${rate>=80?'#34a853':rate>=50?'#fbbc04':'#ea4335'}"></div>
                        <span>${rate}%</span>
                    </div>
                </td>
                <td><span class="score-badge ${scoreClass}">★ ${d.performance_score}</span></td>
            </tr>
        `;
    }).join('');
}

// ── Delays ────────────────────────────────────────────────────────────

async function loadDelays() {
    try {
        const res  = await api('/api/analytics.php?action=delays');
        const data = res.data || [];
        renderDelayChart(data);
    } catch (e) {
        console.warn('Delay data error:', e.message);
    }
}

function renderDelayChart(data) {
    const ctx = document.getElementById('delayChart');
    if (!ctx || !window.Chart) return;

    const labels = data.map(d => d.task_type.replace(/_/g, ' '));
    const rates  = data.map(d => parseFloat(d.delay_rate_pct) || 0);

    if (delayChart) delayChart.destroy();
    delayChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Delay Rate (%)',
                data: rates,
                backgroundColor: rates.map(r => r > 50 ? '#ea4335' : r > 25 ? '#fbbc04' : '#34a853'),
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, max: 100 } },
        },
    });
}

// ── AI Insights ───────────────────────────────────────────────────────

async function loadAiInsights() {
    const container = document.getElementById('aiInsights');
    if (!container) return;
    try {
        const res  = await api('/api/analytics.php?action=employee-score');
        const data = res.data;

        if (data.status === 'unavailable') {
            container.innerHTML = `<p class="text-muted">AI service is currently unavailable.</p>`;
            return;
        }

        const items = Array.isArray(data) ? data : (data.data || []);
        if (!items.length) {
            container.innerHTML = '<p class="text-muted">No AI insights available yet.</p>';
            return;
        }

        container.innerHTML = items.slice(0, 5).map(emp => `
            <div class="insight-item">
                <div style="min-width:120px;font-weight:600">${emp.full_name || emp.employee_code}</div>
                <div>Score: <strong>${emp.composite_score ?? emp.performance_score ?? '–'}</strong></div>
                ${emp.recommendation ? `<div class="text-muted" style="font-size:.8rem">${emp.recommendation}</div>` : ''}
            </div>
        `).join('');
    } catch (e) {
        container.innerHTML = `<p class="text-muted">Could not load AI insights.</p>`;
    }
}

// ── Bottlenecks ───────────────────────────────────────────────────────

async function loadBottlenecks() {
    const list = document.getElementById('bottlenecksList');
    if (!list) return;
    try {
        const res  = await api('/api/analytics.php?action=bottlenecks');
        const data = res.data;

        if (data.status === 'unavailable') {
            list.innerHTML = '<p class="text-muted" style="padding:1rem">AI service unavailable.</p>';
            return;
        }

        const bottles = (data.data || data).bottlenecks || [];
        const recs    = (data.data || data).recommendations || [];

        if (!bottles.length) {
            list.innerHTML = '<p class="text-muted" style="padding:1rem">No bottlenecks detected.</p>';
            return;
        }

        list.innerHTML = bottles.map(b => `
            <div class="bottleneck-item ${b.severity}">
                <strong>${b.type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</strong>
                — ${b.delay_rate}% delay rate (${b.overdue}/${b.total_tasks} tasks overdue)
            </div>
        `).join('') + (recs.length ? `
            <div style="padding:.75rem;font-size:.875rem;color:#6c757d">
                <strong>Recommendations:</strong>
                <ul style="margin:.4rem 0 0 1.2rem">${recs.map(r => `<li>${r}</li>`).join('')}</ul>
            </div>
        ` : '');
    } catch (e) {
        list.innerHTML = `<p class="text-danger" style="padding:1rem">${e.message}</p>`;
    }
}

// ── Delay Prediction Tool ─────────────────────────────────────────────

async function predictDelay() {
    const result = document.getElementById('predictionResult');
    result.classList.add('hidden');
    result.className = 'prediction-result hidden';

    const qs = new URLSearchParams({
        task_type:                  document.getElementById('predTaskType').value,
        priority:                   document.getElementById('predPriority').value,
        days_until_deadline:        document.getElementById('predDays').value,
        employee_performance_score: document.getElementById('predScore').value,
    });

    try {
        const res  = await api(`/api/analytics.php?action=predict-delay&${qs}`);
        const pred = res.data?.data || res.data;

        if (!pred) { showToast('No prediction returned', 'warning'); return; }

        const delayed = pred.will_be_delayed;
        result.className = `prediction-result ${delayed ? 'predict-delayed' : 'predict-on-time'}`;
        result.innerHTML = `
            <strong>${delayed ? '⚠️ Likely to be delayed' : '✅ Likely on time'}</strong>
            (probability: ${(parseFloat(pred.delay_probability) * 100).toFixed(1)}%,
            confidence: ${pred.confidence})<br>
            ${pred.factors?.length ? `<ul style="margin:.4rem 0 0 1.2rem;font-size:.8rem">${pred.factors.map(f => `<li>${f}</li>`).join('')}</ul>` : ''}
        `;
        result.classList.remove('hidden');
    } catch (e) {
        showToast('Prediction failed: ' + e.message, 'error');
    }
}
