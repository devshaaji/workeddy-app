/**
 * task-index.js — Tasks management page
 * Routes: GET /tasks
 * API:    GET    /api/v1/organizations/{id}/tasks
 *         POST   /api/v1/organizations/{id}/tasks
 *         PUT    /api/v1/organizations/{id}/tasks/{tId}
 *         DELETE /api/v1/organizations/{id}/tasks/{tId}
 *
 * Uses App.tables.createAdvanced() for table rendering, filtering, and pagination.
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('tasksPage');
    if (!page) { return; }

    var orgId   = page.getAttribute('data-org-id') || '';
    var apiBase = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/tasks');
    var worksitesApi = '/api/v1/organizations/' + orgId + '/worksites';
    var departmentsApi = '/api/v1/organizations/' + orgId + '/departments';
    var jobRolesApi = '/api/v1/organizations/' + orgId + '/job-roles';

    // Lookup maps for related entities
    var worksiteMap = {};
    var departmentMap = {};
    var jobRoleMap = {};

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    function getRelatedName(map, uuid) {
        if (!uuid || !map[uuid]) { return '<span class="text-muted fst-italic">Not set</span>'; }
        return e(map[uuid].name || map[uuid]);
    }

    function populateSelect(selId, map, emptyLabel) {
        var sel = document.getElementById(selId);
        if (!sel) { return; }
        sel.innerHTML = '<option value="">' + e(emptyLabel || 'All') + '</option>';
        Object.keys(map).forEach(function (id) {
            var item = map[id];
            sel.innerHTML += '<option value="' + e(id) + '">' + e(item.name || item) + '</option>';
        });
    }

    // ── Lookup loading ────────────────────────────────────────────────────────
    function loadLookups(callback) {
        var loaded = 0;
        var total = 3;

        function checkDone() {
            loaded++;
            if (loaded >= total && callback) { callback(); }
        }

        // Load worksites
        App.api.get(worksitesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                worksiteMap = {};
                res.data.forEach(function (w) { worksiteMap[w.id] = { name: w.name, location: w.location }; });
            }
            checkDone();
        }).catch(function () { checkDone(); });

        // Load departments
        App.api.get(departmentsApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                departmentMap = {};
                res.data.forEach(function (d) { departmentMap[d.id] = { name: d.name, worksiteId: d.worksiteId }; });
            }
            checkDone();
        }).catch(function () { checkDone(); });

        // Load job roles
        App.api.get(jobRolesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                jobRoleMap = {};
                res.data.forEach(function (r) { jobRoleMap[r.id] = { name: r.name, departmentId: r.departmentId }; });
            }
            checkDone();
        }).catch(function () { checkDone(); });
    }

    function updateStats(records) {
        App.utils.setText('#task-stat-total', records.length);
        App.utils.setText('#task-stat-active', records.filter(function (r) { return r.status === 'active'; }).length);
    }

    // ── Edit-from-URL support ────────────────────────────────────────────────
    var editTaskId = null;
    (function () {
        var m = window.location.search.match(/[?&]edit=([0-9a-fA-F-]{36})/);
        if (m) { editTaskId = m[1]; }
        if (editTaskId && history.replaceState) {
            var cleanUrl = window.location.pathname + window.location.hash;
            history.replaceState(null, '', cleanUrl);
        }
    })();

    function openEditByUrl(table) {
        if (!editTaskId || !table) { return; }
        var record = null;
        for (var i = 0; i < table.records.length; i++) {
            if (table.records[i].id === editTaskId) {
                record = table.records[i];
                break;
            }
        }
        if (record) {
            window.TaskIndex.openEdit(record);
        } else {
            App.api.get(apiBase + '/' + editTaskId).then(function (res) {
                if (res.ok && res.data) {
                    window.TaskIndex.openEdit(res.data);
                }
            });
        }
    }

    // ── Modal helpers ────────────────────────────────────────────────────────
    var modal      = document.getElementById('taskModal');
    var form       = document.getElementById('taskForm');
    var submitBtn  = document.getElementById('taskSubmitBtn');
    var modalTitle = document.getElementById('taskModalTitle');
    var idField    = document.getElementById('taskId');
    var statusGrp  = document.getElementById('taskStatusGroup');
    var deptSelect = document.getElementById('taskDept');
    var roleSelect = document.getElementById('taskJobRole');

    function filterJobRolesByDept(deptId) {
        if (!roleSelect) { return; }
        roleSelect.innerHTML = '<option value="">No specific job role</option>';
        Object.keys(jobRoleMap).forEach(function (id) {
            var role = jobRoleMap[id];
            if (!deptId || role.departmentId === deptId) {
                roleSelect.innerHTML += '<option value="' + e(id) + '">' + e(role.name) + '</option>';
            }
        });
    }

    function populateModalDropdowns() {
        populateSelect('taskWorksite', worksiteMap, 'No specific worksite');
        populateSelect('taskDept', departmentMap, 'No specific department');
        filterJobRolesByDept(deptSelect ? deptSelect.value : '');
    }

    if (deptSelect) {
        deptSelect.addEventListener('change', function () {
            filterJobRolesByDept(deptSelect.value);
        });
    }

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-plus-circle me-2" style="color:var(--we-primary)"></i>Add Task'; }
        if (idField)    { idField.value = ''; }
        if (statusGrp)  { statusGrp.style.display = 'none'; }
        App.forms.reset(form);
        if (form.elements.assessmentModel) { form.elements.assessmentModel.value = ''; }
        App.ui.clearAlert('#taskModalAlert');
        populateModalDropdowns();
        App.modals.open('#taskModal');
    }

    window.TaskIndex = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Task'; }
            if (idField) { idField.value = record.id; }
            if (statusGrp) { statusGrp.style.display = ''; }

            populateModalDropdowns();

            form.elements.name.value = record.name || '';
            if (form.elements.taskCode) { form.elements.taskCode.value = record.taskCode || ''; }
            if (form.elements.assessmentModel) { form.elements.assessmentModel.value = record.assessmentModel || 'reba'; }
            if (form.elements.description) { form.elements.description.value = record.description || ''; }
            if (form.elements.status) { form.elements.status.value = record.status || 'active'; }

            var wsSel = document.getElementById('taskWorksite');
            if (wsSel && record.worksiteId) { wsSel.value = record.worksiteId; }

            if (deptSelect && record.departmentId) {
                deptSelect.value = record.departmentId;
                filterJobRolesByDept(record.departmentId);
            }

            if (roleSelect && record.jobRoleId) { roleSelect.value = record.jobRoleId; }

            App.ui.clearAlert('#taskModalAlert');
            App.modals.open('#taskModal');
        },
        confirmDelete: function (id, name) {
            App.modals.confirm({
                title: 'Delete Task',
                message: 'Are you sure you want to delete <strong>' + App.utils.escapeHtml(name) + '</strong>? This cannot be undone and may affect linked assessments.',
                confirmText: 'Delete',
                confirmClass: 'btn-danger',
                onConfirm: function () {
                    App.api.delete(apiBase + '/' + id).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Delete failed.'); return; }
                        App.notify.success('Task deleted.');
                        if (window._taskTable) { window._taskTable.reload(); }
                    });
                }
            });
        }
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            var tId = idField ? idField.value : '';
            var method = tId ? 'put' : 'post';
            var url    = tId ? (apiBase + '/' + tId) : apiBase;
            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Task');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#taskModalAlert');
                    return;
                }
                App.notify.success(tId ? 'Task updated.' : 'Task created.');
                App.modals.close('#taskModal');
                if (window._taskTable) { window._taskTable.reload(); }
            });
        });
    }

    var btnAdd = document.getElementById('btnAddTask');
    if (btnAdd) { btnAdd.addEventListener('click', openCreate); }

    // ── Init table after lookups load ────────────────────────────────────────
    loadLookups(function () {
        // Populate filter dropdowns from loaded lookups
        populateSelect('task-worksite-filter', worksiteMap, 'All worksites');
        populateSelect('task-dept-filter', departmentMap, 'All departments');

        window._taskTable = App.tables.createAdvanced({
            card: '#tasksCard',
            tbody: '#tasksBody',
            endpoint: apiBase,
            colspan: 8,
            pageSize: 15,
            defaultSort: 'name',
            sortDir: 'asc',
            loadingText: 'Loading tasks...',
            emptyTitle: 'No tasks found',
            emptySubtitle: 'Create the first task to get started.',
            filters: {
                'task-search': 'q',
                'task-worksite-filter': 'worksiteId',
                'task-dept-filter': 'departmentId',
                'task-status-filter': 'status',
                'task-model-filter': 'assessmentModel'
            },
            filterRecord: function (record, values) {
                var q = (values.q || '').toLowerCase().trim();
                var ws = values.worksiteId || '';
                var dept = values.departmentId || '';
                var st = values.status || '';
                var model = values.assessmentModel || '';

                var matchQ = !q
                    || (record.name || '').toLowerCase().includes(q)
                    || (record.taskCode || '').toLowerCase().includes(q)
                    || (record.description || '').toLowerCase().includes(q);
                var matchWs = !ws || record.worksiteId === ws;
                var matchDept = !dept || record.departmentId === dept;
                var matchSt = !st || record.status === st;
                var matchModel = !model || record.assessmentModel === model;
                return matchQ && matchWs && matchDept && matchSt && matchModel;
            },
            sortValue: function (record, key) {
                if (key === 'worksiteName') return (worksiteMap[record.worksiteId] || {}).name || '';
                if (key === 'departmentName') return (departmentMap[record.departmentId] || {}).name || '';
                if (key === 'jobRoleName') return (jobRoleMap[record.jobRoleId] || {}).name || '';
                return record[key];
            },
            renderRow: function (r) {
                var worksiteName = getRelatedName(worksiteMap, r.worksiteId);
                var deptName = getRelatedName(departmentMap, r.departmentId);
                var roleName = getRelatedName(jobRoleMap, r.jobRoleId);

                return '<tr>' +
                    '<td><a href="/tasks/' + e(r.id) + '" class="fw-medium text-decoration-none">' + e(r.name) + '</a></td>' +
                    '<td class="text-muted">' + (r.taskCode ? e(r.taskCode) : '<span class="text-muted fst-italic">—</span>') + '</td>' +
                    '<td class="text-muted">' + worksiteName + '</td>' +
                    '<td class="text-muted">' + deptName + '</td>' +
                    '<td class="text-muted">' + roleName + '</td>' +
                    '<td><div class="fw-medium text-uppercase">' + e(r.assessmentModel || 'reba') + '</div><small class="text-muted">' + (r.supportsVideo ? 'Manual and video' : 'Manual only') + '</small></td>' +
                    '<td>' + App.ui.statusBadge(r.status) + '</td>' +
                    '<td class="text-end">' + App.tables.actionDropdown([
                        { label: 'View', href: '/tasks/' + e(r.id) },
                        { label: 'Edit', onclick: 'TaskIndex.openEdit(' + JSON.stringify(r) + ')' },
                        { label: 'Delete', class: 'text-danger', onclick: 'TaskIndex.confirmDelete("' + e(r.id) + '", "' + e(r.name) + '")' },
                    ]) + '</td>' +
                '</tr>';
            },
            afterLoad: function (records) {
                updateStats(records);
                openEditByUrl(this);
            },
            resultCount: '#task-result-count'
        });
    });
})();
