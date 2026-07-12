/**
 * organization-members.js — Team Members management page
 * Routes: GET /organizations/{id}/members
 * API:    GET    /api/v1/organizations/{id}/members
 *         POST   /api/v1/organizations/{id}/members        (invite)
 *         PUT    /api/v1/organizations/{id}/members/{mId}  (update role)
 *         DELETE /api/v1/organizations/{id}/members/{mId}  (remove)
 *         GET    /api/v1/organizations/{id}/assignable-roles
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('membersPage');
    if (!page) { return; }

    var orgId      = page.getAttribute('data-org-id') || '';
    var apiBase    = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/members');
    var rolesApi   = page.getAttribute('data-roles-api') || ('/api/v1/organizations/' + orgId + '/assignable-roles');

    var PAGE_SIZE = 15;
    var allRecords      = [];
    var filteredRecords = [];
    var currentPage     = 1;
    var rolesCache      = [];  // { id, slug, name }
    var rolesBySlug     = {};

    var tbody      = document.getElementById('membersBody');
    var countBadge = document.getElementById('mem-result-count');
    var pageInfo   = document.getElementById('mem-page-info');
    var prevBtn    = document.getElementById('mem-prev');
    var nextBtn    = document.getElementById('mem-next');
    var searchEl   = document.getElementById('mem-search');
    var roleFilter = document.getElementById('mem-role-filter');
    var stFilter   = document.getElementById('mem-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    // ── Stats ────────────────────────────────────────────────────────────────
    function updateStats(records) {
        App.utils.setText('#mem-stat-total', records.length);
        App.utils.setText('#mem-stat-active', records.filter(function (r) { return r.status === 'active'; }).length);
    }

    // ── Roles ────────────────────────────────────────────────────────────────
    function loadRoles(cb) {
        App.api.get(rolesApi).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                rolesCache = res.data;
                rolesBySlug = {};
                rolesCache.forEach(function (r) { rolesBySlug[r.slug] = r.name; });

                // Populate role filter
                if (roleFilter) {
                    var opts = '<option value="">All roles</option>';
                    rolesCache.forEach(function (r) {
                        opts += '<option value="' + e(r.slug) + '">' + e(r.name) + '</option>';
                    });
                    roleFilter.innerHTML = opts;
                }

                // Populate invite role select
                var invSel = document.getElementById('inviteRole');
                if (invSel) {
                    var invOpts = '<option value="">Select a role\u2026</option>';
                    rolesCache.forEach(function (r) {
                        invOpts += '<option value="' + e(r.slug) + '">' + e(r.name) + '</option>';
                    });
                    invSel.innerHTML = invOpts;
                }
            }
            if (cb) { cb(); }
        });
    }

    // ── Rendering ────────────────────────────────────────────────────────────
    function avatarInitials(name) {
        if (!name) { return '?'; }
        var parts = name.trim().split(' ');
        return parts.length >= 2
            ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
            : parts[0][0].toUpperCase();
    }

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
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted">' +
                '<i class="bi bi-people fs-3 d-block mb-2 opacity-50"></i>' +
                'No members found. Invite the first team member to get started.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            var user     = r.user || {};
            var fullName = user.fullName || user.email || 'Unknown';
            var email    = user.email || '';
            var roleName = r.roleSlug ? (rolesBySlug[r.roleSlug] || r.roleSlug) : '\u2014';
            var initials = avatarInitials(fullName);
            var isPrimary = r.isPrimary
                ? '<span class="badge bg-label-primary">Primary</span>'
                : '';

            var avatar = '<span class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white me-2 flex-shrink-0" ' +
                'style="width:36px;height:36px;background:var(--we-primary);font-size:.75rem">' +
                e(initials) + '</span>';

            return '<tr>' +
                '<td>' +
                    '<div class="d-flex align-items-center">' +
                        avatar +
                        '<div>' +
                            '<span class="fw-medium d-block">' + e(fullName) + '</span>' +
                            '<small class="text-muted">' + e(email) + '</small>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td class="text-muted">' + e(roleName) + '</td>' +
                '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                '<td>' + isPrimary + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Change Role', onclick: 'OrgMembers.openChangeRole("' + r.id + '","' + e(fullName) + '","' + e(r.roleSlug || '') + '")' },
                    { label: 'Remove Member', class: 'text-danger', onclick: 'OrgMembers.confirmRemove("' + r.id + '","' + e(fullName) + '")' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    // ── Filters ──────────────────────────────────────────────────────────────
    function applyFilters() {
        var q  = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var rl = roleFilter ? roleFilter.value : '';
        var st = stFilter   ? stFilter.value   : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var user = r.user || {};
            var name = (user.fullName || '').toLowerCase();
            var email = (user.email || '').toLowerCase();
            var matchQ  = !q  || name.includes(q) || email.includes(q);
            var matchRl = !rl || r.roleSlug === rl;
            var matchSt = !st || r.status === st;
            return matchQ && matchRl && matchSt;
        });
        renderRows();
    }

    // ── Load ─────────────────────────────────────────────────────────────────
    function load() {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">' +
            '<div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading\u2026</td></tr>';
        App.api.get(apiBase, { limit: 200 }).then(function (res) {
            if (!res.ok) { App.notify.error('Failed to load members.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            updateStats(allRecords);
            applyFilters();
        });
    }

    // ── Invite ───────────────────────────────────────────────────────────────
    var inviteForm      = document.getElementById('inviteMemberForm');
    var inviteSubmitBtn = document.getElementById('inviteMemberSubmitBtn');

    function openInvite() {
        App.forms.reset(inviteForm);
        App.ui.clearAlert('#inviteMemberAlert');
        App.modals.open('#inviteMemberModal');
    }

    if (inviteSubmitBtn) {
        inviteSubmitBtn.addEventListener('click', function () {
            if (!inviteForm.checkValidity()) { inviteForm.classList.add('was-validated'); return; }
            if (App.forms && App.forms.clearValidationErrors) {
                App.forms.clearValidationErrors(inviteForm);
            }
            App.ui.setButtonLoading(inviteSubmitBtn, true);
            App.api.post(apiBase, App.forms.serialize(inviteForm)).then(function (res) {
                App.ui.setButtonLoading(inviteSubmitBtn, false, 'Send Invitation');
                if (!res.ok) {
                    var rendered = App.forms && App.forms.showValidationErrors ? App.forms.showValidationErrors(inviteForm, res.errors || {}) : { fieldErrors: {}, formErrors: [] };
                    if (rendered.formErrors.length) {
                        App.ui.showAlert('danger', rendered.formErrors.join(' '), '#inviteMemberAlert');
                    } else if (!Object.keys(rendered.fieldErrors).length) {
                        App.ui.showAlert('danger', res.message || 'Invitation failed.', '#inviteMemberAlert');
                    }
                    return;
                }
                App.notify.success('Invitation sent. The member will receive an email to set up their account.');
                App.modals.close('#inviteMemberModal');
                load();
            });
        });
    }

    // ── Change Role ──────────────────────────────────────────────────────────
    var changeRoleSubmitBtn = document.getElementById('changeRoleSubmitBtn');

    window.OrgMembers = {
        openChangeRole: function (memberId, memberName, currentSlug) {
            document.getElementById('changeRoleMemberId').value = memberId;
            App.utils.setText('#changeRoleMemberName', memberName);

            // Populate role select
            var sel  = document.getElementById('changeRoleSelect');
            var opts = '<option value="">Select role\u2026</option>';
            rolesCache.forEach(function (r) {
                opts += '<option value="' + e(r.slug) + '"' +
                    (r.slug === currentSlug ? ' selected' : '') +
                    '>' + e(r.name) + '</option>';
            });
            sel.innerHTML = opts;

            App.ui.clearAlert('#changeRoleAlert');
            App.modals.open('#changeMemberRoleModal');
        },
        confirmRemove: function (memberId, memberName) {
            App.modals.confirm({
                title: 'Remove Member',
                message: 'Are you sure you want to remove <strong>' + App.utils.escapeHtml(memberName) +
                    '</strong> from this organization? They will lose access immediately.',
                confirmText: 'Remove',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    App.api.delete(apiBase + '/' + memberId).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Remove failed.'); return; }
                        App.notify.success(App.utils.escapeHtml(memberName) + ' has been removed from the organization.');
                        load();
                    });
                }
            });
        }
    };

    if (changeRoleSubmitBtn) {
        changeRoleSubmitBtn.addEventListener('click', function () {
            var memberId = document.getElementById('changeRoleMemberId').value;
            var roleSlug = document.getElementById('changeRoleSelect').value;
            if (!roleSlug) {
                App.ui.showAlert('warning', 'Please select a role.', '#changeRoleAlert');
                return;
            }
            if (App.forms && App.forms.clearValidationErrors) {
                App.forms.clearValidationErrors(document.getElementById('changeRoleForm'));
            }
            App.ui.setButtonLoading(changeRoleSubmitBtn, true);
            App.api.put(apiBase + '/' + memberId, { roleSlug: roleSlug }).then(function (res) {
                App.ui.setButtonLoading(changeRoleSubmitBtn, false, 'Update Role');
                if (!res.ok) {
                    var rendered = App.forms && App.forms.showValidationErrors ? App.forms.showValidationErrors(document.getElementById('changeRoleForm'), res.errors || {}) : { fieldErrors: {}, formErrors: [] };
                    if (rendered.formErrors.length) {
                        App.ui.showAlert('danger', rendered.formErrors.join(' '), '#changeRoleAlert');
                    } else if (!Object.keys(rendered.fieldErrors).length) {
                        App.ui.showAlert('danger', res.message || 'Role update failed.', '#changeRoleAlert');
                    }
                    return;
                }
                App.notify.success('Role updated successfully.');
                App.modals.close('#changeMemberRoleModal');
                load();
            });
        });
    }

    // ── Events ────────────────────────────────────────────────────────────────
    var btnInvite = document.getElementById('btnInviteMember');
    if (btnInvite) { btnInvite.addEventListener('click', openInvite); }
    if (searchEl)   { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (roleFilter) { roleFilter.addEventListener('change', applyFilters); }
    if (stFilter)   { stFilter.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); }); }

    // Load roles first, then members
    loadRoles(load);
})();
