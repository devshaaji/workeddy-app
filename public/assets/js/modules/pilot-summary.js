/**
 * pilot-summary.js — Pilot Study Dashboard filter support
 * Route: GET /reporting/pilot-summary
 *
 * The dashboard itself is server-rendered (metrics come from real platform
 * activity on every page load). This script only wires up the filter modal:
 *   • populates Worksite / Department / Job Role selects from the
 *     organization structure
 *   • populates Body Region from the worker-voice question catalog
 *   • restores the currently-applied filter values so the modal reflects
 *     the active dashboard scope
 */
(function () {
    'use strict';

    if (!window.App) { return; }

    var page = document.getElementById('pilotSummaryPage');
    if (!page) { return; }

    var orgUuid = page.getAttribute('data-organization-uuid') || '';

    function escape(value) {
        return App.utils.escapeHtml(value === null || value === undefined ? '' : String(value));
    }

    function currentValue(select) {
        return select ? select.getAttribute('data-selected') || '' : '';
    }

    function fillSelect(select, items, emptyLabel, valueKey, labelKey) {
        if (!select) { return; }
        var selected = currentValue(select);
        var options = '<option value="">' + escape(emptyLabel) + '</option>' + items.map(function (item) {
            return '<option value="' + escape(item[valueKey]) + '">' + escape(item[labelKey] || item[valueKey]) + '</option>';
        }).join('');
        select.innerHTML = options;
        if (selected) { select.value = selected; }
    }

    function loadOrganizationStructure() {
        if (!orgUuid) { return; }

        var worksiteSelect = document.getElementById('pilotWorksite');
        var departmentSelect = document.getElementById('pilotDepartment');
        var jobRoleSelect = document.getElementById('pilotJobRole');

        if (worksiteSelect) {
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/worksites', { limit: 200 }).then(function (res) {
                var items = res.ok && Array.isArray(res.data) ? res.data : [];
                fillSelect(worksiteSelect, items, 'All worksites', 'id', 'name');
            });
        }

        if (departmentSelect) {
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/departments', { limit: 200 }).then(function (res) {
                var items = res.ok && Array.isArray(res.data) ? res.data : [];
                fillSelect(departmentSelect, items, 'All departments', 'id', 'name');
            });
        }

        if (jobRoleSelect) {
            App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/job-roles', { limit: 200 }).then(function (res) {
                var items = res.ok && Array.isArray(res.data) ? res.data : [];
                fillSelect(jobRoleSelect, items, 'All job roles', 'id', 'name');
            });
        }
    }

    function loadBodyRegions() {
        var bodyRegionSelect = document.getElementById('pilotBodyRegion');
        if (!bodyRegionSelect) { return; }

        App.api.get('/api/v1/worker-feedback/questions').then(function (res) {
            var regions = res.ok && res.data && Array.isArray(res.data.bodyRegions) ? res.data.bodyRegions : [];
            if (regions.length === 0) { return; }
            fillSelect(bodyRegionSelect, regions, 'All body regions', 'key', 'label');
        });
    }

    loadOrganizationStructure();
    loadBodyRegions();
})();
