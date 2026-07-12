(function () {
    'use strict';

    var page = document.getElementById('workerVoiceSubmitPage');
    if (!page || !window.App) {
        return;
    }

    var questionGrid = document.getElementById('workerVoiceQuestionGrid');
    var form = document.getElementById('workerVoiceSubmitForm');
    var submitBtn = document.getElementById('workerVoiceSubmitBtn');
    var orgUuid = page.dataset.organizationUuid || '';
    var assessmentSelect = document.getElementById('workerVoiceAssessmentSelect');
    var worksiteDisplay = document.getElementById('workerVoiceWorksiteDisplay');
    var departmentDisplay = document.getElementById('workerVoiceDepartmentDisplay');
    var jobRoleDisplay = document.getElementById('workerVoiceJobRoleDisplay');
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

    function showAlert(level, message) {
        App.ui.showAlert(level, message, '#workerVoiceSubmitAlert');
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

    function setOptions(select, items, emptyLabel, valuePicker, labelPicker) {
        select.innerHTML = '<option value="">' + escape(emptyLabel) + '</option>' + items.map(function (item) {
            return '<option value="' + escape(valuePicker(item)) + '">' + escape(labelPicker(item)) + '</option>';
        }).join('');
    }

    function updateTaskContext(taskUuid) {
        var task = taskUuid ? tasksById[taskUuid] : null;
        worksiteDisplay.value = task && task.worksiteId ? (worksitesById[task.worksiteId] || 'Unknown worksite') : 'Pulled from selected assessment';
        departmentDisplay.value = task && task.departmentId ? (departmentsById[task.departmentId] || 'Unknown department') : 'Pulled from selected assessment';
        jobRoleDisplay.value = task && task.jobRoleId ? (jobRolesById[task.jobRoleId] || 'Unknown job role') : 'Pulled from selected assessment';
    }

    function renderCatalog(data) {
        var bodyRegion = document.getElementById('workerVoiceBodyRegion');
        bodyRegion.innerHTML = '<option value="">Select body region</option>' + (data.bodyRegions || []).map(function (region) {
            return '<option value="' + escape(region.key) + '">' + escape(region.label) + '</option>';
        }).join('');

        questionGrid.innerHTML = (data.questions || []).filter(function (question) {
            return question.key !== 'suggestedChange';
        }).map(function (question) {
            if (question.type === 'boolean') {
                return '';
            }
            return '<div class="col-md-6">' +
                '<label class="form-label" for="question_' + escape(question.key) + '">' + escape(question.label) + '</label>' +
                '<input type="range" class="form-range" min="' + escape(question.scaleMin || 0) + '" max="' + escape(question.scaleMax || 5) + '" value="0" id="question_' + escape(question.key) + '" name="' + escape(question.key) + '">' +
                '<div class="small text-muted"><span id="question_value_' + escape(question.key) + '">0</span> / ' + escape(question.scaleMax || 5) + '</div>' +
            '</div>';
        }).join('');

        Array.prototype.forEach.call(questionGrid.querySelectorAll('input[type="range"]'), function (input) {
            input.addEventListener('input', function () {
                var target = document.getElementById('question_value_' + input.name);
                if (target) {
                    target.textContent = input.value;
                }
            });
        });
    }

    function applyPrefill() {
        if (page.dataset.prefillAssessment && assessmentsById[page.dataset.prefillAssessment]) {
            var assessment = assessmentsById[page.dataset.prefillAssessment];
            if (assessment && assessment.taskUuid && tasksById[assessment.taskUuid]) {
                currentTaskUuid = assessment.taskUuid;
            }
            renderAssessmentOptions(page.dataset.prefillAssessment);
            assessmentSelect.value = page.dataset.prefillAssessment;
        }

        if (!currentTaskUuid && page.dataset.prefillTask && tasksById[page.dataset.prefillTask]) {
            currentTaskUuid = page.dataset.prefillTask;
        }

        renderAssessmentOptions(assessmentSelect.value || '');
        updateTaskContext(currentTaskUuid);
    }

    function loadReferenceData() {
        return Promise.all([
            App.api.get('/api/v1/worker-feedback/questions'),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/tasks', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/assessments', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/worksites', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/departments', { limit: 200 }),
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/job-roles', { limit: 200 })
        ]).then(function (responses) {
            if (!responses[0].ok) {
                showAlert('danger', responses[0].message || 'Failed to load worker voice question catalog.');
                return;
            }

            renderCatalog(responses[0].data || {});

            var tasks = responses[1].ok && Array.isArray(responses[1].data) ? responses[1].data : [];
            var assessments = responses[2].ok && Array.isArray(responses[2].data) ? responses[2].data : [];
            var worksites = responses[3].ok && Array.isArray(responses[3].data) ? responses[3].data : [];
            var departments = responses[4].ok && Array.isArray(responses[4].data) ? responses[4].data : [];
            var jobRoles = responses[5].ok && Array.isArray(responses[5].data) ? responses[5].data : [];

            allAssessments = assessments;
            tasks.forEach(function (item) { tasksById[item.id] = item; });
            assessments.forEach(function (item) { assessmentsById[item.id || item.uuid] = item; });
            worksites.forEach(function (item) { worksitesById[item.id] = item.name || item.id; });
            departments.forEach(function (item) { departmentsById[item.id] = item.name || item.id; });
            jobRoles.forEach(function (item) { jobRolesById[item.id] = item.name || item.id; });

            renderAssessmentOptions('');

            applyPrefill();
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
            showAlert('danger', 'Select an assessment before submitting feedback.');
            return;
        }

        if (App.forms && App.forms.clearValidationErrors) {
            App.forms.clearValidationErrors(form);
        }

        var payload = {
            taskUuid: currentTaskUuid || null,
            assessmentUuid: assessmentSelect.value || null,
            bodyRegion: document.getElementById('workerVoiceBodyRegion').value,
            anonymousStatus: document.getElementById('workerVoiceAnonymous').checked,
            hasDiscomfort: document.getElementById('workerVoiceHasDiscomfort').checked,
            discomfortLevel: Number(document.getElementById('question_discomfortLevel').value || 0),
            frequencyLevel: Number(document.getElementById('question_frequencyLevel').value || 0),
            difficultyLevel: Number(document.getElementById('question_difficultyLevel').value || 0),
            reportingComfortLevel: Number(document.getElementById('question_reportingComfortLevel').value || 0),
            pain7DayLevel: Number(document.getElementById('question_pain7DayLevel').value || 0),
            pain30DayLevel: Number(document.getElementById('question_pain30DayLevel').value || 0),
            suggestedChange: document.getElementById('workerVoiceSuggestedChange').value.trim() || null
        };

        var finishSubmit = function () {
            App.ui.setButtonLoading(submitBtn, false);
        };

        App.ui.setButtonLoading(submitBtn, true, 'Submitting...');
        App.api.post('/api/v1/worker-feedback', payload).then(function (res) {
            if (!res.ok) {
                var rendered = showValidationErrors(res.errors || {});
                if (rendered.formErrors.length) {
                    showAlert('danger', rendered.formErrors.join(' '));
                } else if (!Object.keys(rendered.fieldErrors).length) {
                    showAlert('danger', res.message || 'Worker feedback submission failed.');
                }
                finishSubmit();
                return;
            }

            App.notify.success('Worker feedback submitted.');
            if (App.forms && App.forms.reset) {
                App.forms.reset(form);
            } else {
                form.reset();
            }
            window.location.href = '/worker-voice/' + encodeURIComponent(res.data.uuid);
        }).catch(function (err) {
            showAlert('danger', (err && err.message) || 'Worker feedback submission failed.');
        }).then(finishSubmit, finishSubmit);
    });

    loadReferenceData().catch(function () {
        showAlert('danger', 'Reference data could not be loaded for this form.');
    });
})();
