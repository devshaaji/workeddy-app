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
        var providerDefinitions = {
            smtp: {
                label: 'SMTP',
                channels: ['email'],
                requiredFields: ['host', 'port'],
                optionalFields: ['user', 'pass', 'encryption'],
                sensitiveFields: ['pass']
            },
            twilio: {
                label: 'Twilio',
                channels: ['sms', 'whatsapp'],
                requiredFields: ['account_sid', 'auth_token', 'sms_from'],
                optionalFields: ['whatsapp_from', 'status_callback_url'],
                sensitiveFields: ['auth_token']
            }
        };

        var providerFieldMeta = {
            host: { label: 'SMTP host', type: 'text', placeholder: 'smtp.hostinger.com' },
            port: { label: 'SMTP port', type: 'number', placeholder: '587' },
            user: { label: 'SMTP username', type: 'text', placeholder: 'admin@workeddy.com' },
            pass: { label: 'SMTP password', type: 'password', placeholder: 'Leave blank to keep current password' },
            encryption: { label: 'Encryption', type: 'select', options: ['', 'tls', 'ssl'] },
            account_sid: { label: 'Account SID', type: 'text', placeholder: 'AC...' },
            auth_token: { label: 'Auth token', type: 'password', placeholder: 'Leave blank to keep current token' },
            sms_from: { label: 'SMS from', type: 'text', placeholder: '+1234567890' },
            whatsapp_from: { label: 'WhatsApp from', type: 'text', placeholder: 'whatsapp:+1234567890' },
            status_callback_url: { label: 'Status callback URL', type: 'url', placeholder: 'https://example.com/webhooks/status' }
        };

        var providerState = {
            providerList: [],
            activeProviderMap: {}
        };

        function clone(value) {
            return JSON.parse(JSON.stringify(value));
        }

        function supportedChannels(type) {
            return clone((providerDefinitions[type] && providerDefinitions[type].channels) || []);
        }

        function providerFields(type) {
            var definition = providerDefinitions[type];
            if (!definition) {
                return [];
            }
            return definition.requiredFields.concat(definition.optionalFields);
        }

        function normalizeProvider(provider, index) {
            var type = String(provider && provider.provider_type ? provider.provider_type : 'smtp');
            if (!providerDefinitions[type]) {
                type = 'smtp';
            }

            var normalized = {
                key: String(provider && provider.key ? provider.key : (type + '_' + (index + 1))),
                provider_type: type,
                enabled: provider && typeof provider.enabled === 'boolean' ? provider.enabled : true,
                channels: supportedChannels(type),
                priority: parseInt(provider && provider.priority, 10) || (index + 1),
                config: {}
            };

            var sourceConfig = provider && provider.config ? provider.config : {};
            providerFields(type).forEach(function (field) {
                if (Object.prototype.hasOwnProperty.call(sourceConfig, field)) {
                    normalized.config[field] = sourceConfig[field];
                } else {
                    normalized.config[field] = '';
                }
            });

            return normalized;
        }

        function normalizeProviderList(providerList) {
            return (providerList || []).map(function (provider, index) {
                return normalizeProvider(provider, index);
            });
        }

        function providersForChannel(channel) {
            return providerState.providerList.filter(function (provider) {
                return Array.isArray(provider.channels) && provider.channels.indexOf(channel) !== -1;
            });
        }

        function ensureActiveProviderMap() {
            ['email', 'sms', 'whatsapp'].forEach(function (channel) {
                var providers = providersForChannel(channel);
                var current = providerState.activeProviderMap[channel] || '';
                var exists = providers.some(function (provider) { return provider.key === current; });
                if (!exists) {
                    providerState.activeProviderMap[channel] = providers[0] ? providers[0].key : '';
                }
            });
        }

        function renderActiveProviderSelect(channel, selectId, emptyLabel) {
            var select = document.getElementById(selectId);
            if (!select) {
                return;
            }

            var providers = providersForChannel(channel);
            var options = ['<option value="">' + esc(emptyLabel) + '</option>'];
            options = options.concat(providers.map(function (provider) {
                return '<option value="' + esc(provider.key) + '">' + esc(provider.key) + '</option>';
            }));
            select.innerHTML = options.join('');
            select.value = providerState.activeProviderMap[channel] || '';
        }

        function fieldInputHtml(provider, index, field) {
            var meta = providerFieldMeta[field] || { label: field, type: 'text', placeholder: '' };
            var value = provider.config && provider.config[field] !== undefined ? provider.config[field] : '';
            var isSensitive = providerDefinitions[provider.provider_type].sensitiveFields.indexOf(field) !== -1;
            var hasStoredValue = isSensitive && value !== '' && value !== null && value !== undefined;
            var fieldId = 'settingsProvider' + index + '_' + field;

            if (meta.type === 'select') {
                return '<div class="col-md-6">' +
                    '<label for="' + esc(fieldId) + '" class="form-label">' + esc(meta.label) + '</label>' +
                    '<select id="' + esc(fieldId) + '" class="form-select" data-provider-index="' + esc(index) + '" data-config-field="' + esc(field) + '">' +
                    meta.options.map(function (option) {
                        var selected = String(value) === String(option) ? ' selected' : '';
                        var label = option === '' ? 'None' : String(option).toUpperCase();
                        return '<option value="' + esc(option) + '"' + selected + '>' + esc(label) + '</option>';
                    }).join('') +
                    '</select>' +
                    '</div>';
            }

            return '<div class="col-md-6">' +
                '<label for="' + esc(fieldId) + '" class="form-label">' + esc(meta.label) + '</label>' +
                '<input id="' + esc(fieldId) + '" type="' + esc(meta.type || 'text') + '" class="form-control" ' +
                'data-provider-index="' + esc(index) + '" data-config-field="' + esc(field) + '" ' +
                'value="' + esc(isSensitive ? '' : value) + '" placeholder="' + esc(meta.placeholder || '') + '">' +
                (hasStoredValue ? '<div class="form-text">Leave blank to keep the current saved value.</div>' : '') +
                '</div>';
        }

        function renderProviderCard(provider, index) {
            var definition = providerDefinitions[provider.provider_type];
            var supported = definition.channels.map(function (channel) {
                return channelBadge(channel);
            }).join(' ');
            var fields = providerFields(provider.provider_type).map(function (field) {
                return fieldInputHtml(provider, index, field);
            }).join('');

            return '<section class="card border" data-provider-index="' + esc(index) + '">' +
                '<div class="card-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">' +
                '<div>' +
                '<h6 class="mb-1">' + esc(provider.key || ('Provider ' + (index + 1))) + '</h6>' +
                '<div class="small text-muted">Supported channels: ' + supported + '</div>' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-provider-action="remove" data-provider-index="' + esc(index) + '">' +
                '<i class="bi bi-trash me-1"></i>Remove' +
                '</button>' +
                '</div>' +
                '<div class="card-body">' +
                '<div class="row g-4">' +
                '<div class="col-md-4">' +
                '<label class="form-label">Provider key</label>' +
                '<input type="text" class="form-control" data-provider-index="' + esc(index) + '" data-provider-field="key" value="' + esc(provider.key) + '" placeholder="e.g. smtp_main">' +
                '</div>' +
                '<div class="col-md-4">' +
                '<label class="form-label">Provider type</label>' +
                '<select class="form-select" data-provider-index="' + esc(index) + '" data-provider-field="provider_type">' +
                Object.keys(providerDefinitions).map(function (type) {
                    var selected = provider.provider_type === type ? ' selected' : '';
                    return '<option value="' + esc(type) + '"' + selected + '>' + esc(providerDefinitions[type].label) + '</option>';
                }).join('') +
                '</select>' +
                '</div>' +
                '<div class="col-md-2">' +
                '<label class="form-label">Priority</label>' +
                '<input type="number" min="1" class="form-control" data-provider-index="' + esc(index) + '" data-provider-field="priority" value="' + esc(provider.priority) + '">' +
                '</div>' +
                '<div class="col-md-2 d-flex align-items-end">' +
                '<div class="form-check form-switch mb-2">' +
                '<input type="checkbox" class="form-check-input" data-provider-index="' + esc(index) + '" data-provider-field="enabled"' + (provider.enabled ? ' checked' : '') + '>' +
                '<label class="form-check-label">Enabled</label>' +
                '</div>' +
                '</div>' +
                fields +
                '</div>' +
                '</div>' +
                '</section>';
        }

        function renderProviderRegistry() {
            ensureActiveProviderMap();

            renderActiveProviderSelect('email', 'settingsActiveEmailProvider', 'No email provider selected');
            renderActiveProviderSelect('sms', 'settingsActiveSmsProvider', 'No SMS provider selected');
            renderActiveProviderSelect('whatsapp', 'settingsActiveWhatsappProvider', 'No WhatsApp provider selected');

            var list = document.getElementById('settingsProviderRegistryList');
            var emptyState = document.getElementById('settingsProviderRegistryEmpty');
            if (!list || !emptyState) {
                return;
            }

            if (!providerState.providerList.length) {
                list.innerHTML = '';
                emptyState.classList.remove('d-none');
                return;
            }

            emptyState.classList.add('d-none');
            list.innerHTML = providerState.providerList.map(function (provider, index) {
                return renderProviderCard(provider, index);
            }).join('');
        }

        function syncProviderState(values) {
            providerState.providerList = normalizeProviderList(values.provider_list || []);
            providerState.activeProviderMap = clone(values.active_provider_per_channel || {});
            renderProviderRegistry();
        }

        function buildProviderPayload() {
            var providerList = normalizeProviderList(providerState.providerList || []);
            var activeProviderMap = {
                email: String(getFieldValue('settingsActiveEmailProvider') || '').trim(),
                sms: String(getFieldValue('settingsActiveSmsProvider') || '').trim(),
                whatsapp: String(getFieldValue('settingsActiveWhatsappProvider') || '').trim()
            };

            return {
                providerList: providerList,
                activeProviderMap: activeProviderMap
            };
        }

        function nextProviderKey(type) {
            var prefix = type === 'twilio' ? 'twilio' : 'smtp';
            var index = 1;
            var candidate = prefix + '_' + index;

            while (providerState.providerList.some(function (provider) { return provider.key === candidate; })) {
                index += 1;
                candidate = prefix + '_' + index;
            }

            return candidate;
        }

        function addProvider(type) {
            var nextIndex = providerState.providerList.length + 1;
            providerState.providerList.push(normalizeProvider({
                key: nextProviderKey(type),
                provider_type: type,
                enabled: true,
                priority: nextIndex,
                config: {}
            }, providerState.providerList.length));
            renderProviderRegistry();
        }

        function removeProvider(index) {
            providerState.providerList.splice(index, 1);
            providerState.providerList = normalizeProviderList(providerState.providerList);
            renderProviderRegistry();
        }

        function updateProviderField(index, field, value, rerender) {
            var provider = providerState.providerList[index];
            if (!provider) {
                return;
            }

            var previousKey = provider.key;

            if (field === 'enabled') {
                provider.enabled = !!value;
            } else if (field === 'priority') {
                provider.priority = parseInt(value, 10) || (index + 1);
            } else if (field === 'provider_type') {
                provider.provider_type = providerDefinitions[value] ? value : 'smtp';
                provider.channels = supportedChannels(provider.provider_type);
                provider.config = normalizeProvider(provider, index).config;
                renderProviderRegistry();
                return;
            } else {
                provider[field] = String(value || '').trim();
            }

            if (field === 'key' && previousKey !== provider.key) {
                ['email', 'sms', 'whatsapp'].forEach(function (channel) {
                    if (providerState.activeProviderMap[channel] === previousKey) {
                        providerState.activeProviderMap[channel] = provider.key;
                    }
                });
            }

            if (rerender) {
                renderProviderRegistry();
            }
        }

        function updateProviderConfig(index, field, value) {
            var provider = providerState.providerList[index];
            if (!provider) {
                return;
            }

            if (!provider.config) {
                provider.config = {};
            }

            if (value === '' && providerDefinitions[provider.provider_type].sensitiveFields.indexOf(field) !== -1) {
                return;
            }

            if (field === 'port') {
                provider.config[field] = value === '' ? '' : (parseInt(value, 10) || '');
            } else {
                provider.config[field] = value;
            }
        }

        function validateProviderPayload(payload) {
            var seenKeys = {};

            for (var i = 0; i < payload.providerList.length; i += 1) {
                var provider = payload.providerList[i];
                var definition = providerDefinitions[provider.provider_type];
                var key = String(provider.key || '').trim();

                if (!key) {
                    return 'Every provider must have a provider key.';
                }

                if (seenKeys[key]) {
                    return 'Provider keys must be unique.';
                }
                seenKeys[key] = true;

                if (!provider.enabled) {
                    continue;
                }

                for (var j = 0; j < definition.requiredFields.length; j += 1) {
                    var field = definition.requiredFields[j];
                    var value = provider.config ? provider.config[field] : '';
                    if (value === '' || value === null || value === undefined) {
                        return definition.label + ' provider "' + key + '" is missing ' + field + '.';
                    }
                }
            }

            return null;
        }

        function loadSettings() {
            App.api.get('/api/v1/notification/settings').then(function (res) {
                if (!res.ok || !res.data) {
                    App.notify.error('Failed to load notification settings.');
                    return;
                }
                var values = res.data.values || {};
                setFieldValue('settingsDefaultFromName', values.default_from_name || '');
                setFieldValue('settingsDefaultFromEmail', values.default_from_email || '');
                setFieldValue('settingsDefaultReplyToName', values.default_reply_to_name || '');
                setFieldValue('settingsDefaultReplyToEmail', values.default_reply_to_email || '');
                setFieldValue('settingsQueueEnabled', !!values.queue_enabled);
                setFieldValue('settingsFallbackEnabled', !!values.fallback_enabled);
                setFieldValue('settingsRetryMaxAttempts', values.retry_max_attempts || 3);
                setFieldValue('settingsRetryDelaySeconds', values.retry_delay_seconds || 60);
                setFieldValue('settingsHttpTimeout', values.http_timeout_seconds || 10);
                setFieldValue('settingsHttpConnectTimeout', values.http_connect_timeout_seconds || 5);
                syncProviderState(values);
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
                    default_reply_to_name: getFieldValue('settingsDefaultReplyToName') || '',
                    default_reply_to_email: getFieldValue('settingsDefaultReplyToEmail') || '',
                    queue_enabled: !!getFieldValue('settingsQueueEnabled'),
                    fallback_enabled: !!getFieldValue('settingsFallbackEnabled'),
                    retry_max_attempts: parseInt(getFieldValue('settingsRetryMaxAttempts'), 10) || 3,
                    retry_delay_seconds: parseInt(getFieldValue('settingsRetryDelaySeconds'), 10) || 60,
                    http_timeout_seconds: parseInt(getFieldValue('settingsHttpTimeout'), 10) || 10,
                    http_connect_timeout_seconds: parseInt(getFieldValue('settingsHttpConnectTimeout'), 10) || 5,
                };

                var providerPayload = buildProviderPayload();
                var providerError = validateProviderPayload(providerPayload);
                if (providerError) {
                    App.notify.warning(providerError);
                    return;
                }

                data.provider_list = providerPayload.providerList;
                data.active_provider_per_channel = providerPayload.activeProviderMap;

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

            ['settingsActiveEmailProvider', 'settingsActiveSmsProvider', 'settingsActiveWhatsappProvider'].forEach(function (id) {
                var select = document.getElementById(id);
                if (!select) {
                    return;
                }
                select.addEventListener('change', function () {
                    if (id === 'settingsActiveEmailProvider') {
                        providerState.activeProviderMap.email = select.value || '';
                    } else if (id === 'settingsActiveSmsProvider') {
                        providerState.activeProviderMap.sms = select.value || '';
                    } else if (id === 'settingsActiveWhatsappProvider') {
                        providerState.activeProviderMap.whatsapp = select.value || '';
                    }
                });
            });

            var addSmtpProviderBtn = document.getElementById('settingsAddSmtpProviderBtn');
            if (addSmtpProviderBtn) {
                addSmtpProviderBtn.addEventListener('click', function () {
                    addProvider('smtp');
                });
            }

            var addTwilioProviderBtn = document.getElementById('settingsAddTwilioProviderBtn');
            if (addTwilioProviderBtn) {
                addTwilioProviderBtn.addEventListener('click', function () {
                    addProvider('twilio');
                });
            }

            var registry = document.getElementById('settingsProviderRegistryList');
            if (registry) {
                registry.addEventListener('click', function (event) {
                    var removeBtn = event.target.closest('[data-provider-action="remove"]');
                    if (!removeBtn) {
                        return;
                    }
                    removeProvider(parseInt(removeBtn.getAttribute('data-provider-index'), 10));
                });

                registry.addEventListener('input', function (event) {
                    var target = event.target;
                    if (!target) {
                        return;
                    }

                    var providerIndex = parseInt(target.getAttribute('data-provider-index'), 10);
                    if (Number.isNaN(providerIndex)) {
                        return;
                    }

                    var providerField = target.getAttribute('data-provider-field');
                    var configField = target.getAttribute('data-config-field');

                    if (providerField) {
                        updateProviderField(providerIndex, providerField, target.type === 'checkbox' ? target.checked : target.value, false);
                    } else if (configField) {
                        updateProviderConfig(providerIndex, configField, target.value);
                    }
                });

                registry.addEventListener('change', function (event) {
                    var target = event.target;
                    if (!target) {
                        return;
                    }

                    var providerIndex = parseInt(target.getAttribute('data-provider-index'), 10);
                    if (Number.isNaN(providerIndex)) {
                        return;
                    }

                    var providerField = target.getAttribute('data-provider-field');
                    var configField = target.getAttribute('data-config-field');

                    if (providerField) {
                        updateProviderField(providerIndex, providerField, target.type === 'checkbox' ? target.checked : target.value, providerField === 'key' || providerField === 'provider_type');
                    } else if (configField) {
                        updateProviderConfig(providerIndex, configField, target.value);
                    }
                });
            }
        }

        loadSettings();
        initSettingsForm();
    }

})();
