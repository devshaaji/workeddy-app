(function () {
    'use strict';

    var page = document.getElementById('comparisonCreatePage');
    if (!page || !window.App) {
        return;
    }

    var form = document.getElementById('comparisonCreateForm');
    var baselineInput = document.getElementById('baselineAssessmentUuid');
    var followUpInput = document.getElementById('followUpAssessmentUuid');
    var actionInput = document.getElementById('correctiveActionUuid');
    var previewBtn = document.getElementById('previewAssessmentsBtn');
    var submitBtn = document.getElementById('generateComparisonBtn');

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

    function previewCard(data) {
        var score = data.finalScore || data.initialScore || {};
        var bodyRegions = Array.isArray(data.bodyRegions) ? data.bodyRegions.slice(0, 4) : [];

        return '<dl class="row mb-3">' +
            '<dt class="col-5 text-muted">UUID</dt><dd class="col-7 text-break">' + escape(data.uuid) + '</dd>' +
            '<dt class="col-5 text-muted">Status</dt><dd class="col-7">' + escape(asText(data.status, '--').replace(/_/g, ' ')) + '</dd>' +
            '<dt class="col-5 text-muted">Model</dt><dd class="col-7 text-uppercase">' + escape(data.model) + '</dd>' +
            '<dt class="col-5 text-muted">Score</dt><dd class="col-7">' + escape(asText(score.raw, score.raw_score || '--')) + '</dd>' +
            '<dt class="col-5 text-muted">Risk</dt><dd class="col-7">' + escape(asText(score.riskLevel, score.risk_level || '--')) + '</dd>' +
            '</dl>' +
            (bodyRegions.length ? '<div><span class="text-muted small d-block mb-2">Top body regions</span>' + bodyRegions.map(function (region) {
                return '<span class="badge bg-label-secondary me-1 mb-1">' + escape(region.region) + ' ' + escape(region.side) + ' (' + escape(region.intensity) + ')</span>';
            }).join('') + '</div>' : '<p class="text-muted small mb-0">No body region evidence stored.</p>');
    }

    function loadAssessment(uuid) {
        return App.api.get('/api/v1/assessments/' + encodeURIComponent(uuid));
    }

    function preview() {
        clearAlert();
        var baselineUuid = baselineInput.value.trim();
        var followUpUuid = followUpInput.value.trim();

        if (!baselineUuid || !followUpUuid) {
            showAlert('warning', 'Baseline and follow-up assessment UUIDs are required for preview.');
            return;
        }

        App.ui.setButtonLoading(previewBtn, true, 'Loading...');
        Promise.all([loadAssessment(baselineUuid), loadAssessment(followUpUuid)]).then(function (results) {
            App.ui.setButtonLoading(previewBtn, false);
            var baselineRes = results[0];
            var followUpRes = results[1];

            if (!baselineRes.ok || !followUpRes.ok) {
                showAlert('danger', baselineRes.message || followUpRes.message || 'Failed to preview assessments.');
                return;
            }

            var baseline = baselineRes.data || {};
            var followUp = followUpRes.data || {};
            document.getElementById('baselinePreviewPanel').innerHTML = previewCard(baseline);
            document.getElementById('followUpPreviewPanel').innerHTML = previewCard(followUp);
            document.getElementById('comparisonPreviewModel').textContent = String((baseline.model || followUp.model || '--')).toUpperCase();
        }).catch(function () {
            App.ui.setButtonLoading(previewBtn, false);
            showAlert('danger', 'Failed to preview assessments.');
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearAlert();
        if (App.forms && App.forms.clearValidationErrors) {
            App.forms.clearValidationErrors(form);
        }

        var payload = {
            baselineAssessmentUuid: baselineInput.value.trim(),
            followUpAssessmentUuid: followUpInput.value.trim(),
        };

        if (actionInput.value.trim()) {
            payload.correctiveActionUuid = actionInput.value.trim();
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
        });
    });

    if (previewBtn) {
        previewBtn.addEventListener('click', preview);
    }

    if (page.dataset.prefillBaseline && page.dataset.prefillFollowUp) {
        preview();
    }
})();
