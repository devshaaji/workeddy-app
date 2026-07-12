(function () {
    'use strict';

    /* ── Utils ──────────────────────────────────────────────────────────── */

    function esc(value, fallback) {
        return App.utils.escapeHtml(String(value ?? fallback ?? ''));
    }

    function asText(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '--';
        }
        return String(value);
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) { el.textContent = String(value); }
    }

    function setHtml(id, value) {
        var el = document.getElementById(id);
        if (el) { el.innerHTML = value; }
    }

    function statusBadge(status) {
        var map = {
            sent: 'bg-label-success',
            delivered: 'bg-label-success',
            queued: 'bg-label-info',
            pending: 'bg-label-info',
            failed: 'bg-label-danger',
            cancelled: 'bg-label-secondary',
        };
        return '<span class="badge ' + (map[status] || 'bg-label-secondary') + '">' + esc(status) + '</span>';
    }

    function channelBadge(channel) {
        var icons = {
            email: 'bi-envelope',
            sms: 'bi-chat-dots',
            whatsapp: 'bi-whatsapp',
            inapp: 'bi-bell',
        };
        var icon = icons[channel] || 'bi-send';
        return '<span class="badge bg-label-secondary"><i class="bi ' + icon + ' me-1"></i>' + esc(channel) + '</span>';
    }

    /* ═══════════════════════════════════════════════════════════════════════
       NotificationLogs — Delivery log table with filters + detail modal
       ═══════════════════════════════════════════════════════════════════════ */

    var logPage = document.getElementById('notificationLogPage');
    if (logPage && window.App) {

        var logTable = null;

        function dateOrFallback(val) {
            return val ? App.utils.formatDate(val) : '--';
        }

        function updateLogStats(records) {
            setText('logStatTotal', records.length);
            var sent = records.filter(function (r) { return r.status === 'sent' || r.status === 'delivered'; }).length;
            var failed = records.filter(function (r) { return r.status === 'failed'; }).length;
            var queued = records.filter(function (r) { return r.status === 'queued' || r.status === 'pending'; }).length;
            setText('logStatSent', sent);
            setText('logStatFailed', failed);
            setText('logStatQueued', queued);
        }

        function renderLogRow(record) {
            return '<tr>' +
                '<td><a href="#" class="log-detail-trigger text-decoration-none fw-semibold" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#notificationLogDetailModal">' +
                esc(asText(record.notificationType, '—')) + '</a></td>' +
                '<td>' + channelBadge(record.channel) + '</td>' +
                '<td class="text-break">' + esc(asText(record.recipientName || record.recipientEmail || record.recipientPhone, '—')) + '</td>' +
                '<td>' + statusBadge(record.status) + '</td>' +
                '<td>' + esc(asText(record.attemptCount, '0')) + '</td>' +
                '<td class="text-break">' + esc(asText(record.failureReason, '—')) + '</td>' +
                '<td>' + esc(dateOrFallback(record.createdAt)) + '</td>' +
                '<td class="text-end">' +
                '<button class="btn btn-sm btn-outline-secondary log-detail-trigger" type="button" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#notificationLogDetailModal">' +
                '<i class="bi bi-eye"></i></button>' +
                '</td>' +
                '</tr>';
        }

        function showLogDetail(record) {
            setHtml('logDetailBody',
                '<dl class="row mb-0">' +
                '<dt class="col-sm-4 text-muted">Type</dt><dd class="col-sm-8"><code>' + esc(asText(record.notificationType)) + '</code></dd>' +
                '<dt class="col-sm-4 text-muted">Channel</dt><dd class="col-sm-8">' + esc(asText(record.channel)) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Status</dt><dd class="col-sm-8">' + statusBadge(record.status) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Recipient</dt><dd class="col-sm-8">' + esc(asText(record.recipientName)) + ' (' + esc(asText(record.recipientEmail)) + ')</dd>' +
                '<dt class="col-sm-4 text-muted">Provider</dt><dd class="col-sm-8">' + esc(asText(record.provider, '—')) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Subject</dt><dd class="col-sm-8 text-break">' + esc(asText(record.subject, '—')) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Preview</dt><dd class="col-sm-8 text-break"><code class="small">' + esc(asText(record.messagePreview, '—')) + '</code></dd>' +
                '<dt class="col-sm-4 text-muted">Attempts</dt><dd class="col-sm-8">' + esc(asText(record.attemptCount, '0')) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Failure reason</dt><dd class="col-sm-8 text-break">' + esc(asText(record.failureReason, '—')) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Queued</dt><dd class="col-sm-8">' + esc(dateOrFallback(record.queuedAt)) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Sent</dt><dd class="col-sm-8">' + esc(dateOrFallback(record.sentAt)) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Failed</dt><dd class="col-sm-8">' + esc(dateOrFallback(record.failedAt)) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Created</dt><dd class="col-sm-8">' + esc(dateOrFallback(record.createdAt)) + '</dd>' +
                '</dl>'
            );
        }

        function initLogTable() {
            logTable = App.tables.createAdvanced({
                card: '#logRecordsCard',
                tbody: '#logRecordsBody',
                endpoint: '/api/v1/notification/logs',
                colspan: 8,
                defaultSort: 'createdAt',
                sortDir: 'desc',
                pageSize: 15,
                emptyTitle: 'No notification delivery records',
                emptySubtitle: 'Delivery records appear here after notifications are sent.',
                renderRow: function (record) {
                    return renderLogRow(record);
                },
                filterRecord: function (record, filters) {
                    if (filters.channel && record.channel !== filters.channel) {
                        return false;
                    }
                    if (filters.status && record.status !== filters.status) {
                        return false;
                    }
                    if (filters.q) {
                        var q = filters.q.toLowerCase();
                        var searchFields = [record.notificationType, record.recipientName, record.recipientEmail, record.subject, record.failureReason].join(' ').toLowerCase();
                        if (searchFields.indexOf(q) === -1) {
                            return false;
                        }
                    }
                    return true;
                },
                afterLoad: function (records) {
                    updateLogStats(records);
                },
                afterRender: function () {
                    document.querySelectorAll('.log-detail-trigger').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var record;
                            try {
                                record = JSON.parse(btn.getAttribute('data-record'));
                            } catch (_) { return; }
                            showLogDetail(record);
                        });
                    });
                }
            });
        }

        function initLogFilters() {
            var channelFilter = document.getElementById('logChannelFilter');
            var statusFilter = document.getElementById('logStatusFilter');
            var searchInput = document.getElementById('logSearch');
            var clearBtn = document.getElementById('logClearFilters');

            function getFilters() {
                var f = {};
                if (channelFilter && channelFilter.value) f.channel = channelFilter.value;
                if (statusFilter && statusFilter.value) f.status = statusFilter.value;
                if (searchInput && searchInput.value.trim()) f.q = searchInput.value.trim();
                return f;
            }

            function apply() {
                if (logTable) logTable.applyFilters(getFilters());
            }

            if (channelFilter) channelFilter.addEventListener('change', apply);
            if (statusFilter) statusFilter.addEventListener('change', apply);
            if (searchInput) {
                searchInput.addEventListener('input', App.utils.debounce(apply, 300));
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (channelFilter) channelFilter.value = '';
                    if (statusFilter) statusFilter.value = '';
                    if (searchInput) searchInput.value = '';
                    if (logTable) logTable.applyFilters({});
                });
            }
        }

        initLogTable();
        initLogFilters();
    }

    /* ═══════════════════════════════════════════════════════════════════════
       NotificationTemplates — Template list table
       ═══════════════════════════════════════════════════════════════════════ */

    var templatePage = document.getElementById('notificationTemplatePage');
    if (templatePage && window.App) {

        function renderTemplateRow(template) {
            return '<tr>' +
                '<td><code>' + esc(asText(template.type, '—')) + '</code></td>' +
                '<td>' + channelBadge(template.channel) + '</td>' +
                '<td class="text-break">' + esc(asText(template.filename, '—')) + '</td>' +
                '<td class="text-end">' +
                '<button class="btn btn-sm btn-outline-primary template-preview-trigger" type="button" data-template-id="' +
                esc(template.id, '') + '" title="Preview template">' +
                '<i class="bi bi-eye"></i></button>' +
                '</td>' +
                '</tr>';
        }

        function initTemplateTable() {
            App.tables.createAdvanced({
                card: '#templateRecordsCard',
                tbody: '#templateRecordsBody',
                endpoint: '/api/v1/notification/templates',
                colspan: 4,
                defaultSort: 'type',
                sortDir: 'asc',
                pageSize: 25,
                emptyTitle: 'No message templates found',
                emptySubtitle: 'Template files are loaded from the notification templates directory.',
                renderRow: function (template) {
                    return renderTemplateRow(template);
                },
                afterRender: function () {
                    document.querySelectorAll('.template-preview-trigger').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var templateId = btn.getAttribute('data-template-id');
                            if (templateId) {
                                previewTemplate(templateId);
                            }
                        });
                    });
                }
            });
        }

        function previewTemplate(templateId) {
            App.api.get('/api/v1/notification/templates/' + encodeURIComponent(templateId) + '/preview').then(function (res) {
                if (!res.ok) {
                    App.notify.error(res.message || 'Failed to load template preview.');
                    return;
                }
                var d = res.data;
                setText('templatePreviewSubject', asText(d.subject, '(No subject)'));
                setHtml('templatePreviewBody', '<pre class="bg-dark text-white rounded p-3 mb-0" style="max-height: 400px; overflow-y: auto; white-space: pre-wrap;">' + esc(asText(d.body, '')) + '</pre>');
                App.modals.open('#templatePreviewModal');
            });
        }

        initTemplateTable();
    }

    /* ═══════════════════════════════════════════════════════════════════════
       NotificationSettings — Settings form (GET/PUT)
       ═══════════════════════════════════════════════════════════════════════ */

    var settingsPage = document.getElementById('notificationSettingsPage');
    if (settingsPage && window.App) {

        function loadSettings() {
            App.api.get('/api/v1/notification/settings').then(function (res) {
                if (!res.ok || !res.data) {
                    App.notify.error('Failed to load notification settings.');
                    return;
                }
                var values = res.data.values || {};
                setFieldValue('settingsDefaultFromName', values.default_from_name || '');
                setFieldValue('settingsDefaultFromEmail', values.default_from_email || '');
                setFieldValue('settingsQueueEnabled', !!values.queue_enabled);
                setFieldValue('settingsFallbackEnabled', !!values.fallback_enabled);
                setFieldValue('settingsRetryMaxAttempts', values.retry_max_attempts || 3);
                setFieldValue('settingsRetryDelaySeconds', values.retry_delay_seconds || 60);
                setFieldValue('settingsHttpTimeout', values.http_timeout_seconds || 10);
                setFieldValue('settingsHttpConnectTimeout', values.http_connect_timeout_seconds || 5);
                setFieldValue('settingsProviderList', values.provider_list || '[]');
            });
        }

        function setFieldValue(id, value) {
            var el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'checkbox') {
                el.checked = !!value;
            } else {
                el.value = String(value);
            }
        }

        function getFieldValue(id) {
            var el = document.getElementById(id);
            if (!el) return null;
            if (el.type === 'checkbox') {
                return el.checked ? true : false;
            }
            return el.value;
        }

        function initSettingsForm() {
            var form = document.getElementById('notificationSettingsForm');
            if (!form) return;

            function showValidationErrors(errors) {
                if (!errors || !App.forms || !App.forms.showValidationErrors) {
                    return { fieldErrors: {}, formErrors: [] };
                }

                return App.forms.showValidationErrors(form, errors);
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (App.forms && App.forms.clearValidationErrors) {
                    App.forms.clearValidationErrors(form);
                }

                var data = {
                    default_from_name: getFieldValue('settingsDefaultFromName') || '',
                    default_from_email: getFieldValue('settingsDefaultFromEmail') || '',
                    queue_enabled: !!getFieldValue('settingsQueueEnabled'),
                    fallback_enabled: !!getFieldValue('settingsFallbackEnabled'),
                    retry_max_attempts: parseInt(getFieldValue('settingsRetryMaxAttempts'), 10) || 3,
                    retry_delay_seconds: parseInt(getFieldValue('settingsRetryDelaySeconds'), 10) || 60,
                    http_timeout_seconds: parseInt(getFieldValue('settingsHttpTimeout'), 10) || 10,
                    http_connect_timeout_seconds: parseInt(getFieldValue('settingsHttpConnectTimeout'), 10) || 5,
                };

                var providerListRaw = getFieldValue('settingsProviderList');
                try {
                    var parsed = JSON.parse(providerListRaw);
                    data.provider_list = Array.isArray(parsed) ? parsed : [];
                } catch (_) {
                    App.notify.warning('Provider JSON is invalid. Check syntax before saving.');
                    return;
                }

                var submitBtn = document.getElementById('settingsSaveBtn');
                App.ui.setButtonLoading(submitBtn, true);

                App.api.put('/api/v1/notification/settings', { values: data }).then(function (res) {
                    App.ui.setButtonLoading(submitBtn, false);
                    if (res.ok) {
                        App.notify.success('Notification settings saved.');
                        loadSettings();
                    } else {
                        var rendered = showValidationErrors(res.errors || {});
                        if (rendered.formErrors.length) {
                            App.notify.error(rendered.formErrors.join(' '));
                        } else if (!Object.keys(rendered.fieldErrors).length) {
                            App.notify.error(res.message || 'Failed to save settings.');
                        }
                    }
                });
            });
        }

        loadSettings();
        initSettingsForm();
    }

})();
