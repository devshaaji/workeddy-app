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

    function getUserMeta(name) {
        var m = document.querySelector('meta[name="' + name + '"]');
        return m ? m.getAttribute('content') : '';
    }

    var orgUuid = getUserMeta('org-uuid');

    /* ═══════════════════════════════════════════════════════════════════════
       PrivacyConsent — Consent capture + records table
       ═══════════════════════════════════════════════════════════════════════ */

    var consentPage = document.getElementById('privacyConsentPage');
    if (consentPage && window.App) {

        var consentApiPath = '/api/v1/privacy/video-consents' + (orgUuid ? '?organizationUuid=' + encodeURIComponent(orgUuid) : '');

        /* ── Load assessment options ─────────────────────────────────────── */
        function loadAssessmentOptions() {
            var formSelector = document.getElementById('consentAssessmentUuid');
            var filterSelector = document.getElementById('consentAssessmentFilter');

            var apiPath = '/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/assessments?limit=200';
            App.api.get(apiPath).then(function (res) {
                if (!res.ok || !Array.isArray(res.data)) {
                    var fallback = '<option value="">Unable to load assessments</option>';
                    if (formSelector) formSelector.innerHTML = fallback;
                    if (filterSelector) filterSelector.innerHTML = '<option value="">All assessments</option>';
                    return;
                }
                var formHtml = '<option value="">Select assessment...</option>';
                var filterHtml = '<option value="">All assessments</option>';
                res.data.forEach(function (a) {
                    var label = asText(a.title || a.taskName || a.task_description || 'Assessment', '');
                    var date = a.createdAt ? App.utils.formatDate(a.createdAt) : '';
                    var detail = date ? ' (' + date + ')' : '';
                    var opt = '<option value="' + esc(a.uuid, '') + '">' + esc(label + detail) + '</option>';
                    formHtml += opt;
                    filterHtml += opt;
                });
                if (formSelector) formSelector.innerHTML = formHtml;
                if (filterSelector) filterSelector.innerHTML = filterHtml;
            });
        }

        /* ── Stats ────────────────────────────────────────────────────────── */
        function updateConsentStats(records) {
            var total = records.length;
            setText('consentStatTotal', total);

            // Count unique assessments
            var seen = {};
            records.forEach(function (r) { seen[r.assessmentUuid || r.assessment_uuid] = true; });
            setText('consentStatAssessments', Object.keys(seen).length);

            // Count consents with accepted notice
            var accepted = records.filter(function (r) { return r.acceptedNotice || r.accepted_notice; }).length;
            setText('consentStatAccepted', accepted);

            // Count pending (if any way to determine — placeholder)
            setText('consentStatPending', '—');
        }

        /* ── Consent table ────────────────────────────────────────────────── */
        var consentTable = null;

        function renderConsentRow(record) {
            var assessmentLabel = asText(record.assessmentUuid || record.assessment_uuid, '—');
            var consentVersion = asText(record.textVersion || record.text_version, '—');
            var acceptedAt = record.acceptedAt || record.accepted_at || '';
            var dateFormatted = acceptedAt ? App.utils.formatDate(acceptedAt) : '—';
            var recordedBy = asText(record.userId || record.user_id, '—');
            var ip = asText(record.ipAddress || record.ip_address, '—');
            var userAgent = asText(record.userAgent || record.user_agent, '—');

            var detailHtml =
                '<dl class="row mb-0 small text-start">' +
                '<dt class="col-4 text-muted">Accepted at</dt><dd class="col-8">' + esc(dateFormatted) + '</dd>' +
                '<dt class="col-4 text-muted">Assessment</dt><dd class="col-8 text-break">' + esc(assessmentLabel) + '</dd>' +
                '<dt class="col-4 text-muted">Consent version</dt><dd class="col-8 text-break">' + esc(consentVersion) + '</dd>' +
                '<dt class="col-4 text-muted">Recorded by</dt><dd class="col-8">' + esc(recordedBy) + '</dd>' +
                '<dt class="col-4 text-muted">IP address</dt><dd class="col-8">' + esc(ip) + '</dd>' +
                '<dt class="col-4 text-muted">User agent</dt><dd class="col-8 text-break">' + esc(userAgent.length > 80 ? userAgent.substring(0, 80) + '…' : userAgent) + '</dd>' +
                '</dl>';

            return '<tr>' +
                '<td><a href="#" class="consent-detail-trigger text-decoration-none fw-semibold" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#consentDetailModal">' +
                esc(dateFormatted) + '</a></td>' +
                '<td class="text-break">' + esc(assessmentLabel) + '</td>' +
                '<td class="text-break">' + esc(consentVersion.length > 50 ? consentVersion.substring(0, 50) + '…' : consentVersion) + '</td>' +
                '<td>' + esc(recordedBy) + '</td>' +
                '<td class="text-end">' +
                '<button class="btn btn-sm btn-outline-secondary consent-detail-trigger" type="button" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#consentDetailModal">' +
                '<i class="bi bi-eye"></i></button>' +
                '</td>' +
                '</tr>';
        }

        function initConsentTable() {
            consentTable = App.tables.createAdvanced({
                card: '#consentRecordsCard',
                tbody: '#consentRecordsBody',
                endpoint: consentApiPath,
                colspan: 5,
                defaultSort: 'acceptedAt',
                sortDir: 'desc',
                pageSize: 15,
                emptyTitle: 'No consent records yet',
                emptySubtitle: 'Record your first video consent using the form above.',
                renderRow: function (record) {
                    return renderConsentRow(record);
                },
                filterRecord: function (record, filters) {
                    if (filters.assessment && record.assessmentUuid !== filters.assessment && record.assessment_uuid !== filters.assessment) {
                        return false;
                    }
                    return true;
                },
                afterLoad: function (records) {
                    updateConsentStats(records);
                },
                afterRender: function () {
                    // Attach detail modal triggers
                    document.querySelectorAll('.consent-detail-trigger').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                            var record;
                            try {
                                record = JSON.parse(btn.getAttribute('data-record'));
                            } catch (_) { return; }
                            showConsentDetail(record);
                        });
                    });
                }
            });
        }

        function showConsentDetail(record) {
            var assessmentLabel = asText(record.assessmentUuid || record.assessment_uuid, '—');
            var consentVersion = asText(record.textVersion || record.text_version, '—');
            var acceptedAt = record.acceptedAt || record.accepted_at || '';
            var dateFormatted = acceptedAt ? App.utils.formatDate(acceptedAt) : '—';
            var recordedBy = asText(record.userId || record.user_id, '—');
            var ip = asText(record.ipAddress || record.ip_address, '—');
            var userAgent = asText(record.userAgent || record.user_agent, '—');
            var storageFile = asText(record.storageFileUuid || record.storage_file_uuid, 'Not linked');

            setHtml('consentDetailBody',
                '<dl class="row mb-0">' +
                '<dt class="col-sm-4 text-muted">Accepted at</dt><dd class="col-sm-8">' + esc(dateFormatted) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Assessment</dt><dd class="col-sm-8 text-break">' + esc(assessmentLabel) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Consent version</dt><dd class="col-sm-8 text-break">' + esc(consentVersion) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Recorded by (user ID)</dt><dd class="col-sm-8">' + esc(recordedBy) + '</dd>' +
                '<dt class="col-sm-4 text-muted">IP address</dt><dd class="col-sm-8">' + esc(ip) + '</dd>' +
                '<dt class="col-sm-4 text-muted">User agent</dt><dd class="col-sm-8 text-break">' + esc(userAgent) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Storage file</dt><dd class="col-sm-8 text-break">' + esc(storageFile) + '</dd>' +
                '</dl>'
            );
        }

        /* ── Consent form ─────────────────────────────────────────────────── */
        function initConsentForm() {
            var form = document.getElementById('consentForm');
            if (!form) return;

            App.forms.bindAjaxForm(form, {
                url: '/api/v1/privacy/video-consents',
                method: 'POST',
                submitBtn: '#consentSubmitBtn',
                onSuccess: function () {
                    App.notify.success('Video consent recorded successfully.');
                    form.reset();
                    if (consentTable) {
                        consentTable.refresh();
                    }
                },
                onError: function (res) {
                    App.notify.error(res.message || 'Failed to record consent.');
                }
            });
        }

        /* ── Filters ──────────────────────────────────────────────────────── */
        function initConsentFilters() {
            var assessmentFilter = document.getElementById('consentAssessmentFilter');
            var clearBtn = document.getElementById('consentClearFilters');

            if (assessmentFilter) {
                assessmentFilter.addEventListener('change', function () {
                    if (consentTable) {
                        consentTable.applyFilters({ assessment: this.value });
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (assessmentFilter) assessmentFilter.value = '';
                    if (consentTable) {
                        consentTable.applyFilters({});
                    }
                });
            }
        }

        /* ── Init ─────────────────────────────────────────────────────────── */
        loadAssessmentOptions();
        initConsentForm();
        initConsentTable();
        initConsentFilters();
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PrivacyRetention — Retention policy view + update
       ═══════════════════════════════════════════════════════════════════════ */

    var retentionPage = document.getElementById('privacyRetentionPage');
    if (retentionPage && window.App) {

        var retentionApiPath = '/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/privacy/retention-policy';

        function loadRetentionPolicy() {
            App.api.get(retentionApiPath).then(function (res) {
                if (!res.ok || !res.data) {
                    setText('retentionCurrentPolicy', 'No policy configured yet');
                    setText('retentionCurrentDays', '—');
                    setText('retentionCurrentScreenshots', '—');
                    setText('retentionCurrentEvidence', '—');
                    return;
                }

                var d = res.data;
                var policyLabels = {
                    'retain_for_review': 'Retain for review',
                    'delete_after_processing': 'Delete after processing',
                    'retain_deidentified_only': 'Retain de-identified only'
                };
                setText('retentionCurrentPolicy', policyLabels[d.rawVideoPolicy] || d.rawVideoPolicy);
                setText('retentionCurrentDays', asText(d.retentionDays, '0') + ' days');
                setText('retentionCurrentScreenshots', d.retainScreenshotsOnly ? 'Yes' : 'No');
                setText('retentionCurrentEvidence', d.retainForPilotEvidence ? 'Yes' : 'No');

                // Populate form — radio buttons
                var policyVal = d.rawVideoPolicy || 'retain_for_review';
                var radio = document.querySelector('input[name="rawVideoPolicy"][value="' + policyVal + '"]');
                if (radio) radio.checked = true;

                var screenshots = document.getElementById('retentionScreenshotsOnly');
                if (screenshots) screenshots.checked = !!d.retainScreenshotsOnly;

                var evidence = document.getElementById('retentionPilotEvidence');
                if (evidence) evidence.checked = !!d.retainForPilotEvidence;

                var days = document.getElementById('retentionDays');
                if (days) days.value = d.retentionDays || 90;

                var updatedBy = document.getElementById('retentionUpdatedBy');
                if (updatedBy) setText('retentionUpdatedBy', asText(d.updatedBy, '—'));

                var updatedAt = document.getElementById('retentionUpdatedAt');
                if (updatedAt) {
                    setText('retentionUpdatedAt', d.updatedAt ? App.utils.formatDate(d.updatedAt) : '—');
                }
            });
        }

        function initRetentionForm() {
            var form = document.getElementById('retentionForm');
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (App.forms && App.forms.clearValidationErrors) {
                    App.forms.clearValidationErrors(form);
                }

                var rawPolicy = document.getElementById('retentionRawVideoPolicy');
                var screenshots = document.getElementById('retentionScreenshotsOnly');
                var evidence = document.getElementById('retentionPilotEvidence');
                var days = document.getElementById('retentionDays');

                var data = {
                    rawVideoPolicy: rawPolicy ? rawPolicy.value : 'retain_for_review',
                    retainScreenshotsOnly: screenshots ? screenshots.checked : false,
                    retainForPilotEvidence: evidence ? evidence.checked : false,
                    retentionDays: days ? parseInt(days.value, 10) || 0 : 0,
                };

                var submitBtn = document.getElementById('retentionSaveBtn');
                App.ui.setButtonLoading(submitBtn, true);

                App.api.put(retentionApiPath, data).then(function (res) {
                    App.ui.setButtonLoading(submitBtn, false);
                    if (res.ok) {
                        App.notify.success('Retention policy updated successfully.');
                        loadRetentionPolicy();
                    } else {
                        var rendered = App.forms && App.forms.showValidationErrors
                            ? App.forms.showValidationErrors(form, res.errors || {})
                            : { fieldErrors: {}, formErrors: [] };
                        if (rendered.formErrors.length) {
                            App.notify.error(rendered.formErrors.join(' '));
                        } else if (!Object.keys(rendered.fieldErrors).length) {
                            App.notify.error(res.message || 'Failed to update retention policy.');
                        }
                    }
                });
            });
        }

        function updateRetentionDescription() {
            var rawPolicy = document.getElementById('retentionRawVideoPolicy');
            var desc = document.getElementById('retentionPolicyDescription');
            if (!rawPolicy || !desc) return;

            var descriptions = {
                'retain_for_review': 'Raw video files are kept after processing so reviewers can re-examine them. This provides the highest level of auditability but stores the most data.',
                'delete_after_processing': 'Raw video files are permanently deleted once AI scoring completes. Screenshots and scores are retained. Recommended for standard privacy posture.',
                'retain_deidentified_only': 'After processing, faces are blurred and metadata stripped from all retained media. Raw video is deleted. Best for highest privacy sensitivity.'
            };

            rawPolicy.addEventListener('change', function () {
                desc.textContent = descriptions[this.value] || 'Select a policy above to see its operational impact.';
            });
        }

        loadRetentionPolicy();
        initRetentionForm();
        updateRetentionDescription();
    }

    /* ═══════════════════════════════════════════════════════════════════════
       PrivacyAccessLog — Video access log table
       ═══════════════════════════════════════════════════════════════════════ */

    var accessLogPage = document.getElementById('privacyAccessLogPage');
    if (accessLogPage && window.App) {

        var accessLogApiPath = '/api/v1/privacy/video-access-logs' + (orgUuid ? '?organizationUuid=' + encodeURIComponent(orgUuid) : '');
        var accessLogTable = null;

        function updateAccessLogStats(records) {
            setText('accessLogStatTotal', records.length);

            var users = {};
            var assessments = {};
            records.forEach(function (r) {
                users[r.userId || r.user_id] = true;
                assessments[r.assessmentUuid || r.assessment_uuid] = true;
            });
            setText('accessLogStatUsers', Object.keys(users).length);
            setText('accessLogStatAssessments', Object.keys(assessments).length);
        }

        function renderAccessLogRow(record) {
            var ts = record.accessedAt || record.accessed_at || '';
            var dateFormatted = ts ? App.utils.formatDate(ts) : '—';
            var userId = asText(record.userId || record.user_id, '—');
            var purpose = asText(record.purpose, '—');
            var assessmentLabel = asText(record.assessmentUuid || record.assessment_uuid, '—');
            var ip = asText(record.ipAddress || record.ip_address, '—');

            return '<tr>' +
                '<td><a href="#" class="log-detail-trigger text-decoration-none fw-semibold" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#accessLogDetailModal">' +
                esc(dateFormatted) + '</a></td>' +
                '<td>' + esc(userId) + '</td>' +
                '<td><code class="small">' + esc(purpose) + '</code></td>' +
                '<td class="text-break">' + esc(assessmentLabel) + '</td>' +
                '<td><code class="small">' + esc(ip) + '</code></td>' +
                '<td class="text-end">' +
                '<button class="btn btn-sm btn-outline-secondary log-detail-trigger" type="button" data-record=\'' +
                esc(JSON.stringify(record), '{}') + '\' data-bs-toggle="modal" data-bs-target="#accessLogDetailModal">' +
                '<i class="bi bi-eye"></i></button>' +
                '</td>' +
                '</tr>';
        }

        function showAccessLogDetail(record) {
            var ts = record.accessedAt || record.accessed_at || '';
            var dateFormatted = ts ? App.utils.formatDate(ts) : '—';
            var userId = asText(record.userId || record.user_id, '—');
            var purpose = asText(record.purpose, '—');
            var assessmentLabel = asText(record.assessmentUuid || record.assessment_uuid, '—');
            var ip = asText(record.ipAddress || record.ip_address, '—');
            var userAgent = asText(record.userAgent || record.user_agent, '—');
            var storageFile = asText(record.storageFileUuid || record.storage_file_uuid, '—');

            setHtml('accessLogDetailBody',
                '<dl class="row mb-0">' +
                '<dt class="col-sm-4 text-muted">Accessed at</dt><dd class="col-sm-8">' + esc(dateFormatted) + '</dd>' +
                '<dt class="col-sm-4 text-muted">User</dt><dd class="col-sm-8">' + esc(userId) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Purpose</dt><dd class="col-sm-8"><code>' + esc(purpose) + '</code></dd>' +
                '<dt class="col-sm-4 text-muted">Assessment</dt><dd class="col-sm-8 text-break">' + esc(assessmentLabel) + '</dd>' +
                '<dt class="col-sm-4 text-muted">IP address</dt><dd class="col-sm-8"><code>' + esc(ip) + '</code></dd>' +
                '<dt class="col-sm-4 text-muted">User agent</dt><dd class="col-sm-8 text-break">' + esc(userAgent) + '</dd>' +
                '<dt class="col-sm-4 text-muted">Storage file</dt><dd class="col-sm-8 text-break"><code>' + esc(storageFile) + '</code></dd>' +
                '</dl>'
            );
        }

        function initAccessLogTable() {
            accessLogTable = App.tables.createAdvanced({
                card: '#accessLogCard',
                tbody: '#accessLogBody',
                endpoint: accessLogApiPath,
                colspan: 6,
                defaultSort: 'accessedAt',
                sortDir: 'desc',
                pageSize: 15,
                emptyTitle: 'No video access events',
                emptySubtitle: 'Video access events appear here when users view video evidence.',
                renderRow: function (record) {
                    return renderAccessLogRow(record);
                },
                filterRecord: function (record, filters) {
                    if (filters.purpose && record.purpose !== filters.purpose) {
                        return false;
                    }
                    if (filters.assessment && record.assessmentUuid !== filters.assessment && record.assessment_uuid !== filters.assessment) {
                        return false;
                    }
                    return true;
                },
                afterLoad: function (records) {
                    updateAccessLogStats(records);
                },
                afterRender: function () {
                    document.querySelectorAll('.log-detail-trigger').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            var record;
                            try {
                                record = JSON.parse(btn.getAttribute('data-record'));
                            } catch (_) { return; }
                            showAccessLogDetail(record);
                        });
                    });
                }
            });
        }

        function initAccessLogFilters() {
            var purposeFilter = document.getElementById('accessLogPurposeFilter');
            var searchInput = document.getElementById('accessLogSearch');
            var clearBtn = document.getElementById('accessLogClearFilters');

            function getFilters() {
                var f = {};
                if (purposeFilter && purposeFilter.value) f.purpose = purposeFilter.value;
                if (searchInput && searchInput.value.trim()) f.q = searchInput.value.trim();
                return f;
            }

            function apply() {
                if (accessLogTable) {
                    accessLogTable.applyFilters(getFilters());
                }
            }

            if (purposeFilter) purposeFilter.addEventListener('change', apply);
            if (searchInput) {
                searchInput.addEventListener('input', App.utils.debounce(apply, 300));
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (purposeFilter) purposeFilter.value = '';
                    if (searchInput) searchInput.value = '';
                    if (accessLogTable) accessLogTable.applyFilters({});
                });
            }
        }

        initAccessLogTable();
        initAccessLogFilters();
    }

})();
