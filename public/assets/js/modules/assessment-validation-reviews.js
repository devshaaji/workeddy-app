(function () {
    'use strict';

    var page = document.getElementById('assessmentValidationReviewsPage');
    if (!page || !window.App) {
        return;
    }

    var form = document.getElementById('validationReviewForm');
    var table = document.getElementById('validationReviewTable');
    var assessmentApi = page.getAttribute('data-assessment-api') || '';
    var reviewsApi = page.getAttribute('data-reviews-api') || '';
    var orgUuid = '';
    var currentAssessment = null;

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function setText(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    }

    function percentText(value) {
        if (value === null || value === undefined || value === '') {
            return '--';
        }

        return Number(value).toFixed(1) + '%';
    }

    function renderChoices(targetId, items, name, valuePicker, labelPicker) {
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            target.innerHTML = '<span class="text-muted">No options available.</span>';
            return;
        }

        target.innerHTML = items.map(function (item, index) {
            var value = valuePicker(item);
            var label = labelPicker(item);
            var id = targetId + '_' + index;
            return '<div class="form-check">' +
                '<input class="form-check-input" type="checkbox" id="' + escape(id) + '" name="' + escape(name) + '" value="' + escape(value) + '">' +
                '<label class="form-check-label" for="' + escape(id) + '">' + escape(label) + '</label>' +
            '</div>';
        }).join('');
    }

    function checkedValues(name) {
        return Array.prototype.slice.call(form.querySelectorAll('input[name="' + name + '"]:checked')).map(function (input) {
            return input.value;
        });
    }

    function showValidationErrors(errors) {
        if (!form || !errors || !App.forms || !App.forms.showValidationErrors) {
            return { fieldErrors: {}, formErrors: [] };
        }

        return App.forms.showValidationErrors(form, errors);
    }

    function loadAssessment() {
        return App.api.get(assessmentApi).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load assessment.', '#validationReviewAlert');
                return;
            }

            currentAssessment = res.data || {};
            orgUuid = currentAssessment.organizationUuid || '';

            setText('validationAssessmentTitle', (currentAssessment.model || 'Assessment').toUpperCase() + ' Assessment');
            setText('validationAssessmentMeta', 'This assessment was generated using the ' + String(currentAssessment.model || '--').toUpperCase() + ' algorithm and is currently in the ' + String(currentAssessment.status || 'draft').replace(/_/g, ' ') + ' workflow state.');

            var statusEl = document.getElementById('validationAssessmentStatus');
            if (statusEl) {
                var statusText = String(currentAssessment.status || 'draft').replace(/_/g, ' ');
                statusEl.textContent = statusText.toUpperCase();
                statusEl.className = 'badge';
                if (currentAssessment.status === 'locked' || currentAssessment.status === 'reviewed') {
                    statusEl.classList.add('bg-success');
                } else if (currentAssessment.status === 'flagged') {
                    statusEl.classList.add('bg-danger');
                } else {
                    statusEl.classList.add('bg-warning');
                }
            }

            var riskText = currentAssessment.finalScore && currentAssessment.finalScore.riskLevel 
                ? currentAssessment.finalScore.riskLevel 
                : (currentAssessment.initialScore && currentAssessment.initialScore.riskLevel ? currentAssessment.initialScore.riskLevel : '--');
            setText('validationAssessmentRisk', riskText);

            var riskIcon = document.querySelector('#assessmentValidationReviewsPage .bi-exclamation-triangle');
            if (riskIcon) {
                riskIcon.className = 'bi bi-exclamation-triangle fs-4';
                var normRisk = riskText.toLowerCase();
                if (normRisk.indexOf('high') !== -1 || normRisk.indexOf('very high') !== -1) {
                    riskIcon.classList.add('text-danger');
                } else if (normRisk.indexOf('medium') !== -1) {
                    riskIcon.classList.add('text-warning');
                } else {
                    riskIcon.classList.add('text-success');
                }
            }

            var regionsEl = document.getElementById('validationAssessmentRegions');
            if (regionsEl) {
                var regions = currentAssessment.bodyRegions || [];
                if (regions.length) {
                    regionsEl.innerHTML = regions.map(function (item) {
                        var name = (item && typeof item === 'object') ? (item.region || item.label || '--') : String(item);
                        return '<span class="badge bg-label-secondary text-capitalize me-1 mb-1">' + escape(name.replace(/_/g, ' ')) + '</span>';
                    }).join('');
                } else {
                    regionsEl.innerHTML = '<span class="text-muted small">None recorded</span>';
                }
            }
            setText('validationAgreementOverall', percentText(currentAssessment.validationAgreement && currentAssessment.validationAgreement.overallAgreementRate));
            setText('validationAgreementRisk', percentText(currentAssessment.validationAgreement && currentAssessment.validationAgreement.riskLevelAgreementRate));
            setText('validationAgreementScore', percentText(currentAssessment.validationAgreement && currentAssessment.validationAgreement.scoreAgreementRate));
            setText('validationAgreementPairs', currentAssessment.validationAgreement && currentAssessment.validationAgreement.pairCount !== undefined ? String(currentAssessment.validationAgreement.pairCount) : '--');

            if (form.elements.reviewRound && Array.isArray(currentAssessment.validationReviews) && currentAssessment.validationReviews.length > 0) {
                form.elements.reviewRound.value = String(currentAssessment.validationReviews.length + 1);
            }

            if (form.elements.scoreRaw) {
                if (currentAssessment.finalScore && currentAssessment.finalScore.raw !== undefined && currentAssessment.finalScore.raw !== null) {
                    form.elements.scoreRaw.value = String(currentAssessment.finalScore.raw);
                } else if (currentAssessment.aiAssistance && currentAssessment.aiAssistance.score && currentAssessment.aiAssistance.score.raw !== undefined) {
                    form.elements.scoreRaw.value = String(currentAssessment.aiAssistance.score.raw);
                }
            }
            if (form.elements.riskLevel) {
                var riskLevel = currentAssessment.finalScore && currentAssessment.finalScore.riskLevel
                    ? String(currentAssessment.finalScore.riskLevel).toLowerCase().replace(/\s+/g, '_')
                    : (currentAssessment.aiAssistance && currentAssessment.aiAssistance.score && currentAssessment.aiAssistance.score.riskLevel
                        ? String(currentAssessment.aiAssistance.score.riskLevel).toLowerCase().replace(/\s+/g, '_')
                        : '');
                form.elements.riskLevel.value = riskLevel;
            }

            renderChoices('validationRiskFactors', (currentAssessment.riskFactors || []).map(function (item) { return { key: item, label: item.replace(/_/g, ' ') }; }), 'riskFactors', function (item) { return item.key; }, function (item) { return item.label; });
        });
    }

    function loadBodyRegions() {
        return App.api.get('/api/v1/worker-feedback/questions').then(function (res) {
            if (!res.ok) {
                return;
            }

            renderChoices('validationBodyRegions', res.data && res.data.bodyRegions ? res.data.bodyRegions : [], 'bodyRegions', function (item) {
                return item.key || item;
            }, function (item) {
                return item.label || item.key || item;
            });
        });
    }

    function renderReviews(items) {
        if (!Array.isArray(items) || items.length === 0) {
            table.innerHTML = '<tr><td colspan="7" class="text-muted">No validation reviews submitted yet.</td></tr>';
            return;
        }

        table.innerHTML = items.map(function (item) {
            var score = item.score && item.score.raw !== undefined ? item.score.raw : '--';
            return '<tr>' +
                '<td>' + escape(item.reviewerName || '--') + '</td>' +
                '<td>' + escape(item.reviewRound || '--') + '</td>' +
                '<td>' + escape(score) + '</td>' +
                '<td>' + escape(item.riskLevel || '--') + '</td>' +
                '<td>' + (item.isPrimary ? 'Yes' : 'No') + '</td>' +
                '<td>' + (item.isFinal ? 'Yes' : 'No') + '</td>' +
                '<td>' + escape(item.submittedAt || '--') + '</td>' +
            '</tr>';
        }).join('');
    }

    function loadReviews() {
        App.api.get(reviewsApi).then(function (res) {
            if (!res.ok) {
                App.ui.showAlert('danger', res.message || 'Failed to load validation reviews.', '#validationReviewAlert');
                return;
            }

            renderReviews(Array.isArray(res.data) ? res.data : []);
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (App.forms && App.forms.clearValidationErrors) {
            App.forms.clearValidationErrors(form);
        }

        var payload = {
            reviewerName: form.elements.reviewerName.value.trim(),
            reviewerCredentials: form.elements.reviewerCredentials.value.trim() || null,
            reviewRound: Number(form.elements.reviewRound.value || 1),
            riskLevel: form.elements.riskLevel.value,
            score: { raw: Number(form.elements.scoreRaw.value || 0) },
            bodyRegions: checkedValues('bodyRegions'),
            riskFactors: checkedValues('riskFactors'),
            notes: form.elements.notes.value.trim() || null,
            isPrimary: form.elements.isPrimary.checked,
            isFinal: form.elements.isFinal.checked
        };

        App.api.post(reviewsApi, payload).then(function (res) {
            if (!res.ok) {
                var rendered = showValidationErrors(res.errors || {});
                if (rendered.formErrors.length) {
                    App.ui.showAlert('danger', rendered.formErrors.join(' '), '#validationReviewAlert');
                } else if (!Object.keys(rendered.fieldErrors).length) {
                    App.ui.showAlert('danger', res.message || 'Validation review submission failed.', '#validationReviewAlert');
                }
                return;
            }

            App.notify.success('Validation review submitted.');
            var modalEl = document.getElementById('submitValidationReviewModal');
            if (modalEl && window.bootstrap) {
                var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.hide();
            }
            form.reset();
            form.elements.reviewRound.value = String(payload.reviewRound + 1);
            form.elements.isFinal.checked = true;
            form.elements.isPrimary.checked = false;
            loadAssessment();
            loadReviews();
        });
    });

    Promise.all([loadAssessment(), loadBodyRegions()]).then(function () {
        loadReviews();
    });
})();
