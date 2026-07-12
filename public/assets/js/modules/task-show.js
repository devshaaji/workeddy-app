/**
 * task-show.js — Task detail page
 * Routes: GET /tasks/{taskId}
 * API:    GET /api/v1/organizations/{id}/tasks/{taskId}
 *         PUT /api/v1/organizations/{id}/tasks/{taskId}
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('taskShowPage');
    if (!page) { return; }

    var taskId = page.getAttribute('data-task-id') || '';
    var orgId  = page.getAttribute('data-org-id') || '';
    var apiBase = page.getAttribute('data-api-base') || ('/api/v1/organizations/' + orgId + '/tasks/' + taskId);
    var assessmentsApi = page.getAttribute('data-assessments-api') || ('/api/v1/organizations/' + orgId + '/assessments');
    var feedbackApi = page.getAttribute('data-feedback-api') || '/api/v1/worker-feedback';
    var caApi = page.getAttribute('data-ca-api') || '/api/v1/corrective-actions';
    var worksitesApi = '/api/v1/organizations/' + orgId + '/worksites';
    var departmentsApi = '/api/v1/organizations/' + orgId + '/departments';
    var jobRolesApi = '/api/v1/organizations/' + orgId + '/job-roles';

    var worksiteMap = {};
    var departmentMap = {};
    var jobRoleMap = {};

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    function loadLookups(callback) {
        var loaded = 0;
        var total = 3;

        function checkDone() {
            loaded++;
            if (loaded >= total && callback) { callback(); }
        }

        App.api.get(worksitesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                res.data.forEach(function (w) { worksiteMap[w.id] = w.name; });
            }
            checkDone();
        }).catch(function () { checkDone(); });

        App.api.get(departmentsApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                res.data.forEach(function (d) { departmentMap[d.id] = d.name; });
            }
            checkDone();
        }).catch(function () { checkDone(); });

        App.api.get(jobRolesApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                res.data.forEach(function (r) { jobRoleMap[r.id] = r.name; });
            }
            checkDone();
        }).catch(function () { checkDone(); });
    }

    function loadTask(callback) {
        App.api.get(apiBase).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load task.', '#taskDetailAlert');
                App.utils.setText('#task-name-display', 'Error loading task');
                return;
            }
            var d = res.data;
            App.utils.setText('#task-name-display', d.name || '—');
            App.utils.setText('#task-code-display', d.taskCode || '—');
            App.utils.setText('#task-created-display', d.createdAt ? App.utils.formatDate(d.createdAt) : '—');
            App.utils.setText('#task-description-display', d.description || 'No description provided.');

            var badge = document.getElementById('task-status-badge');
            if (badge) { badge.innerHTML = App.ui.statusBadge(d.status); }

            // Show related entity names from lookups
            var wsName = d.worksiteId && worksiteMap[d.worksiteId] ? worksiteMap[d.worksiteId] : 'Not set';
            var deptName = d.departmentId && departmentMap[d.departmentId] ? departmentMap[d.departmentId] : 'Not set';
            var roleName = d.jobRoleId && jobRoleMap[d.jobRoleId] ? jobRoleMap[d.jobRoleId] : 'Not set';

            App.utils.setText('#task-worksite-display', wsName);
            App.utils.setText('#task-dept-display', deptName);
            App.utils.setText('#task-role-display', roleName);
            App.utils.setText('#task-model-display', (d.assessmentModel || 'reba').toUpperCase());
            App.utils.setText('#task-input-support-display', d.supportsVideo ? 'Manual and video supported' : 'Manual input only');

            var videoBtn = document.getElementById('taskVideoAssessmentLink');
            if (videoBtn) {
                if (d.supportsVideo) {
                    videoBtn.classList.remove('disabled');
                    videoBtn.removeAttribute('aria-disabled');
                } else {
                    videoBtn.classList.add('disabled');
                    videoBtn.setAttribute('aria-disabled', 'true');
                }
            }
            var pageVideoBtn = document.getElementById('btnTaskVideoCapture');
            if (pageVideoBtn) {
                if (d.supportsVideo) {
                    pageVideoBtn.classList.remove('disabled');
                    pageVideoBtn.removeAttribute('aria-disabled');
                } else {
                    pageVideoBtn.classList.add('disabled');
                    pageVideoBtn.setAttribute('aria-disabled', 'true');
                }
            }

            if (callback) { callback(d); }
        });
    }

    function loadAssessmentCount() {
        App.api.get(assessmentsApi, { limit: 200 }).then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                var count = res.data.filter(function (a) { return a.taskUuid === taskId; }).length;
                App.utils.setText('#task-assessments-count', count);
            }
        });
    }

    function loadFeedbackCount() {
        App.api.get(feedbackApi + '?limit=200').then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                var count = res.data.filter(function (f) { return f.taskUuid === taskId; }).length;
                App.utils.setText('#task-feedback-count', count);
            }
        }).catch(function () {});
    }

    function loadCaCount() {
        App.api.get(caApi + '?limit=200').then(function (res) {
            if (res.ok && Array.isArray(res.data)) {
                App.utils.setText('#task-ca-count', res.data.length);
            }
        }).catch(function () {});
    }

    // ── Edit Modal ────────────────────────────────────────────────────────
    function populateEditDropdowns(record) {
        var wsSel = document.getElementById('editTaskWorksite');
        if (wsSel) {
            wsSel.innerHTML = '<option value="">No specific worksite</option>';
            Object.keys(worksiteMap).forEach(function (id) {
                wsSel.innerHTML += '<option value="' + e(id) + '">' + e(worksiteMap[id]) + '</option>';
            });
            if (record.worksiteId) { wsSel.value = record.worksiteId; }
        }

        var deptSel = document.getElementById('editTaskDept');
        if (deptSel) {
            deptSel.innerHTML = '<option value="">No specific department</option>';
            Object.keys(departmentMap).forEach(function (id) {
                deptSel.innerHTML += '<option value="' + e(id) + '">' + e(departmentMap[id]) + '</option>';
            });
            if (record.departmentId) { deptSel.value = record.departmentId; }
        }

        var roleSel = document.getElementById('editTaskRole');
        if (roleSel) {
            roleSel.innerHTML = '<option value="">No specific job role</option>';
            Object.keys(jobRoleMap).forEach(function (id) {
                roleSel.innerHTML += '<option value="' + e(id) + '">' + e(jobRoleMap[id]) + '</option>';
            });
            if (record.jobRoleId) { roleSel.value = record.jobRoleId; }
        }
    }

    var btnEdit = document.getElementById('btnEditTask');
    if (btnEdit) {
        btnEdit.addEventListener('click', function () {
            App.ui.clearAlert('#taskEditAlert');
            // Load current data into form
            App.api.get(apiBase).then(function (res) {
                if (!res.ok) { App.notify.error('Failed to load task data.'); return; }
                var d = res.data;
                document.getElementById('editTaskName').value = d.name || '';
                document.getElementById('editTaskCode').value = d.taskCode || '';
                document.getElementById('editTaskAssessmentModel').value = d.assessmentModel || 'reba';
                document.getElementById('editTaskDescription').value = d.description || '';
                document.getElementById('editTaskStatus').value = d.status || 'active';
                populateEditDropdowns(d);
                App.modals.open('#taskEditModal');
            });
        });
    }

    var editSubmitBtn = document.getElementById('taskEditSubmitBtn');
    if (editSubmitBtn) {
        editSubmitBtn.addEventListener('click', function () {
            var form = document.getElementById('taskEditForm');
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            App.ui.setButtonLoading(editSubmitBtn, true);
            App.api.put(apiBase, App.forms.serialize(form)).then(function (res) {
                App.ui.setButtonLoading(editSubmitBtn, false, 'Save Changes');
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Update failed.', '#taskEditAlert');
                    return;
                }
                App.notify.success('Task updated.');
                App.modals.close('#taskEditModal');
                // Reload
                loadLookups(function () {
                    loadTask();
                });
            });
        });
    }

    // ── Initialise ────────────────────────────────────────────────────────
    loadLookups(function () {
        loadTask(function () {
            loadAssessmentCount();
            loadFeedbackCount();
            loadCaCount();
        });
    });
})();
