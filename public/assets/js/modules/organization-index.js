/**
 * organization-index.js — Organizations list page
 * Routes: GET /organizations
 * API:    GET /api/v1/organizations
 *         POST /api/v1/organizations
 *         PUT  /api/v1/organizations/{id}
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var PAGE_SIZE = 15;
    var allRecords = [];
    var filteredRecords = [];
    var currentPage = 1;

    var tbody      = document.getElementById('orgsBody');
    var countBadge = document.getElementById('org-result-count');
    var pageInfo   = document.getElementById('org-page-info');
    var prevBtn    = document.getElementById('org-prev');
    var nextBtn    = document.getElementById('org-next');
    var searchEl   = document.getElementById('org-search');
    var statusEl   = document.getElementById('org-status-filter');

    // ── Stats ────────────────────────────────────────────────────────────────
    function updateStats(records) {
        var total = records.length;
        var active = records.filter(function (r) { return r.status === 'active'; }).length;
        var suspended = records.filter(function (r) { return r.status === 'suspended'; }).length;
        var pending = records.filter(function (r) { return r.status === 'pending'; }).length;
        App.utils.setText('#stat-total', total);
        App.utils.setText('#stat-active', active);
        App.utils.setText('#stat-suspended', suspended);
        App.utils.setText('#stat-pending', pending);
    }

    // ── Rendering ────────────────────────────────────────────────────────────
    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    function renderRows() {
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageRows = filteredRecords.slice(start, start + PAGE_SIZE);
        var total = filteredRecords.length;
        var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

        if (countBadge) { countBadge.textContent = total; }
        if (pageInfo) { pageInfo.textContent = 'Showing ' + Math.min(start + 1, total) + '–' + Math.min(start + PAGE_SIZE, total) + ' of ' + total; }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; }

        if (!pageRows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">' +
                '<i class="bi bi-building fs-3 d-block mb-2 opacity-50"></i>' +
                'No organizations found. Try adjusting your filters.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            var slug = r.slug ? '<small class="text-muted">/' + e(r.slug) + '</small>' : '';
            var email = r.contactEmail ? '<small class="d-block text-muted">' + e(r.contactEmail) + '</small>' : '';
            var phone = r.phone ? '<small class="d-block text-muted"><i class="bi bi-telephone me-1"></i>' + e(r.phone) + '</small>' : '';
            return '<tr style="cursor:pointer" onclick="window.location.href=\'/organizations/' + e(r.id) + '\'">' +
                '<td>' +
                    '<span class="fw-medium">' + e(r.name) + '</span>' +
                    (slug ? '<br>' + slug : '') +
                '</td>' +
                '<td>' + email + phone + '</td>' +
                '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                '<td><a href="/organizations/' + e(r.id) + '" class="btn btn-outline-secondary btn-sm me-1" title="View" onclick="event.stopPropagation()"><i class="bi bi-diagram-3"></i></a></td>' +
                '<td class="text-end" onclick="event.stopPropagation()">' +
                    App.tables.actionDropdown([
                        { label: 'View Profile', href: '/organizations/' + r.id },
                        { label: 'Worksites', href: '/organizations/' + r.id + '/worksites' },
                        { label: 'Members', href: '/organizations/' + r.id + '/members' },
                        { label: 'Edit', onclick: 'OrgIndex.openEdit(' + JSON.stringify(r) + ')' },
                    ]) +
                '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Filters ──────────────────────────────────────────────────────────────
    function applyFilters() {
        var q = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var st = statusEl ? statusEl.value : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var matchQ = !q || (r.name || '').toLowerCase().includes(q) || (r.contactEmail || '').toLowerCase().includes(q);
            var matchSt = !st || r.status === st;
            return matchQ && matchSt;
        });
        renderRows();
    }

    // ── Load ─────────────────────────────────────────────────────────────────
    function load() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading…</td></tr>';
        App.api.get('/api/v1/organizations', { limit: 200 }).then(function (res) {
            if (!res.ok) { App.notify.error(res.message || 'Failed to load organizations.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            updateStats(allRecords);
            applyFilters();
        });
    }

    // ── Create ───────────────────────────────────────────────────────────────
    function openCreate() {
        App.forms.reset(document.getElementById('createOrgForm'));
        App.ui.clearAlert('#createOrgAlert');
        App.modals.open('#createOrgModal');
    }

    var createSubmitBtn = document.getElementById('createOrgSubmitBtn');
    if (createSubmitBtn) {
        createSubmitBtn.addEventListener('click', function () {
            var form = document.getElementById('createOrgForm');
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            if (App.forms && App.forms.clearValidationErrors) {
                App.forms.clearValidationErrors(form);
            }
            App.ui.setButtonLoading(createSubmitBtn, true);
            var data = App.forms.serialize(form);
            App.api.post('/api/v1/organizations', data).then(function (res) {
                App.ui.setButtonLoading(createSubmitBtn, false, 'Create Organization');
                if (!res.ok) {
                    var rendered = App.forms && App.forms.showValidationErrors ? App.forms.showValidationErrors(form, res.errors || {}) : { fieldErrors: {}, formErrors: [] };
                    if (rendered.formErrors.length) {
                        App.ui.showAlert('danger', rendered.formErrors.join(' '), '#createOrgAlert');
                    } else if (!Object.keys(rendered.fieldErrors).length) {
                        App.ui.showAlert('danger', res.message || 'Failed to create organization.', '#createOrgAlert');
                    }
                    return;
                }
                App.notify.success('Organization created.');
                App.modals.close('#createOrgModal');
                load();
            });
        });
    }

    // ── Edit ─────────────────────────────────────────────────────────────────
    window.OrgIndex = {
        openEdit: function (record) {
            document.getElementById('editOrgId').value = record.id;
            document.getElementById('editOrgName').value = record.name || '';
            document.getElementById('editOrgEmail').value = record.contactEmail || '';
            document.getElementById('editOrgPhone').value = record.phone || '';
            App.ui.clearAlert('#editOrgAlert');
            App.modals.open('#editOrgModal');
        }
    };

    var editSubmitBtn = document.getElementById('editOrgSubmitBtn');
    if (editSubmitBtn) {
        editSubmitBtn.addEventListener('click', function () {
            var form = document.getElementById('editOrgForm');
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            var orgId = document.getElementById('editOrgId').value;
            if (App.forms && App.forms.clearValidationErrors) {
                App.forms.clearValidationErrors(form);
            }
            App.ui.setButtonLoading(editSubmitBtn, true);
            var data = App.forms.serialize(form);
            App.api.put('/api/v1/organizations/' + orgId, data).then(function (res) {
                App.ui.setButtonLoading(editSubmitBtn, false, 'Save Changes');
                if (!res.ok) {
                    var rendered = App.forms && App.forms.showValidationErrors ? App.forms.showValidationErrors(form, res.errors || {}) : { fieldErrors: {}, formErrors: [] };
                    if (rendered.formErrors.length) {
                        App.ui.showAlert('danger', rendered.formErrors.join(' '), '#editOrgAlert');
                    } else if (!Object.keys(rendered.fieldErrors).length) {
                        App.ui.showAlert('danger', res.message || 'Failed to update organization.', '#editOrgAlert');
                    }
                    return;
                }
                App.notify.success('Organization updated.');
                App.modals.close('#editOrgModal');
                load();
            });
        });
    }

    // ── Events ───────────────────────────────────────────────────────────────
    var btnCreate = document.getElementById('btnCreateOrg');
    if (btnCreate) { btnCreate.addEventListener('click', openCreate); }
    if (searchEl) { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (statusEl) { statusEl.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); }); }

    load();
})();
