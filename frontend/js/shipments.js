/**
 * OpsMan – Shipments JS
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee' || user.role === 'field_agent') {
        window.location.href = 'employee-portal.html'; return;
    }
    document.getElementById('userInfo').textContent = user.username;

    // Hide create button for non-managers
    if (!['admin','operations_manager'].includes(user.role)) {
        const btn = document.getElementById('createBtn');
        if (btn) btn.classList.add('hidden');
    }

    loadShipments();
});

async function loadShipments(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus')?.value || '';
    const search = document.getElementById('filterSearch')?.value || '';
    try {
        const params = new URLSearchParams({ page, per_page: 20 });
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        const res = await api(`/api/shipments.php?${params}`);
        renderTable(res.data?.data ?? []);
        renderPaginationBar(res.data?.total ?? 0, page, 20);
        loadStats(res.data?.data ?? []);
    } catch (e) {
        showToast('Error loading shipments: ' + e.message, 'error');
    }
}

function loadStats(items) {
    document.getElementById('sTotal').textContent   = items.length;
    document.getElementById('sActive').textContent  = items.filter(s => ['pending','in_transit','arrived'].includes(s.status)).length;
    document.getElementById('sCleared').textContent = items.filter(s => s.status === 'cleared').length;
    document.getElementById('sHeld').textContent    = items.filter(s => s.status === 'held').length;
}

function applyFilters() {
    loadShipments(1);
}

function renderTable(items) {
    const tbody = document.getElementById('shipmentsBody');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No shipments found</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(s => `
        <tr>
            <td><strong>${s.ref_number}</strong></td>
            <td>${s.shipper_name}</td>
            <td>${s.consignee_name}</td>
            <td>${s.origin} → ${s.destination}</td>
            <td>${s.cargo_type}${s.cargo_weight ? ' (' + s.cargo_weight + ' kg)' : ''}</td>
            <td>${shipmentStatusBadge(s.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="openDetailModal(${s.id})">View</button>
                <button class="btn btn-sm btn-outline" onclick="openEditModal(${s.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteShipment(${s.id})">Del</button>
            </td>
        </tr>
    `).join('');
}

function shipmentStatusBadge(status) {
    const map = {
        pending: 'badge-secondary', in_transit: 'badge-info',
        arrived: 'badge-primary', cleared: 'badge-success', held: 'badge-danger'
    };
    return `<span class="badge ${map[status] || 'badge-secondary'}">${status.replace(/_/g, ' ')}</span>`;
}

function renderPaginationBar(total, page, perPage) {
    const container = document.getElementById('paginationBar');
    if (!container) return;
    const lastPage = Math.ceil(total / perPage);
    if (lastPage <= 1) { container.innerHTML = ''; return; }
    let html = '';
    if (page > 1) html += `<button class="page-btn" onclick="loadShipments(${page - 1})">‹ Prev</button>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(lastPage, page + 2); i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadShipments(${i})">${i}</button>`;
    }
    if (page < lastPage) html += `<button class="page-btn" onclick="loadShipments(${page + 1})">Next ›</button>`;
    container.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('shipmentId').value = '';
    document.getElementById('shipmentForm').reset();
    document.getElementById('formModalTitle').textContent = 'New Shipment';
    document.getElementById('formModal').classList.remove('hidden');
}

async function openEditModal(id) {
    try {
        const res = await api(`/api/shipments.php?id=${id}`);
        const s = res.data;
        document.getElementById('shipmentId').value         = s.id;
        document.getElementById('fRefNumber').value         = s.ref_number     || '';
        document.getElementById('fShipperName').value       = s.shipper_name   || '';
        document.getElementById('fConsigneeName').value     = s.consignee_name || '';
        document.getElementById('fOrigin').value            = s.origin         || '';
        document.getElementById('fDestination').value       = s.destination    || '';
        document.getElementById('fCargoType').value         = s.cargo_type     || '';
        document.getElementById('fCargoWeight').value       = s.cargo_weight   || '';
        document.getElementById('fStatus').value            = s.status         || 'pending';
        document.getElementById('fClientName').value        = s.client_name    || '';
        document.getElementById('fClientEmail').value       = s.client_email   || '';
        document.getElementById('fClientPhone').value       = s.client_phone   || '';
        document.getElementById('fNotes').value             = s.notes          || '';
        document.getElementById('formModalTitle').textContent = `Edit Shipment – ${s.ref_number}`;
        document.getElementById('formModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error loading shipment: ' + e.message, 'error');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('shipmentId').value;
    const data = {
        ref_number:     document.getElementById('fRefNumber').value.trim(),
        shipper_name:   document.getElementById('fShipperName').value.trim(),
        consignee_name: document.getElementById('fConsigneeName').value.trim(),
        origin:         document.getElementById('fOrigin').value.trim(),
        destination:    document.getElementById('fDestination').value.trim(),
        cargo_type:     document.getElementById('fCargoType').value.trim(),
        cargo_weight:   document.getElementById('fCargoWeight').value || null,
        status:         document.getElementById('fStatus').value,
        client_name:    document.getElementById('fClientName').value.trim() || null,
        client_email:   document.getElementById('fClientEmail').value.trim() || null,
        client_phone:   document.getElementById('fClientPhone').value.trim() || null,
        notes:          document.getElementById('fNotes').value.trim() || null,
    };
    try {
        if (id) {
            await api(`/api/shipments.php?id=${id}`, 'PUT', data);
            showToast('Shipment updated', 'success');
        } else {
            await api('/api/shipments.php', 'POST', data);
            showToast('Shipment created', 'success');
        }
        closeModal('formModal');
        loadShipments(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteShipment(id) {
    if (!confirm('Delete this shipment?')) return;
    try {
        await api(`/api/shipments.php?id=${id}`, 'DELETE');
        showToast('Shipment deleted', 'success');
        loadShipments(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function openDetailModal(id) {
    try {
        const res = await api(`/api/shipments.php?id=${id}`);
        const s = res.data;
        let html = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div><strong>Ref #:</strong> ${s.ref_number}</div>
                <div><strong>Status:</strong> ${shipmentStatusBadge(s.status)}</div>
                <div><strong>Shipper:</strong> ${s.shipper_name}</div>
                <div><strong>Consignee:</strong> ${s.consignee_name}</div>
                <div><strong>Origin:</strong> ${s.origin}</div>
                <div><strong>Destination:</strong> ${s.destination}</div>
                <div><strong>Cargo Type:</strong> ${s.cargo_type}</div>
                <div><strong>Weight:</strong> ${s.cargo_weight ? s.cargo_weight + ' kg' : '–'}</div>
                <div><strong>Client:</strong> ${s.client_name || '–'}</div>
                <div><strong>Email:</strong> ${s.client_email || '–'}</div>
                <div><strong>Phone:</strong> ${s.client_phone || '–'}</div>
                <div><strong>Assigned To:</strong> ${s.assigned_name || '–'}</div>
            </div>
            ${s.notes ? `<div style="margin-top:1rem"><strong>Notes:</strong> ${s.notes}</div>` : ''}
        `;
        if (s.customs_declarations?.length) {
            html += `<h4 style="margin-top:1.5rem">Customs Declarations</h4><ul>` +
                s.customs_declarations.map(c => `<li>${c.declaration_no} — ${c.status}</li>`).join('') + '</ul>';
        }
        if (s.transit_records?.length) {
            html += `<h4 style="margin-top:1rem">Transit Records</h4><ul>` +
                s.transit_records.map(t => `<li>${t.vehicle_no} — ${t.driver_name || '–'} — ${t.status}</li>`).join('') + '</ul>';
        }
        document.getElementById('detailTitle').textContent = `Shipment – ${s.ref_number}`;
        document.getElementById('detailBody').innerHTML = html;
        document.getElementById('detailModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}
