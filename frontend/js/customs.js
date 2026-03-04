/**
 * OpsMan – Customs Declarations JS
 */

let currentPage = 1;

document.addEventListener('DOMContentLoaded', () => {
    const user = getStoredUser();
    if (!user) { window.location.href = 'index.html'; return; }
    if (user.role === 'field_employee' || user.role === 'field_agent') {
        window.location.href = 'employee-portal.html'; return;
    }
    document.getElementById('userInfo').textContent = user.username;
    loadDeclarations();
});

async function loadDeclarations(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus')?.value || '';
    const search = document.getElementById('filterSearch')?.value || '';
    try {
        const params = new URLSearchParams({ page, per_page: 20 });
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        const res = await api(`/api/customs.php?${params}`);
        renderTable(res.data?.data ?? []);
        renderPaginationBar(res.data?.total ?? 0, page, 20);
    } catch (e) {
        showToast('Error loading declarations: ' + e.message, 'error');
    }
}

function applyFilters() {
    loadDeclarations(1);
}

function customsStatusBadge(status) {
    const map = {
        draft: 'badge-secondary', submitted: 'badge-info',
        under_review: 'badge-warning', approved: 'badge-success',
        rejected: 'badge-danger', cleared: 'badge-success'
    };
    return `<span class="badge ${map[status] || 'badge-secondary'}">${status.replace(/_/g, ' ')}</span>`;
}

function renderTable(items) {
    const tbody = document.getElementById('customsBody');
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No declarations found</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(d => `
        <tr>
            <td><strong>${d.declaration_no}</strong></td>
            <td>${d.declarant_name}</td>
            <td>${d.shipment_ref || '–'}</td>
            <td>${d.invoice_value ? '$' + parseFloat(d.invoice_value).toLocaleString() + ' ' + d.currency : '–'}</td>
            <td>${d.country_of_origin || '–'}</td>
            <td>${d.port_of_entry || '–'}</td>
            <td>${customsStatusBadge(d.status)}</td>
            <td>
                <button class="btn btn-sm btn-outline" onclick="openEditModal(${d.id})">Edit</button>
                <button class="btn btn-sm btn-danger" onclick="deleteDeclaration(${d.id})">Del</button>
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
    if (page > 1) html += `<button class="page-btn" onclick="loadDeclarations(${page - 1})">‹ Prev</button>`;
    for (let i = Math.max(1, page - 2); i <= Math.min(lastPage, page + 2); i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadDeclarations(${i})">${i}</button>`;
    }
    if (page < lastPage) html += `<button class="page-btn" onclick="loadDeclarations(${page + 1})">Next ›</button>`;
    container.innerHTML = html;
}

function openCreateModal() {
    document.getElementById('declarationId').value = '';
    document.getElementById('customsForm').reset();
    document.getElementById('fCurrency').value = 'USD';
    document.getElementById('formModalTitle').textContent = 'New Declaration';
    document.getElementById('formModal').classList.remove('hidden');
}

async function openEditModal(id) {
    try {
        const res = await api(`/api/customs.php?id=${id}`);
        const d = res.data;
        document.getElementById('declarationId').value      = d.id;
        document.getElementById('fDeclarationNo').value     = d.declaration_no     || '';
        document.getElementById('fDeclarantName').value     = d.declarant_name     || '';
        document.getElementById('fInvoiceValue').value      = d.invoice_value      || '';
        document.getElementById('fCurrency').value          = d.currency           || 'USD';
        document.getElementById('fCountryOfOrigin').value   = d.country_of_origin  || '';
        document.getElementById('fPortOfEntry').value       = d.port_of_entry      || '';
        document.getElementById('fSubmissionDate').value    = d.submission_date    || '';
        document.getElementById('fShipmentId').value        = d.shipment_id        || '';
        document.getElementById('fHsCodes').value           = d.hs_codes           || '';
        document.getElementById('fStatus').value            = d.status             || 'draft';
        document.getElementById('fNotes').value             = d.notes              || '';
        document.getElementById('formModalTitle').textContent = `Edit – ${d.declaration_no}`;
        document.getElementById('formModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function handleFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('declarationId').value;
    const data = {
        declaration_no:    document.getElementById('fDeclarationNo').value.trim(),
        declarant_name:    document.getElementById('fDeclarantName').value.trim(),
        invoice_value:     document.getElementById('fInvoiceValue').value || null,
        currency:          document.getElementById('fCurrency').value.trim() || 'USD',
        country_of_origin: document.getElementById('fCountryOfOrigin').value.trim() || null,
        port_of_entry:     document.getElementById('fPortOfEntry').value.trim() || null,
        submission_date:   document.getElementById('fSubmissionDate').value || null,
        shipment_id:       document.getElementById('fShipmentId').value || null,
        hs_codes:          document.getElementById('fHsCodes').value.trim() || null,
        status:            document.getElementById('fStatus').value,
        notes:             document.getElementById('fNotes').value.trim() || null,
    };
    try {
        if (id) {
            await api(`/api/customs.php?id=${id}`, 'PUT', data);
            showToast('Declaration updated', 'success');
        } else {
            await api('/api/customs.php', 'POST', data);
            showToast('Declaration created', 'success');
        }
        closeModal('formModal');
        loadDeclarations(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function updateStatus(id, status) {
    try {
        await api(`/api/customs.php?id=${id}&action=update-status`, 'PUT', { status });
        showToast('Status updated', 'success');
        loadDeclarations(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}

async function deleteDeclaration(id) {
    if (!confirm('Delete this declaration?')) return;
    try {
        await api(`/api/customs.php?id=${id}`, 'DELETE');
        showToast('Declaration deleted', 'success');
        loadDeclarations(currentPage);
    } catch (e) {
        showToast('Error: ' + e.message, 'error');
    }
}
