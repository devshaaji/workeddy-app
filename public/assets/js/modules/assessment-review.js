(function () {
    'use strict';

    var page = document.getElementById('assessmentReviewPage');
    if (!page || !window.App) {
        return;
    }

    var assessmentUuid = page.getAttribute('data-assessment-uuid');
    var orgMeta = document.querySelector('meta[name="org-uuid"]');
    var organizationUuid = page.getAttribute('data-organization-uuid') || (orgMeta ? orgMeta.content : '') || '';
    var endpoint = '/api/v1/organizations/' + encodeURIComponent(organizationUuid) + '/assessments/' + encodeURIComponent(assessmentUuid);
    var approveForm = document.getElementById('approveAssessmentForm');
    var flagForm = document.getElementById('flagAssessmentForm');

    function asText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '--';
        }

        return String(value);
    }

    function setText(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    }

    function setHtml(id, html) {
        var node = document.getElementById(id);
        if (node) {
            node.innerHTML = html;
        }
    }

    function renderMetrics(metrics) {
        var keys = Object.keys(metrics || {});
        if (keys.length === 0) {
            setHtml('reviewMetricsTable', '<tr><td colspan="2" class="text-muted">No stored metrics.</td></tr>');
            return;
        }

        setHtml('reviewMetricsTable', keys.map(function (key) {
            return '<tr><td>' + App.utils.escapeHtml(key) + '</td><td>' + App.utils.escapeHtml(asText(metrics[key], '--')) + '</td></tr>';
        }).join(''));
    }

    function renderRiskFactors(factors) {
        var html = (factors || []).length
            ? factors.map(function (factor) {
                return '<span class="badge bg-label-primary">' + App.utils.escapeHtml(factor.replace(/_/g, ' ')) + '</span>';
            }).join('')
            : '<span class="text-muted small">No risk factors captured.</span>';

        setHtml('reviewRiskFactors', html);
    }

    function renderAiAssistance(ai) {
        var payload = ai || {};
        var score = payload.score || {};
        var flags = payload.flags || {};
        var section = document.getElementById('reviewAiGuardrailSection');

        if (!payload.available) {
            if (section) {
                section.classList.add('d-none');
            }
            return;
        }

        if (section) {
            section.classList.remove('d-none');
        }

        var messageNode = document.getElementById('reviewAiMessage');
        if (messageNode) {
            messageNode.className = 'alert alert-warning d-flex gap-2 align-items-start mb-3';
            messageNode.innerHTML = '<i class="bi bi-shield-exclamation"></i><div>' + App.utils.escapeHtml(asText(payload.message, 'No AI assistance stored.')) + '</div>';
        }

        setText('reviewAiScore', asText(score.raw, '--'));
        setText('reviewAiRiskLevel', asText(score.riskLevel, 'Risk band unavailable'));
        setText('reviewAiConfidenceBand', asText(payload.confidenceBand, '--'));
        setText('reviewAiConfidenceValue', payload.confidence !== null && payload.confidence !== undefined ? 'Confidence ' + Number(payload.confidence).toFixed(2) : 'No confidence yet');
        setText('reviewAiModelVersion', asText(payload.modelVersion, '--'));
        setText('reviewAiWorker', 'Worker ' + asText(payload.createdByWorker, '--'));

        var flagKeys = Object.keys(flags).filter(function (key) { return !!flags[key]; });
        setHtml('reviewAiFlags', flagKeys.length
            ? flagKeys.map(function (key) { return '<span class="badge bg-label-warning">' + App.utils.escapeHtml(key.replace(/_/g, ' ')) + '</span>'; }).join('')
            : '<span class="text-muted small">No AI flags raised.</span>');
    }

    function closeModal(id) {
        var modalEl = document.getElementById(id);
        if (modalEl && window.bootstrap) {
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.hide();
        }
    }

    function load() {
        App.api.get(endpoint).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load assessment.', '#reviewAlert');
                return;
            }

            var data = res.data || {};
            var finalScore = data.finalScore || {};
            var initialScore = data.initialScore || {};
            var review = data.review || {};
            var locked = !!data.isLocked;
            var baselineBadge = document.getElementById('reviewBaselineBadge');
            var lockBadge = document.getElementById('reviewLockBadge');

            setText('reviewFinalScore', asText(finalScore.raw, '--'));
            setText('reviewRiskLevel', asText(finalScore.riskLevel, 'Risk level unavailable'));
            setText('reviewRawScore', asText(initialScore.raw, '--'));
            setText('reviewMethod', String((data.model || '--')).toUpperCase());
            setText('reviewStatusText', asText(data.status, '--').replace(/_/g, ' '));
            setText('reviewExistingNotes', asText(review.reviewerNotes, 'No existing reviewer notes.'));

            if (baselineBadge) {
                baselineBadge.classList.toggle('d-none', !data.isBaseline);
            }
            if (lockBadge) {
                lockBadge.classList.toggle('d-none', !locked);
            }

            renderRiskFactors(data.riskFactors || []);
            renderMetrics(data.metrics || {});
            renderAiAssistance(data.aiAssistance || {});

            if (locked) {
                approveForm.querySelectorAll('input, textarea, button').forEach(function (el) { el.disabled = true; });
                flagForm.querySelectorAll('input, textarea, button').forEach(function (el) { el.disabled = true; });
                document.querySelectorAll('.btn-de-action').forEach(function (el) { el.disabled = true; });
                App.ui.showAlert('warning', 'This assessment is locked and cannot be changed.', '#reviewAlert');
            }
        });
    }

    App.forms.bindAjaxForm(approveForm, {
        url: '/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/review/approve',
        method: 'POST',
        submitBtn: '#approveAssessmentBtn',
        beforeSend: function (form) {
            var adjusted = document.getElementById('adjustedScoreInput').value.trim();
            var reason = document.getElementById('adjustmentReasonInput').value.trim();
            if (adjusted !== '' && reason === '') {
                App.forms.showFormError(form, 'Adjusted scores require an adjustment reason.');
                return false;
            }

            return true;
        },
        onSuccess: function () {
            App.notify.success('Assessment approved.');
            closeModal('approveAssessmentModal');
            window.location.href = '/assessments/' + encodeURIComponent(assessmentUuid);
        },
        onError: function (res) {
            App.notify.error(res.message || 'Approval failed.');
        }
    });

    App.forms.bindAjaxForm(flagForm, {
        url: '/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/review/flag',
        method: 'POST',
        submitBtn: '#flagAssessmentBtn',
        onSuccess: function () {
            App.notify.success('Assessment flagged for rework.');
            closeModal('flagAssessmentModal');
            flagForm.reset();
            load();
        },
        onError: function (res) {
            App.notify.error(res.message || 'Flag action failed.');
        }
    });

    load();
})();
