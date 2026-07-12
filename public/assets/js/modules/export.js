(function () {
    'use strict';

    var page = document.getElementById('exportPage');
    if (!page || !window.App) {
        return;
    }

    var form = document.getElementById('research-export-form');
    var previewButton = document.getElementById('research-export-preview');
    var generateButton = document.getElementById('research-export-generate');
    var previewEmpty = document.getElementById('research-export-preview-empty');
    var previewPanel = document.getElementById('research-export-preview-panel');
    var estimatedRows = document.getElementById('researchExportEstimatedRows');
    var includedCount = document.getElementById('researchExportIncludedCount');
    var includedColumns = document.getElementById('researchExportIncludedColumns');
    var excludedFields = document.getElementById('researchExportExcludedFields');
    var transformations = document.getElementById('researchExportTransformations');
    var previewModalElement = document.getElementById('researchExportPreviewModal');
    var previewModal = previewModalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(previewModalElement) : null;

    function payload() {
        return App.forms.serialize(form);
    }

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function showAlert(type, message) {
        App.ui.showAlert(type, message, '#researchExportAlert');
    }

    function renderList(items, renderer, emptyText) {
        if (!items.length) {
            return '<li>' + escape(emptyText) + '</li>';
        }

        return items.map(renderer).join('');
    }

    function renderPreview(data) {
        var included = Array.isArray(data.includedColumns) ? data.includedColumns : [];
        var excluded = Array.isArray(data.excludedFields) ? data.excludedFields : [];
        var transforms = Array.isArray(data.transformations) ? data.transformations : [];

        previewEmpty.classList.add('d-none');
        previewPanel.classList.remove('d-none');
        estimatedRows.textContent = String(data.estimatedRows || 0);
        includedCount.textContent = String(included.length);

        includedColumns.innerHTML = included.map(function (item) {
            var label = typeof item === 'string'
                ? item
                : (item.label || item.key || item.name || '');

            return '<span class="badge bg-label-primary">' + escape(label) + '</span>';
        }).join('');

        excludedFields.innerHTML = renderList(excluded, function (item) {
            return '<li>' + escape(item) + '</li>';
        }, 'No excluded fields declared.');

        transformations.innerHTML = renderList(transforms, function (item) {
            return '<li>' + escape(item) + '</li>';
        }, 'No transformations declared.');
    }

    function requestSignedLink(exportUuid, button) {
        App.ui.setButtonLoading(button, true);
        App.api.post('/api/v1/research-exports/' + encodeURIComponent(exportUuid) + '/signed-access', {}).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Failed to issue signed export link.');
                return;
            }

            var signedUrl = res.data && res.data.signedUrl ? res.data.signedUrl : '';
            if (signedUrl) {
                window.open(signedUrl, '_blank', 'noopener');
            }
            showAlert('success', 'Signed download link issued.');
        }).catch(function () {
            showAlert('danger', 'Failed to issue signed export link.');
        }).finally(function () {
            App.ui.setButtonLoading(button, false, '<i class="bi bi-link-45deg me-1"></i>Signed link');
        });
    }

    previewButton.addEventListener('click', function () {
        App.ui.setButtonLoading(previewButton, true);
        App.api.get('/api/v1/research-exports/preview', payload()).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Failed to load field preview.');
                return;
            }

            renderPreview(res.data || {});
            if (previewModal) {
                previewModal.show();
            }
            showAlert('info', 'Field preview loaded.');
        }).catch(function () {
            showAlert('danger', 'Failed to load field preview.');
        }).finally(function () {
            App.ui.setButtonLoading(previewButton, false, '<i class="bi bi-search me-1"></i>Preview fields');
        });
    });

    generateButton.addEventListener('click', function () {
        App.ui.setButtonLoading(generateButton, true);
        App.api.post('/api/v1/research-exports', payload()).then(function (res) {
            if (!res.ok) {
                showAlert('danger', res.message || 'Failed to generate research export.');
                return;
            }

            if (res.data && res.data.export && res.data.export.columnSchema) {
                renderPreview({
                    estimatedRows: res.data.export.rowCount || 0,
                    includedColumns: res.data.export.columnSchema || [],
                    excludedFields: [],
                    transformations: ['Generated export stored through Storage and protected with signed access.']
                });
            }

            var signedUrl = res.data && res.data.signedAccess ? res.data.signedAccess.signedUrl : '';
            if (signedUrl) {
                window.open(signedUrl, '_blank', 'noopener');
            }

            if (previewModal) {
                previewModal.show();
            }

            showAlert('success', 'Research export generated.');
        }).catch(function () {
            showAlert('danger', 'Failed to generate research export.');
        }).finally(function () {
            App.ui.setButtonLoading(generateButton, false, '<i class="bi bi-box-arrow-down-right me-1"></i>Generate export');
        });
    });

    page.addEventListener('click', function (event) {
        var button = event.target.closest('.research-export-download');
        if (!button) {
            return;
        }

        requestSignedLink(button.getAttribute('data-export-uuid') || '', button);
    });
})();
