(function () {
    'use strict';

    var page = document.getElementById('assessmentVideoEvidencePage');
    if (!page || !window.App) {
        return;
    }

    var assessmentUuid = page.getAttribute('data-assessment-uuid') || '';
    var orgMeta = document.querySelector('meta[name="org-uuid"]');
    var organizationUuid = page.getAttribute('data-organization-uuid') || (orgMeta ? orgMeta.content : '') || '';
    var endpoint = '/api/v1/assessments/' + encodeURIComponent(assessmentUuid);
    var state = {
        assessment: null,
        selectedAsset: null,
        signedAccess: null
    };

    function escape(value) {
        return App.utils.escapeHtml(value == null ? '' : String(value));
    }

    function setText(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    }

    function setHtml(id, value) {
        var node = document.getElementById(id);
        if (node) {
            node.innerHTML = value;
        }
    }

    function showAlert(level, message) {
        App.ui.showAlert(level, message, '#videoEvidenceAlert');
    }

    function titleize(value) {
        return String(value || '--').replace(/_/g, ' ');
    }

    function isExpired() {
        return !!(state.signedAccess && state.signedAccess.expiresAt && new Date(state.signedAccess.expiresAt.replace(' ', 'T') + 'Z').getTime() <= Date.now());
    }

    function signedUrlAvailable() {
        return !!(state.signedAccess && state.signedAccess.signedUrl && !isExpired());
    }

    function renderStatusRail(statusRail) {
        setText('statusRailConsent', titleize(statusRail.consentStatus));
        setText('statusRailProcessing', titleize(statusRail.processingStatus));
        setText('statusRailBlur', titleize(statusRail.blurStatus));
        setText('statusRailRetention', titleize(statusRail.retentionStatus));
        setText('statusRailReadiness', titleize(statusRail.reviewerReportReadiness));
    }

    function assetButton(asset, selected) {
        var disabled = asset.actions && asset.actions.canView === false;
        return '' +
            '<button type="button" class="list-group-item list-group-item-action' + (selected ? ' active' : '') + '" data-asset-uuid="' + escape(asset.storageFileUuid) + '"' + (disabled ? ' disabled' : '') + '>' +
                '<div class="d-flex justify-content-between align-items-start gap-2">' +
                    '<div>' +
                        '<div class="fw-semibold">' + escape(asset.label || titleize(asset.assetType)) + '</div>' +
                        '<div class="text-muted small">' + escape(titleize(asset.assetType)) + '</div>' +
                    '</div>' +
                    '<span class="badge bg-label-secondary">' + escape(titleize(asset.processingStatus)) + '</span>' +
                '</div>' +
            '</button>';
    }

    function renderNavigator() {
        var assets = (state.assessment && state.assessment.videoAssets) || [];
        var evidence = assets.filter(function (asset) {
            return asset.assetType === 'original_video' || asset.assetType === 'blurred_video';
        });
        var outputs = assets.filter(function (asset) {
            return asset.assetType !== 'original_video' && asset.assetType !== 'blurred_video';
        });

        setHtml('videoEvidenceList', evidence.length ? evidence.map(function (asset) {
            return assetButton(asset, state.selectedAsset && state.selectedAsset.storageFileUuid === asset.storageFileUuid);
        }).join('') : '<div class="list-group-item text-muted small">No source evidence available.</div>');

        setHtml('videoOutputList', outputs.length ? outputs.map(function (asset) {
            return assetButton(asset, state.selectedAsset && state.selectedAsset.storageFileUuid === asset.storageFileUuid);
        }).join('') : '<div class="list-group-item text-muted small">No generated processing outputs available.</div>');
    }

    function renderReports() {
        var reporting = (state.assessment && state.assessment.reporting) || {};
        var reports = reporting.reports || [];
        if (!reports.length) {
            setHtml('videoReportsList', '<p class="text-muted small mb-0">No reports available yet.</p>');
            return;
        }

        setHtml('videoReportsList', reports.map(function (report) {
            return '' +
                '<a class="d-flex justify-content-between align-items-center text-decoration-none border rounded-3 p-3 mb-2" target="_blank" rel="noopener" href="' + escape(report.url) + '">' +
                    '<div>' +
                        '<div class="fw-semibold text-body">' + escape(report.label) + '</div>' +
                        '<div class="text-muted small">' + escape(titleize(report.reportType)) + '</div>' +
                    '</div>' +
                    '<i class="bi bi-box-arrow-up-right text-muted"></i>' +
                '</a>';
        }).join(''));
    }

    function renderMetadata() {
        var asset = state.selectedAsset;
        if (!asset) {
            setHtml('selectedAssetMetadata', '<dt class="col-sm-4 text-muted">Artifact Type</dt><dd class="col-sm-8">--</dd>');
            return;
        }

        var rows = [
            ['Artifact Type', titleize(asset.assetType)],
            ['Storage File UUID', asset.storageFileUuid || '--'],
            ['Processing Status', titleize(asset.processingStatus)],
            ['Consent Text Version', asset.consentTextVersion || '--'],
            ['Face Blur Requested', asset.faceBlurRequested ? 'Yes' : 'No'],
            ['Faces Blurred', asset.facesBlurred ? 'Yes' : 'No'],
            ['Processing Confidence', asset.processingConfidence == null ? '--' : String(asset.processingConfidence)],
            ['Created', asset.createdAt ? App.utils.formatDate(asset.createdAt) : '--'],
            ['Processed', asset.processedAt ? App.utils.formatDate(asset.processedAt) : '--'],
            ['Retention Expiry', asset.retentionExpiresAt ? App.utils.formatDate(asset.retentionExpiresAt) : '--']
        ];

        setHtml('selectedAssetMetadata', rows.map(function (row) {
            return '<dt class="col-sm-4 text-muted">' + escape(row[0]) + '</dt><dd class="col-sm-8">' + escape(row[1]) + '</dd>';
        }).join(''));
    }

    function renderPreview() {
        var asset = state.selectedAsset;
        var requestButton = document.getElementById('requestSignedAccessBtn');
        var openButton = document.getElementById('openSignedAssetBtn');
        if (!asset) {
            setHtml('selectedAssetPreview', '<p class="text-muted mb-0">Select an evidence asset to begin review.</p>');
            setText('selectedAssetAccessState', 'No asset selected');
            setText('selectedAssetExpiry', '--');
            if (requestButton) { requestButton.disabled = true; }
            if (openButton) { openButton.classList.add('disabled'); openButton.href = '#'; }
            return;
        }

        var blocked = asset.processingStatus === 'processing' || asset.processingStatus === 'queued' || asset.processingStatus === 'pending';
        var failed = asset.processingStatus === 'failed';
        var noPermission = asset.actions && asset.actions.canRequestSignedAccess === false;
        var expired = isExpired();
        var previewHtml = '';
        var accessState = 'Request signed access to preview';

        if (blocked) {
            previewHtml = '<div class="text-center"><i class="bi bi-hourglass-split fs-1 text-muted"></i><p class="text-muted mb-0 mt-2">This asset is still processing. Preview is blocked until processing completes.</p></div>';
            accessState = 'Processing';
        } else if (failed) {
            previewHtml = '<div class="text-center"><i class="bi bi-x-octagon fs-1 text-danger"></i><p class="text-muted mb-0 mt-2">' + escape(asset.processingError || 'Processing failed. Preview unavailable.') + '</p></div>';
            accessState = 'Unavailable';
        } else if (noPermission) {
            previewHtml = '<div class="text-center"><i class="bi bi-lock fs-1 text-muted"></i><p class="text-muted mb-0 mt-2">You do not have permission to request access for this asset.</p></div>';
            accessState = 'Permission denied';
        } else if (signedUrlAvailable()) {
            if (asset.kind === 'image') {
                previewHtml = '<img src="' + escape(state.signedAccess.signedUrl) + '" alt="' + escape(asset.label) + '" class="img-fluid rounded-3" style="max-height: 420px;">';
            } else {
                previewHtml = '<video controls preload="metadata" class="w-100 rounded-3" style="max-height: 420px;" src="' + escape(state.signedAccess.signedUrl) + '"></video>';
            }
            accessState = 'Signed access issued';
        } else if (expired) {
            previewHtml = '<div class="text-center"><i class="bi bi-arrow-clockwise fs-1 text-warning"></i><p class="text-muted mb-0 mt-2">Signed access expired. Request new access to continue secure review.</p></div>';
            accessState = 'Expired';
        } else {
            previewHtml = '<div class="text-center"><i class="bi bi-shield-lock fs-1 text-primary"></i><p class="text-muted mb-0 mt-2">Signed playback is requested on demand. Use the access button to preview this asset.</p></div>';
        }

        setHtml('selectedAssetPreview', previewHtml);
        setText('selectedAssetAccessState', accessState);
        setText('selectedAssetExpiry', state.signedAccess && state.signedAccess.expiresAt ? App.utils.formatDate(state.signedAccess.expiresAt) : '--');
        if (requestButton) {
            requestButton.disabled = blocked || failed || noPermission;
            requestButton.textContent = expired ? 'Request new access' : 'Request access';
        }
        if (openButton) {
            openButton.href = signedUrlAvailable() ? state.signedAccess.signedUrl : '#';
            openButton.classList.toggle('disabled', !signedUrlAvailable());
        }
    }

    function renderAudit(rows) {
        if (!rows || !rows.length) {
            setHtml('selectedAssetAudit', '<p class="text-muted small mb-0">No recent asset audit events.</p>');
            return;
        }

        setHtml('selectedAssetAudit', rows.map(function (row) {
            var label = row.action === 'privacy.video.signed_access_streamed' ? 'signed playback streamed' : 'signed access issued';
            return '' +
                '<div class="border rounded-3 p-3 mb-2">' +
                    '<div class="d-flex justify-content-between gap-2">' +
                        '<strong>' + escape(label) + '</strong>' +
                        '<span class="text-muted small">' + escape(row.accessedAt ? App.utils.formatDate(row.accessedAt) : '--') + '</span>' +
                    '</div>' +
                    '<div class="text-muted small mt-1">Actor: ' + escape(row.userId || '--') + ' | Purpose: ' + escape(row.purpose || '--') + ' | Artifact: ' + escape(titleize((state.selectedAsset && state.selectedAsset.assetType) || 'asset')) + '</div>' +
                '</div>';
        }).join(''));
    }

    function loadAudit(asset) {
        if (!asset || !(asset.actions && asset.actions.canViewAssetAudit)) {
            setHtml('selectedAssetAudit', '<p class="text-muted small mb-0">Audit access is not available for this asset.</p>');
            return;
        }
        if (!organizationUuid) {
            setHtml('selectedAssetAudit', '<p class="text-muted small mb-0">Audit activity is unavailable outside an organization scope.</p>');
            return;
        }

        App.api.get('/api/v1/organizations/' + encodeURIComponent(organizationUuid) + '/assessments/' + encodeURIComponent(assessmentUuid) + '/video-assets/' + encodeURIComponent(asset.storageFileUuid) + '/audit?limit=10')
            .then(function (res) {
                if (!res.ok) {
                    setHtml('selectedAssetAudit', '<p class="text-muted small mb-0">Unable to load audit activity.</p>');
                    return;
                }
                renderAudit(res.data || []);
            });
    }

    function selectAsset(asset) {
        state.selectedAsset = asset;
        state.signedAccess = null;
        renderNavigator();
        renderPreview();
        renderMetadata();
        loadAudit(asset);
    }

    function bindNavigator() {
        ['videoEvidenceList', 'videoOutputList'].forEach(function (containerId) {
            var container = document.getElementById(containerId);
            if (!container) {
                return;
            }

            container.addEventListener('click', function (event) {
                var button = event.target.closest('[data-asset-uuid]');
                if (!button || !state.assessment) {
                    return;
                }
                var asset = (state.assessment.videoAssets || []).find(function (item) {
                    return item.storageFileUuid === button.getAttribute('data-asset-uuid');
                });
                if (asset) {
                    selectAsset(asset);
                }
            });
        });
    }

    function requestSignedAccess() {
        if (!state.selectedAsset) {
            return;
        }

        App.api.post('/api/v1/privacy/signed-video-access', {
            organizationUuid: organizationUuid,
            assessmentUuid: assessmentUuid,
            storageFileUuid: state.selectedAsset.storageFileUuid,
            purpose: 'review',
            ttlSeconds: 300
        }).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Unable to request signed access.');
                return;
            }

            state.signedAccess = res.data || null;
            renderPreview();
            loadAudit(state.selectedAsset);
            App.notify.success('Signed access issued.');
        });
    }

    function firstAllowedAsset(assets) {
        return (assets || []).find(function (asset) {
            return !asset.actions || asset.actions.canView !== false;
        }) || null;
    }

    function load() {
        App.api.get(endpoint).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Failed to load assessment evidence.');
                return;
            }

            state.assessment = res.data || {};
            renderStatusRail(state.assessment.statusRail || {});
            renderNavigator();
            renderReports();
            renderPreview();
            renderMetadata();

            var initialAsset = firstAllowedAsset(state.assessment.videoAssets || []);
            if (initialAsset) {
                selectAsset(initialAsset);
            } else {
                setHtml('selectedAssetAudit', '<p class="text-muted small mb-0">No accessible assets available for this assessment.</p>');
            }
        });
    }

    var requestButton = document.getElementById('requestSignedAccessBtn');
    if (requestButton) {
        requestButton.addEventListener('click', requestSignedAccess);
    }

    bindNavigator();
    load();
})();
