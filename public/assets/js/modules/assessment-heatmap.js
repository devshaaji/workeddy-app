(function () {
    'use strict';

    var page = document.getElementById('assessmentHeatmapPage');
    if (!page || !window.App) {
        return;
    }

    var assessmentUuid = page.getAttribute('data-assessment-uuid');
    var endpoint = '/api/v1/assessments/' + encodeURIComponent(assessmentUuid);
    var updateEndpoint = '/api/v1/assessments/' + encodeURIComponent(assessmentUuid);
    var canEdit = false;
    var currentRegions = [];
    var requiredRegions = [
        ['neck', 'Neck', 'front'],
        ['shoulders', 'Shoulders', 'back'],
        ['upper_back', 'Upper back', 'back'],
        ['lower_back', 'Lower back', 'back'],
        ['elbows', 'Elbows', 'front'],
        ['wrists_hands', 'Wrists and hands', 'front'],
        ['hips', 'Hips', 'front'],
        ['knees', 'Knees', 'front'],
        ['ankles_feet', 'Ankles and feet', 'front']
    ];
    var colors = ['#e8f5e9', '#c8e6c9', '#fff59d', '#ffcc80', '#ef9a9a'];

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

    function normalizeKey(value) {
        return String(value || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
    }

    function labelFor(key) {
        var found = requiredRegions.find(function (item) { return item[0] === key; });
        return found ? found[1] : key.replace(/_/g, ' ');
    }

    function regionValue(key) {
        var found = currentRegions.find(function (item) { return normalizeKey(item.region) === key; });
        return found ? Math.max(0, Math.min(4, Number(found.intensity || 0))) : 0;
    }

    function regionPayload() {
        return requiredRegions.map(function (item) {
            return {
                region: item[0],
                side: item[2],
                intensity: regionValue(item[0])
            };
        }).filter(function (item) {
            return item.intensity > 0;
        });
    }

    function setRegionValue(key, value) {
        var required = requiredRegions.find(function (item) { return item[0] === key; });
        if (!required) { return; }
        var next = regionPayload().filter(function (item) { return normalizeKey(item.region) !== key; });
        var intensity = Math.max(0, Math.min(4, Number(value || 0)));
        if (intensity > 0) {
            next.push({ region: key, side: required[2], intensity: intensity });
        }
        currentRegions = next;
        renderEditor();
        renderLiveHeatmap();
    }

    function riskLabel(score) {
        return ['None', 'Low', 'Moderate', 'High', 'Very high'][Math.max(0, Math.min(4, Number(score || 0)))] || 'None';
    }

    function renderEditor() {
        var rows = requiredRegions.map(function (item) {
            var key = item[0];
            var score = regionValue(key);
            return '<tr>' +
                '<td><button type="button" class="btn btn-sm btn-link px-0 heatmap-region-click" data-region="' + App.utils.escapeHtml(key) + '">' + App.utils.escapeHtml(item[1]) + '</button></td>' +
                '<td class="text-muted">' + App.utils.escapeHtml(item[2]) + '</td>' +
                '<td><select class="form-select form-select-sm heatmap-region-select" data-region="' + App.utils.escapeHtml(key) + '"' + (canEdit ? '' : ' disabled') + '>' +
                    [0, 1, 2, 3, 4].map(function (value) {
                        return '<option value="' + value + '"' + (value === score ? ' selected' : '') + '>' + riskLabel(value) + '</option>';
                    }).join('') +
                '</select></td>' +
                '<td><span class="badge" style="background:' + colors[score] + ';color:#111827">' + score + '</span></td>' +
            '</tr>';
        }).join('');
        setHtml('heatmapRegionEditorRows', rows);
        var saveBtn = document.getElementById('heatmapSaveRegionsBtn');
        if (saveBtn) {
            saveBtn.disabled = !canEdit;
            saveBtn.title = canEdit ? '' : 'Assessment is not editable.';
        }
    }

    // Anatomical marker positions in the shared 100x180 SVG viewBox, matching the
    // coordinates used to render the persisted heat map on the backend so the
    // clickable editor and the saved evidence never disagree on where a region sits.
    var regionCoords = {
        neck: [50, 30],
        shoulders: [50, 40],
        upper_back: [50, 60],
        lower_back: [50, 85],
        elbows: [27, 68],
        wrists_hands: [18, 92],
        hips: [50, 100],
        knees: [40, 130],
        ankles_feet: [36, 158]
    };

    function marker(key) {
        var score = regionValue(key);
        var coords = regionCoords[key] || [50, 90];
        var radius = 3 + (score * 1.2);
        var label = App.utils.escapeHtml(labelFor(key) + ': ' + riskLabel(score));
        return '<g class="heatmap-hotspot" data-region="' + key + '" style="cursor:' + (canEdit ? 'pointer' : 'default') + ';">' +
            '<circle cx="' + coords[0] + '" cy="' + coords[1] + '" r="' + radius + '" fill="' + colors[score] + '" stroke="#1f2937" stroke-width="1.25" />' +
            '<text x="' + coords[0] + '" y="' + coords[1] + '" font-size="5.5" font-weight="700" text-anchor="middle" dominant-baseline="central" fill="#111827" style="pointer-events:none;">' + score + '</text>' +
            '<title>' + label + '</title>' +
        '</g>';
    }

    function bodyShell(title, markers) {
        return '<div class="border rounded-3 p-2" style="min-height:300px;background:#f8fafc;">' +
            '<div class="small fw-semibold text-center mb-2">' + App.utils.escapeHtml(title) + '</div>' +
            '<svg viewBox="0 0 100 180" style="width:100%;height:250px;"><circle cx="50" cy="20" r="12" fill="#e5e7eb"/><rect x="35" y="34" width="30" height="70" rx="14" fill="#e5e7eb"/><path d="M35 45 L18 92" stroke="#e5e7eb" stroke-width="9" stroke-linecap="round"/><path d="M65 45 L82 92" stroke="#e5e7eb" stroke-width="9" stroke-linecap="round"/><path d="M43 103 L36 158" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/><path d="M57 103 L64 158" stroke="#e5e7eb" stroke-width="10" stroke-linecap="round"/>' +
            markers +
            '</svg>' +
        '</div>';
    }

    function renderLiveHeatmap() {
        setHtml('heatmapInteractiveCanvas',
            '<div class="row g-3">' +
                '<div class="col-sm-6">' + bodyShell('Front', marker('neck') + marker('elbows') + marker('wrists_hands') + marker('hips') + marker('knees') + marker('ankles_feet')) + '</div>' +
                '<div class="col-sm-6">' + bodyShell('Back', marker('shoulders') + marker('upper_back') + marker('lower_back')) + '</div>' +
            '</div>'
        );
    }

    function renderAssessment(data) {
        var finalScore = data.finalScore || {};
        var badge = document.getElementById('heatmapStatusBadge');
        canEdit = !!(data.actions && data.actions.canEdit);
        currentRegions = Array.isArray(data.bodyRegions) ? data.bodyRegions.slice() : [];

        setText('heatmapMethod', String((data.model || '--')).toUpperCase());
        setText('heatmapFinalScore', asText(finalScore.raw, '--'));
        setText('heatmapRiskLevel', asText(finalScore.riskLevel, '--'));
        setText('heatmapBaselineState', data.isBaseline ? 'Yes' : 'No');
        setText('heatmapLockedState', data.isLocked ? 'Yes' : 'No');

        if (badge) {
            badge.textContent = asText(data.status, '--').replace(/_/g, ' ');
            badge.className = 'badge ' + (data.isLocked ? 'bg-label-warning' : 'bg-label-secondary');
        }

        setHtml('heatmapFrontCanvas', data.bodyRegionHeatmap && data.bodyRegionHeatmap.frontSvg
            ? data.bodyRegionHeatmap.frontSvg
            : '<p class="text-muted small mb-0">No front-side evidence.</p>');
        setHtml('heatmapBackCanvas', data.bodyRegionHeatmap && data.bodyRegionHeatmap.backSvg
            ? data.bodyRegionHeatmap.backSvg
            : '<p class="text-muted small mb-0">No back-side evidence.</p>');

        var regions = data.bodyRegions || [];
        setHtml('heatmapRegionsList', regions.length
            ? regions.map(function (region) {
                var label = asText(region.region, 'region').replace(/_/g, ' ');
                var side = asText(region.side, 'front');
                var intensity = asText(region.intensity, '0');
                return '<span class="badge bg-label-primary">' + App.utils.escapeHtml(label + ' | ' + side + ' | ' + intensity) + '</span>';
            }).join('')
            : '<span class="text-muted small">No body-region evidence captured.</span>');
        renderEditor();
        renderLiveHeatmap();
    }

    App.api.get(endpoint).then(function (res) {
        if (!res.ok) {
            App.ui.showAlert('danger', res.message || 'Failed to load heat map.', '#heatmapAlert');
            return;
        }

        renderAssessment(res.data || {});
    });

    document.addEventListener('change', function (event) {
        if (!event.target.classList.contains('heatmap-region-select')) { return; }
        setRegionValue(event.target.getAttribute('data-region'), event.target.value);
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('.heatmap-hotspot, .heatmap-region-click');
        if (!trigger || !canEdit) { return; }
        var key = trigger.getAttribute('data-region');
        setRegionValue(key, (regionValue(key) + 1) % 5);
    });

    var saveBtn = document.getElementById('heatmapSaveRegionsBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            if (!canEdit) { return; }
            App.ui.setButtonLoading(saveBtn, true, 'Saving...');
            App.api.put(updateEndpoint, { bodyRegions: regionPayload() }).then(function (res) {
                App.ui.setButtonLoading(saveBtn, false);
                if (!res.ok) {
                    App.ui.showAlert('danger', res.message || 'Failed to save body-region severity.', '#heatmapAlert');
                    return;
                }
                App.notify.success('Body-region severity saved.');
                var modalEl = document.getElementById('reviewerSeverityModal');
                if (modalEl && window.bootstrap) {
                    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                    modal.hide();
                }
                renderAssessment(res.data || {});
            });
        });
    }
})();
