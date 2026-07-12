/**
 * IAM Module – Unified Frontend Logic
 * ====================================
 * Handles all dynamic data-binding, table rendering, form submission,
 * and action workflows for the IAM module screens.
 *
 * Screen detection uses [data-iam-screen] attributes on the page.
 * All API interaction flows through window.App (app.js).
 */
(function (window, document) {
  'use strict';

  /* -----------------------------------------------------------------------
   * BOOTSTRAP GUARD
   * --------------------------------------------------------------------- */
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  /* -----------------------------------------------------------------------
   * UTILITY HELPERS
   * --------------------------------------------------------------------- */
  var esc = function (v) {
    return window.App && App.utils && App.utils.escapeHtml
      ? App.utils.escapeHtml(v)
      : String(v == null ? '' : v)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
  };

  var display = function (v) { return esc(v != null && v !== '' ? v : '--'); };

  var statusBadge = function (status) {
    var map = {
      active: 'bg-label-success',
      pending: 'bg-label-warning',
      suspended: 'bg-label-danger',
      inactive: 'bg-label-secondary'
    };
    var cls = map[String(status).toLowerCase()] || 'bg-label-secondary';
    return '<span class="badge ' + cls + '">' + esc(status || 'unknown') + '</span>';
  };

  var riskBadge = function (level) {
    var map = {
      critical: 'bg-label-danger',
      high: 'bg-label-warning',
      medium: 'bg-label-info',
      low: 'bg-label-success'
    };
    var cls = map[String(level).toLowerCase()] || 'bg-label-secondary';
    return '<span class="badge ' + cls + '">' + esc(level || '--') + '</span>';
  };

  var notify = function (type, msg) {
    if (window.App && App.notify && App.notify[type]) {
      App.notify[type](msg);
    }
  };

  var showAlert = function (type, message, target) {
    if (window.App && App.ui && App.ui.showAlert) {
      App.ui.showAlert(type, message, target);
    }
  };

  function mapActionItems(actions) {
    return (actions || []).map(function (action) {
      if (!action || !action.label) { return null; }
      if (String(action.method || 'GET').toUpperCase() === 'LINK' || action.url) {
        return {
          label: action.label,
          href: action.url || '#'
        };
      }
      return null;
    }).filter(Boolean);
  }

  function cloneSelectionMap(source) {
    var target = {};
    Object.keys(source || {}).forEach(function (key) {
      if (source[key]) { target[key] = true; }
    });
    return target;
  }

  function selectedPermissionIds(selectionMap) {
    return Object.keys(selectionMap || {}).filter(function (key) { return !!selectionMap[key]; });
  }

  function initRolePermissionWorkspace(mode) {
    var screen = document.querySelector('[data-iam-screen="' + mode + '"]');
    if (!screen) { return; }

    var configMap = {
      'role-create': {
        formId: 'iam-role-create-form',
        tbody: '#iam-role-create-permissions',
        pagination: '#iam-role-create-pagination',
        feedback: '#iam-role-create-feedback',
        countEl: null,
        saveButtonId: null,
        resetButtonId: null,
        selectAllButtonId: 'iam-role-create-select-all',
        showSelectedId: null,
        searchId: null,
        moduleId: null,
        riskId: null,
        submitMethod: 'POST',
        redirectBase: '/roles/'
      },
      'role-edit': {
        formId: 'iam-role-edit-form',
        tbody: '#iam-role-edit-permissions',
        pagination: '#iam-role-edit-pagination',
        feedback: '#iam-role-edit-feedback',
        countEl: null,
        saveButtonId: null,
        resetButtonId: null,
        selectAllButtonId: null,
        showSelectedId: null,
        searchId: null,
        moduleId: null,
        riskId: null,
        submitMethod: 'PUT',
        redirectBase: '/roles/'
      },
      'role-permissions': {
        formId: null,
        tbody: '#iam-role-assign-permissions',
        pagination: '#iam-role-assign-pagination',
        feedback: '#iam-role-assign-feedback',
        countEl: 'iam-role-assign-count',
        saveButtonId: 'iam-role-assign-save',
        resetButtonId: 'iam-role-assign-reset',
        selectAllButtonId: null,
        showSelectedId: 'show_selected',
        searchId: 'iam-role-assign-search',
        moduleId: 'iam-role-assign-module',
        riskId: 'iam-role-assign-risk',
        submitMethod: 'PUT',
        redirectBase: null
      }
    };

    var config = configMap[mode];
    if (!config) { return; }

    var form = config.formId ? document.getElementById(config.formId) : null;
    var roleId = '';
    if (form) {
      roleId = form.getAttribute('data-role-id') || '';
    }
    if (!roleId) {
      var saveBtn = config.saveButtonId ? document.getElementById(config.saveButtonId) : null;
      if (saveBtn) {
        roleId = saveBtn.getAttribute('data-role-id') || '';
      }
    }
    if (!roleId) {
      var pathMatch = window.location.pathname.match(/\/roles\/([0-9a-fA-F-]{36})/);
      if (pathMatch) { roleId = pathMatch[1]; }
    }

    var tbody = document.querySelector(config.tbody);
    if (!tbody) { return; }

    var card = tbody.closest('.card');
    var countEl = config.countEl ? document.getElementById(config.countEl) : null;
    var saveBtn = config.saveButtonId ? document.getElementById(config.saveButtonId) : null;
    var resetBtn = config.resetButtonId ? document.getElementById(config.resetButtonId) : null;
    var selectAllBtn = config.selectAllButtonId ? document.getElementById(config.selectAllButtonId) : null;
    var showSelected = config.showSelectedId ? document.getElementById(config.showSelectedId) : null;
    var searchInput = config.searchId ? document.getElementById(config.searchId) : null;
    var moduleSelect = config.moduleId ? document.getElementById(config.moduleId) : null;
    var riskSelect = config.riskId ? document.getElementById(config.riskId) : null;
    var selectedIds = {};
    var initialSelection = {};
    var table = null;

    function updateSummary() {
      if (countEl) {
        countEl.textContent = String(selectedPermissionIds(selectedIds).length);
      }
    }

    function bindSelectionControls() {
      tbody.querySelectorAll('.iam-role-permission-check').forEach(function (checkbox) {
        if (checkbox._iamBound) { return; }
        checkbox._iamBound = true;
        checkbox.addEventListener('change', function () {
          var permissionId = checkbox.getAttribute('data-permission-id') || '';
          if (!permissionId) { return; }
          selectedIds[permissionId] = checkbox.checked;
          updateSummary();
          if (showSelected && showSelected.checked) {
            table.applyFilters();
          }
        });
      });
    }

    function populateModuleOptions(records) {
      if (!moduleSelect) { return; }
      var current = moduleSelect.value;
      var modules = [];
      records.forEach(function (record) {
        var moduleName = String(record.module || '').trim();
        if (moduleName && modules.indexOf(moduleName) === -1) {
          modules.push(moduleName);
        }
      });
      modules.sort(function (a, b) { return a.localeCompare(b); });
      moduleSelect.innerHTML = '<option value="">All modules</option>' + modules.map(function (moduleName) {
        return '<option value="' + esc(moduleName) + '">' + esc(moduleName) + '</option>';
      }).join('');
      moduleSelect.value = current;
    }

    function loadInitialSelection() {
      if (!roleId) { return Promise.resolve(); }
      return App.api.get('/api/v1/iam/roles/' + roleId).then(function (res) {
        if (!res.ok) { throw new Error(res.message || 'Failed to load role permissions.'); }
        var slugs = (res.data && res.data.permissions) || [];
        var slugSet = {};
        slugs.forEach(function (slug) { slugSet[slug] = true; });

        return App.api.get('/api/v1/iam/permissions').then(function (permRes) {
          if (!permRes.ok) { throw new Error(permRes.message || 'Failed to load permission catalog.'); }
          (permRes.data || []).forEach(function (permission) {
            if (slugSet[permission.slug]) {
              selectedIds[permission.id] = true;
            }
          });
          initialSelection = cloneSelectionMap(selectedIds);
          updateSummary();
        });
      });
    }

    loadInitialSelection()
      .then(function () {
        table = App.tables.createAdvanced({
          card: card,
          tbody: config.tbody,
          endpoint: '/api/v1/iam/permissions',
          colspan: mode === 'role-permissions' ? 7 : 6,
          pageSize: 15,
          defaultSort: 'module',
          pagination: config.pagination,
          emptyTitle: 'No permissions found',
          emptySubtitle: 'Permission rows will appear here when available.',
          filters: {
            'iam-role-assign-search': 'search',
            'iam-role-assign-module': 'module',
            'iam-role-assign-risk': 'risk'
          },
          filterRecord: function (record, values) {
            if (mode !== 'role-permissions') { return true; }
            var q = (values.search || '').toLowerCase().trim();
            var moduleName = (values.module || '').toLowerCase();
            var risk = (values.risk || '').toLowerCase();
            var haystack = [
              record.name,
              record.slug,
              record.description,
              record.module,
              record.actionCategory
            ].join(' ').toLowerCase();

            if (q && haystack.indexOf(q) === -1) { return false; }
            if (moduleName && String(record.module || '').toLowerCase() !== moduleName) { return false; }
            if (risk && String(record.riskLevel || '').toLowerCase() !== risk) { return false; }
            if (showSelected && showSelected.checked && !selectedIds[record.id]) { return false; }

            return true;
          },
          sortValue: function (record, field) {
            return String(record[field] || '').toLowerCase();
          },
          renderRow: function (record) {
            var checked = selectedIds[record.id] ? ' checked' : '';
            return '<tr>' +
              '<td><input class="form-check-input iam-role-permission-check" type="checkbox" data-permission-id="' + esc(record.id || '') + '"' + checked + '></td>' +
              '<td><span class="badge bg-label-info">' + esc(record.module || 'system') + '</span></td>' +
              '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(record.name || '--') + '</span>' + (record.slug ? '<small class="text-muted">' + esc(record.slug) + '</small>' : '') + '</div></td>' +
              (mode === 'role-permissions' ? '<td><code>' + esc(record.slug || '--') + '</code></td>' : '') +
              '<td>' + display(record.actionCategory) + '</td>' +
              '<td>' + riskBadge(record.riskLevel) + '</td>' +
              '<td>' + (record.systemOnly ? '<span class="badge bg-label-primary">Yes</span>' : '<span class="badge bg-label-secondary">No</span>') + '</td>' +
              '</tr>';
          },
          afterLoad: function (records) {
            populateModuleOptions(records);
            updateSummary();
          },
          afterRender: function () {
            bindSelectionControls();
          }
        });

        if (showSelected) {
          showSelected.addEventListener('change', function () {
            table.applyFilters();
          });
        }

        if (selectAllBtn) {
          selectAllBtn.addEventListener('click', function () {
            table.records.forEach(function (record) {
              if (!record.systemOnly) {
                selectedIds[record.id] = true;
              }
            });
            updateSummary();
            table.render();
          });
        }

        if (resetBtn) {
          resetBtn.addEventListener('click', function () {
            selectedIds = cloneSelectionMap(initialSelection);
            updateSummary();
            table.render();
          });
        }

        if (saveBtn) {
          saveBtn.addEventListener('click', function () {
            App.api.request('PUT', '/api/v1/iam/roles/' + roleId + '/permissions', {
              permissionIds: selectedPermissionIds(selectedIds)
            }).then(function (res) {
              if (!res.ok) {
                showAlert('danger', res.message || 'Failed to save role permissions.', config.feedback);
                return;
              }
              initialSelection = cloneSelectionMap(selectedIds);
              updateSummary();
              showAlert('success', 'Role permissions saved successfully.', config.feedback);
            })['catch'](function (err) {
              showAlert('danger', (err && err.message) || 'Failed to save role permissions.', config.feedback);
            });
          });
        }

        if (form) {
          App.forms.bindAjaxForm(form, {
            method: config.submitMethod,
            url: form.getAttribute('action'),
            alertTarget: config.feedback,
            transformData: function (data) {
              data.permissionIds = selectedPermissionIds(selectedIds);
              return data;
            },
            onSuccess: function (res) {
              if (res && res.data && res.data.id) {
                window.location.href = '/roles/' + res.data.id;
              }
            }
          });
        }
      })['catch'](function (err) {
        showAlert('danger', (err && err.message) || 'Failed to initialize the permission workspace.', config.feedback || screen);
      });
  }

  /* -----------------------------------------------------------------------
   * SCREEN: ROLES  (data-iam-screen="roles")
   * --------------------------------------------------------------------- */
  function initRoles() {
    var card = document.getElementById('iam-roles-list');
    if (!card) { return; }

    var rolesTable = App.tables.createAdvanced({
      card: '#iam-roles-list',
      tbody: '#iam-roles-body',
      endpoint: '/api/v1/iam/roles',
      colspan: 8,
      pageSize: 15,
      defaultSort: 'name',
      pagination: '#iam-roles-pagination',
      emptyTitle: 'No roles found',
      emptySubtitle: 'Adjust the filters or create a new role to continue.',
      filters: {
        'iam-roles-search': 'search',
        'iam-roles-risk': 'risk',
        'iam-roles-system': 'system'
      },
      filterRecord: function (record, values) {
        var q = (values.search || '').toLowerCase().trim();
        var risk = (values.risk || '').toLowerCase();
        var system = (values.system || '').toLowerCase();
        var roleRisk = String(record.riskLevel || '').toLowerCase();
        var isSystem = !!record.isSystem;
        var haystack = [
          record.name,
          record.slug,
          record.description,
          record.scope
        ].join(' ').toLowerCase();

        if (q && haystack.indexOf(q) === -1) { return false; }
        if (risk && roleRisk !== risk) { return false; }
        if (system === 'yes' && !isSystem) { return false; }
        if (system === 'no' && isSystem) { return false; }

        return true;
      },
      sortValue: function (record, field) {
        if (field === 'userCount' || field === 'permissionCount') {
          return parseInt(record[field] || 0, 10);
        }
        return String(record[field] || '').toLowerCase();
      },
      renderRow: function (record, table, index) {
        var systemBadge = record.isSystem
          ? '<span class="badge bg-label-primary">System</span>'
          : '<span class="badge bg-label-secondary">Custom</span>';
        var actionItems = mapActionItems(record.actions);
        var rowNum = (table.currentPage - 1) * table.pageSize + index + 1;

        return '<tr>' +
          '<td><span class="text-muted small">' + rowNum + '</span></td>' +
          '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(record.name || '--') + '</span><small class="text-muted">' + esc(record.slug || '--') + '</small></div></td>' +
          '<td>' + display(record.description) + '</td>' +
          '<td>' + systemBadge + '</td>' +
          '<td>' + riskBadge(record.riskLevel) + '</td>' +
          '<td>' + esc(record.userCount != null ? record.userCount : 0) + '</td>' +
          '<td>' + esc(record.permissionCount != null ? record.permissionCount : 0) + '</td>' +
          '<td class="text-end">' + (actionItems.length ? App.tables.actionDropdown(actionItems) : '<span class="text-muted">--</span>') + '</td>' +
          '</tr>';
      }
    });

    var resetBtn = card.querySelector('[data-iam-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        card.querySelectorAll('[data-iam-filters] input, [data-iam-filters] select').forEach(function (el) {
          el.value = '';
        });
        rolesTable.applyFilters();
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: PERMISSIONS  (data-iam-screen="permissions")
   * --------------------------------------------------------------------- */
  function initPermissions() {
    var card = document.querySelector('[data-iam-screen="permissions"]');
    if (!card) { return; }

    var moduleSelect = document.getElementById('iam-permissions-module');

    var permissionsTable = App.tables.createAdvanced({
      card: card,
      tbody: '#iam-permissions-body',
      endpoint: '/api/v1/iam/permissions',
      colspan: 8,
      pageSize: 20,
      defaultSort: 'module',
      pagination: '#iam-permissions-pagination',
      emptyTitle: 'No permissions found',
      emptySubtitle: 'Adjust the filters to inspect a different part of the catalog.',
      filters: {
        'iam-permissions-search': 'search',
        'iam-permissions-module': 'module',
        'iam-permissions-risk': 'risk'
      },
      filterRecord: function (record, values) {
        var q = (values.search || '').toLowerCase().trim();
        var moduleName = (values.module || '').toLowerCase();
        var risk = (values.risk || '').toLowerCase();
        var haystack = [
          record.name,
          record.slug,
          record.description,
          record.module,
          record.actionCategory
        ].join(' ').toLowerCase();

        if (q && haystack.indexOf(q) === -1) { return false; }
        if (moduleName && String(record.module || '').toLowerCase() !== moduleName) { return false; }
        if (risk && String(record.riskLevel || '').toLowerCase() !== risk) { return false; }

        return true;
      },
      sortValue: function (record, field) {
        return String(record[field] || '').toLowerCase();
      },
      renderRow: function (record, table, index) {
        var defaults = Array.isArray(record.defaultAssignments)
          ? record.defaultAssignments.join(', ')
          : '';
        var rowNum = (table.currentPage - 1) * table.pageSize + index + 1;

        return '<tr>' +
          '<td><span class="text-muted small">' + rowNum + '</span></td>' +
          '<td><span class="badge bg-label-info">' + esc(record.module || 'system') + '</span></td>' +
          '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(record.name || '--') + '</span><small class="text-muted">' + display(record.description) + '</small></div></td>' +
          '<td><code>' + esc(record.slug || '--') + '</code></td>' +
          '<td>' + display(record.actionCategory) + '</td>' +
          '<td>' + riskBadge(record.riskLevel) + '</td>' +
          '<td>' + display(defaults) + '</td>' +
          '<td>' + (record.systemOnly ? '<span class="badge bg-label-primary">Yes</span>' : '<span class="badge bg-label-secondary">No</span>') + '</td>' +
          '</tr>';
      },
      afterLoad: function (records) {
        if (!moduleSelect) { return; }

        var current = moduleSelect.value;
        var modules = [];
        records.forEach(function (record) {
          var moduleName = String(record.module || '').trim();
          if (moduleName && modules.indexOf(moduleName) === -1) {
            modules.push(moduleName);
          }
        });
        modules.sort(function (a, b) { return a.localeCompare(b); });

        moduleSelect.innerHTML = '<option value="">All modules</option>' + modules.map(function (moduleName) {
          return '<option value="' + esc(moduleName) + '">' + esc(moduleName) + '</option>';
        }).join('');
        moduleSelect.value = current;
      }
    });

    var resetBtn = card.querySelector('[data-iam-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        card.querySelectorAll('[data-iam-filters] input, [data-iam-filters] select').forEach(function (el) {
          el.value = '';
        });
        permissionsTable.applyFilters();
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: USERS DIRECTORY  (data-iam-screen="users")
   * --------------------------------------------------------------------- */
  function initUsersDirectory() {
    var card = document.getElementById('iam-users-directory');
    if (!card) { return; }

    // Populate role filter dropdown from API
    var roleSelect = document.getElementById('iam-users-role');
    if (roleSelect && roleSelect.options.length <= 1) {
      App.api.get('/api/v1/iam/roles').then(function (res) {
        if (!res.ok) { return; }
        (res.data || []).forEach(function (role) {
          var opt = document.createElement('option');
          opt.value = role.slug || role.id;
          opt.textContent = role.name || role.slug;
          roleSelect.appendChild(opt);
        });
      });
    }

    var usersTable = App.tables.createAdvanced({
      card: '#iam-users-directory',
      tbody: '#iam-users-table-body',
      endpoint: '/api/v1/iam/users',
      colspan: 7,
      pageSize: 15,
      defaultSort: 'fullName',
      pagination: '#iam-users-pagination',
      filters: {
        'iam-users-search': 'search',
        'iam-users-status': 'status',
        'iam-users-role': 'role'
      },
      filterRecord: function (record, values) {
        var profile = record.profile || {};
        var membership = record.membership || {};

        // Text search
        if (values.search) {
          var q = values.search.toLowerCase();
          var haystack = [
            profile.fullName, record.email, membership.roleSlug
          ].join(' ').toLowerCase();
          if (haystack.indexOf(q) === -1) { return false; }
        }

        // Status filter
        if (values.status && String(profile.status).toLowerCase() !== values.status.toLowerCase()) {
          return false;
        }

        // Role filter
        if (values.role && membership.roleSlug !== values.role) {
          return false;
        }

        return true;
      },
      sortValue: function (record, field) {
        if (field === 'fullName') { return (record.profile || {}).fullName || ''; }
        if (field === 'status') { return (record.profile || {}).status || ''; }
        if (field === 'role') { return (record.membership || {}).roleSlug || ''; }
        return record[field] || '';
      },
      renderRow: function (record, table, index) {
        var profile = record.profile || {};
        var membership = record.membership || {};
        var name = esc(profile.fullName || '--');
        var email = esc(record.email || '--');
        var initials = (profile.fullName || '--').split(' ').map(function (w) { return w.charAt(0); }).join('').substring(0, 2).toUpperCase();
        var role = esc(membership.roleName || membership.roleSlug || '--');
        var rowNum = (table.currentPage - 1) * table.pageSize + index + 1;

        var actionsHtml = '';
        if (record.actions && record.actions.length) {
          var items = record.actions.map(function (action) {
            if (action.method === 'LINK') {
              return '<a class="dropdown-item" href="' + esc(action.url) + '">' + esc(action.label) + '</a>';
            }
            return '<a class="dropdown-item" href="#" data-action-url="' + esc(action.url) + '" ' +
              'data-action-method="' + esc(action.method) + '" ' +
              'data-confirm-title="' + esc(action.confirmTitle || '') + '" ' +
              'data-confirm-text="' + esc(action.confirmText || '') + '" ' +
              'data-success-message="' + esc(action.successMessage || 'Done.') + '">' +
              esc(action.label) + '</a>';
          }).join('');
          actionsHtml = App.tables.actionDropdown(items);
        }

        return '<tr>' +
          '<td><span class="text-muted small">' + rowNum + '</span></td>' +
          '<td>' +
          '<div class="d-flex align-items-center">' +
          '<span class="avatar avatar-sm me-2 bg-primary text-white">' + initials + '</span>' +
          '<div>' +
          '<div class="fw-medium">' + name + '</div>' +
          '<small class="text-muted">' + email + '</small>' +
          '</div>' +
          '</div>' +
          '</td>' +
          '<td>' + role + '</td>' +
          '<td>' + statusBadge(profile.status) + '</td>' +
          '<td>' + display(profile.lastLoginAt) + '</td>' +
          '<td>' + display(profile.createdAt) + '</td>' +
          '<td>' + actionsHtml + '</td>' +
          '</tr>';
      },
      afterRender: function () {
        // Bind inline workflow actions
        card.querySelectorAll('[data-action-url]').forEach(function (link) {
          if (link._iamBound) { return; }
          link._iamBound = true;
          link.addEventListener('click', function (e) {
            e.preventDefault();
            handleWorkflowAction(link, function () { usersTable.reload(); });
          });
        });
      }
    });

    // Reset button
    var resetBtn = card.querySelector('[data-iam-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        card.querySelectorAll('[data-iam-filters] input, [data-iam-filters] select').forEach(function (el) {
          el.value = '';
        });
        usersTable.applyFilters();
      });
    }
  }

  /* -----------------------------------------------------------------------
   * GENERIC WORKFLOW ACTION HANDLER
   * Reads data-attributes from a trigger element, shows confirmation
   * dialog, then fires the API request.
   * --------------------------------------------------------------------- */
  function handleWorkflowAction(triggerEl, onSuccess) {
    var url = triggerEl.getAttribute('data-action-url');
    var method = triggerEl.getAttribute('data-action-method') || 'POST';
    var confirmTitle = triggerEl.getAttribute('data-confirm-title');
    var confirmText = triggerEl.getAttribute('data-confirm-text');
    var successMessage = triggerEl.getAttribute('data-success-message') || 'Action completed.';

    var performAction = function () {
      App.api.request(method, url)
        .then(function (res) {
          if (res.ok) {
            notify('success', res.message || successMessage);
            if (onSuccess) { onSuccess(res); }
          } else {
            notify('error', res.message || 'Action failed.');
          }
        })['catch'](function (err) {
          notify('error', (err && err.message) || 'A network error occurred.');
        });
    };

    if (confirmTitle) {
      App.modals.confirm({
        title: confirmTitle,
        text: confirmText || '',
        confirmText: 'Confirm',
        onConfirm: performAction
      });
    } else {
      performAction();
    }
  }

  // Expose for inline onclick usage if needed
  window.handleUserWorkflowAction = function (btn) {
    handleWorkflowAction(btn, function () { window.location.reload(); });
  };

  /* -----------------------------------------------------------------------
   * SCREEN: PROFILE OVERVIEW  (data-iam-screen="profile-overview")
   * --------------------------------------------------------------------- */
  function initProfileOverview() {
    var screen = document.querySelector('[data-iam-screen="profile-overview"]');
    if (!screen) { return; }

    App.tables.createAdvanced({
      card: screen,
      tbody: '#iam-profile-activity-body',
      endpoint: '/api/v1/iam/profile/activity',
      colspan: 5,
      pageSize: 10,
      defaultSort: 'createdAt',
      sortDir: 'desc',
      pagination: '#iam-profile-activity-pagination',
      emptyTitle: 'No activity found',
      emptySubtitle: 'Profile activity rows appear here when available.',
      sortValue: function (record, field) {
        if (field === 'createdAt') {
          return new Date(record.createdAt || 0).getTime();
        }
        return String(record[field] || '').toLowerCase();
      },
      renderRow: function (record, table, index) {
        return '<tr>' +
          '<td><div class="fw-medium">' + esc(record.action || '--') + '</div></td>' +
          '<td>' + display(record.description) + '</td>' +
          '<td>' + display(record.location) + '</td>' +
          '<td>' + display(record.os) + '</td>' +
          '<td>' + display(record.createdAt) + '</td>' +
          '</tr>';
      }
    });
  }

  /* -----------------------------------------------------------------------
   * SCREEN: PROFILE SECURITY  (data-iam-screen="profile-security")
   * --------------------------------------------------------------------- */
  function initProfileSecurity() {
    var form = document.getElementById('iam-profile-password-form');
    if (!form) { return; }

    App.forms.bindAjaxForm(form, {
      method: 'PUT',
      url: form.getAttribute('action'),
      alertTarget: '#iam-profile-security-feedback',
      onSuccess: function () {
        notify('success', 'Password updated successfully.');
        App.forms.reset(form);
      }
    });
  }

  /* -----------------------------------------------------------------------
   * SCREEN: PROFILE SESSIONS  (data-iam-screen="profile-sessions")
   * --------------------------------------------------------------------- */
  function initProfileSessions() {
    var screen = document.querySelector('[data-iam-screen="profile-sessions"]');
    if (!screen) { return; }

    var table = App.tables.createAdvanced({
      card: screen,
      tbody: '#iam-profile-sessions-body',
      endpoint: '/api/v1/iam/profile/sessions',
      colspan: 7,
      pageSize: 10,
      defaultSort: 'lastSeenAt',
      sortDir: 'desc',
      pagination: '#iam-profile-sessions-pagination',
      emptyTitle: 'No active sessions',
      emptySubtitle: 'Session rows appear here when available.',
      filters: {
        'iam-profile-sessions-search': 'search',
        'iam-profile-sessions-status': 'status',
        'iam-profile-sessions-age': 'started'
      },
      filterRecord: function (record, values) {
        var haystack = [
          record.userAgent,
          record.ipAddress,
          record.location,
          record.current ? 'current' : 'active'
        ].join(' ').toLowerCase();

        if (values.search && haystack.indexOf(values.search.toLowerCase()) === -1) {
          return false;
        }
        var rowStatus = record.current ? 'current' : 'active';
        if (values.status && rowStatus !== values.status.toLowerCase()) {
          return false;
        }
        if (values.started) {
          var started = new Date(record.loginAt || 0);
          var now = new Date();
          if (values.started === 'today' && started.toDateString() !== now.toDateString()) { return false; }
          if (values.started === 'week') {
            var weekAgo = new Date(now);
            weekAgo.setDate(weekAgo.getDate() - 7);
            if (started < weekAgo) { return false; }
          }
          if (values.started === 'month') {
            var monthAgo = new Date(now);
            monthAgo.setMonth(monthAgo.getMonth() - 1);
            if (started < monthAgo) { return false; }
          }
        }
        return true;
      },
      sortValue: function (record, field) {
        if (field === 'loginAt' || field === 'lastSeenAt') {
          return new Date(record[field] || 0).getTime();
        }
        return String(record[field] || '').toLowerCase();
      },
      renderRow: function (record, table, index) {
        var currentBadge = record.current
          ? '<span class="badge bg-success">Current</span>'
          : '<span class="badge bg-label-secondary">Active</span>';
        var actionHtml = record.current
          ? '<span class="text-muted">--</span>'
          : '<button type="button" class="btn btn-sm btn-outline-danger js-revoke-profile-session" data-session-id="' + esc(record.sessionId || '') + '">Revoke</button>';

        return '<tr>' +
          '<td><div class="fw-medium">' + display(record.userAgent || 'Unknown device') + '</div></td>' +
          '<td>' + display(record.ipAddress) + '</td>' +
          '<td>' + display(record.location || '--') + '</td>' +
          '<td>' + display(record.loginAt) + '</td>' +
          '<td>' + display(record.lastSeenAt) + '</td>' +
          '<td>' + currentBadge + '</td>' +
          '<td class="text-end">' + actionHtml + '</td>' +
          '</tr>';
      },
      afterRender: function () {
        screen.querySelectorAll('.js-revoke-profile-session').forEach(function (btn) {
          if (btn._iamBound) { return; }
          btn._iamBound = true;
          btn.addEventListener('click', function () {
            var sessionId = btn.getAttribute('data-session-id') || '';
            if (!sessionId) { return; }
            App.modals.confirm({
              title: 'Revoke Session?',
              text: 'This will end the selected session.',
              confirmText: 'Revoke',
              onConfirm: function () {
                App.api.delete('/api/v1/iam/profile/sessions/' + sessionId)
                  .then(function (res) {
                    if (res.ok) {
                      notify('success', res.message || 'Session revoked.');
                      table.reload();
                    } else {
                      notify('error', res.message || 'Failed to revoke session.');
                    }
                  })['catch'](function (err) {
                    notify('error', (err && err.message) || 'An error occurred.');
                  });
              }
            });
          });
        });
      }
    });

    var resetBtn = screen.querySelector('[data-iam-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        screen.querySelectorAll('[data-iam-filters] input, [data-iam-filters] select').forEach(function (el) {
          el.value = '';
        });
        table.applyFilters();
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: PENDING APPROVALS  (data-iam-screen="pending-approvals")
   * --------------------------------------------------------------------- */
  function initPendingApprovals() {
    var card = document.getElementById('iam-pending-table');
    if (!card) { return; }

    // Fetch available roles for the assignment dropdown
    var rolesCache = [];
    App.api.get('/api/v1/iam/roles').then(function (res) {
      if (res.ok) { rolesCache = res.data || []; }
    });

    var pendingTable = App.tables.createAdvanced({
      card: '#iam-pending-table',
      tbody: '#iam-pending-body',
      endpoint: '/api/v1/iam/users/pending-approvals',
      colspan: 5,
      pageSize: 10,
      defaultSort: 'createdAt',
      sortDir: 'desc',
      pagination: '#iam-pending-pagination',
      filters: {
        'iam-pending-search': 'search',
        'iam-pending-age': 'submitted'
      },
      filterRecord: function (record, values) {
        var profile = record.profile || {};
        if (values.search) {
          var q = values.search.toLowerCase();
          var haystack = [profile.fullName, record.email].join(' ').toLowerCase();
          if (haystack.indexOf(q) === -1) { return false; }
        }
        if (values.submitted) {
          var created = new Date(profile.createdAt);
          var now = new Date();
          if (values.submitted === 'today' && created.toDateString() !== now.toDateString()) { return false; }
          if (values.submitted === 'week') {
            var weekAgo = new Date(now); weekAgo.setDate(weekAgo.getDate() - 7);
            if (created < weekAgo) { return false; }
          }
          if (values.submitted === 'month') {
            var monthAgo = new Date(now); monthAgo.setMonth(monthAgo.getMonth() - 1);
            if (created < monthAgo) { return false; }
          }
        }
        return true;
      },
      renderRow: function (record) {
        var profile = record.profile || {};
        var name = esc(profile.fullName || '--');
        var email = esc(record.email || '--');

        // Role assignment dropdown
        var roleOptions = '<option value="">Select role…</option>';
        rolesCache.forEach(function (role) {
          roleOptions += '<option value="' + esc(role.id) + '">' + esc(role.name) + '</option>';
        });

        return '<tr>' +
          '<td><div class="fw-medium">' + name + '</div></td>' +
          '<td><small>' + email + '</small></td>' +
          '<td><select class="form-select form-select-sm pending-role-select" data-user-id="' + esc(record.uuid) + '">' + roleOptions + '</select></td>' +
          '<td>' + display(profile.createdAt) + '</td>' +
          '<td class="text-end">' +
          '<div class="btn-group btn-group-sm">' +
          '<button class="btn btn-success pending-approve-btn" data-user-id="' + esc(record.uuid) + '">Approve</button>' +
          '<button class="btn btn-outline-danger pending-reject-btn" data-user-id="' + esc(record.uuid) + '">Reject</button>' +
          '</div>' +
          '</td>' +
          '</tr>';
      },
      afterRender: function () {
        // Approve buttons
        card.querySelectorAll('.pending-approve-btn').forEach(function (btn) {
          if (btn._iamBound) { return; }
          btn._iamBound = true;
          btn.addEventListener('click', function () {
            var userId = btn.getAttribute('data-user-id');
            var select = card.querySelector('.pending-role-select[data-user-id="' + userId + '"]');
            var roleId = select ? select.value : '';
            if (!roleId) {
              notify('error', 'Please select a role before approving.');
              if (select) { select.focus(); }
              return;
            }
            App.modals.confirm({
              title: 'Approve User?',
              text: 'This will assign the selected role and activate the user account.',
              confirmText: 'Approve',
              onConfirm: function () {
                App.api.request('PUT', '/api/v1/iam/users/' + userId + '/role', { roleId: roleId })
                  .then(function (res) {
                    if (!res.ok) { throw new Error(res.message || 'Failed to assign role.'); }
                    return App.api.post('/api/v1/iam/users/' + userId + '/activate');
                  })
                  .then(function (res) {
                    if (res.ok) {
                      notify('success', 'User approved and activated.');
                      pendingTable.reload();
                    } else {
                      notify('error', res.message || 'Failed to activate user.');
                    }
                  })['catch'](function (err) {
                    notify('error', (err && err.message) || 'An error occurred during approval.');
                  });
              }
            });
          });
        });

        // Reject buttons
        card.querySelectorAll('.pending-reject-btn').forEach(function (btn) {
          if (btn._iamBound) { return; }
          btn._iamBound = true;
          btn.addEventListener('click', function () {
            var userId = btn.getAttribute('data-user-id');
            App.modals.confirm({
              title: 'Reject Registration?',
              text: 'This will remove the pending registration. This action cannot be undone.',
              confirmText: 'Reject',
              onConfirm: function () {
                App.api.delete('/api/v1/iam/users/' + userId)
                  .then(function (res) {
                    if (res.ok) {
                      notify('success', 'Registration rejected.');
                      pendingTable.reload();
                    } else {
                      notify('error', res.message || 'Failed to reject.');
                    }
                  })['catch'](function (err) {
                    notify('error', (err && err.message) || 'An error occurred.');
                  });
              }
            });
          });
        });
      }
    });

    // Reset button
    var resetBtn = card.querySelector('[data-iam-reset]');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        card.querySelectorAll('[data-iam-filters] input, [data-iam-filters] select').forEach(function (el) { el.value = ''; });
        pendingTable.applyFilters();
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: USER DETAIL  (data-iam-screen="user-detail")
   * --------------------------------------------------------------------- */
  function initUserDetail() {
    var screenEl = document.querySelector('[data-iam-screen="user-detail"]');
    if (!screenEl) { return; }

    // Extract userId from any link that contains the UUID pattern
    var editLink = screenEl.querySelector('a[href*="/users/"][href$="/edit"]');
    var userId = '';
    if (editLink) {
      var match = editLink.getAttribute('href').match(/\/users\/([0-9a-fA-F-]{36})/);
      if (match) { userId = match[1]; }
    }
    if (!userId) {
      // Fallback: try the role-assignment link
      var roleLink = screenEl.querySelector('a[href*="/users/"][href$="/role"]');
      if (roleLink) {
        var m2 = roleLink.getAttribute('href').match(/\/users\/([0-9a-fA-F-]{36})/);
        if (m2) { userId = m2[1]; }
      }
    }
    if (!userId) { return; }

    var permBody = document.getElementById('iam-user-permissions-body');
    var overrideBody = document.getElementById('iam-user-overrides-body');
    var permCountEl = document.getElementById('iam-user-permission-count');
    var overrideCountEl = document.getElementById('iam-user-override-count');
    var sessionCountEl = document.getElementById('iam-user-session-count');

    // Load effective permissions and overrides
    App.api.get('/api/v1/iam/users/' + userId + '/permissions')
      .then(function (res) {
        if (!res.ok) { return; }
        var data = res.data || {};
        var effective = data.effectivePermissionRows || [];
        var overrides = data.overrides || [];

        if (permCountEl) { permCountEl.textContent = effective.length; }
        if (overrideCountEl) { overrideCountEl.textContent = overrides.length; }

        // Render effective permissions
        if (permBody) {
          if (effective.length === 0) {
            permBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No effective permissions assigned.</td></tr>';
          } else {
            permBody.innerHTML = effective.map(function (row) {
              return '<tr>' +
                '<td><span class="badge bg-label-info">' + esc(row.module || 'system') + '</span></td>' +
                '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(row.name) + '</span><small class="text-muted">' + esc(row.slug) + '</small></div></td>' +
                '<td>' + esc(row.actionCategory || '--') + '</td>' +
                '<td><span class="badge bg-label-primary">' + esc(row.source || 'Effective') + '</span></td>' +
                '</tr>';
            }).join('');
          }
        }

        // Render overrides
        if (overrideBody) {
          if (overrides.length === 0) {
            overrideBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No permission overrides configured.</td></tr>';
          } else {
            overrideBody.innerHTML = overrides.map(function (row) {
              var overrideBadge = row.type === 'grant'
                ? '<span class="badge bg-success">Grant</span>'
                : '<span class="badge bg-danger">Deny</span>';
              var resultBadge = row.effective
                ? '<span class="badge bg-label-success">Allowed</span>'
                : '<span class="badge bg-label-danger">Denied</span>';
              var roleDefaultBadge = row.roleDefault
                ? '<span class="badge bg-label-primary">Allowed</span>'
                : '<span class="badge bg-label-secondary">Denied</span>';
              return '<tr>' +
                '<td><span class="badge bg-label-info">' + esc(row.module || 'system') + '</span></td>' +
                '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(row.name) + '</span><small class="text-muted">' + esc(row.slug) + '</small></div></td>' +
                '<td>' + roleDefaultBadge + '</td>' +
                '<td>' + overrideBadge + '</td>' +
                '<td>' + resultBadge + '</td>' +
                '</tr>';
            }).join('');
          }
        }
      })['catch'](function () {
        if (permBody) { permBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load permissions.</td></tr>'; }
      });

    // Load session count
    if (sessionCountEl) {
      App.api.get('/api/v1/iam/users/' + userId + '/sessions')
        .then(function (res) {
          if (res.ok) { sessionCountEl.textContent = (res.data || []).length; }
        })['catch'](function () { /* silent */ });
    }

    // Simple tab switching (show/hide sections)
    screenEl.querySelectorAll('.nav-tabs .nav-link').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        screenEl.querySelectorAll('.nav-tabs .nav-link').forEach(function (l) { l.classList.remove('active'); });
        link.classList.add('active');
        var target = link.getAttribute('href');
        ['#iam-user-permissions-section', '#iam-user-overrides-section', '#iam-user-history-section'].forEach(function (id) {
          var el = document.querySelector(id);
          if (el) { el.style.display = (id === target) ? '' : 'none'; }
        });
      });
    });
    // Hide non-active sections on load
    var activeTab = screenEl.querySelector('.nav-tabs .nav-link.active');
    if (activeTab) {
      var activeTarget = activeTab.getAttribute('href');
      ['#iam-user-permissions-section', '#iam-user-overrides-section', '#iam-user-history-section'].forEach(function (id) {
        var el = document.querySelector(id);
        if (el) { el.style.display = (id === activeTarget) ? '' : 'none'; }
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: USER SECURITY  (data-iam-form="user-password" / sessions)
   * --------------------------------------------------------------------- */
  function initUserSecurity() {
    var passwordForm = document.getElementById('iam-user-password-form');
    if (!passwordForm) { return; }

    var userId = passwordForm.getAttribute('data-user-id') || '';

    // Bind password reset form
    App.forms.bindAjaxForm(passwordForm, {
      method: 'PUT',
      url: passwordForm.getAttribute('action'),
      alertTarget: '#iam-user-security-feedback',
      onSuccess: function () {
        notify('success', 'Password has been reset successfully.');
        App.forms.reset(passwordForm);
      }
    });

    // Load and render active sessions
    var sessionsTbody = document.getElementById('iam-user-security-sessions');
    function loadSessions() {
      if (!sessionsTbody || !userId) { return; }
      sessionsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading sessions…</td></tr>';

      App.api.get('/api/v1/iam/users/' + userId + '/sessions')
        .then(function (res) {
          if (!res.ok) {
            sessionsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Failed to load sessions.</td></tr>';
            return;
          }
          var sessions = res.data || [];
          if (sessions.length === 0) {
            sessionsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No active sessions.</td></tr>';
            return;
          }

          sessionsTbody.innerHTML = sessions.map(function (s) {
            var device = s.userAgent || 'Unknown Device';
            if (device.length > 50) { device = device.substring(0, 47) + '…'; }
            var statusHtml = s.current
              ? '<span class="badge bg-success">Current</span>'
              : '<span class="badge bg-secondary">Active</span>';
            var actionHtml = s.current
              ? '<span class="text-muted">--</span>'
              : '<button class="btn btn-sm btn-outline-danger revoke-session-btn" data-session-id="' + esc(s.sessionId) + '">Revoke</button>';
            return '<tr>' +
              '<td>' + esc(device) + '</td>' +
              '<td>' + esc(s.ipAddress || 'Unknown') + '</td>' +
              '<td>' + display(s.lastSeenAt) + '</td>' +
              '<td>' + statusHtml + '</td>' +
              '<td class="text-end">' + actionHtml + '</td>' +
              '</tr>';
          }).join('');

          // Bind revoke buttons
          sessionsTbody.querySelectorAll('.revoke-session-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var sessionId = btn.getAttribute('data-session-id');
              App.modals.confirm({
                title: 'Revoke Session?',
                text: 'The user will be forced to log in again.',
                confirmText: 'Revoke',
                onConfirm: function () {
                  App.api.delete('/api/v1/iam/users/' + userId + '/sessions/' + sessionId)
                    .then(function (res) {
                      if (res.ok) { notify('success', 'Session revoked.'); loadSessions(); }
                      else { notify('error', res.message || 'Failed to revoke session.'); }
                    })['catch'](function (err) {
                      notify('error', (err && err.message) || 'An error occurred.');
                    });
                }
              });
            });
          });
        })['catch'](function () {
          sessionsTbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Failed to load sessions.</td></tr>';
        });
    }
    loadSessions();

    // Bind Account State action buttons (data-user-action)
    var actionsContainer = document.getElementById('iam-user-security-actions');
    if (actionsContainer) {
      actionsContainer.querySelectorAll('[data-user-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          handleWorkflowAction(btn, function () {
            setTimeout(function () { window.location.reload(); }, 800);
          });
        });
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SCREEN: ROLE DETAIL  (data-iam-screen="role-detail")
   * --------------------------------------------------------------------- */
  function initRoleDetail() {
    var screenEl = document.querySelector('[data-iam-screen="role-detail"]');
    if (!screenEl) { return; }

    // Extract roleId from the page URL
    var roleId = '';
    var urlMatch = window.location.pathname.match(/\/roles\/([0-9a-fA-F-]{36})/);
    if (urlMatch) { roleId = urlMatch[1]; }
    if (!roleId) { return; }

    var usersBody = document.getElementById('iam-role-users-body');
    var permBody = document.getElementById('iam-role-permissions-body');

    // Fetch full role details (with permissions and assigned users)
    App.api.get('/api/v1/iam/roles/' + roleId)
      .then(function (res) {
        if (!res.ok) { return; }
        var role = res.data || {};
        var permissionSlugs = role.permissions || [];
        var assignedUsers = role.assignedUsers || [];

        // Render assigned users
        if (usersBody) {
          if (assignedUsers.length === 0) {
            usersBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">No users assigned to this role.</td></tr>';
          } else {
              usersBody.innerHTML = assignedUsers.map(function (u) {
                var name = esc(u.displayName || u.fullName || u.email || '--');
                return '<tr>' +
                  '<td><a href="/users/' + esc(u.id || u.uuid || '') + '">' + name + '</a></td>' +
                  '<td>' + statusBadge(u.status) + '</td>' +
                  '</tr>';
              }).join('');
          }
        }

        // Fetch the full permission catalog to match slugs
        if (permBody && permissionSlugs.length > 0) {
          App.api.get('/api/v1/iam/permissions')
            .then(function (permRes) {
              if (!permRes.ok) { return; }
              var catalog = permRes.data || [];
              var slugMap = {};
              catalog.forEach(function (p) { slugMap[p.slug] = p; });

              var rows = permissionSlugs.map(function (slug) {
                var p = slugMap[slug] || {};
                return '<tr>' +
                  '<td><span class="badge bg-label-info">' + esc(p.module || 'system') + '</span></td>' +
                  '<td><div class="d-flex flex-column"><span class="fw-medium">' + esc(p.name || slug) + '</span><small class="text-muted">' + esc(slug) + '</small></div></td>' +
                  '<td>' + esc(p.actionCategory || '--') + '</td>' +
                  '<td>' + riskBadge(p.riskLevel) + '</td>' +
                  '</tr>';
              });
              permBody.innerHTML = rows.join('');
            });
        } else if (permBody) {
          permBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No permissions assigned to this role.</td></tr>';
        }
      })['catch'](function () {
        if (usersBody) { usersBody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-4">Failed to load role data.</td></tr>'; }
      });
  }

  /* -----------------------------------------------------------------------
   * SCREEN: SETTINGS  (data-iam-screen="settings")
   * --------------------------------------------------------------------- */
  function initSettings() {
    var form = document.getElementById('iam-settings-form');
    if (!form) { return; }

    App.forms.bindAjaxForm(form, {
      method: 'PUT',
      url: form.getAttribute('action') || '/api/v1/settings/iam',
      alertTarget: '#iam-settings-feedback',
      transformData: function (data) {
        // Checkboxes → 1 / 0
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
          var name = cb.getAttribute('name');
          if (name) { data[name] = cb.checked ? 1 : 0; }
        });
        // JSON-list textareas → arrays
        form.querySelectorAll('textarea[data-setting-type="json-list"]').forEach(function (ta) {
          var name = ta.getAttribute('name');
          if (name) {
            data[name] = (ta.value || '').split('\n')
              .map(function (s) { return s.trim(); })
              .filter(function (s) { return s.length > 0; });
          }
        });
        // Number inputs → integers
        form.querySelectorAll('input[type="number"]').forEach(function (input) {
          var name = input.getAttribute('name');
          if (name && input.value !== '') { data[name] = parseInt(input.value, 10); }
        });
        return data;
      },
      onSuccess: function (res) {
        showAlert('success', res.message || 'Settings saved successfully.', '#iam-settings-feedback');
      }
    });

    // Reset defaults button
    var resetBtn = document.getElementById('iam-settings-reset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        App.modals.confirm({
          title: 'Reset Settings?',
          text: 'Are you sure you want to reset all IAM settings to their default values? This action cannot be undone.',
          confirmText: 'Reset Defaults',
          onConfirm: function () {
            var keys = [];
            form.querySelectorAll('[name]').forEach(function (el) {
              var name = el.getAttribute('name');
              if (name && keys.indexOf(name) === -1) { keys.push(name); }
            });
            if (keys.length === 0) { return; }

            App.api.request('DELETE', '/api/v1/settings/iam', { keys: keys })
              .then(function (res) {
                if (res.ok) {
                  // Restore all fields from data-default attributes
                  form.querySelectorAll('[name]').forEach(function (el) {
                    var defaultValue = el.getAttribute('data-default');
                    if (defaultValue !== null) {
                      if (el.type === 'checkbox') {
                        el.checked = defaultValue === '1' || defaultValue === 'true';
                      } else if (el.tagName === 'TEXTAREA' && el.getAttribute('data-setting-type') === 'json-list') {
                        try {
                          var parsed = JSON.parse(defaultValue);
                          el.value = Array.isArray(parsed) ? parsed.join('\n') : defaultValue;
                        } catch (e) { el.value = defaultValue; }
                      } else {
                        el.value = defaultValue;
                      }
                    }
                  });
                  showAlert('success', res.message || 'Settings reset to default values.', '#iam-settings-feedback');
                } else {
                  showAlert('danger', res.message || 'Failed to reset settings.', '#iam-settings-feedback');
                }
              })['catch'](function (err) {
                showAlert('danger', (err && err.message) || 'A network error occurred.', '#iam-settings-feedback');
              });
          }
        });
      });
    }
  }

  /* -----------------------------------------------------------------------
   * SHARED: ACTIVITY TIMELINE  (#app-timeline)
   * Loads audit log entries for a given entity (user or role).
   * --------------------------------------------------------------------- */
  function initTimeline() {
    var timelineEl = document.getElementById('app-timeline');
    if (!timelineEl) { return; }

    var entityId = timelineEl.getAttribute('data-entity-id') || '';
    var entityType = timelineEl.getAttribute('data-entity-type') || '';
    var emptyMsg = timelineEl.getAttribute('data-empty-message') || 'No activity logged yet.';

    if (!entityId) {
      timelineEl.innerHTML = '<li class="timeline-item timeline-item-transparent text-center text-muted py-4">' + esc(emptyMsg) + '</li>';
      return;
    }

    var params = { entityType: entityType, entityId: entityId, limit: 10 };

    App.api.get('/api/v1/audit/logs', params)
      .then(function (res) {
        if (!res || !res.ok || !res.data) {
          // Fallback endpoint
          return App.api.get('/api/v1/logs', params);
        }
        return res;
      })
      .then(function (res) {
        if (!res || !res.ok) {
          timelineEl.innerHTML = '<li class="timeline-item timeline-item-transparent text-center text-muted py-4">' + esc(emptyMsg) + '</li>';
          return;
        }

        var logs = res.data || [];
        if (logs.length === 0) {
          timelineEl.innerHTML = '<li class="timeline-item timeline-item-transparent text-center text-muted py-4">' + esc(emptyMsg) + '</li>';
          return;
        }

        timelineEl.innerHTML = logs.map(function (log) {
          var actionLabel = (log.action || 'Activity').replace(/[._-]/g, ' ');
          actionLabel = actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1);
          var desc = log.actorLabel || log.actorName || 'System';
          if (log.ipAddress) { desc += ' from ' + log.ipAddress; }
          var dateStr = log.createdAt || '';

          return '<li class="timeline-item timeline-item-transparent">' +
            '<span class="timeline-point-wrapper"><span class="timeline-point timeline-point-primary"></span></span>' +
            '<div class="timeline-event">' +
            '<div class="timeline-header mb-1">' +
            '<h6 class="mb-0">' + esc(actionLabel) + '</h6>' +
            '<small class="text-muted">' + esc(dateStr) + '</small>' +
            '</div>' +
            '<p class="mb-0 text-secondary">' + esc(desc) + '</p>' +
            '</div>' +
            '</li>';
        }).join('');
      })['catch'](function () {
        timelineEl.innerHTML = '<li class="timeline-item timeline-item-transparent text-center text-muted py-4">Unable to load activity logs.</li>';
      });
  }

  /* -----------------------------------------------------------------------
   * INIT DISPATCHER
   * Runs on DOMContentLoaded – detects the current screen and calls
   * the appropriate initializer.
   * --------------------------------------------------------------------- */
  ready(function () {
    if (!window.App) { return; }

    var screenEl = document.querySelector('[data-iam-screen]');
    var screen = screenEl ? screenEl.getAttribute('data-iam-screen') : '';

    switch (screen) {
      case 'role-create': initRolePermissionWorkspace('role-create'); break;
      case 'role-edit': initRolePermissionWorkspace('role-edit'); break;
      case 'role-permissions': initRolePermissionWorkspace('role-permissions'); break;
      case 'roles': initRoles(); break;
      case 'permissions': initPermissions(); break;
      case 'users': initUsersDirectory(); break;
      case 'profile-overview': initProfileOverview(); break;
      case 'profile-security': initProfileSecurity(); break;
      case 'profile-sessions': initProfileSessions(); break;
      case 'pending-approvals': initPendingApprovals(); break;
      case 'user-detail': initUserDetail(); break;
      case 'role-detail': initRoleDetail(); break;
      case 'settings': initSettings(); break;
    }

    // User security page uses form attribute, not data-iam-screen
    if (document.getElementById('iam-user-password-form')) {
      initUserSecurity();
    }

    // Timeline is shared across detail pages
    initTimeline();
  });

})(window, document);
