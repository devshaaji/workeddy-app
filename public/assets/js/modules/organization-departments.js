/**
 * organization-departments.js — Departments management page
 * Routes: GET /organizations/{id}/departments
 * API:    GET    /api/v1/organizations/{id}/departments
 *         POST   /api/v1/organizations/{id}/departments
 *         PUT    /api/v1/organizations/{id}/departments/{dId}
 *         DELETE /api/v1/organizations/{id}/departments/{dId}
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('departmentsPage');
    if (!page) { return; }

    var orgId        = page.getAttribute('data-org-id') || '';
    var apiBase      = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/departments');
    var worksitesApi = page.getAttribute('data-worksites-api') || ('/api/v1/organizations/' + orgId + '/worksites');

    var PAGE_SIZE = 15;
    var allRecords     = [];
    var filteredRecords = [];
    var currentPage    = 1;
    var worksitesById  = {};
    var departmentsById = {};

    var tbody        = document.getElementById('departmentsBody');
    var countBadge   = document.getElementById('dept-result-count');
    var pageInfo     = document.getElementById('dept-page-info');
    var prevBtn      = document.getElementById('dept-prev');
    var nextBtn      = document.getElementById('dept-next');
    var searchEl     = document.getElementById('dept-search');
    var wsFilterEl   = document.getElementById('dept-worksite-filter');
    var stFilterEl   = document.getElementById('dept-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── Stats ────────────────────────────────────────────────────────────────
    function updateStats(records) {
        App.utils.setText('#dept-stat-total', records.length);
        App.utils.setText('#dept-stat-active', records.filter(function (r) { return r.status === 'active'; }).length);
    }

    // ── Populate worksite filter dropdown ────────────────────────────────────
    function populateWorksiteFilter(worksites) {
        if (!wsFilterEl) { return; }
        var opts = '<option value="">All worksites</option>';
        worksites.forEach(function (ws) {
            worksitesById[ws.id] = ws.name;
            opts += '<option value="' + e(ws.id) + '">' + e(ws.name) + '</option>';
        });
        wsFilterEl.innerHTML = opts;
    }

    // ── Populate department selects in modal ─────────────────────────────────
    function populateDeptSelects(forId) {
        var wsSelect     = document.getElementById('deptWorksite');
        var parentSelect = document.getElementById('deptParent');

        if (wsSelect) {
            var wsOpts = '<option value="">No specific worksite</option>';
            Object.keys(worksitesById).forEach(function (id) {
                wsOpts += '<option value="' + e(id) + '">' + e(worksitesById[id]) + '</option>';
            });
            wsSelect.innerHTML = wsOpts;
        }

        if (parentSelect) {
            var pOpts = '<option value="">No parent (top-level)</option>';
            allRecords.forEach(function (r) {
                if (r.id !== forId) {
                    pOpts += '<option value="' + e(r.id) + '">' + e(r.name) + '</option>';
                }
            });
            parentSelect.innerHTML = pOpts;
        }
    }

    // ── Rendering ────────────────────────────────────────────────────────────
    function renderRows() {
        var start      = (currentPage - 1) * PAGE_SIZE;
        var pageRows   = filteredRecords.slice(start, start + PAGE_SIZE);
        var total      = filteredRecords.length;
        var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

        if (countBadge) { countBadge.textContent = total; }
        if (pageInfo) {
            pageInfo.textContent = 'Showing ' +
                Math.min(start + 1, total) + '–' +
                Math.min(start + PAGE_SIZE, total) + ' of ' + total;
        }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; }

        if (!pageRows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">' +
                '<i class="bi bi-diagram-3 fs-3 d-block mb-2 opacity-50"></i>' +
                'No departments found. Add the first department to organise your workforce.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            var ws     = r.worksiteId ? (worksitesById[r.worksiteId] || r.worksiteId) : '—';
            var parent = r.parentDepartmentId ? (departmentsById[r.parentDepartmentId] || r.parentDepartmentId) : '—';
            return '<tr>' +
                '<td><span class="fw-medium">' + e(r.name) + '</span></td>' +
                '<td class="text-muted">' + e(ws) + '</td>' +
                '<td class="text-muted">' + e(parent) + '</td>' +
                '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Edit', onclick: 'OrgDepts.openEdit(' + JSON.stringify(r) + ')' },
                    { label: 'Delete', class: 'text-danger', onclick: 'OrgDepts.confirmDelete("' + r.id + '","' + e(r.name) + '")' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Filters ──────────────────────────────────────────────────────────────
    function applyFilters() {
        var q  = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var ws = wsFilterEl ? wsFilterEl.value : '';
        var st = stFilterEl ? stFilterEl.value : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var matchQ  = !q  || (r.name || '').toLowerCase().includes(q);
            var matchWs = !ws || r.worksiteId === ws;
            var matchSt = !st || r.status === st;
            return matchQ && matchWs && matchSt;
        });
        renderRows();
    }

    // ── Load ─────────────────────────────────────────────────────────────────
    function load() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>';
        App.api.get(apiBase, { limit: 200 }).then(function (res) {
            if (!res.ok) { App.notify.error('Failed to load departments.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            departmentsById = {};
            allRecords.forEach(function (r) { departmentsById[r.id] = r.name; });
            updateStats(allRecords);
            applyFilters();
        });
    }

    // ── Worksites (for filter + modal) ───────────────────────────────────────
    function loadWorksites() {
        App.api.get(worksitesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                populateWorksiteFilter(res.data);
            }
            load();
        });
    }

    // ── Modal ────────────────────────────────────────────────────────────────
    var form       = document.getElementById('deptForm');
    var submitBtn  = document.getElementById('deptSubmitBtn');
    var modalTitle = document.getElementById('deptModalTitle');
    var idField    = document.getElementById('deptId');
    var stGroup    = document.getElementById('deptStatusGroup');

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-diagram-3 me-2" style="color:var(--we-primary)"></i>Add Department'; }
        if (idField) { idField.value = ''; }
        if (stGroup) { stGroup.style.display = 'none'; }
        App.forms.reset(form);
        populateDeptSelects('');
        App.ui.clearAlert('#deptModalAlert');
        App.modals.open('#deptModal');
    }

    window.OrgDepts = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Department'; }
            if (idField) { idField.value = record.id; }
            if (stGroup) { stGroup.style.display = ''; }
            populateDeptSelects(record.id);
            form.elements.name.value = record.name || '';
            var wsEl = document.getElementById('deptWorksite');
            if (wsEl) { wsEl.value = record.worksiteId || ''; }
            var pEl = document.getElementById('deptParent');
            if (pEl) { pEl.value = record.parentDepartmentId || ''; }
            var stEl = document.getElementById('deptStatus');
            if (stEl) { stEl.value = record.status || 'active'; }
            App.ui.clearAlert('#deptModalAlert');
            App.modals.open('#deptModal');
        },
        confirmDelete: function (id, name) {
            App.modals.confirm({
                title: 'Delete Department',
                message: 'Are you sure you want to delete <strong>' + App.utils.escapeHtml(name) + '</strong>? This cannot be undone.',
                confirmText: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    App.api.delete(apiBase + '/' + id).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Delete failed.'); return; }
                        App.notify.success('Department deleted.');
                        load();
                    });
                }
            });
        }
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            var deptId = idField ? idField.value : '';
            var method = deptId ? 'put' : 'post';
            var url    = deptId ? (apiBase + '/' + deptId) : apiBase;
            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Department');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#deptModalAlert');
                    return;
                }
                App.notify.success(deptId ? 'Department updated.' : 'Department added.');
                App.modals.close('#deptModal');
                load();
            });
        });
    }

    // ── Events ───────────────────────────────────────────────────────────────
    var btnAdd = document.getElementById('btnAddDept');
    if (btnAdd) { btnAdd.addEventListener('click', openCreate); }
    if (searchEl) { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (wsFilterEl) { wsFilterEl.addEventListener('change', applyFilters); }
    if (stFilterEl) { stFilterEl.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); }); }

    loadWorksites();
})();
