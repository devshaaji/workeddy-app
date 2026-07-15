/**
 * national-importance-admin.js — National Statistics management page
 * Route: GET /reporting/national-importance/manage
 * API:    GET    /api/v1/reporting/national-statistics
 *         POST   /api/v1/reporting/national-statistics
 *         PUT    /api/v1/reporting/national-statistics/{uuid}
 *         DELETE /api/v1/reporting/national-statistics/{uuid}
 *
 * Every statistic requires sourceName, sourceYear, and sourceUrl \u2014 the
 * backend (NationalStatisticInput::validate) rejects saves without them, and
 * this script surfaces those validation errors inline on the form.
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('nationalStatisticsAdminPage');
    if (!page) { return; }

    var apiBase = page.getAttribute('data-api-base') || '/api/v1/reporting/national-statistics';

    var PAGE_SIZE = 15;
    var allRecords = [];
    var filteredRecords = [];
    var currentPage = 1;

    var tbody = document.getElementById('statisticsBody');
    var count = document.getElementById('ns-result-count');
    var pageInfo = document.getElementById('ns-page-info');
    var prevBtn = document.getElementById('ns-prev');
    var nextBtn = document.getElementById('ns-next');
    var searchEl = document.getElementById('ns-search');
    var categoryEl = document.getElementById('ns-category-filter');
    var statusEl = document.getElementById('ns-status-filter');

    function e(v) { return App.utils.escapeHtml(v === null || v === undefined ? '' : String(v)); }

    function categoryLabel(key) {
        var select = document.getElementById('nsCategory');
        if (!select) { return e(key); }
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === key) { return select.options[i].textContent; }
        }
        return e(key);
    }

    function publishedBadge(isPublished) {
        return isPublished
            ? '<span class="badge bg-label-success">Published</span>'
            : '<span class="badge bg-label-secondary">Unpublished</span>';
    }

    function renderRows() {
        var start = (currentPage - 1) * PAGE_SIZE;
        var pageRows = filteredRecords.slice(start, start + PAGE_SIZE);
        var total = filteredRecords.length;
        var totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (count) { count.textContent = total; }
        if (pageInfo) { pageInfo.textContent = 'Showing ' + Math.min(start + 1, total) + '\u2013' + Math.min(start + PAGE_SIZE, total) + ' of ' + total; }
        if (prevBtn) { prevBtn.disabled = currentPage <= 1; }
        if (nextBtn) { nextBtn.disabled = currentPage >= totalPages; }

        if (!pageRows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">' +
                '<i class="bi bi-bar-chart-line fs-3 d-block mb-2 opacity-50"></i>' +
                'No statistics found. Add the first sourced statistic to get started.</td></tr>';
            return;
        }

        tbody.innerHTML = pageRows.map(function (r) {
            return '<tr>' +
                '<td><span class="fw-medium">' + e(r.title) + '</span><div class="small text-muted">' + e(r.value) + (r.unit ? ' ' + e(r.unit) : '') + '</div></td>' +
                '<td>' + e(categoryLabel(r.category)) + '</td>' +
                '<td class="small text-muted">' + e(r.sourceName) + ' (' + e(r.sourceYear) + ')</td>' +
                '<td>' + publishedBadge(r.isPublished) + '</td>' +
                '<td class="text-muted small">' + (r.updatedAt ? App.utils.formatDate(r.updatedAt) : '\u2014') + '</td>' +
                '<td class="text-end">' + App.tables.actionDropdown([
                    { label: 'Edit', onclick: 'NationalStatisticsAdmin.openEdit(' + JSON.stringify(r).replace(/"/g, '&quot;') + ')' },
                    { label: 'Delete', class: 'text-danger', onclick: 'NationalStatisticsAdmin.confirmDelete("' + r.uuid + '", "' + e(r.title) + '")' },
                ]) + '</td>' +
            '</tr>';
        }).join('');
    }

    function applyFilters() {
        var q = (searchEl ? searchEl.value : '').toLowerCase().trim();
        var cat = categoryEl ? categoryEl.value : '';
        var st = statusEl ? statusEl.value : '';
        currentPage = 1;
        filteredRecords = allRecords.filter(function (r) {
            var matchQ = !q || (r.title || '').toLowerCase().includes(q) || (r.sourceName || '').toLowerCase().includes(q);
            var matchCat = !cat || r.category === cat;
            var matchSt = !st || (st === 'published' ? !!r.isPublished : !r.isPublished);
            return matchQ && matchCat && matchSt;
        });
        renderRows();
    }

    function load() {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading\u2026</td></tr>';
        App.api.get(apiBase).then(function (res) {
            if (!res.ok) { App.notify.error(res.message || 'Failed to load national statistics.'); return; }
            allRecords = Array.isArray(res.data) ? res.data : [];
            applyFilters();
        });
    }

    // ── Modal helpers ────────────────────────────────────────────────────────
    var modal = document.getElementById('statisticModal');
    var form = document.getElementById('statisticForm');
    var submitBtn = document.getElementById('statisticSubmitBtn');
    var modalTitle = document.getElementById('statisticModalTitle');
    var uuidField = document.getElementById('statisticUuid');

    function openCreate() {
        if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-bar-chart-line me-2" style="color:var(--we-primary)"></i>Add Statistic'; }
        if (uuidField) { uuidField.value = ''; }
        App.forms.reset(form);
        App.ui.clearAlert('#statisticModalAlert');
        App.modals.open('#statisticModal');
    }

    window.NationalStatisticsAdmin = {
        openEdit: function (record) {
            if (modalTitle) { modalTitle.innerHTML = '<i class="bi bi-pencil me-2" style="color:var(--we-primary)"></i>Edit Statistic'; }
            if (uuidField) { uuidField.value = record.uuid; }
            App.forms.clearValidationErrors(form);
            form.elements.title.value = record.title || '';
            form.elements.value.value = record.value || '';
            form.elements.unit.value = record.unit || '';
            form.elements.category.value = record.category || '';
            form.elements.industryRelevance.value = record.industryRelevance || '';
            form.elements.sourceName.value = record.sourceName || '';
            form.elements.sourceYear.value = record.sourceYear || '';
            form.elements.sourceUrl.value = record.sourceUrl || '';
            form.elements.isPublished.value = record.isPublished ? '1' : '0';
            App.ui.clearAlert('#statisticModalAlert');
            App.modals.open('#statisticModal');
        },
        confirmDelete: function (uuid, title) {
            App.modals.confirm({
                title: 'Delete Statistic',
                text: 'Are you sure you want to delete "' + title + '"? This removes it from the National Importance dashboard and its PDF export.',
                confirmText: 'Delete',
                onConfirm: function () {
                    App.api['delete'](apiBase + '/' + uuid).then(function (res) {
                        if (!res.ok) { App.notify.error(res.message || 'Delete failed.'); return; }
                        App.notify.success('Statistic deleted.');
                        load();
                    });
                },
            });
        },
    };

    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
            App.forms.clearValidationErrors(form);
            var uuid = uuidField ? uuidField.value : '';
            var method = uuid ? 'put' : 'post';
            var url = uuid ? (apiBase + '/' + uuid) : apiBase;
            var payload = App.forms.serialize(form);
            payload.isPublished = payload.isPublished === '1';
            payload.sourceYear = parseInt(payload.sourceYear, 10) || 0;

            App.ui.setButtonLoading(submitBtn, true);
            App.api[method](url, payload).then(function (res) {
                App.ui.setButtonLoading(submitBtn, false, 'Save Statistic');
                if (!res.ok) {
                    if (res.errors) {
                        App.forms.showValidationErrors(form, res.errors);
                    }
                    App.ui.showAlert('danger', res.message || 'Save failed.', '#statisticModalAlert');
                    return;
                }
                App.notify.success(uuid ? 'Statistic updated.' : 'Statistic added.');
                App.modals.close('#statisticModal');
                load();
            });
        });
    }

    var btnAdd = document.getElementById('btnAddStatistic');
    if (btnAdd) { btnAdd.addEventListener('click', function (event) { event.preventDefault(); openCreate(); }); }
    if (searchEl) { searchEl.addEventListener('input', App.utils.debounce(applyFilters, 300)); }
    if (categoryEl) { categoryEl.addEventListener('change', applyFilters); }
    if (statusEl) { statusEl.addEventListener('change', applyFilters); }
    if (prevBtn) { prevBtn.addEventListener('click', function () { if (currentPage > 1) { currentPage--; renderRows(); } }); }
    if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderRows(); } ); }

    load();
})();
