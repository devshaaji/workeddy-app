/**
 * organization-pilot-sites.js — Pilot Sites management page
 * Routes: GET /organizations/{id}/pilot-sites
 * API:    GET  /api/v1/organizations/{id}/pilot-sites
 *         POST /api/v1/organizations/{id}/pilot-sites
 *         PUT  /api/v1/organizations/{id}/pilot-sites/{psId}
 *         GET  /api/v1/organizations/{id}/worksites          (for worksite selector)
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('organizationPilotSitesPage');
    if (!page) { return; }

    var orgId        = page.getAttribute('data-org-id') || '';
    var apiBase      = page.getAttribute('data-api-base')    || ('/api/v1/organizations/' + orgId + '/pilot-sites');
    var worksitesApi = page.getAttribute('data-worksites-api') || ('/api/v1/organizations/' + orgId + '/worksites');

    var allRecords      = [];
    var filteredRecords = [];
    var worksitesById   = {};

    var tbody      = document.getElementById('pilotSiteTable');
    var countBadge = document.getElementById('ps-result-count');
    var stFilter   = document.getElementById('ps-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── Status badge colours ──────────────────────────────────────────────────
    var STATUS_CLASS = {
        enrolled:  'bg-label-info',
        active:    'bg-label-success',
        paused:    'bg-label-warning',
        completed: 'bg-label-secondary'
    };

    function statusBadge(st) {
        var cls = STATUS_CLASS[st] || 'bg-label-secondary';
        var label = st ? (st.charAt(0).toUpperCase() + st.slice(1)) : '\u2014';
        return '<span class="badge ' + cls + '">' + e(label) + '</span>';
    }

    // ── Stats ─────────────────────────────────────────────────────────────────
    function updateStats(records) {
        App.utils.setText('#ps-stat-total', records.length);
        App.utils.setText('#ps-stat-active', records.filter(function (r) { return r.pilotStatus === 'active'; }).length);
        var target = records.reduce(function (s, r) { return s + (Number(r.targetWorkerCount) || 0); }, 0);
        var actual = records.reduce(function (s, r) { return s + (Number(r.actualWorkerCount) || 0); }, 0);
        App.utils.setText('#ps-stat-target', target);
        App.utils.setText('#ps-stat-actual', actual);
    }

    // ── Populate worksite select ───────────────────────────────────────────────
    function populateWorksiteSelect(worksites) {
        var sel  = document.getElementById('pilotSiteWorksite');
        var opts = '<option value="">Select worksite\u2026</option>';
        worksites.forEach(function (ws) {
            worksitesById[ws.id] = ws.name;
            opts += '<option value="' + e(ws.id) + '">' + e(ws.name) + '</option>';
        });
        if (sel) { sel.innerHTML = opts; }
    }

    // ── Rendering ─────────────────────────────────────────────────────────────
    function renderRows() {
        var total = filteredRecords.length;
        if (countBadge) { countBadge.textContent = total; }

        if (!total) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">' +
                '<i class="bi bi-activity fs-3 d-block mb-2 opacity-50"></i>' +
                'No pilot sites enrolled yet. Enroll the first worksite to begin tracking the programme.</td></tr>';
            return;
        }

        tbody.innerHTML = filteredRecords.map(function (r) {
            var wsName = r.worksiteId ? (worksitesById[r.worksiteId] || r.worksiteId) : '\u2014';
            return '<tr>' +
                '<td><span class="fw-medium">' + e(wsName) + '</span></td>' +
                '<td>' + statusBadge(r.pilotStatus) + '</td>' +
                '<td class="text-muted">' + (r.enrollmentDate ? App.utils.formatDate(r.enrollmentDate) : '\u2014') + '</td>' +
                '<td class="text-muted">' + e(r.industry || '\u2014') + '</td>' +
                '<td class="text-center">' + e(r.targetWorkerCount || 0) + '</td>' +
                '<td class="text-center">' + e(r.actualWorkerCount || 0) + '</td>' +
                '<td class="text-muted" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="' + e(r.notes || '') + '">' +
                    e(r.notes || '\u2014') + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Edit', onclick: 'OrgPilotSites.openEdit(' + JSON.stringify(r) + ')' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Filter ────────────────────────────────────────────────────────────────
    function applyFilter() {
        var st = stFilter ? stFilter.value : '';
        filteredRecords = allRecords.filter(function (r) {
            return !st || r.pilotStatus === st;
        });
        renderRows();
    }

    // ── Load ──────────────────────────────────────────────────────────────────
    function load() {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading\u2026</td></tr>';
        App.api.get(apiBase).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load pilot sites.', '#pilotSiteAlert');
                tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-4">Could not load pilot sites.</td></tr>';
                return;
            }
            allRecords = Array.isArray(res.data) ? res.data : [];
            updateStats(allRecords);
            applyFilter();
        });
    }

    function loadWorksites() {
        App.api.get(worksitesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                populateWorksiteSelect(res.data);
            }
            load();
        });
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    var form       = document.getElementById('pilotSiteForm');
    var submitBtn  = document.getElementById('pilotSiteSubmitBtn');
    var modalTitle = document.getElementById('pilotSiteModalTitle');
    var idField    = document.getElementById('pilotSiteId');

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-activity me-2" style="color:var(--we-primary)"></i>Enroll Pilot Site'; }
        if (idField) { idField.value = ''; }
        App.forms.reset(form);
        // Set today's date
        var dateEl = document.getElementById('pilotSiteEnrollmentDate');
        if (dateEl) { dateEl.valueAsDate = new Date(); }
        App.ui.clearAlert('#pilotSiteModalAlert');
        App.modals.open('#pilotSiteModal');
    }

    window.OrgPilotSites = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Pilot Site'; }
            if (idField) { idField.value = record.id; }

            var wsEl = document.getElementById('pilotSiteWorksite');
            if (wsEl) { wsEl.value = record.worksiteId || ''; }

            var dateEl = document.getElementById('pilotSiteEnrollmentDate');
            if (dateEl) { dateEl.value = record.enrollmentDate || ''; }

            var stEl = document.getElementById('pilotSiteStatus');
            if (stEl) { stEl.value = record.pilotStatus || 'enrolled'; }

            var tEl = document.getElementById('pilotSiteTargetWorkers');
            if (tEl) { tEl.value = record.targetWorkerCount || 0; }

            var aEl = document.getElementById('pilotSiteActualWorkers');
            if (aEl) { aEl.value = record.actualWorkerCount || 0; }

            var iEl = document.getElementById('pilotSiteIndustry');
            if (iEl) { iEl.value = record.industry || ''; }

            var nEl = document.getElementById('pilotSiteNotes');
            if (nEl) { nEl.value = record.notes || ''; }

            App.ui.clearAlert('#pilotSiteModalAlert');
            App.modals.open('#pilotSiteModal');
        }
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }

            var psId   = idField ? idField.value : '';
            var method = psId ? 'put' : 'post';
            var url    = psId ? (apiBase + '/' + psId) : apiBase;

            var payload = {
                worksiteId:        form.elements.worksiteId       ? form.elements.worksiteId.value        : '',
                enrollmentDate:    form.elements.enrollmentDate    ? form.elements.enrollmentDate.value    : '',
                pilotStatus:       form.elements.pilotStatus       ? form.elements.pilotStatus.value       : 'enrolled',
                targetWorkerCount: form.elements.targetWorkerCount ? Number(form.elements.targetWorkerCount.value || 0) : 0,
                actualWorkerCount: form.elements.actualWorkerCount ? Number(form.elements.actualWorkerCount.value || 0) : 0,
                industry:          form.elements.industry          ? (form.elements.industry.value.trim() || null)     : null,
                notes:             form.elements.notes             ? (form.elements.notes.value.trim()    || null)     : null,
            };

            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, payload).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Enrollment');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#pilotSiteModalAlert');
                    return;
                }
                App.notify.success(psId ? 'Pilot site updated.' : 'Pilot site enrolled.');
                App.modals.close('#pilotSiteModal');
                load();
            });
        });
    }

    // ── Events ────────────────────────────────────────────────────────────────
    var btnEnroll = document.getElementById('btnEnrollPilot');
    if (btnEnroll) { btnEnroll.addEventListener('click', openCreate); }
    if (stFilter) { stFilter.addEventListener('change', applyFilter); }

    loadWorksites();
})();
