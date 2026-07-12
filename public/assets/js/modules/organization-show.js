/**
 * organization-show.js — Organization profile page
 * Routes: GET /organizations/{id}
 * API:    GET /api/v1/organizations/{id}
 *         PUT /api/v1/organizations/{id}
 *         PATCH /api/v1/organizations/{id}/status
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('orgShowPage');
    if (!page) { return; }

    var orgId = page.getAttribute('data-org-id') || '';
    var apiBase = '/api/v1/organizations/' + orgId;

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── Load organization data ────────────────────────────────────────────────
    function loadOrg() {
        App.api.get(apiBase).then(function (res) {
            if (!res.ok) { App.notify.error('Failed to load organization profile.'); return; }
            var d = res.data;
            App.utils.setText('#org-name-display', d.name || '—');
            App.utils.setText('#org-slug-display', d.slug ? '/' + d.slug : '');
            App.utils.setText('#org-email-display', d.contactEmail || '—');
            App.utils.setText('#org-phone-display', d.phone || '—');
            var badge = document.getElementById('org-status-badge');
            if (badge) { badge.innerHTML = App.ui.statusBadge(d.status); }
            // Prefill edit form
            var f = document.getElementById('editOrgForm');
            if (f) {
                f.elements.name.value = d.name || '';
                f.elements.contactEmail.value = d.contactEmail || '';
                if (f.elements.phone) { f.elements.phone.value = d.phone || ''; }
            }
        });
    }

    // ── Load sub-counts ──────────────────────────────────────────────────────
    function loadCount(endpoint, elId) {
        App.api.get(apiBase + '/' + endpoint, { limit: 1 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                App.utils.setText('#' + elId, res.data.length >= 50 ? '50+' : res.data.length);
            }
        });
    }

    function loadSubCounts() {
        App.api.get(apiBase + '/worksites', { limit: 200 }).then(function (res) {
            if (res.ok) { App.utils.setText('#nav-worksites-count', Array.isArray(res.data) ? res.data.length : '—'); }
        });
        App.api.get(apiBase + '/departments', { limit: 200 }).then(function (res) {
            if (res.ok) { App.utils.setText('#nav-departments-count', Array.isArray(res.data) ? res.data.length : '—'); }
        });
        App.api.get(apiBase + '/job-roles', { limit: 200 }).then(function (res) {
            if (res.ok) { App.utils.setText('#nav-jobroles-count', Array.isArray(res.data) ? res.data.length : '—'); }
        });
        App.api.get(apiBase + '/members', { limit: 200 }).then(function (res) {
            if (res.ok) { App.utils.setText('#nav-members-count', Array.isArray(res.data) ? res.data.length : '—'); }
        });
        App.api.get(apiBase + '/pilot-sites').then(function (res) {
            if (res.ok) { App.utils.setText('#nav-pilotsites-count', Array.isArray(res.data) ? res.data.length : '—'); }
        });
    }

    // ── Edit ─────────────────────────────────────────────────────────────────
    var btnEdit = document.getElementById('btnEditOrg');
    if (btnEdit) {
        btnEdit.addEventListener('click', function () {
            App.ui.clearAlert('#editOrgAlert');
            App.modals.open('#editOrgModal');
        });
    }

    var editSubmitBtn = document.getElementById('editOrgSubmitBtn');
    if (editSubmitBtn) {
        editSubmitBtn.addEventListener('click', function () {
            var form = document.getElementById('editOrgForm');
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            App.ui.setButtonLoading(editSubmitBtn, true);
            App.api.put(apiBase, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(editSubmitBtn, false, 'Save Changes');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Update failed.', '#editOrgAlert');
                    return;
                }
                App.notify.success('Organization updated.');
                App.modals.close('#editOrgModal');
                loadOrg();
            });
        });
    }

    // ── Status management ────────────────────────────────────────────────────
    function changeStatus(newStatus) {
        App.api.patch(apiBase + '/status', { status: newStatus }).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Status change failed.', '#statusAlert');
                return;
            }
            App.notify.success('Status updated to ' + newStatus + '.');
            loadOrg();
        });
    }

    var btnActivate   = document.getElementById('btnActivate');
    var btnSuspend    = document.getElementById('btnSuspend');
    var btnDeactivate = document.getElementById('btnDeactivate');

    if (btnActivate)   { btnActivate.addEventListener('click', function () { changeStatus('active'); }); }
    if (btnSuspend)    { btnSuspend.addEventListener('click', function () { changeStatus('suspended'); }); }
    if (btnDeactivate) { btnDeactivate.addEventListener('click', function () { changeStatus('inactive'); }); }

    loadOrg();
    loadSubCounts();
})();
