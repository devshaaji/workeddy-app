/**
 * organization-worksites.js — Worksites management page
 * Routes: GET /organizations/{id}/worksites
 * API:    GET    /api/v1/organizations/{id}/worksites
 *         POST   /api/v1/organizations/{id}/worksites
 *         PUT    /api/v1/organizations/{id}/worksites/{wId}
 *         DELETE /api/v1/organizations/{id}/worksites/{wId}
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('worksitesPage');
    if (!page) { return; }

    var orgId   = page.getAttribute('data-org-id') || '';
    var apiBase = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/worksites');

    var PAGE_SIZE = 15;
    var allRecords = [];
    var filteredRecords = [];
    var currentPage = 1;

    var tbody    = document.getElementById('worksitesBody');
    var count    = document.getElementById('ws-result-count');
    var pageInfo = document.getElementById('ws-page-info');
    var prevBtn  = document.getElementById('ws-prev');
    var nextBtn  = document.getElementById('ws-next');
    var searchEl = document.getElementById('ws-search');
    var statusEl = document.getElementById('ws-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    function updateStats(records) {
        App.utils.setText('#ws-stat-total', records.length);
        App.utils.setText('#ws-stat-active', records.filter(function (r) { return r.status === 'active'; }).length);
    }

    function renderRows() {
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageRows = filteredRecords.slice(start, start + PAGE_SIZE);
        var total = filteredRecords.length;
        var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (count) { count.textContent = total; }
        if (pageInfo) { pageInfo.textContent = 'Showing ' + Math.min(start + 1, total) + '–' + Math.min(start + PAGE_SIZE, total) + ' of ' + total; }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; }

        if (!pageRows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">' +
                '<i class="bi bi-geo-alt fs-3 d-block mb-2 opacity-50"></i>' +
                'No worksites found. Add the first worksite to get started.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            return '<tr>' +
                '<td><span class="fw-medium">' + e(r.name) + '</span></td>' +
                '<td class="text-muted">' + (r.location ? e(r.location) : '<span class="text-muted fst-italic">Not specified</span>') + '</td>' +
                '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Edit', onclick: 'OrgWorksites.openEdit(' + JSON.stringify(r) + ')' },
                    { label: 'Delete', class: 'text-danger', onclick: 'OrgWorksites.confirmDelete("' + r.id + '", "' + e(r.name) + '")' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    function applyFilters() {
        var q = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var st = statusEl ? statusEl.value : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var matchQ = !q || (r.name || '').toLowerCase().includes(q) || (r.location || '').toLowerCase().includes(q);
            var matchSt = !st || r.status === st;
            return matchQ && matchSt;
        });
        renderRows();
    }

    function load() {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>';
        App.api.get(apiBase, { limit: 200 }).then(function (res) {
            if (!res.ok) { App.notify.error('Failed to load worksites.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            updateStats(allRecords);
            applyFilters();
        });
    }

    // ── Modal helpers ────────────────────────────────────────────────────────
    var modal      = document.getElementById('worksiteModal');
    var form       = document.getElementById('worksiteForm');
    var submitBtn  = document.getElementById('worksiteSubmitBtn');
    var modalTitle = document.getElementById('worksiteModalTitle');
    var idField    = document.getElementById('worksiteId');
    var statusGrp  = document.getElementById('wsStatusGroup');

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-geo-alt me-2" style="color:var(--we-primary)"></i>Add Worksite'; }
        if (idField)    { idField.value = ''; }
        if (statusGrp)  { statusGrp.style.display = 'none'; }
        App.forms.reset(form);
        App.ui.clearAlert('#worksiteModalAlert');
        App.modals.open('#worksiteModal');
    }

    window.OrgWorksites = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Worksite'; }
            if (idField) { idField.value = record.id; }
            if (statusGrp) { statusGrp.style.display = ''; }
            form.elements.name.value = record.name || '';
            if (form.elements.location) { form.elements.location.value = record.location || ''; }
            if (form.elements.status) { form.elements.status.value = record.status || 'active'; }
            App.ui.clearAlert('#worksiteModalAlert');
            App.modals.open('#worksiteModal');
        },
        confirmDelete: function (id, name) {
            App.modals.confirm({
                title: 'Delete Worksite',
                message: 'Are you sure you want to delete <strong>' + App.utils.escapeHtml(name) + '</strong>? This cannot be undone.',
                confirmText: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    App.api.delete(apiBase + '/' + id).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Delete failed.'); return; }
                        App.notify.success('Worksite deleted.');
                        load();
                    });
                }
            });
        }
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            var wsId = idField ? idField.value : '';
            var method = wsId ? 'put' : 'post';
            var url    = wsId ? (apiBase + '/' + wsId) : apiBase;
            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Worksite');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#worksiteModalAlert');
                    return;
                }
                App.notify.success(wsId ? 'Worksite updated.' : 'Worksite added.');
                App.modals.close('#worksiteModal');
                load();
            });
        });
    }

    var btnAdd = document.getElementById('btnAddWorksite');
    if (btnAdd) { btnAdd.addEventListener('click', openCreate); }
    if (searchEl) { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (statusEl) { statusEl.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); }); }

    load();
})();
