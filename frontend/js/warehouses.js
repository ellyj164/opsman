/**
 * OpsMan – Warehouses JS
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee' || user.role === 'field_agent') {
        window.location.href = 'employee-portal.html'; return;
    }
    document.getElementById('userInfo').textContent = user.username;
    loadWarehouses();
});

async function loadWarehouses(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus')?.value || '';
    const search = document.getElementById('filterSearch')?.value || '';
    try {
        const params = new URLSearchParams({ page, per_page: 20 });
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        const res = await api(`/api/warehouses.php?${params}`);
        renderGrid(res.data?.data ?? []);
        renderPaginationBar(res.data?.total ?? 0, page, 20);
    } catch (e) {
        showToast('Error loading warehouses: ' + e.message, 'error');
    }
}

function applyFilters() {
    loadWarehouses(1);
}

function warehouseStatusBadge(status) {
    const map = { active: 'badge-success', inactive: 'badge-secondary', maintenance: 'badge-warning' };
    return `<span class="badge ${map[status] || 'badge-secondary'}">${status}</span>`;
}

function renderGrid(items) {
    const grid = document.getElementById('warehouseGrid');
    if (!items.length) {
        grid.innerHTML = '<p class="text-center text-muted">No warehouses found</p>';
        return;
    }
    grid.innerHTML = items.map(w => `
        <div class="card" style="padding:1.25rem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.75rem">
                <div>
                    <h4 style="margin:0">${w.name}</h4>
                    <span style="color:#64748b;font-size:.875rem">${w.code}</span>
                </div>
                ${warehouseStatusBadge(w.status)}
            </div>
            <div style="font-size:.875rem;color:#475569;margin-bottom:.5rem">
                📍 ${[w.city, w.country].filter(Boolean).join(', ') || '–'}
            </div>
            ${w.capacity_sqm ? `<div style="font-size:.875rem;color:#475569">📐 ${parseFloat(w.capacity_sqm).toLocaleString()} sqm</div>` : ''}
            ${w.manager_name ? `<div style="font-size:.875rem;color:#475569;margin-top:.25rem">👤 ${w.manager_name}</div>` : ''}
            <div style="margin-top:1rem;display:flex;gap:.5rem">
                <button class="btn btn-sm btn-outline" onclick="openDetailModal(${w.id})">Details</button>
                <button class="btn btn-sm btn-outline" onclick="openEditModal(${w.id})">Edit</button>
                <button class="btn btn-sm btn-outline" onclick="addRecord(${w.id})">+ Record</button>
                <button class="btn btn-sm btn-danger" onclick="deleteWarehouse(${w.id})">Del</button>
            </div>
        </div>
    `).join('');
}

function renderPaginationBar(total, page, perPage) {
    const container = document.getElementById('paginationBar');
    if (!container) return;
    const lastPage = Math.ceil(total / perPage);
    if (lastPage <= 1) { container.innerHTML = ''; return; }
    let html = '';
    if (page > 1) html += `<button class="page-btn" onclick="loadWarehouses(${page - 1})">‹ Prev</button>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(lastPage, page + 2); i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadWarehouses(${i})">${i}</button>`;
    }
    if (page < lastPage) html += `<button class="page-btn" onclick="loadWarehouses(${page + 1})">Next ›</button>`;
    container.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('warehouseId').value = '';
    document.getElementById('warehouseForm').reset();
    document.getElementById('formModalTitle').textContent = 'New Warehouse';
    document.getElementById('formModal').classList.remove('hidden');
}

async function openEditModal(id) {
    try {
        const res = await api(`/api/warehouses.php?id=${id}`);
        const w = res.data;
        document.getElementById('warehouseId').value     = w.id;
        document.getElementById('fName').value           = w.name           || '';
        document.getElementById('fCode').value           = w.code           || '';
        document.getElementById('fAddress').value        = w.address        || '';
        document.getElementById('fCity').value           = w.city           || '';
        document.getElementById('fCountry').value        = w.country        || '';
        document.getElementById('fLatitude').value       = w.latitude       || '';
        document.getElementById('fLongitude').value      = w.longitude      || '';
        document.getElementById('fCapacitySqm').value    = w.capacity_sqm   || '';
        document.getElementById('fStatus').value         = w.status         || 'active';
        document.getElementById('formModalTitle').textContent = `Edit – ${w.name}`;
        document.getElementById('formModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('warehouseId').value;
    const data = {
        name:         document.getElementById('fName').value.trim(),
        code:         document.getElementById('fCode').value.trim(),
        address:      document.getElementById('fAddress').value.trim() || null,
        city:         document.getElementById('fCity').value.trim() || null,
        country:      document.getElementById('fCountry').value.trim() || null,
        latitude:     document.getElementById('fLatitude').value || null,
        longitude:    document.getElementById('fLongitude').value || null,
        capacity_sqm: document.getElementById('fCapacitySqm').value || null,
        status:       document.getElementById('fStatus').value,
    };
    try {
        if (id) {
            await api(`/api/warehouses.php?id=${id}`, 'PUT', data);
            showToast('Warehouse updated', 'success');
        } else {
            await api('/api/warehouses.php', 'POST', data);
            showToast('Warehouse created', 'success');
        }
        closeModal('formModal');
        loadWarehouses(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function openDetailModal(id) {
    try {
        const [whRes, recRes] = await Promise.all([
            api(`/api/warehouses.php?id=${id}`),
            api(`/api/warehouses.php?action=records&warehouse_id=${id}&per_page=10`)
        ]);
        const w = whRes.data;
        const records = recRes.data ?? [];
        let html = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                <div><strong>Name:</strong> ${w.name}</div>
                <div><strong>Code:</strong> ${w.code}</div>
                <div><strong>City:</strong> ${w.city || '–'}</div>
                <div><strong>Country:</strong> ${w.country || '–'}</div>
                <div><strong>Capacity:</strong> ${w.capacity_sqm ? w.capacity_sqm + ' sqm' : '–'}</div>
                <div><strong>Status:</strong> ${warehouseStatusBadge(w.status)}</div>
                <div><strong>Manager:</strong> ${w.manager_name || '–'}</div>
            </div>
            <h4>Recent Records</h4>
        `;
        if (records.length) {
            html += `<table class="table"><thead><tr><th>Type</th><th>Cargo</th><th>Qty</th><th>Condition</th><th>Date</th></tr></thead><tbody>` +
                records.map(r => `<tr>
                    <td>${r.record_type}</td>
                    <td>${r.cargo_description ? r.cargo_description.substring(0, 40) + '…' : '–'}</td>
                    <td>${r.quantity ? r.quantity + ' ' + (r.unit || '') : '–'}</td>
                    <td>${r.condition_status}</td>
                    <td>${r.inspection_date ? formatDateShort(r.inspection_date) : '–'}</td>
                </tr>`).join('') + '</tbody></table>';
        } else {
            html += '<p class="text-muted">No records yet</p>';
        }
        document.getElementById('detailTitle').textContent = `Warehouse – ${w.name}`;
        document.getElementById('detailBody').innerHTML = html;
        document.getElementById('detailModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

function addRecord(warehouseId) {
    document.getElementById('recordForm').reset();
    document.getElementById('rWarehouseId').value = warehouseId;
    document.getElementById('recordModal').classList.remove('hidden');
}

async function handleRecordSubmit(e) {
    e.preventDefault();
    const data = {
        warehouse_id:     document.getElementById('rWarehouseId').value,
        record_type:      document.getElementById('rRecordType').value,
        condition_status: document.getElementById('rConditionStatus').value,
        cargo_description:document.getElementById('rCargoDescription').value.trim() || null,
        quantity:         document.getElementById('rQuantity').value || null,
        unit:             document.getElementById('rUnit').value.trim() || null,
        weight_kg:        document.getElementById('rWeightKg').value || null,
        shipment_id:      document.getElementById('rShipmentId').value || null,
        inspection_date:  document.getElementById('rInspectionDate').value || null,
        notes:            document.getElementById('rNotes').value.trim() || null,
    };
    try {
        await api('/api/warehouses.php?action=record', 'POST', data);
        showToast('Record added', 'success');
        closeModal('recordModal');
        loadWarehouses(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteWarehouse(id) {
    if (!confirm('Delete this warehouse?')) return;
    try {
        await api(`/api/warehouses.php?id=${id}`, 'DELETE');
        showToast('Warehouse deleted', 'success');
        loadWarehouses(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}
