(function () {
    'use strict';

    var page = document.getElementById('supervisorFeedbackSubmitPage');
    if (!page || !window.App) {
        return;
    }

    var form = document.getElementById('supervisorFeedbackForm');
    var orgUuid = page.getAttribute('data-organization-uuid') || '';
    var assessmentSelect = document.getElementById('supervisorAssessmentSelect');
    var bodyRegionSelect = document.getElementById('supervisorBodyRegion');
    var worksiteDisplay = document.getElementById('supervisorWorksiteDisplay');
    var departmentDisplay = document.getElementById('supervisorDepartmentDisplay');
    var jobRoleDisplay = document.getElementById('supervisorJobRoleDisplay');
    var allAssessments = [];
    var currentTaskUuid = '';
    var tasksById = {};
    var assessmentsById = {};
    var worksitesById = {};
    var departmentsById = {};
    var jobRolesById = {};

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function setOptions(select, items, emptyLabel, valuePicker, labelPicker) {
        select.innerHTML = '<option value="">' + escape(emptyLabel) + '</option>' + items.map(function (item) {
            return '<option value="' + escape(valuePicker(item)) + '">' + escape(labelPicker(item)) + '</option>';
        }).join('');
    }

    function alert(type, message) {
        App.ui.showAlert(type, message, '#supervisorFeedbackAlert');
    }

    function showValidationErrors(errors) {
        if (!form || !errors || !App.forms || !App.forms.showValidationErrors) {
            return { fieldErrors: {}, formErrors: [] };
        }

        return App.forms.showValidationErrors(form, errors);
    }

    function titleCase(value) {
        return String(value || '')
            .replace(/_/g, ' ')
            .replace(/\b[a-z]/g, function (match) { return match.toUpperCase(); });
    }

    function formatAssessmentLabel(item) {
        var task = item && item.taskUuid ? tasksById[item.taskUuid] : null;
        var taskName = task && task.name ? task.name : 'Unlinked assessment';
        var createdAt = item && item.createdAt ? App.utils.formatDate(item.createdAt) : 'Undated';
        var status = item && item.status ? titleCase(item.status) : 'Assessment';
        var model = item && item.model ? String(item.model).toUpperCase() : null;

        return taskName + ' - ' + createdAt + ' - ' + status + (model ? ' (' + model + ')' : '');
    }

    function renderAssessmentOptions(selectedValue) {
        setOptions(
            assessmentSelect,
            allAssessments,
            'Select assessment',
            function (item) { return item.id || item.uuid; },
            formatAssessmentLabel
        );

        if (selectedValue && allAssessments.some(function (item) { return (item.id || item.uuid) === selectedValue; })) {
            assessmentSelect.value = selectedValue;
        }
    }

    function updateTaskContext(taskUuid) {
        var task = taskUuid ? tasksById[taskUuid] : null;
        worksiteDisplay.value = task && task.worksiteId ? (worksitesById[task.worksiteId] || 'Unknown worksite') : 'Pulled from selected assessment';
        departmentDisplay.value = task && task.departmentId ? (departmentsById[task.departmentId] || 'Unknown department') : 'Pulled from selected assessment';
        jobRoleDisplay.value = task && task.jobRoleId ? (jobRolesById[task.jobRoleId] || 'Unknown job role') : 'Pulled from selected assessment';
    }

    function loadCatalogs() {
        return Promise.all([
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/tasks', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/assessments', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/worksites', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/departments', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/job-roles', { limit: 200 }),
            App.api.get('/api/v1/worker-feedback/questions')
        ]).then(function (responses) {
            var tasks = responses[0].ok && Array.isArray(responses[0].data) ? responses[0].data : [];
            var assessments = responses[1].ok && Array.isArray(responses[1].data) ? responses[1].data : [];
            var worksites = responses[2].ok && Array.isArray(responses[2].data) ? responses[2].data : [];
            var departments = responses[3].ok && Array.isArray(responses[3].data) ? responses[3].data : [];
            var jobRoles = responses[4].ok && Array.isArray(responses[4].data) ? responses[4].data : [];
            var bodyRegions = responses[5].ok && responses[5].data && Array.isArray(responses[5].data.bodyRegions) ? responses[5].data.bodyRegions : [];

            allAssessments = assessments;
            tasks.forEach(function (item) { tasksById[item.id] = item; });
            assessments.forEach(function (item) { assessmentsById[item.id || item.uuid] = item; });
            worksites.forEach(function (item) { worksitesById[item.id] = item.name || item.id; });
            departments.forEach(function (item) { departmentsById[item.id] = item.name || item.id; });
            jobRoles.forEach(function (item) { jobRolesById[item.id] = item.name || item.id; });

            renderAssessmentOptions('');
            setOptions(bodyRegionSelect, bodyRegions, 'Select body region', function (item) { return item.key || item; }, function (item) { return item.label || item.key || item; });
        });
    }

    assessmentSelect.addEventListener('change', function () {
        var assessment = assessmentsById[assessmentSelect.value] || null;
        if (assessment && assessment.taskUuid && tasksById[assessment.taskUuid]) {
            currentTaskUuid = assessment.taskUuid;
        } else {
            currentTaskUuid = '';
        }
        updateTaskContext(currentTaskUuid);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!assessmentSelect.value) {
            alert('danger', 'Select an assessment before submitting feedback.');
            return;
        }

        if (App.forms && App.forms.clearValidationErrors) {
            App.forms.clearValidationErrors(form);
        }

        var payload = {
            assessmentUuid: assessmentSelect.value || null,
            taskUuid: currentTaskUuid || null,
            bodyRegion: bodyRegionSelect.value || null,
            observedRiskLevel: form.elements.observedRiskLevel.value,
            observedIssueType: form.elements.observedIssueType.value,
            frequencyLevel: Number(form.elements.frequencyLevel.value || 0),
            severityLevel: Number(form.elements.severityLevel.value || 0),
            suggestedChange: form.elements.suggestedChange.value.trim() || null,
            notes: form.elements.notes.value.trim() || null
        };

        var finishSubmit = function () {
            App.ui.setButtonLoading(form.querySelector('button[type="submit"]'), false);
        };

        App.ui.setButtonLoading(form.querySelector('button[type="submit"]'), true, 'Submitting...');
        App.api.post('/api/v1/supervisor-feedback', payload).then(function (res) {
            if (!res.ok) {
                var rendered = showValidationErrors(res.errors || {});
                if (rendered.formErrors.length) {
                    alert('danger', rendered.formErrors.join(' '));
                } else if (!Object.keys(rendered.fieldErrors).length) {
                    alert('danger', res.message || 'Supervisor feedback submission failed.');
                }
                finishSubmit();
                return;
            }

            App.notify.success('Supervisor feedback submitted.');
            if (App.forms && App.forms.reset) {
                App.forms.reset(form);
            } else {
                form.reset();
            }
            currentTaskUuid = '';
            updateTaskContext('');
        }).catch(function (err) {
            alert('danger', (err && err.message) || 'Supervisor feedback submission failed.');
        }).then(finishSubmit, finishSubmit);
    });

    loadCatalogs().catch(function () {
        alert('danger', 'Reference data could not be loaded.');
    });
})();
