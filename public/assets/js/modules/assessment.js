(function (window, document) {
    'use strict';

    if (!window.App) {
        return;
    }

    function qs(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function qsa(selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    }

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined || value === '' ? '--' : String(value));
    }

    function attr(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function text(selector, value) {
        var node = qs(selector);
        if (node) {
            node.textContent = String(value);
        }
    }

    function html(selector, value) {
        var node = qs(selector);
        if (node) {
            node.innerHTML = value;
        }
    }

    function firstScore(assessment) {
        return assessment.finalScore || assessment.initialScore || assessment.aiAssistance && assessment.aiAssistance.score || {};
    }

    function riskText(assessment) {
        var score = firstScore(assessment);
        return score.riskLevel || score.risk_level || score.risk_category || '--';
    }

    function rawScore(assessment) {
        var score = firstScore(assessment);
        return score.raw || score.raw_score || score.score || '--';
    }

    function statusBadge(status) {
        var map = {
            draft: 'bg-label-secondary',
            pending_review: 'bg-label-info',
            reviewed: 'bg-label-success',
            locked: 'bg-label-warning',
            flagged: 'bg-label-danger'
        };
        return '<span class="badge ' + (map[status] || 'bg-label-secondary') + '">' + escape(String(status || '--').replace(/_/g, ' ')) + '</span>';
    }

    function scoreSource(assessment) {
        if (assessment.aiAssistance && assessment.aiAssistance.available) {
            return 'video';
        }
        return assessment.scoreSource || 'manual';
    }

    function assessmentActions(assessment, reviewMode) {
        var uuid = encodeURIComponent(assessment.uuid || assessment.id || '');
        var actions = [
            '<a class="dropdown-item" href="/assessments/' + uuid + '"><i class="bi bi-eye me-2"></i>View detail</a>',
            '<a class="dropdown-item" href="/assessments/' + uuid + '/heatmap"><i class="bi bi-body-text me-2"></i>Heat map</a>',
            '<a class="dropdown-item" href="/assessments/' + uuid + '/video-evidence"><i class="bi bi-camera-video me-2"></i>Video evidence</a>'
        ];

        if (reviewMode || assessment.status === 'pending_review') {
            actions.unshift('<a class="dropdown-item" href="/assessments/' + uuid + '/review"><i class="bi bi-clipboard-check me-2"></i>Review</a>');
        }

        if (assessment.status === 'reviewed' || assessment.status === 'locked') {
            actions.push('<a class="dropdown-item" href="/assessments/' + uuid + '/validation-reviews"><i class="bi bi-person-check me-2"></i>Validation reviews</a>');
        }

        return '<div class="dropdown">' +
            '<button class="btn btn-sm btn-icon btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Assessment actions"><i class="bi bi-three-dots-vertical"></i></button>' +
            '<div class="dropdown-menu dropdown-menu-end">' + actions.join('') + '</div>' +
            '</div>';
    }

    function normalizeList(res) {
        if (!res.ok) {
            return [];
        }
        if (Array.isArray(res.data)) {
            return res.data;
        }
        if (res.data && Array.isArray(res.data.data)) {
            return res.data.data;
        }
        return [];
    }

    function initAssessmentList() {
        var page = qs('#assessmentIndexPage');
        if (!page) {
            return;
        }

        var state = { records: [] };
        var endpoint = page.getAttribute('data-api-base');

        function filters() {
            return {
                search: (qs('#assessmentSearch') ? qs('#assessmentSearch').value : '').toLowerCase(),
                status: qs('#assessmentStatusFilter') ? qs('#assessmentStatusFilter').value : '',
                model: qs('#assessmentModelFilter') ? qs('#assessmentModelFilter').value : ''
            };
        }

        function filtered() {
            var active = filters();
            return state.records.filter(function (assessment) {
                var haystack = [
                    assessment.uuid,
                    assessment.id,
                    assessment.taskUuid,
                    assessment.model,
                    assessment.status,
                    riskText(assessment),
                    assessment.review && assessment.review.reviewerName
                ].join(' ').toLowerCase();

                if (active.search && haystack.indexOf(active.search) === -1) {
                    return false;
                }
                if (active.status && assessment.status !== active.status) {
                    return false;
                }
                if (active.model && assessment.model !== active.model) {
                    return false;
                }
                return true;
            });
        }

        function render() {
            var rows = filtered();
            text('#assessmentStatTotal', state.records.length);
            text('#assessmentStatPending', state.records.filter(function (item) { return item.status === 'pending_review'; }).length);
            text('#assessmentStatReviewed', state.records.filter(function (item) { return item.status === 'reviewed'; }).length);
            text('#assessmentStatLocked', state.records.filter(function (item) { return item.status === 'locked'; }).length);
            text('#assessmentResultCount', rows.length + ' assessments');

            if (rows.length === 0) {
                html('#assessmentTableBody', '<tr><td colspan="7" class="text-center text-muted py-5">No assessments match the current filters.</td></tr>');
                return;
            }

            html('#assessmentTableBody', rows.map(function (assessment) {
                var uuid = assessment.uuid || assessment.id || '';
                return '<tr>' +
                    '<td><div class="fw-semibold text-break">' + escape(uuid) + '</div><small class="text-muted text-break">Task ' + escape(assessment.taskUuid || '--') + '</small></td>' +
                    '<td><span class="text-uppercase">' + escape(assessment.model) + '</span></td>' +
                    '<td>' + escape(rawScore(assessment)) + '</td>' +
                    '<td>' + escape(riskText(assessment)) + '</td>' +
                    '<td>' + statusBadge(assessment.status) + '</td>' +
                    '<td>' + escape(assessment.createdAt ? App.utils.formatDate(assessment.createdAt) : '--') + '</td>' +
                    '<td class="text-end">' + assessmentActions(assessment, false) + '</td>' +
                '</tr>';
            }).join(''));
        }

        function load() {
            App.api.get(endpoint, { limit: 100 }).then(function (res) {
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Failed to load assessments.', '#assessmentIndexPage');
                    return;
                }
                state.records = normalizeList(res);
                render();
            });
        }

        ['#assessmentSearch', '#assessmentStatusFilter', '#assessmentModelFilter'].forEach(function (selector) {
            var control = qs(selector);
            if (control) {
                control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', render);
            }
        });

        var clear = qs('#assessmentClearFilters');
        if (clear) {
            clear.addEventListener('click', function () {
                ['#assessmentSearch', '#assessmentStatusFilter', '#assessmentModelFilter'].forEach(function (selector) {
                    var control = qs(selector);
                    if (control) {
                        control.value = '';
                    }
                });
                render();
            });
        }

        load();
    }

    function initReviewerQueue() {
        var page = qs('#assessmentReviewerQueuePage');
        if (!page) {
            return;
        }

        var state = { records: [] };
        var endpoint = page.getAttribute('data-api-base');

        function filtered() {
            var search = (qs('#queueSearch') ? qs('#queueSearch').value : '').toLowerCase();
            var model = qs('#queueModelFilter') ? qs('#queueModelFilter').value : '';
            return state.records.filter(function (assessment) {
                var haystack = [assessment.uuid, assessment.taskUuid, assessment.model, riskText(assessment), scoreSource(assessment)].join(' ').toLowerCase();
                if (search && haystack.indexOf(search) === -1) {
                    return false;
                }
                if (model && assessment.model !== model) {
                    return false;
                }
                return true;
            });
        }

        function isHighRisk(assessment) {
            return String(riskText(assessment)).toLowerCase().indexOf('high') !== -1 || String(riskText(assessment)).toLowerCase().indexOf('very') !== -1;
        }

        function render() {
            var rows = filtered();
            text('#queueStatPending', state.records.length);
            text('#queueStatHighRisk', state.records.filter(isHighRisk).length);
            text('#queueStatVideo', state.records.filter(function (item) { return scoreSource(item) === 'video' || scoreSource(item) === 'ai_assisted'; }).length);
            text('#queueStatManual', state.records.filter(function (item) { return scoreSource(item) !== 'video' && scoreSource(item) !== 'ai_assisted'; }).length);
            text('#queueResultCount', rows.length + ' pending');

            if (rows.length === 0) {
                html('#queueTableBody', '<tr><td colspan="7" class="text-center text-muted py-5">No pending assessments match the current filters.</td></tr>');
                return;
            }

            html('#queueTableBody', rows.map(function (assessment) {
                var uuid = assessment.uuid || assessment.id || '';
                return '<tr>' +
                    '<td><div class="fw-semibold text-break">' + escape(uuid) + '</div><small class="text-muted text-break">Task ' + escape(assessment.taskUuid || '--') + '</small></td>' +
                    '<td><span class="text-uppercase">' + escape(assessment.model) + '</span></td>' +
                    '<td>' + escape(rawScore(assessment)) + '</td>' +
                    '<td>' + escape(riskText(assessment)) + '</td>' +
                    '<td>' + statusBadge(scoreSource(assessment)) + '</td>' +
                    '<td>' + escape(assessment.createdAt ? App.utils.formatDate(assessment.createdAt) : '--') + '</td>' +
                    '<td class="text-end">' + assessmentActions(assessment, true) + '</td>' +
                '</tr>';
            }).join(''));
        }

        function load() {
            App.api.get(endpoint, { limit: 100 }).then(function (res) {
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Failed to load reviewer queue.', '#assessmentReviewerQueuePage');
                    return;
                }
                state.records = normalizeList(res);
                render();
            });
        }

        ['#queueSearch', '#queueModelFilter'].forEach(function (selector) {
            var control = qs(selector);
            if (control) {
                control.addEventListener(control.tagName === 'INPUT' ? 'input' : 'change', render);
            }
        });

        var clear = qs('#queueClearFilters');
        if (clear) {
            clear.addEventListener('click', function () {
                if (qs('#queueSearch')) { qs('#queueSearch').value = ''; }
                if (qs('#queueModelFilter')) { qs('#queueModelFilter').value = ''; }
                render();
            });
        }

        var refresh = qs('#reviewerQueueRefresh');
        if (refresh) {
            refresh.addEventListener('click', function (event) {
                event.preventDefault();
                load();
            });
        }

        load();
    }

    function initManualForm() {
        var page = qs('#assessmentManualPage');
        if (!page) {
            return;
        }

        var form = qs('#assessmentManualForm');
        var endpoint = page.getAttribute('data-api-base');
        var tasksEndpoint = page.getAttribute('data-tasks-api');
        var preselectedTaskUuid = page.getAttribute('data-task') || '';
        var wizard = qs('#assessmentManualWizard');
        var currentStep = 0;
        var tasks = [];
        var bodyRegionOptions = [
            { value: 'neck', label: 'Neck', side: 'front' },
            { value: 'shoulders', label: 'Shoulders', side: 'back' },
            { value: 'upper_back', label: 'Upper back', side: 'back' },
            { value: 'lower_back', label: 'Lower back', side: 'back' },
            { value: 'elbows', label: 'Elbows', side: 'front' },
            { value: 'wrists_hands', label: 'Wrists and hands', side: 'front' },
            { value: 'hips', label: 'Hips', side: 'front' },
            { value: 'knees', label: 'Knees', side: 'front' },
            { value: 'ankles_feet', label: 'Ankles and feet', side: 'front' }
        ];
        var metricSets = {
            reba: {
                hint: 'REBA supports whole-body posture, load, coupling, and activity factors.',
                fields: [
                    ['neck_angle', 'Neck angle', 'number', '20'],
                    ['trunk_angle', 'Trunk angle', 'number', '20'],
                    ['upper_arm_angle', 'Upper arm angle', 'number', '45'],
                    ['lower_arm_angle', 'Lower arm angle', 'number', '90'],
                    ['wrist_angle', 'Wrist angle', 'number', '15'],
                    ['leg_score', 'Leg score', 'number', '1'],
                    ['knee_angle', 'Knee angle', 'number', '30'],
                    ['load_weight', 'Load weight', 'number', '5'],
                    ['coupling', 'Coupling', 'select', 'good'],
                    ['static_posture', 'Static posture', 'checkbox', ''],
                    ['repetitive', 'Repetitive', 'checkbox', ''],
                    ['rapid_change', 'Rapid change', 'checkbox', '']
                ]
            },
            rula: {
                hint: 'RULA focuses on upper limb, neck, trunk, wrist, force, and repetition.',
                fields: [
                    ['neck_angle', 'Neck angle', 'number', '20'],
                    ['trunk_angle', 'Trunk angle', 'number', '20'],
                    ['upper_arm_angle', 'Upper arm angle', 'number', '45'],
                    ['lower_arm_angle', 'Lower arm angle', 'number', '90'],
                    ['wrist_angle', 'Wrist angle', 'number', '15'],
                    ['leg_score', 'Leg score', 'number', '1'],
                    ['load_weight', 'Load weight', 'number', '5'],
                    ['wrist_twist', 'Wrist twist', 'checkbox', ''],
                    ['static_posture', 'Static posture', 'checkbox', ''],
                    ['repetitive', 'Repetitive', 'checkbox', '']
                ]
            },
            niosh: {
                hint: 'NIOSH lifting equation uses load, reach, height, travel, twist, frequency, and coupling.',
                fields: [
                    ['load_weight', 'Load weight', 'number', '10'],
                    ['horizontal_distance', 'Horizontal distance', 'number', '25'],
                    ['vertical_start', 'Vertical start', 'number', '75'],
                    ['vertical_travel', 'Vertical travel', 'number', '30'],
                    ['twist_angle', 'Twist angle', 'number', '0'],
                    ['frequency', 'Frequency', 'number', '1'],
                    ['coupling', 'Coupling', 'select', 'good']
                ]
            }
        };

        function metricControl(field) {
            var name = field[0];
            var label = field[1];
            var type = field[2];
            var value = field[3];
            if (type === 'checkbox') {
                return '<div class="col-sm-6 col-lg-4"><div class="form-check form-switch mt-4">' +
                    '<input class="form-check-input manual-metric" type="checkbox" id="metric_' + attr(name) + '" name="' + attr(name) + '">' +
                    '<label class="form-check-label" for="metric_' + attr(name) + '">' + escape(label) + '</label>' +
                '</div></div>';
            }
            if (type === 'select') {
                return '<div class="col-sm-6 col-lg-4"><label class="form-label" for="metric_' + attr(name) + '">' + escape(label) + '</label>' +
                    '<select class="form-select manual-metric" id="metric_' + attr(name) + '" name="' + attr(name) + '">' +
                    '<option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option>' +
                    '</select></div>';
            }
            return '<div class="col-sm-6 col-lg-4"><label class="form-label" for="metric_' + attr(name) + '">' + escape(label) + '</label>' +
                '<input class="form-control manual-metric" id="metric_' + attr(name) + '" name="' + attr(name) + '" type="number" step="0.01" value="' + attr(value) + '" required>' +
            '</div>';
        }

        function initTooltips(container) {
            if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                return;
            }
            qsa('[data-bs-toggle="tooltip"]', container || document).forEach(function (el) {
                if (bootstrap.Tooltip.getInstance(el)) {
                    return;
                }
                new bootstrap.Tooltip(el);
            });
        }

        function stepTriggers() {
            return qsa('[data-manual-step-trigger]', wizard);
        }

        function stepContents() {
            return qsa('[data-manual-step-content]', wizard);
        }

        function updateSummary() {
            var taskSelect = qs('#manualTaskUuid');
            var taskLabel = taskSelect && taskSelect.selectedIndex > 0 ? taskSelect.options[taskSelect.selectedIndex].text : 'Not selected';
            var modelLabel = (qs('#manualModel').value || 'Not selected').toUpperCase();
            var riskLabels = qsa('input[name="riskFactors[]"]:checked').map(function (input) {
                var label = qs('label[for="' + input.id + '"]');
                return label ? label.textContent.trim() : input.value;
            });
            var bodyRegions = collectBodyRegions().map(function (row) {
                var option = bodyRegionOptions.find(function (item) { return item.value === row.region; });
                var label = option ? option.label : row.region;
                return label + ' (' + row.intensity + ')';
            });

            text('#manualSummaryTask', taskLabel);
            text('#manualSummaryModel', modelLabel);
            text('#manualSummaryRiskFactors', riskLabels.length ? riskLabels.join(', ') : 'None selected');
            text('#manualSummaryBodyRegions', bodyRegions.length ? bodyRegions.join(', ') : 'None added');
        }

        function showStep(index) {
            currentStep = Math.max(0, Math.min(index, stepContents().length - 1));
            stepTriggers().forEach(function (trigger, triggerIndex) {
                var step = trigger.closest('.step');
                trigger.setAttribute('aria-selected', triggerIndex === currentStep ? 'true' : 'false');
                if (step) {
                    step.classList.toggle('active', triggerIndex === currentStep);
                    step.classList.toggle('is-complete', triggerIndex < currentStep);
                }
            });
            stepContents().forEach(function (content, contentIndex) {
                var active = contentIndex === currentStep;
                content.classList.toggle('d-none', !active);
                content.classList.toggle('d-block', active);
                content.classList.toggle('active', active);
            });
            if (currentStep === 3) {
                updateSummary();
            }
            initTooltips(wizard);
        }

        function renderMetrics() {
            var model = qs('#manualModel').value || 'reba';
            html('#manualMetricsGrid', (metricSets[model] || metricSets.reba).fields.map(metricControl).join(''));
        }

        function selectedTask() {
            var taskUuid = qs('#manualTaskUuid').value;
            return tasks.find(function (task) {
                return String(task.id || task.uuid || '') === String(taskUuid);
            }) || null;
        }

        function syncTaskModel() {
            var task = selectedTask();
            var model = task && task.assessmentModel ? String(task.assessmentModel).toLowerCase() : '';
            qs('#manualModel').value = model;
            var display = qs('#manualModelDisplay');
            if (display) {
                display.value = model ? model.toUpperCase() : 'Select a task first';
            }
            if (model) {
                renderMetrics();
            } else {
                html('#manualMetricsGrid', '<div class="col-12"><div class="text-muted small">Choose a task to load the required scoring inputs.</div></div>');
            }
        }

        function bodyRegionRow(region, side, intensity) {
            var index = qsa('.manual-body-region').length + 1;
            var selected = bodyRegionOptions.find(function (item) { return item.value === region; }) || bodyRegionOptions[3];
            var options = bodyRegionOptions.map(function (item) {
                return '<option value="' + attr(item.value) + '" data-side="' + attr(item.side) + '"' + (item.value === selected.value ? ' selected' : '') + '>' + attr(item.label) + '</option>';
            }).join('');

            return '<div class="manual-body-region border rounded-3 p-3">' +
                '<div class="row g-2 align-items-end">' +
                    '<div class="col-sm-6"><label class="form-label" for="bodyRegion_' + index + '">Region</label><select id="bodyRegion_' + index + '" class="form-select manual-region-select" name="region">' + options + '</select></div>' +
                    '<div class="col-sm-3"><label class="form-label" for="bodySide_' + index + '">Side</label><input id="bodySide_' + index + '" class="form-control manual-region-side" name="side" value="' + attr(side || selected.side) + '" readonly></div>' +
                    '<div class="col-sm-2"><label class="form-label" for="bodyIntensity_' + index + '">0-5</label><input id="bodyIntensity_' + index + '" class="form-control" name="intensity" type="number" min="0" max="5" value="' + attr(intensity || 2) + '"></div>' +
                    '<div class="col-sm-1 d-grid"><button type="button" class="btn btn-outline-secondary btn-icon manual-remove-region" aria-label="Remove region"><i class="bi bi-x-lg"></i></button></div>' +
                '</div>' +
            '</div>';
        }

        function addBodyRegion(region, side, intensity) {
            var holder = qs('#manualBodyRegions');
            var selected = bodyRegionOptions.find(function (item) { return item.value === region; }) || bodyRegionOptions[3];
            holder.insertAdjacentHTML('beforeend', bodyRegionRow(selected.value, side || selected.side, intensity));
        }

        function updateRegionSide(row) {
            var select = qs('.manual-region-select', row);
            var sideInput = qs('.manual-region-side', row);
            var option = select ? select.options[select.selectedIndex] : null;
            if (sideInput && option) {
                sideInput.value = option.getAttribute('data-side') || 'front';
            }
        }

        function loadTasks() {
            App.api.get(tasksEndpoint, { limit: 100 }).then(function (res) {
                tasks = normalizeList(res);
                var select = qs('#manualTaskUuid');
                if (!res.ok || tasks.length === 0) {
                    select.innerHTML = '<option value="">No tasks available</option>';
                    return;
                }
                select.innerHTML = '<option value="">Select task</option>' + tasks.map(function (task) {
                    var id = task.id || task.uuid || '';
                    var label = task.name || task.taskName || task.taskCode || id;
                    return '<option value="' + escape(id) + '">' + escape(label) + '</option>';
                }).join('');
                if (preselectedTaskUuid) {
                    select.value = preselectedTaskUuid;
                }
                syncTaskModel();
            });
        }

        function collectMetrics() {
            var metrics = {};
            qsa('.manual-metric').forEach(function (input) {
                if (input.type === 'checkbox') {
                    metrics[input.name] = input.checked;
                } else if (input.type === 'number') {
                    metrics[input.name] = input.value === '' ? 0 : Number(input.value);
                } else {
                    metrics[input.name] = input.value;
                }
            });
            return metrics;
        }

        function collectBodyRegions() {
            return qsa('.manual-body-region').map(function (row) {
                return {
                    region: qs('[name="region"]', row).value.trim(),
                    side: qs('[name="side"]', row).value,
                    intensity: Number(qs('[name="intensity"]', row).value || 0)
                };
            }).filter(function (row) {
                return row.region !== '';
            });
        }

        function collectRiskFactors() {
            return qsa('input[name="riskFactors[]"]:checked').map(function (input) {
                return input.value;
            });
        }

        qs('#manualTaskUuid').addEventListener('change', syncTaskModel);
        qs('#manualResetMetrics').addEventListener('click', renderMetrics);
        qs('#manualAddBodyRegion').addEventListener('click', function () { addBodyRegion('', 'front', 2); });
        qsa('[data-manual-step-trigger]', wizard).forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                showStep(Number(trigger.getAttribute('data-manual-step-trigger') || 0));
            });
        });
        qsa('[data-manual-next]', wizard).forEach(function (button) {
            button.addEventListener('click', function () {
                showStep(currentStep + 1);
            });
        });
        qsa('[data-manual-prev]', wizard).forEach(function (button) {
            button.addEventListener('click', function () {
                showStep(currentStep - 1);
            });
        });
        qs('#manualBodyRegions').addEventListener('click', function (event) {
            var button = event.target.closest('.manual-remove-region');
            if (button) {
                button.closest('.manual-body-region').remove();
            }
        });
        qs('#manualBodyRegions').addEventListener('change', function (event) {
            var select = event.target.closest('.manual-region-select');
            if (select) {
                updateRegionSide(select.closest('.manual-body-region'));
            }
        });
        qsa('[data-submit-mode]', form).forEach(function (button) {
            button.addEventListener('click', function () {
                qs('#manualSubmitMode').value = button.getAttribute('data-submit-mode') || 'submit';
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (App.forms && App.forms.clearValidationErrors) {
                App.forms.clearValidationErrors(form);
            }
            var payload = {
                taskUuid: qs('#manualTaskUuid').value,
                model: qs('#manualModel').value,
                metrics: collectMetrics(),
                riskFactors: collectRiskFactors(),
                bodyRegions: collectBodyRegions(),
                submitMode: qs('#manualSubmitMode').value || 'submit'
            };

            if (!payload.taskUuid) {
                App.ui.showAlert('warning', 'Select a task before creating an assessment.', '#assessmentManualAlert');
                return;
            }
            if (!payload.model) {
                App.ui.showAlert('warning', 'The selected task does not have an assessment model configured.', '#assessmentManualAlert');
                return;
            }

            var actionButton = payload.submitMode === 'draft' ? qs('#manualSaveDraftBtn') : qs('#manualSubmitBtn');
            App.ui.setButtonLoading(actionButton, true, payload.submitMode === 'draft' ? 'Saving...' : 'Submitting...');
            App.api.post(endpoint, payload).then(function (res) {
                App.ui.setButtonLoading(actionButton, false);
                if (!res.ok) {
                    var rendered = App.forms && App.forms.showValidationErrors ? App.forms.showValidationErrors(form, res.errors || {}) : { fieldErrors: {}, formErrors: [] };
                    if (rendered.formErrors.length) {
                        App.ui.showAlert('danger', rendered.formErrors.join(' '), '#assessmentManualAlert');
                    } else if (!Object.keys(rendered.fieldErrors).length) {
                        App.ui.showAlert('danger', res.message || 'Failed to create assessment.', '#assessmentManualAlert');
                    }
                    return;
                }
                App.notify.success(payload.submitMode === 'draft' ? 'Draft assessment saved.' : 'Assessment submitted.');
                window.location.href = '/assessments/' + encodeURIComponent((res.data && (res.data.uuid || res.data.id)) || '');
            });
        });

        syncTaskModel();
        addBodyRegion('lower_back', 'back', 2);
        loadTasks();
        showStep(0);
    }

    initAssessmentList();
    initReviewerQueue();
    initManualForm();
})(window, document);
