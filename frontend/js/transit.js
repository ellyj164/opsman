/**
 * OpsMan – Transit Records JS
 */

let currentPage = 1;
let transitMap = null;
let transitMarkers = [];

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee' || user.role === 'field_agent') {
        window.location.href = 'employee-portal.html'; return;
    }
    document.getElementById('userInfo').textContent = user.username;
    initMap();
    loadTransits();
});

function initMap() {
    if (!window.L) return;
    transitMap = L.map('transitMap').setView([40, -95], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(transitMap);
}

async function refreshMap() {
    if (!transitMap || !window.L) return;
    try {
        const res = await api('/api/transit.php?status=in_transit&per_page=100');
        const items = res.data?.data ?? [];

        transitMarkers.forEach(m => transitMap.removeLayer(m));
        transitMarkers = [];

        items.filter(t => t.latitude && t.longitude).forEach(t => {
            const marker = L.marker([t.latitude, t.longitude])
                .bindPopup(`<strong>${t.vehicle_no}</strong><br>${t.driver_name || '–'}<br>
                    Shipment: ${t.shipment_ref || '–'}<br>Status: ${t.status}`)
                .addTo(transitMap);
            transitMarkers.push(marker);
        });
    } catch (e) {
        // Map refresh failure is non-critical
    }
}

async function loadTransits(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus')?.value || '';
    const search = document.getElementById('filterSearch')?.value || '';
    try {
        const params = new URLSearchParams({ page, per_page: 20 });
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        const res = await api(`/api/transit.php?${params}`);
        renderTable(res.data?.data ?? []);
        renderPaginationBar(res.data?.total ?? 0, page, 20);
        refreshMap();
    } catch (e) {
        showToast('Error loading transits: ' + e.message, 'error');
    }
}

function applyFilters() {
    loadTransits(1);
}

function transitStatusBadge(status) {
    const map = {
        scheduled: 'badge-secondary', in_transit: 'badge-info',
        border_entry: 'badge-warning', border_clearance: 'badge-warning',
        completed: 'badge-success', delayed: 'badge-danger', stopped: 'badge-danger'
    };
    return `<span class="badge ${map[status] || 'badge-secondary'}">${status.replace(/_/g, ' ')}</span>`;
}

function renderTable(items) {
    const tbody = document.getElementById('transitBody');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No transit records found</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(t => `
        <tr>
            <td><strong>${t.vehicle_no}</strong></td>
            <td>${t.driver_name || '–'}${t.driver_phone ? '<br><small>' + t.driver_phone + '</small>' : ''}</td>
            <td>${t.shipment_ref || '–'}</td>
            <td>${t.origin_border || '–'} → ${t.destination_border || '–'}</td>
            <td>${t.departure_time ? formatDateShort(t.departure_time) : '–'}</td>
            <td>${t.expected_arrival ? formatDateShort(t.expected_arrival) : '–'}</td>
            <td>${transitStatusBadge(t.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="updateTransitStatus(${t.id})">Update</button>
                <button class="btn btn-sm btn-outline" onclick="openEditModal(${t.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteTransit(${t.id})">Del</button>
            </td>
        </tr>
    `).join('');
}

function renderPaginationBar(total, page, perPage) {
    const container = document.getElementById('paginationBar');
    if (!container) return;
    const lastPage = Math.ceil(total / perPage);
    if (lastPage <= 1) { container.innerHTML = ''; return; }
    let html = '';
    if (page > 1) html += `<button class="page-btn" onclick="loadTransits(${page - 1})">‹ Prev</button>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(lastPage, page + 2); i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadTransits(${i})">${i}</button>`;
    }
    if (page < lastPage) html += `<button class="page-btn" onclick="loadTransits(${page + 1})">Next ›</button>`;
    container.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('transitId').value = '';
    document.getElementById('transitForm').reset();
    document.getElementById('formModalTitle').textContent = 'New Transit Record';
    document.getElementById('formModal').classList.remove('hidden');
}

async function openEditModal(id) {
    try {
        const res = await api(`/api/transit.php?id=${id}`);
        const t = res.data;
        document.getElementById('transitId').value           = t.id;
        document.getElementById('fVehicleNo').value          = t.vehicle_no           || '';
        document.getElementById('fDriverName').value         = t.driver_name          || '';
        document.getElementById('fDriverPhone').value        = t.driver_phone         || '';
        document.getElementById('fOriginBorder').value       = t.origin_border        || '';
        document.getElementById('fDestinationBorder').value  = t.destination_border   || '';
        document.getElementById('fDepartureTime').value      = t.departure_time       ? t.departure_time.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('fExpectedArrival').value    = t.expected_arrival     ? t.expected_arrival.replace(' ', 'T').substring(0, 16) : '';
        document.getElementById('fStatus').value             = t.status               || 'scheduled';
        document.getElementById('fLatitude').value           = t.latitude             || '';
        document.getElementById('fLongitude').value          = t.longitude            || '';
        document.getElementById('fShipmentId').value         = t.shipment_id          || '';
        document.getElementById('fNotes').value              = t.notes                || '';
        document.getElementById('formModalTitle').textContent = `Edit – ${t.vehicle_no}`;
        document.getElementById('formModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('transitId').value;
    const data = {
        vehicle_no:          document.getElementById('fVehicleNo').value.trim(),
        driver_name:         document.getElementById('fDriverName').value.trim() || null,
        driver_phone:        document.getElementById('fDriverPhone').value.trim() || null,
        origin_border:       document.getElementById('fOriginBorder').value.trim() || null,
        destination_border:  document.getElementById('fDestinationBorder').value.trim() || null,
        departure_time:      document.getElementById('fDepartureTime').value || null,
        expected_arrival:    document.getElementById('fExpectedArrival').value || null,
        status:              document.getElementById('fStatus').value,
        latitude:            document.getElementById('fLatitude').value || null,
        longitude:           document.getElementById('fLongitude').value || null,
        shipment_id:         document.getElementById('fShipmentId').value || null,
        notes:               document.getElementById('fNotes').value.trim() || null,
    };
    try {
        if (id) {
            await api(`/api/transit.php?id=${id}`, 'PUT', data);
            showToast('Transit record updated', 'success');
        } else {
            await api('/api/transit.php', 'POST', data);
            showToast('Transit record created', 'success');
        }
        closeModal('formModal');
        loadTransits(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

function updateTransitStatus(id) {
    document.getElementById('sTransitId').value = id;
    document.getElementById('statusForm').reset();
    document.getElementById('statusModal').classList.remove('hidden');
}

async function handleStatusSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('sTransitId').value;
    const data = {
        status:    document.getElementById('sStatus').value,
        latitude:  document.getElementById('sLatitude').value || null,
        longitude: document.getElementById('sLongitude').value || null,
    };
    try {
        await api(`/api/transit.php?id=${id}&action=update-status`, 'PUT', data);
        showToast('Status updated', 'success');
        closeModal('statusModal');
        loadTransits(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteTransit(id) {
    if (!confirm('Delete this transit record?')) return;
    try {
        await api(`/api/transit.php?id=${id}`, 'DELETE');
        showToast('Transit record deleted', 'success');
        loadTransits(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}
