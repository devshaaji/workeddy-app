(function () {
    'use strict';

    if (!window.App) {
        return;
    }

    var utils = App.utils || {};
    var state = {
        sortKey: 'createdAt',
        sortDir: 'desc',
        offset: 0,
        limit: 100,
        total: 0,
        items: []
    };

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function escape(value) {
        if (utils.escapeHtml) {
            return utils.escapeHtml(value === null || value === undefined ? '' : String(value));
        }

        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(value) {
        if (!value) {
            return '--';
        }
        return utils.formatDate ? utils.formatDate(value) : String(value);
    }

    function titleCase(value) {
        return String(value || '')
            .replace(/[._-]/g, ' ')
            .replace(/\b[a-z]/g, function (match) { return match.toUpperCase(); });
    }

    function showFeedback(selector, level, message) {
        var target = qs(selector);
        if (!target) {
            return;
        }

        target.className = 'alert alert-' + (level === 'danger' ? 'danger' : level) + ' mb-4';
        target.textContent = message;
        target.classList.remove('d-none');
    }

    function clearFeedback(selector) {
        var target = qs(selector);
        if (!target) {
            return;
        }

        target.textContent = '';
        target.classList.add('d-none');
    }

    function getFormValues(form) {
        var data = {};
        if (!form) {
            return data;
        }

        Array.prototype.forEach.call(form.elements, function (field) {
            if (!field.name) {
                return;
            }

            var value = typeof field.value === 'string' ? field.value.trim() : field.value;
            if (value === '') {
                return;
            }

            data[field.name] = value;
        });

        return data;
    }

    function toComparable(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).toLowerCase();
    }

    function sortItems(items) {
        var key = state.sortKey;
        var dir = state.sortDir === 'asc' ? 1 : -1;

        return items.slice().sort(function (left, right) {
            var a = left[key];
            var b = right[key];

            if (key === 'createdAt') {
                a = Date.parse(a || '') || 0;
                b = Date.parse(b || '') || 0;
            } else {
                a = toComparable(a);
                b = toComparable(b);
            }

            if (a < b) {
                return -1 * dir;
            }
            if (a > b) {
                return 1 * dir;
            }
            return 0;
        });
    }

    function updateSortIndicators(screen) {
        qsa('[data-sort]', screen).forEach(function (button) {
            var key = button.getAttribute('data-sort');
            var label = button.getAttribute('data-base-label') || button.textContent.replace(/\s+[<>]$/, '');
            button.setAttribute('data-base-label', label);
            button.textContent = label + (key === state.sortKey ? (state.sortDir === 'asc' ? ' <' : ' >') : '');
        });
    }

    function renderLogTable(screen) {
        var body = qs('[data-log-table-body]', screen);
        var count = qs('#audit-log-result-count', screen);
        var pagination = qs('#audit-log-pagination', screen);
        if (!body) {
            return;
        }

        var sorted = sortItems(state.items);
        if (!sorted.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-5">No audit events matched the current filters.</td></tr>';
        } else {
            body.innerHTML = sorted.map(function (item) {
                var actor = item.actorLabel || item.actorName || item.actorUsername || (item.actorId !== null && item.actorId !== undefined ? 'User #' + item.actorId : 'System');
                return '<tr>' +
                    '<td><div class="fw-medium">' + escape(formatDate(item.createdAt)) + '</div><div class="text-muted small">' + escape(item.id) + '</div></td>' +
                    '<td><div class="fw-medium">' + escape(actor) + '</div><div class="text-muted small">' + escape(item.actorUsername || '') + '</div></td>' +
                    '<td>' + escape(item.module || '--') + '</td>' +
                    '<td>' + escape(titleCase(item.action || '--')) + '</td>' +
                    '<td><div class="fw-medium">' + escape(item.entityType || '--') + '</div><div class="text-muted small">' + escape(item.entityId || '--') + '</div></td>' +
                    '<td>' + escape(item.ipAddress || '--') + '</td>' +
                    '<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/audit/logs/' + encodeURIComponent(item.id) + '">View</a></td>' +
                '</tr>';
            }).join('');
        }

        if (count) {
            var from = sorted.length ? state.offset + 1 : 0;
            var to = state.offset + sorted.length;
            count.textContent = 'Showing ' + from + '-' + to + ' of ' + state.total + ' audit events';
        }

        if (pagination) {
            var previousDisabled = state.offset <= 0 ? ' disabled' : '';
            var nextDisabled = (state.offset + state.limit) >= state.total ? ' disabled' : '';
            pagination.innerHTML = '' +
                '<li class="page-item' + previousDisabled + '"><button class="page-link" type="button" data-page="prev">Previous</button></li>' +
                '<li class="page-item disabled"><span class="page-link">' + (sorted.length ? (Math.floor(state.offset / state.limit) + 1) : 1) + '</span></li>' +
                '<li class="page-item' + nextDisabled + '"><button class="page-link" type="button" data-page="next">Next</button></li>';
        }

        updateSortIndicators(screen);
    }

    function fetchLogIndex(resetOffset) {
        var screen = qs('[data-log-screen]');
        if (!screen) {
            return;
        }

        var form = qs('[data-log-filters]', screen);
        var params = getFormValues(form);
        state.limit = Math.max(1, Number(params.limit || state.limit || 100));
        if (resetOffset) {
            state.offset = 0;
        }
        params.limit = state.limit;
        params.offset = state.offset;

        clearFeedback('#audit-log-feedback');
        App.api.get(screen.getAttribute('data-api-index'), params).then(function (res) {
            if (!res.ok) {
                showFeedback('#audit-log-feedback', 'danger', res.message || 'Unable to load audit logs.');
                return;
            }

            state.items = Array.isArray(res.data) ? res.data : [];
            state.total = res.meta && typeof res.meta.total !== 'undefined' ? Number(res.meta.total) : state.items.length;
            renderLogTable(screen);
        });
    }

    function initLogIndex() {
        var screen = qs('[data-log-screen]');
        if (!screen || screen.getAttribute('data-logs-initialized') === '1') {
            return;
        }
        screen.setAttribute('data-logs-initialized', '1');

        var form = qs('[data-log-filters]', screen);
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                fetchLogIndex(true);
            });
        }

        var refresh = qs('#audit-log-refresh', screen);
        if (refresh) {
            refresh.addEventListener('click', function () {
                fetchLogIndex(false);
            });
        }

        var reset = qs('[data-log-reset]', screen);
        if (reset && form) {
            reset.addEventListener('click', function () {
                form.reset();
                state.sortKey = 'createdAt';
                state.sortDir = 'desc';
                fetchLogIndex(true);
            });
        }

        qsa('[data-sort]', screen).forEach(function (button) {
            button.addEventListener('click', function () {
                var key = button.getAttribute('data-sort');
                if (state.sortKey === key) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortKey = key;
                    state.sortDir = key === 'createdAt' ? 'desc' : 'asc';
                }
                renderLogTable(screen);
            });
        });

        var pagination = qs('#audit-log-pagination', screen);
        if (pagination) {
            pagination.addEventListener('click', function (event) {
                var button = event.target.closest('[data-page]');
                if (!button || button.closest('.disabled')) {
                    return;
                }

                if (button.getAttribute('data-page') === 'prev') {
                    state.offset = Math.max(0, state.offset - state.limit);
                } else {
                    state.offset = state.offset + state.limit;
                }
                fetchLogIndex(false);
            });
        }

        fetchLogIndex(true);
    }

    function prettyJson(value) {
        return JSON.stringify(value || {}, null, 2);
    }

    function copyText(value, successMessage) {
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            App.notify.error('Clipboard copy is not available in this browser.');
            return;
        }

        navigator.clipboard.writeText(value).then(function () {
            App.notify.success(successMessage);
        }).catch(function () {
            App.notify.error('Copy failed.');
        });
    }

    function loadLogDetail() {
        var screen = qs('#audit-log-detail-screen');
        if (!screen) {
            return;
        }

        var logId = screen.getAttribute('data-audit-log-id') || '';
        if (!logId) {
            showFeedback('#audit-log-detail-feedback', 'danger', 'Audit log identifier is missing.');
            return;
        }

        App.api.get((screen.getAttribute('data-api-show-base') || '') + encodeURIComponent(logId)).then(function (res) {
            if (!res.ok) {
                showFeedback('#audit-log-detail-feedback', 'danger', res.message || 'Unable to load audit log detail.');
                return;
            }

            var item = res.data || {};
            var actor = item.actorLabel || item.actorName || item.actorUsername || (item.actorId !== null && item.actorId !== undefined ? 'User #' + item.actorId : 'System');
            var map = {
                '#audit-log-detail-id': item.id || '--',
                '#audit-log-detail-actor-id': actor,
                '#audit-log-detail-module': item.module || '--',
                '#audit-log-detail-action': titleCase(item.action || '--'),
                '#audit-log-detail-entity-type': item.entityType || '--',
                '#audit-log-detail-entity-id': item.entityId || '--',
                '#audit-log-detail-ip-address': item.ipAddress || '--',
                '#audit-log-detail-created-at': formatDate(item.createdAt)
            };

            Object.keys(map).forEach(function (selector) {
                var target = qs(selector, screen);
                if (target) {
                    target.textContent = map[selector];
                }
            });

            var beforeText = prettyJson(item.before);
            var afterText = prettyJson(item.after);
            var beforeEl = qs('#audit-log-detail-before', screen);
            var afterEl = qs('#audit-log-detail-after', screen);
            if (beforeEl) {
                beforeEl.textContent = beforeText;
            }
            if (afterEl) {
                afterEl.textContent = afterText;
            }

            var exportButton = qs('#audit-log-detail-export', screen);
            if (exportButton) {
                exportButton.disabled = false;
                exportButton.addEventListener('click', function () {
                    window.location.href = (screen.getAttribute('data-web-export') || '/audit/export') + '?entityId=' + encodeURIComponent(item.entityId || '') + '&entityType=' + encodeURIComponent(item.entityType || '');
                });
            }

            var copyBefore = qs('#audit-log-copy-before', screen);
            var copyAfter = qs('#audit-log-copy-after', screen);
            if (copyBefore) {
                copyBefore.addEventListener('click', function () {
                    copyText(beforeText, 'Before state copied.');
                });
            }
            if (copyAfter) {
                copyAfter.addEventListener('click', function () {
                    copyText(afterText, 'After state copied.');
                });
            }
        });
    }

    function buildQueryString(params) {
        return Object.keys(params).filter(function (key) {
            return params[key] !== null && params[key] !== undefined && params[key] !== '';
        }).map(function (key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');
    }

    function initExport() {
        var screen = qs('#audit-export-screen');
        if (!screen) {
            return;
        }

        var button = qs('#audit-export-prepare', screen);
        var form = qs('#audit-export-filters', screen);
        if (!button || !form || screen.getAttribute('data-export-initialized') === '1') {
            return;
        }
        screen.setAttribute('data-export-initialized', '1');

        button.addEventListener('click', function () {
            var params = getFormValues(form);
            var query = buildQueryString(params);
            window.location.href = screen.getAttribute('data-api-export') + (query ? ('?' + query) : '');
        });
    }

    function initSettings() {
        var form = qs('#audit-settings-form');
        if (!form || form.getAttribute('data-settings-initialized') === '1') {
            return;
        }
        form.setAttribute('data-settings-initialized', '1');

        var endpoint = form.getAttribute('data-endpoint');
        var resetButton = qs('#audit-settings-reset');
        var saveButton = qs('#audit-settings-save');
        var defaults = null;

        function applyValues(values, derived) {
            values = values || {};
            derived = derived || {};

            var retention = qs('#audit-settings-retention-days');
            var maxQuery = qs('#audit-settings-max-query-results');
            var mask = qs('#audit-settings-mask-sensitive-fields');
            var recordIp = qs('#audit-settings-record-ip-address');
            var storeDiffs = qs('#audit-settings-store-state-diffs');

            if (retention) {
                retention.value = values.retention_days || derived.retentionDays || '';
            }
            if (maxQuery) {
                maxQuery.value = values.max_query_results || derived.maxQueryResults || '';
            }
            if (mask) {
                mask.checked = String(values.mask_sensitive_fields) === '1' || values.mask_sensitive_fields === true;
            }
            if (recordIp) {
                recordIp.checked = String(values.record_ip_address) === '1' || values.record_ip_address === true;
            }
            if (storeDiffs) {
                storeDiffs.checked = String(values.store_state_diffs) === '1' || values.store_state_diffs === true;
            }
        }

        function loadSettings() {
            clearFeedback('#audit-settings-feedback');
            App.api.get(endpoint).then(function (res) {
                if (!res.ok) {
                    showFeedback('#audit-settings-feedback', 'danger', res.message || 'Unable to load audit settings.');
                    return;
                }

                var data = res.data || {};
                defaults = {};
                Array.isArray(data.definitions || []).forEach(function (definition) {
                    defaults[definition.key] = definition.default;
                });
                applyValues(data.values || {}, data.derived || {});
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var payload = {
                retention_days: Number(qs('#audit-settings-retention-days').value || 0) || null,
                max_query_results: Number(qs('#audit-settings-max-query-results').value || 0) || null,
                mask_sensitive_fields: !!qs('#audit-settings-mask-sensitive-fields').checked,
                record_ip_address: !!qs('#audit-settings-record-ip-address').checked,
                store_state_diffs: !!qs('#audit-settings-store-state-diffs').checked
            };

            App.ui.setButtonLoading(saveButton, true, 'Saving...');
            App.api.put(endpoint, payload).then(function (res) {
                App.ui.setButtonLoading(saveButton, false);
                if (!res.ok) {
                    showFeedback('#audit-settings-feedback', 'danger', res.message || 'Unable to save audit settings.');
                    return;
                }

                showFeedback('#audit-settings-feedback', 'success', 'Audit settings saved.');
                var data = res.data || {};
                applyValues(data.values || {}, data.derived || {});
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                if (defaults) {
                    applyValues(defaults, {});
                }
            });
        }

        loadSettings();
    }

    initLogIndex();
    loadLogDetail();
    initExport();
    initSettings();
})();
