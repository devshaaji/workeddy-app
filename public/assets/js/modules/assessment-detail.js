(function () {
    'use strict';

    var page = document.getElementById('assessmentDetailPage');
    if (!page || !window.App) {
        return;
    }

    var assessmentUuid = page.getAttribute('data-assessment-uuid');
    var endpoint = '/api/v1/assessments/' + encodeURIComponent(assessmentUuid);

    function asText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '--';
        }

        return String(value);
    }

    function statusBadgeClass(status) {
        switch (status) {
            case 'locked':
                return 'bg-label-warning';
            case 'reviewed':
                return 'bg-label-success';
            case 'pending_review':
                return 'bg-label-info';
            case 'flagged':
                return 'bg-label-danger';
            default:
                return 'bg-label-secondary';
        }
    }

    function boolState(value) {
        return value ? 'Yes' : 'No';
    }

    function setHtml(id, html) {
        var node = document.getElementById(id);
        if (node) {
            node.innerHTML = html;
        }
    }

    function setText(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    }

    function showAlert(level, message) {
        App.ui.showAlert(level, message, '#assessmentDetailAlert');
    }

    function renderMetrics(metrics) {
        var rows = Object.keys(metrics || {});
        if (rows.length === 0) {
            setHtml('assessmentMetricsTable', '<tr><td colspan="2" class="text-muted">No stored metrics.</td></tr>');
            return;
        }

        setHtml('assessmentMetricsTable', rows.map(function (key) {
            return '<tr><td>' + App.utils.escapeHtml(key) + '</td><td>' + App.utils.escapeHtml(asText(metrics[key], '--')) + '</td></tr>';
        }).join(''));
    }

    function renderRiskFactors(factors) {
        var html = (factors || []).length
            ? factors.map(function (factor) {
                return '<span class="badge bg-label-primary">' + App.utils.escapeHtml(factor.replace(/_/g, ' ')) + '</span>';
            }).join('')
            : '<span class="text-muted small">No risk factors captured.</span>';

        setHtml('assessmentRiskFactors', html);
    }

    function renderHeatmap(targetId, svg) {
        if (svg) {
            setHtml(targetId, svg);
            return;
        }

        setHtml(targetId, '<p class="text-muted small mb-0">No body-region evidence.</p>');
    }

    function renderAiAssistance(ai) {
        var payload = ai || {};
        var score = payload.score || {};
        var flags = payload.flags || {};
        var timeline = payload.timelinePreview || [];
        var messageClass = payload.available ? 'alert alert-warning d-flex gap-2 align-items-start mb-4' : 'alert alert-secondary d-flex gap-2 align-items-start mb-4';

        setHtml('assessmentAiMessage', '<i class="bi bi-shield-exclamation"></i><div>' + App.utils.escapeHtml(asText(payload.message, 'No AI assistance stored.')) + '</div>');
        var messageNode = document.getElementById('assessmentAiMessage');
        if (messageNode) {
            messageNode.className = messageClass;
        }

        setText('assessmentAiScore', payload.available ? asText(score.raw, '--') : '--');
        setText('assessmentAiRiskLevel', payload.available ? asText(score.riskLevel, 'Risk band unavailable') : 'No AI estimate');
        setText('assessmentAiConfidenceBand', payload.available ? asText(payload.confidenceBand, '--') : '--');
        setText('assessmentAiConfidenceValue', payload.available && payload.confidence !== null && payload.confidence !== undefined ? 'Confidence ' + Number(payload.confidence).toFixed(2) : 'No confidence yet');
        setText('assessmentAiModelVersion', payload.available ? asText(payload.modelVersion, '--') : '--');
        setText('assessmentAiWorker', payload.available ? ('Worker ' + asText(payload.createdByWorker, '--')) : 'Worker unavailable');

        var flagKeys = Object.keys(flags).filter(function (key) { return !!flags[key]; });
        setHtml('assessmentAiFlags', flagKeys.length
            ? flagKeys.map(function (key) { return '<span class="badge bg-label-warning">' + App.utils.escapeHtml(key.replace(/_/g, ' ')) + '</span>'; }).join('')
            : '<span class="text-muted small">No AI flags raised.</span>');

        setHtml('assessmentAiTimeline', timeline.length
            ? timeline.map(function (entry) {
                var keys = Object.keys(entry || {}).filter(function (key) { return key !== 'time_seconds'; });
                var signal = keys.length ? (keys[0] + ': ' + asText(entry[keys[0]], '--')) : 'No signal';
                return '<tr><td>' + App.utils.escapeHtml(asText(entry.time_seconds, '--')) + 's</td><td>' + App.utils.escapeHtml(signal) + '</td></tr>';
            }).join('')
            : '<tr><td colspan="2" class="text-muted">No stored AI timeline preview.</td></tr>');
    }

    function configureActionButton(id, allowed, handler) {
        var button = document.getElementById(id);
        if (!button) {
            return;
        }

        button.disabled = !allowed;
        button.classList.toggle('d-none', !allowed);
        if (allowed) {
            button.onclick = handler;
        }
    }

    function configureComparisonLink(allowed, uuid) {
        var link = document.getElementById('generateComparisonLink');
        if (!link) {
            return;
        }

        link.classList.toggle('d-none', !allowed);
        if (allowed && uuid) {
            link.href = '/assessments/comparisons/new?baseline=' + encodeURIComponent(uuid);
        }
    }

    function load() {
        App.api.get(endpoint).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Failed to load assessment.');
                return;
            }

            var data = res.data || {};
            var finalScore = data.finalScore || {};
            var initialScore = data.initialScore || {};
            var review = data.review || {};
            var actions = data.actions || {};
            var statusBadge = document.getElementById('assessmentStatusBadge');

            setText('assessmentFinalScore', asText(finalScore.raw, '--'));
            setText('assessmentRiskLevel', asText(finalScore.riskLevel, 'Risk level unavailable'));
            setText('assessmentRawScore', asText(initialScore.raw, '--'));
            setText('assessmentMethod', String((data.model || '--')).toUpperCase());
            setText('assessmentReviewState', asText(data.status, '--').replace(/_/g, ' '));
            setText('assessmentReviewerName', asText(review.reviewerName, 'Pending review'));
            setText('assessmentScoreSource', asText(data.scoreSource, 'manual').replace(/_/g, ' '));
            setText('assessmentUuidValue', asText(data.uuid, '--'));
            setText('assessmentTaskUuid', asText(data.taskUuid, '--'));
            setText('assessmentCreatedAt', data.createdAt ? App.utils.formatDate(data.createdAt) : '--');
            setText('assessmentReviewerNotes', asText(review.reviewerNotes, 'No reviewer notes yet.'));

            if (statusBadge) {
                statusBadge.className = 'badge ' + statusBadgeClass(data.status);
                statusBadge.textContent = asText(data.status, '--').replace(/_/g, ' ');
            }

            var baselineBadge = document.getElementById('assessmentBaselineBadge');
            var lockBadge = document.getElementById('assessmentLockBadge');
            if (baselineBadge) {
                baselineBadge.classList.toggle('d-none', !data.isBaseline);
            }
            if (lockBadge) {
                lockBadge.classList.toggle('d-none', !data.isLocked);
            }

            setText('canEditState', boolState(actions.canEdit));
            setText('canReviewState', boolState(actions.canReview));
            setText('canFlagState', boolState(actions.canFlag));
            setText('canBaselineState', boolState(actions.canMarkBaseline));
            configureComparisonLink(!!actions.canGenerateComparison, data.uuid || assessmentUuid);

            renderRiskFactors(data.riskFactors || []);
            renderMetrics(data.metrics || {});
            renderHeatmap('assessmentHeatmapFront', data.bodyRegionHeatmap && data.bodyRegionHeatmap.frontSvg);
            renderHeatmap('assessmentHeatmapBack', data.bodyRegionHeatmap && data.bodyRegionHeatmap.backSvg);
            renderAiAssistance(data.aiAssistance || {});

            configureActionButton('submitForReviewBtn', data.status === 'draft', function () {
                App.api.post('/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/submit', {}).then(function (submitRes) {
                    if (!submitRes.ok) {
                        showAlert('danger', submitRes.message || 'Unable to submit assessment.');
                        return;
                    }

                    App.notify.success('Assessment submitted for review.');
                    load();
                });
            });

            configureActionButton('markBaselineBtn', !!actions.canMarkBaseline, function () {
                App.api.post('/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/baseline', {}).then(function (baselineRes) {
                    if (!baselineRes.ok) {
                        showAlert('danger', baselineRes.message || 'Unable to mark baseline.');
                        return;
                    }

                    App.notify.success('Assessment baseline saved.');
                    load();
                });
            });
        });
    }

    load();
})();
