(function () {
    'use strict';

    var page = document.getElementById('comparisonCreatePage');
    if (!page || !window.App) {
        return;
    }

    var App = window.App;
    var form = document.getElementById('comparisonCreateForm');
    var baselineInput = document.getElementById('baselineAssessmentUuid');
    var followUpInput = document.getElementById('followUpAssessmentUuid');
    var actionInput = document.getElementById('correctiveActionUuid');
    var previewBtn = document.getElementById('previewAssessmentsBtn');
    var submitBtn = document.getElementById('generateComparisonBtn');
    var eligibilityPanel = document.getElementById('comparisonEligibilityPanel');
    var baselinePanel = document.getElementById('baselinePreviewPanel');
    var followUpPanel = document.getElementById('followUpPreviewPanel');
    var previewModel = document.getElementById('comparisonPreviewModel');
    var organizationUuid = page.dataset.organizationUuid || '';
    var state = {
        assessments: [],
        correctiveActions: [],
        assessmentsByUuid: {},
        actionsByUuid: {},
        loaded: false,
        prefills: {
            baseline: page.dataset.prefillBaseline || '',
            followUp: page.dataset.prefillFollowUp || '',
            action: page.dataset.prefillAction || ''
        }
    };

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function showAlert(level, message) {
        App.ui.showAlert(level, message, '#comparisonCreateAlert');
    }

    function clearAlert() {
        App.ui.clearAlert('#comparisonCreateAlert');
    }

    function showValidationErrors(errors) {
        if (!form || !errors || !App.forms || !App.forms.showValidationErrors) {
            return { fieldErrors: {}, formErrors: [] };
        }

        return App.forms.showValidationErrors(form, errors);
    }

    function asText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '--';
        }

        return String(value);
    }

    function formatDate(value) {
        if (!value) {
            return '--';
        }

        return App.utils.formatDate(value);
    }

    function scoreData(assessment) {
        return assessment.finalScore || assessment.initialScore || {};
    }

    function riskText(assessment) {
        var score = scoreData(assessment);
        return score.riskLevel || score.risk_level || '--';
    }

    function rawScore(assessment) {
        var score = scoreData(assessment);
        return score.raw || score.raw_score || score.score || '--';
    }

    function bodyRegionList(assessment) {
        if (Array.isArray(assessment.bodyRegions) && assessment.bodyRegions.length) {
            return assessment.bodyRegions;
        }

        if (Array.isArray(assessment.riskFactors) && assessment.riskFactors.length) {
            return assessment.riskFactors;
        }

        return [];
    }

    function normalizeText(value) {
        return String(value || '').toLowerCase();
    }

    function optionLabelForAssessment(assessment) {
        var parts = [
            String(assessment.model || '').toUpperCase(),
            String(assessment.status || '').replace(/_/g, ' '),
            'Risk ' + riskText(assessment),
            'Score ' + rawScore(assessment),
            formatDate(assessment.createdAt)
        ].filter(Boolean);

        return parts.join(' | ') + ' | ' + asText(assessment.taskUuid, 'Task unavailable');
    }

    function searchTextForAssessment(assessment) {
        return normalizeText([
            assessment.uuid,
            assessment.taskUuid,
            assessment.model,
            assessment.status,
            riskText(assessment),
            rawScore(assessment),
            assessment.createdAt
        ].join(' '));
    }

    function optionLabelForAction(action) {
        var parts = [
            asText(action.title, 'Untitled action'),
            String(action.status || '').replace(/_/g, ' '),
            action.priority ? 'Priority ' + action.priority : '',
            action.dueDate ? 'Due ' + formatDate(action.dueDate) : ''
        ].filter(Boolean);

        return parts.join(' | ');
    }

    function searchTextForAction(action) {
        return normalizeText([
            action.uuid,
            action.title,
            action.reason,
            action.description,
            action.status,
            action.priority,
            action.assessmentUuid,
            action.dueDate
        ].join(' '));
    }

    function isFollowUpCandidate(assessment) {
        return assessment && ['reviewed', 'locked'].indexOf(String(assessment.status || '')) !== -1;
    }

    function isBaselineReady(assessment) {
        return !!(assessment && assessment.isBaseline && assessment.isLocked);
    }

    function eligibilityChecks(baseline, followUp) {
        var checks = [];
        checks.push({
            ok: !!baseline,
            text: 'A before assessment is selected.'
        });
        checks.push({
            ok: !!followUp,
            text: 'An after assessment is selected.'
        });
        checks.push({
            ok: !baseline || isBaselineReady(baseline),
            text: 'Before assessment is marked as baseline and locked.'
        });
        checks.push({
            ok: !followUp || isFollowUpCandidate(followUp),
            text: 'After assessment is already reviewed or locked.'
        });
        checks.push({
            ok: !baseline || !followUp || baseline.uuid !== followUp.uuid,
            text: 'Before and after assessments are different records.'
        });
        checks.push({
            ok: !baseline || !followUp || String(baseline.model || '') === String(followUp.model || ''),
            text: 'Both assessments use the same scoring model.'
        });

        return checks;
    }

    function previewCard(data, roleLabel) {
        if (!data) {
            return '<p class="text-muted small mb-0">No ' + escape(roleLabel) + ' assessment selected.</p>';
        }

        var bodyRegions = bodyRegionList(data).slice(0, 4);
        var flags = [];
        if (data.isBaseline) {
            flags.push('<span class="badge bg-label-warning me-1 mb-1">Marked baseline</span>');
        }
        if (data.isLocked) {
            flags.push('<span class="badge bg-label-success me-1 mb-1">Locked</span>');
        }
        if (data.status) {
            flags.push('<span class="badge bg-label-info me-1 mb-1">' + escape(String(data.status).replace(/_/g, ' ')) + '</span>');
        }

        return '<dl class="row mb-3">' +
            '<dt class="col-5 text-muted">Assessment stage</dt><dd class="col-7">' + escape(asText(String(data.status || '').replace(/_/g, ' '), '--')) + '</dd>' +
            '<dt class="col-5 text-muted">Model</dt><dd class="col-7 text-uppercase">' + escape(asText(data.model, '--')) + '</dd>' +
            '<dt class="col-5 text-muted">Score</dt><dd class="col-7">' + escape(asText(rawScore(data), '--')) + '</dd>' +
            '<dt class="col-5 text-muted">Risk</dt><dd class="col-7">' + escape(asText(riskText(data), '--')) + '</dd>' +
            '<dt class="col-5 text-muted">Created</dt><dd class="col-7">' + escape(formatDate(data.createdAt)) + '</dd>' +
            '</dl>' +
            (flags.length ? '<div class="mb-3">' + flags.join('') + '</div>' : '') +
            (bodyRegions.length
                ? '<div><span class="text-muted small d-block mb-2">Evidence summary</span>' + bodyRegions.map(function (region) {
                    return '<span class="badge bg-label-secondary me-1 mb-1">' + escape(region.region || region) + (region.side ? ' ' + escape(region.side) : '') + (region.intensity ? ' (' + escape(region.intensity) + ')' : '') + '</span>';
                }).join('') + '</div>'
                : '<p class="text-muted small mb-0">No body-region evidence stored.</p>');
    }

    function getSelectedAssessment(input) {
        return state.assessmentsByUuid[input.value] || null;
    }

    function renderAssessmentSelect(select, records, selectedValue, emptyText) {
        if (!records.length) {
            select.innerHTML = '<option value="">' + escape(emptyText) + '</option>';
            return;
        }

        select.innerHTML = records.map(function (assessment) {
            return '<option value="' + escape(assessment.uuid) + '"' + (assessment.uuid === selectedValue ? ' selected' : '') + '>' +
                escape(optionLabelForAssessment(assessment)) +
                '</option>';
        }).join('');
    }

    function renderActionSelect(records, selectedValue) {
        var options = ['<option value="">No linked corrective action</option>'];
        records.forEach(function (action) {
            options.push('<option value="' + escape(action.uuid) + '"' + (action.uuid === selectedValue ? ' selected' : '') + '>' +
                escape(optionLabelForAction(action)) +
                '</option>');
        });
        actionInput.innerHTML = options.join('');
    }

    function filteredBaselineRecords() {
        return state.assessments.filter(function (assessment) {
            return isBaselineReady(assessment);
        }).sort(function (left, right) {
            return String(right.createdAt || '').localeCompare(String(left.createdAt || ''));
        });
    }

    function filteredFollowUpRecords() {
        var baseline = getSelectedAssessment(baselineInput);
        var baselineUuid = baselineInput.value;

        return state.assessments.filter(function (assessment) {
            if (!isFollowUpCandidate(assessment)) {
                return false;
            }
            if (baselineUuid && assessment.uuid === baselineUuid) {
                return false;
            }
            if (baseline && String(assessment.model || '') !== String(baseline.model || '')) {
                return false;
            }

            return true;
        });
    }

    function filteredActionRecords() {
        var relatedAssessmentUuids = {};
        if (baselineInput.value) {
            relatedAssessmentUuids[baselineInput.value] = true;
        }
        if (followUpInput.value) {
            relatedAssessmentUuids[followUpInput.value] = true;
        }

        return state.correctiveActions.filter(function (action) {
            if (!Object.keys(relatedAssessmentUuids).length) {
                return true;
            }

            if (action.assessmentUuid && relatedAssessmentUuids[action.assessmentUuid]) {
                return true;
            }

            return true;
        }).sort(function (left, right) {
            var leftLinked = left.assessmentUuid && relatedAssessmentUuids[left.assessmentUuid] ? 1 : 0;
            var rightLinked = right.assessmentUuid && relatedAssessmentUuids[right.assessmentUuid] ? 1 : 0;
            return rightLinked - leftLinked;
        });
    }

    function syncPreview() {
        var baseline = getSelectedAssessment(baselineInput);
        var followUp = getSelectedAssessment(followUpInput);
        var checks = eligibilityChecks(baseline, followUp);
        var hasRequiredSelections = !!baseline && !!followUp;

        baselinePanel.innerHTML = previewCard(baseline, 'before');
        followUpPanel.innerHTML = previewCard(followUp, 'after');
        previewModel.textContent = String((baseline && baseline.model) || (followUp && followUp.model) || '--').toUpperCase();
        eligibilityPanel.innerHTML = '<ul class="list-unstyled mb-0">' + checks.map(function (item) {
            return '<li class="d-flex gap-2 mb-2">' +
                '<i class="bi ' + (item.ok ? 'bi-check-circle text-success' : 'bi-exclamation-circle text-warning') + '"></i>' +
                '<span>' + escape(item.text) + '</span>' +
                '</li>';
        }).join('') +
        '<li class="d-flex gap-2 ' + (checks.length ? '' : 'mb-2') + '">' +
            '<i class="bi bi-info-circle text-info"></i>' +
            '<span>Risk reduction is evidence of change, not a guarantee of outcome.</span>' +
        '</li></ul>';

        submitBtn.disabled = !hasRequiredSelections;
    }

    function refreshSelectors() {
        var selectedBaseline = baselineInput.value || state.prefills.baseline || '';
        var selectedFollowUp = followUpInput.value || state.prefills.followUp || '';
        var selectedAction = actionInput.value || state.prefills.action || '';

        renderAssessmentSelect(
            baselineInput,
            filteredBaselineRecords(),
            selectedBaseline,
            'No locked baseline assessments are available yet.'
        );
        renderAssessmentSelect(
            followUpInput,
            filteredFollowUpRecords(),
            selectedFollowUp,
            baselineInput.value
                ? 'No reviewed follow-up assessments match the selected baseline model.'
                : 'Select a locked baseline first.'
        );
        renderActionSelect(filteredActionRecords(), selectedAction);

        if (selectedBaseline && !state.assessmentsByUuid[selectedBaseline]) {
            baselineInput.value = '';
        }
        if (selectedFollowUp && !state.assessmentsByUuid[selectedFollowUp]) {
            followUpInput.value = '';
        }
        if (selectedAction && !state.actionsByUuid[selectedAction]) {
            actionInput.value = '';
        }

        syncPreview();
        state.prefills.baseline = '';
        state.prefills.followUp = '';
        state.prefills.action = '';
    }

    function loadAssessments() {
        var endpoint = organizationUuid
            ? '/api/v1/organizations/' + encodeURIComponent(organizationUuid) + '/assessments'
            : '/api/v1/assessments';

        return App.api.get(endpoint, { limit: 200 }).then(function (res) {
            if (!res.ok) {
                throw new Error(res.message || 'Failed to load assessments.');
            }

            state.assessments = Array.isArray(res.data) ? res.data : [];
            state.assessmentsByUuid = {};
            state.assessments.forEach(function (assessment) {
                if (assessment && assessment.uuid) {
                    state.assessmentsByUuid[assessment.uuid] = assessment;
                }
            });
        });
    }

    function loadCorrectiveActions() {
        return App.api.get('/api/v1/corrective-actions').then(function (res) {
            if (!res.ok) {
                throw new Error(res.message || 'Failed to load corrective actions.');
            }

            state.correctiveActions = Array.isArray(res.data) ? res.data : [];
            state.actionsByUuid = {};
            state.correctiveActions.forEach(function (action) {
                if (action && action.uuid) {
                    state.actionsByUuid[action.uuid] = action;
                }
            });
        });
    }

    function loadPageData() {
        clearAlert();
        App.ui.setButtonLoading(previewBtn, true, 'Loading...');

        Promise.all([loadAssessments(), loadCorrectiveActions()]).then(function () {
            state.loaded = true;
            App.ui.setButtonLoading(previewBtn, false);
            refreshSelectors();
        }).catch(function (error) {
            App.ui.setButtonLoading(previewBtn, false);
            showAlert('danger', error && error.message ? error.message : 'Failed to load comparison inputs.');
            baselineInput.innerHTML = '<option value="">Assessments unavailable</option>';
            followUpInput.innerHTML = '<option value="">Assessments unavailable</option>';
            actionInput.innerHTML = '<option value="">Corrective actions unavailable</option>';
            submitBtn.disabled = true;
        });
    }

    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        Array.prototype.slice.call(page.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach(function (node) {
            window.bootstrap.Tooltip.getOrCreateInstance(node);
        });
    }

    function preview() {
        clearAlert();
        syncPreview();

        var baseline = getSelectedAssessment(baselineInput);
        var followUp = getSelectedAssessment(followUpInput);
        if (!baseline || !followUp) {
            showAlert('warning', !baseline
                ? 'Select a locked baseline assessment before previewing the comparison.'
                : 'Select a reviewed follow-up assessment before previewing the comparison.');
            return;
        }

        var failedChecks = eligibilityChecks(baseline, followUp).filter(function (item) {
            return !item.ok;
        });
        if (failedChecks.length) {
            showAlert('warning', failedChecks[0].text);
            return;
        }

        App.notify.info('Comparison inputs are ready for generation.');
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearAlert();

        if (App.forms && App.forms.clearValidationErrors) {
            App.forms.clearValidationErrors(form);
        }

        var baseline = getSelectedAssessment(baselineInput);
        var followUp = getSelectedAssessment(followUpInput);
        var checks = eligibilityChecks(baseline, followUp).filter(function (item) {
            return !item.ok;
        });

        if (checks.length) {
            showAlert('warning', checks[0].text);
            return;
        }

        var payload = {
            baselineAssessmentUuid: baselineInput.value,
            followUpAssessmentUuid: followUpInput.value
        };

        if (actionInput.value) {
            payload.correctiveActionUuid = actionInput.value;
        }

        App.ui.setButtonLoading(submitBtn, true, 'Generating...');
        App.api.post('/api/v1/comparison-reports', payload).then(function (res) {
            App.ui.setButtonLoading(submitBtn, false);
            if (!res.ok) {
                var rendered = showValidationErrors(res.errors || {});
                if (rendered.formErrors.length) {
                    showAlert('danger', rendered.formErrors.join(' '));
                } else if (!Object.keys(rendered.fieldErrors).length) {
                    showAlert('danger', res.message || 'Comparison report generation failed.');
                }
                return;
            }

            App.notify.success('Comparison report generated.');
            window.location.href = '/assessments/comparisons/' + encodeURIComponent(res.data.uuid);
        }).catch(function () {
            App.ui.setButtonLoading(submitBtn, false);
            showAlert('danger', 'Comparison report generation failed.');
        });
    });

    [baselineInput, followUpInput, actionInput].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('change', refreshSelectors);
    });

    if (previewBtn) {
        previewBtn.addEventListener('click', preview);
    }

    initTooltips();
    loadPageData();
})();
