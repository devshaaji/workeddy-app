/**
 * organization-job-roles.js — Job Roles management page
 * Routes: GET /organizations/{id}/job-roles
 * API:    GET    /api/v1/organizations/{id}/job-roles
 *         POST   /api/v1/organizations/{id}/job-roles
 *         PUT    /api/v1/organizations/{id}/job-roles/{jId}
 *         DELETE /api/v1/organizations/{id}/job-roles/{jId}
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('jobRolesPage');
    if (!page) { return; }

    var orgId       = page.getAttribute('data-org-id') || '';
    var apiBase     = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/job-roles');
    var deptsApi    = page.getAttribute('data-departments-api') || ('/api/v1/organizations/' + orgId + '/departments');

    var PAGE_SIZE = 15;
    var allRecords      = [];
    var filteredRecords = [];
    var currentPage     = 1;
    var departmentsById = {};

    var tbody      = document.getElementById('jobRolesBody');
    var countBadge = document.getElementById('jr-result-count');
    var pageInfo   = document.getElementById('jr-page-info');
    var prevBtn    = document.getElementById('jr-prev');
    var nextBtn    = document.getElementById('jr-next');
    var searchEl   = document.getElementById('jr-search');
    var deptFilter = document.getElementById('jr-dept-filter');
    var stFilter   = document.getElementById('jr-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── Stats ────────────────────────────────────────────────────────────────
    function updateStats(records) {
        App.utils.setText('#jr-stat-total', records.length);
        App.utils.setText('#jr-stat-active', records.filter(function (r) { return r.status === 'active'; }).length);
    }

    // ── Populate department filter ────────────────────────────────────────────
    function populateDeptFilter(depts) {
        if (!deptFilter) { return; }
        var opts = '<option value="">All departments</option>';
        depts.forEach(function (d) {
            departmentsById[d.id] = d.name;
            opts += '<option value="' + e(d.id) + '">' + e(d.name) + '</option>';
        });
        deptFilter.innerHTML = opts;
    }

    // ── Populate department select in modal ──────────────────────────────────
    function populateDeptSelect() {
        var sel = document.getElementById('jrDept');
        if (!sel) { return; }
        var opts = '<option value="">No specific department</option>';
        Object.keys(departmentsById).forEach(function (id) {
            opts += '<option value="' + e(id) + '">' + e(departmentsById[id]) + '</option>';
        });
        sel.innerHTML = opts;
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
                Math.min(start + 1, total) + '\u2013' +
                Math.min(start + PAGE_SIZE, total) + ' of ' + total;
        }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; }

        if (!pageRows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">' +
                '<i class="bi bi-person-badge fs-3 d-block mb-2 opacity-50"></i>' +
                'No job roles found. Add the first role to link workers to tasks and assessments.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            var deptName = r.departmentId
                ? (departmentsById[r.departmentId] || r.departmentId)
                : '\u2014';
            return '<tr>' +
                '<td><span class="fw-medium">' + e(r.name) + '</span></td>' +
                '<td class="text-muted">' + e(deptName) + '</td>' +
                '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Edit',   onclick: 'OrgJobRoles.openEdit(' + JSON.stringify(r) + ')' },
                    { label: 'Delete', class: 'text-danger', onclick: 'OrgJobRoles.confirmDelete("' + r.id + '","' + e(r.name) + '")' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Filters ──────────────────────────────────────────────────────────────
    function applyFilters() {
        var q  = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var d  = deptFilter ? deptFilter.value : '';
        var st = stFilter   ? stFilter.value   : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var matchQ  = !q  || (r.name || '').toLowerCase().includes(q);
            var matchD  = !d  || r.departmentId === d;
            var matchSt = !st || r.status === st;
            return matchQ && matchD && matchSt;
        });
        renderRows();
    }

    // ── Load ─────────────────────────────────────────────────────────────────
    function load() {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading\u2026</td></tr>';
        App.api.get(apiBase, { limit: 200 }).then(function (res) {
            if (!res.ok) { App.notify.error('Failed to load job roles.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            updateStats(allRecords);
            applyFilters();
        });
    }

    function loadDepts() {
        App.api.get(deptsApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                populateDeptFilter(res.data);
            }
            load();
        });
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    var form       = document.getElementById('jobRoleForm');
    var submitBtn  = document.getElementById('jobRoleSubmitBtn');
    var modalTitle = document.getElementById('jobRoleModalTitle');
    var idField    = document.getElementById('jobRoleId');
    var stGroup    = document.getElementById('jrStatusGroup');

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-person-badge me-2" style="color:var(--we-primary)"></i>Add Job Role'; }
        if (idField) { idField.value = ''; }
        if (stGroup) { stGroup.style.display = 'none'; }
        App.forms.reset(form);
        populateDeptSelect();
        App.ui.clearAlert('#jobRoleModalAlert');
        App.modals.open('#jobRoleModal');
    }

    window.OrgJobRoles = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Job Role'; }
            if (idField) { idField.value = record.id; }
            if (stGroup) { stGroup.style.display = ''; }
            populateDeptSelect();
            form.elements.name.value = record.name || '';
            var dEl = document.getElementById('jrDept');
            if (dEl) { dEl.value = record.departmentId || ''; }
            var sEl = document.getElementById('jrStatus');
            if (sEl) { sEl.value = record.status || 'active'; }
            App.ui.clearAlert('#jobRoleModalAlert');
            App.modals.open('#jobRoleModal');
        },
        confirmDelete: function (id, name) {
            App.modals.confirm({
                title: 'Delete Job Role',
                message: 'Are you sure you want to delete <strong>' + App.utils.escapeHtml(name) + '</strong>? This cannot be undone.',
                confirmText: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    App.api.delete(apiBase + '/' + id).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Delete failed.'); return; }
                        App.notify.success('Job role deleted.');
                        load();
                    });
                }
            });
        }
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            var jrId   = idField ? idField.value : '';
            var method = jrId ? 'put' : 'post';
            var url    = jrId ? (apiBase + '/' + jrId) : apiBase;
            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Job Role');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#jobRoleModalAlert');
                    return;
                }
                App.notify.success(jrId ? 'Job role updated.' : 'Job role added.');
                App.modals.close('#jobRoleModal');
                load();
            });
        });
    }

    // ── Events ────────────────────────────────────────────────────────────────
    var btnAdd = document.getElementById('btnAddJobRole');
    if (btnAdd) { btnAdd.addEventListener('click', openCreate); }
    if (searchEl)   { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (deptFilter) { deptFilter.addEventListener('change', applyFilters); }
    if (stFilter)   { stFilter.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); }); }

    loadDepts();
})();
