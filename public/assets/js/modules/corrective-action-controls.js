(function () {
  'use strict';

  if (!window.App) { return; }

  var App = window.App;
  var state = {
    library: { summary: {}, meta: {}, items: [] },
    rules: { summary: {}, meta: {}, items: [] },
    selectedLibraryItemUuid: null
  };

  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function escape(value) {
    return App.utils.escapeHtml(value == null ? '' : String(value));
  }

  function human(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function text(selector, value) {
    var el = qs(selector);
    if (el) { el.textContent = value == null || value === '' ? '-' : String(value); }
  }

  function emptyCard(icon, title, subtitle) {
    return '<article class="border rounded-3 p-4 text-center bg-body-tertiary">' +
      '<div class="mb-2"><i class="bi bi-' + escape(icon) + ' fs-2 text-muted"></i></div>' +
      '<h6 class="mb-1">' + escape(title) + '</h6>' +
      '<p class="text-muted small mb-0">' + escape(subtitle || '') + '</p>' +
      '</article>';
  }

  function badge(status) {
    var map = { active: 'bg-label-success', inactive: 'bg-label-secondary' };
    return '<span class="badge ' + (map[status] || 'bg-label-secondary') + '">' + escape(human(status)) + '</span>';
  }

  function reviewBadge(rule) {
    return rule.needsReview
      ? '<span class="badge bg-label-warning">Needs review</span>'
      : '<span class="badge bg-label-success">Healthy</span>';
  }

  function readLibraryFilters() {
    return {
      search: qs('#caLibrarySearch') ? qs('#caLibrarySearch').value.trim() : '',
      category: qs('#caLibraryCategory') ? qs('#caLibraryCategory').value : '',
      risk_level: qs('#caLibraryRisk') ? qs('#caLibraryRisk').value : '',
      status: qs('#caLibraryStatus') ? qs('#caLibraryStatus').value : ''
    };
  }

  function readRuleFilters() {
    return {
      search: qs('#caRuleSearch') ? qs('#caRuleSearch').value.trim() : '',
      status: qs('#caRuleStatus') ? qs('#caRuleStatus').value : '',
      assessment_type: qs('#caRuleAssessmentType') ? qs('#caRuleAssessmentType').value : '',
      review_needed: qs('#caRuleReviewNeeded') ? qs('#caRuleReviewNeeded').value : '',
      linked_action: qs('#caRuleLinkedAction') ? qs('#caRuleLinkedAction').value : ''
    };
  }

  function toQuery(params) {
    var search = new URLSearchParams();
    Object.keys(params).forEach(function (key) {
      if (params[key] !== '') {
        search.set(key, params[key]);
      }
    });
    return search.toString();
  }

  function selectedLibraryItem() {
    return state.library.items.find(function (item) { return item.uuid === state.selectedLibraryItemUuid; }) || null;
  }

  function updateSummary() {
    text('#caSummaryTotalActions', state.library.summary.totalActions || 0);
    text('#caSummaryActiveActions', state.library.summary.activeActions || 0);
    text('#caSummaryActiveRules', state.rules.summary.activeRules || 0);
    text('#caSummaryRulesReview', state.rules.summary.rulesNeedingReview || 0);
    text('#caLibraryCount', (state.library.meta.total || 0) + ' controls');
    text('#caRuleCount', (state.rules.meta.total || 0) + ' rules');
  }

  function populateLinkedControlOptions() {
    var options = ['<option value="">All linked controls</option>'];
    var modalOptions = ['<option value="">Select control</option>'];

    state.library.items.forEach(function (item) {
      var label = escape(item.title + ' (' + human(item.status) + ')');
      options.push('<option value="' + escape(item.uuid) + '">' + label + '</option>');
      modalOptions.push('<option value="' + escape(item.uuid) + '">' + label + '</option>');
    });

    var linkedAction = qs('#caRuleLinkedAction');
    if (linkedAction) {
      var currentValue = linkedAction.value;
      linkedAction.innerHTML = options.join('');
      linkedAction.value = currentValue;
    }

    var ruleLibrary = qs('#caRuleLibraryItemUuid');
    if (ruleLibrary) {
      var currentRuleValue = ruleLibrary.value;
      ruleLibrary.innerHTML = modalOptions.join('');
      ruleLibrary.value = currentRuleValue;
    }
  }

  function renderLibrary() {
    var target = qs('#caLibraryList');
    if (!target) { return; }

    if (!state.library.items.length) {
      target.innerHTML = emptyCard('archive', 'No controls found', 'Adjust filters or create a new corrective action control.');
      return;
    }

    if (!selectedLibraryItem()) {
      state.selectedLibraryItemUuid = state.library.items[0].uuid;
    }

    target.innerHTML = state.library.items.map(function (item) {
      var selected = item.uuid === state.selectedLibraryItemUuid;
      var evidenceTypes = Array.isArray(item.evidenceTypes) ? item.evidenceTypes : [];
      return '<article class="border rounded-3 p-3 cursor-pointer ' + (selected ? 'border-warning bg-warning-subtle' : 'bg-white') + '" data-ca-library-select="' + escape(item.uuid) + '">' +
        '<div class="d-flex align-items-start justify-content-between gap-3">' +
          '<div>' +
            '<div class="d-flex align-items-center flex-wrap gap-2 mb-2">' +
              '<h6 class="mb-0">' + escape(item.title) + '</h6>' +
              badge(item.status) +
              '<span class="badge text-bg-light">' + escape(human(item.category)) + '</span>' +
              '<span class="badge text-bg-light">' + escape(human(item.controlType)) + '</span>' +
            '</div>' +
            '<p class="text-muted small mb-2">' + escape(item.description || 'No description provided.') + '</p>' +
            '<p class="small mb-2">' + escape(item.reasonText || 'No explicit implementation reason recorded.') + '</p>' +
            '<div class="d-flex flex-wrap gap-2 small text-muted">' +
              '<span>Risk factor: ' + escape(human(item.bodyArea || 'unspecified')) + '</span>' +
              '<span>Task: ' + escape(human(item.taskType || 'unspecified')) + '</span>' +
              '<span>Risk: ' + escape(human(item.riskLevel)) + '</span>' +
              '<span>Due: ' + escape(item.dueDays) + ' day(s)</span>' +
              '<span>Follow-up: ' + escape(item.followUpDays || '-') + ' day(s)</span>' +
            '</div>' +
            (evidenceTypes.length ? '<div class="small text-muted mt-2">Evidence: ' + escape(evidenceTypes.map(human).join(', ')) + '</div>' : '') +
          '</div>' +
          '<div class="text-end">' +
            '<div class="small text-muted mb-2">' + escape(item.linkedRuleCount) + ' linked rules</div>' +
            '<div class="btn-group btn-group-sm">' +
              '<button type="button" class="btn btn-outline-secondary cursor-pointer" data-ca-library-edit="' + escape(item.uuid) + '">Edit</button>' +
              '<button type="button" class="btn ' + (item.status === 'active' ? 'btn-outline-danger' : 'btn-outline-success') + ' cursor-pointer" data-ca-library-toggle="' + escape(item.uuid) + '">' + escape(item.status === 'active' ? 'Deactivate' : 'Activate') + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');
  }

  function renderRules() {
    var target = qs('#caRuleList');
    if (!target) { return; }

    if (!state.rules.items.length) {
      target.innerHTML = emptyCard('sliders', 'No rules found', 'Adjust filters or create a new recommendation rule.');
      return;
    }

    var selectedUuid = state.selectedLibraryItemUuid;
    var items = state.rules.items.slice().sort(function (left, right) {
      var leftRelated = selectedUuid && left.linkedActionId === selectedUuid ? 1 : 0;
      var rightRelated = selectedUuid && right.linkedActionId === selectedUuid ? 1 : 0;
      return rightRelated - leftRelated;
    });

    target.innerHTML = items.map(function (rule) {
      var related = selectedUuid && rule.linkedActionId === selectedUuid;
      return '<article class="border rounded-3 p-3 ' + (related ? 'border-warning bg-warning-subtle' : 'bg-white') + '">' +
        '<div class="d-flex align-items-start justify-content-between gap-3">' +
          '<div class="flex-grow-1">' +
            '<div class="d-flex align-items-center flex-wrap gap-2 mb-2">' +
              '<h6 class="mb-0">' + escape(rule.linkedActionTitle || 'Missing linked control') + '</h6>' +
              badge(rule.status) +
              reviewBadge(rule) +
              '<span class="badge text-bg-light">' + escape(String(rule.assessmentType || 'all').toUpperCase()) + '</span>' +
            '</div>' +
            '<p class="mb-2 small text-muted">' + escape(rule.triggerSummary) + '</p>' +
            '<div class="d-flex flex-wrap gap-2 small text-muted">' +
              '<span>Priority: ' + escape(rule.priority) + '</span>' +
              '<span>Confidence: ' + escape(rule.confidenceThreshold == null ? '-' : rule.confidenceThreshold) + '</span>' +
              (rule.reviewReason ? '<span>Review reason: ' + escape(human(rule.reviewReason)) + '</span>' : '') +
            '</div>' +
          '</div>' +
          '<div class="text-end">' +
            '<div class="small text-muted mb-2">' + escape(App.utils.formatDate(rule.updatedAt)) + '</div>' +
            '<div class="btn-group btn-group-sm">' +
              '<button type="button" class="btn btn-outline-secondary cursor-pointer" data-ca-rule-edit="' + escape(rule.uuid) + '">Edit</button>' +
              '<button type="button" class="btn ' + (rule.status === 'active' ? 'btn-outline-danger' : 'btn-outline-success') + ' cursor-pointer" data-ca-rule-toggle="' + escape(rule.uuid) + '">' + escape(rule.status === 'active' ? 'Disable' : 'Enable') + '</button>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
    }).join('');
  }

  function handleError(message) {
    App.notify.error(message || 'Request failed.');
  }

  function loadLibrary() {
    return App.api.get('/api/v1/corrective-action-library?' + toQuery(readLibraryFilters())).then(function (res) {
      if (!res.ok) {
        throw new Error(res.message || 'Could not load corrective action library.');
      }
      state.library = res.data || { summary: {}, meta: {}, items: [] };
      if (!selectedLibraryItem() && state.library.items.length) {
        state.selectedLibraryItemUuid = state.library.items[0].uuid;
      }
      populateLinkedControlOptions();
      renderLibrary();
      updateSummary();
      return res;
    });
  }

  function loadRules() {
    return App.api.get('/api/v1/recommendation-rules?' + toQuery(readRuleFilters())).then(function (res) {
      if (!res.ok) {
        throw new Error(res.message || 'Could not load recommendation rules.');
      }
      state.rules = res.data || { summary: {}, meta: {}, items: [] };
      renderRules();
      updateSummary();
      return res;
    });
  }

  function loadAll() {
    return Promise.all([loadLibrary(), loadRules()]).catch(function (error) {
      handleError(error.message);
    });
  }

  function libraryRow(uuid) {
    return state.library.items.find(function (item) { return item.uuid === uuid; }) || null;
  }

  function ruleRow(uuid) {
    return state.rules.items.find(function (item) { return item.uuid === uuid; }) || null;
  }

  function checkedEvidenceTypes() {
    return qsa('#caLibraryEvidenceTypes input[type="checkbox"]:checked').map(function (input) { return input.value; });
  }

  function setEvidenceTypes(values) {
    var allowed = {};
    (values || []).forEach(function (value) { allowed[String(value)] = true; });
    qsa('#caLibraryEvidenceTypes input[type="checkbox"]').forEach(function (input) {
      input.checked = !!allowed[input.value];
    });
  }

  function openLibraryModal(item) {
    qs('#caLibraryModalTitle').textContent = item ? 'Edit control' : 'New control';
    qs('#caLibraryUuid').value = item ? item.uuid : '';
    qs('#caLibraryTitle').value = item ? item.title : '';
    qs('#caLibraryDescription').value = item ? (item.description || '') : '';
    qs('#caLibraryReason').value = item ? (item.reasonText || item.reason || '') : '';
    qs('#caLibraryHierarchyLevel').value = item ? item.hierarchyLevel : 'engineering';
    qs('#caLibraryControlType').value = item ? item.controlType : 'engineering';
    qs('#caLibraryDueDays').value = item ? (item.dueDays || 30) : 30;
    qs('#caLibraryFollowUpDays').value = item && item.followUpDays ? item.followUpDays : '';
    qs('#caLibraryRiskFactor').value = item ? (item.riskFactor || '') : '';
    qs('#caLibraryTaskType').value = item ? (item.taskType || '') : '';
    qs('#caLibraryIndustry').value = item ? (item.industry || '') : '';
    qs('#caLibraryPriorityInput').value = item ? item.priority : 'medium';
    qs('#caLibraryEvidenceRequired').checked = item ? !!item.evidenceRequired : true;
    qs('#caLibraryIsActive').checked = item ? !!item.isActive : true;
    setEvidenceTypes(item && item.evidenceTypes ? item.evidenceTypes : []);
    App.modals.open('#caLibraryModal');
  }

  function openRuleModal(rule) {
    populateLinkedControlOptions();
    qs('#caRuleModalTitle').textContent = rule ? 'Edit rule' : 'New rule';
    qs('#caRuleUuid').value = rule ? rule.uuid : '';
    qs('#caRuleLibraryItemUuid').value = rule ? (rule.linkedActionId || '') : (state.selectedLibraryItemUuid || '');
    qs('#caRuleAssessmentTypeInput').value = rule ? (rule.condition.assessmentType || 'reba') : 'reba';
    qs('#caRuleWeight').value = rule ? (rule.weight || rule.priority || 100) : 100;
    qs('#caRuleRiskFactor').value = rule ? (rule.condition.riskFactor || '') : '';
    qs('#caRuleMinScore').value = rule ? (rule.condition.minScore || 50) : 50;
    qs('#caRuleConfidenceThreshold').value = rule && rule.condition.confidenceThreshold != null ? rule.condition.confidenceThreshold : '';
    qs('#caRuleIsActive').checked = rule ? !!rule.isActive : true;
    App.modals.open('#caRuleModal');
  }

  function submitLibraryForm(event) {
    event.preventDefault();

    var payload = {
      uuid: qs('#caLibraryUuid').value || undefined,
      title: qs('#caLibraryTitle').value.trim(),
      description: qs('#caLibraryDescription').value.trim(),
      reason: qs('#caLibraryReason').value.trim(),
      hierarchyLevel: qs('#caLibraryHierarchyLevel').value,
      controlType: qs('#caLibraryControlType').value,
      dueDays: Number(qs('#caLibraryDueDays').value || 30),
      followUpDays: qs('#caLibraryFollowUpDays').value ? Number(qs('#caLibraryFollowUpDays').value) : null,
      riskFactor: qs('#caLibraryRiskFactor').value.trim(),
      taskType: qs('#caLibraryTaskType').value.trim(),
      industry: qs('#caLibraryIndustry').value.trim(),
      priority: qs('#caLibraryPriorityInput').value,
      evidenceRequired: qs('#caLibraryEvidenceRequired').checked,
      evidenceTypes: checkedEvidenceTypes(),
      isActive: qs('#caLibraryIsActive').checked
    };

    App.api.post('/api/v1/corrective-action-library', payload).then(function (res) {
      if (!res.ok) {
        handleError(res.message || 'Could not save control.');
        return;
      }
      App.notify.success('Control saved.');
      App.modals.close('#caLibraryModal');
      loadAll();
    });
  }

  function submitRuleForm(event) {
    event.preventDefault();

    var confidenceRaw = qs('#caRuleConfidenceThreshold').value.trim();
    var payload = {
      uuid: qs('#caRuleUuid').value || undefined,
      condition: {
        assessmentType: qs('#caRuleAssessmentTypeInput').value,
        riskFactor: qs('#caRuleRiskFactor').value.trim(),
        minScore: Number(qs('#caRuleMinScore').value || 0)
      },
      action: {
        libraryItemUuid: qs('#caRuleLibraryItemUuid').value
      },
      weight: Number(qs('#caRuleWeight').value || 0),
      isActive: qs('#caRuleIsActive').checked
    };

    if (confidenceRaw !== '') {
      payload.condition.confidenceThreshold = Number(confidenceRaw);
    }

    App.api.post('/api/v1/recommendation-rules', payload).then(function (res) {
      if (!res.ok) {
        handleError(res.message || 'Could not save rule.');
        return;
      }
      App.notify.success('Rule saved.');
      App.modals.close('#caRuleModal');
      loadRules();
      loadLibrary();
    });
  }

  function toggleLibrary(uuid) {
    var item = libraryRow(uuid);
    if (!item) { return; }

    App.api.post('/api/v1/corrective-action-library', {
      uuid: item.uuid,
      title: item.title,
      description: item.description,
      reason: item.reasonText || item.reason,
      controlType: item.controlType,
      hierarchyLevel: item.hierarchyLevel,
      riskFactor: item.riskFactor,
      taskType: item.taskType,
      industry: item.industry,
      priority: item.priority,
      dueDays: item.dueDays,
      followUpDays: item.followUpDays,
      evidenceRequired: item.evidenceRequired,
      evidenceTypes: item.evidenceTypes || [],
      isActive: !item.isActive
    }).then(function (res) {
      if (!res.ok) {
        handleError(res.message || 'Could not update control status.');
        return;
      }
      App.notify.success(item.isActive ? 'Control deactivated.' : 'Control activated.');
      loadAll();
    });
  }

  function toggleRule(uuid) {
    var rule = ruleRow(uuid);
    if (!rule) { return; }

    App.api.post('/api/v1/recommendation-rules', {
      uuid: rule.uuid,
      condition: rule.condition,
      action: rule.action,
      weight: rule.weight || rule.priority,
      isActive: !rule.isActive
    }).then(function (res) {
      if (!res.ok) {
        handleError(res.message || 'Could not update rule status.');
        return;
      }
      App.notify.success(rule.isActive ? 'Rule disabled.' : 'Rule enabled.');
      loadRules();
    });
  }

  function bindFilters() {
    ['#caLibrarySearch', '#caLibraryCategory', '#caLibraryRisk', '#caLibraryStatus'].forEach(function (selector) {
      var el = qs(selector);
      if (!el) { return; }
      el.addEventListener('input', loadLibrary);
      el.addEventListener('change', loadLibrary);
    });

    ['#caRuleSearch', '#caRuleStatus', '#caRuleAssessmentType', '#caRuleReviewNeeded', '#caRuleLinkedAction'].forEach(function (selector) {
      var el = qs(selector);
      if (!el) { return; }
      el.addEventListener('input', loadRules);
      el.addEventListener('change', loadRules);
    });

    var clearLibrary = qs('#caClearLibraryFilters');
    if (clearLibrary) {
      clearLibrary.addEventListener('click', function () {
        ['#caLibrarySearch', '#caLibraryCategory', '#caLibraryRisk', '#caLibraryStatus'].forEach(function (selector) {
          var el = qs(selector);
          if (el) { el.value = ''; }
        });
        loadLibrary();
      });
    }

    var clearRules = qs('#caClearRuleFilters');
    if (clearRules) {
      clearRules.addEventListener('click', function () {
        ['#caRuleSearch', '#caRuleStatus', '#caRuleAssessmentType', '#caRuleReviewNeeded', '#caRuleLinkedAction'].forEach(function (selector) {
          var el = qs(selector);
          if (el) { el.value = ''; }
        });
        loadRules();
      });
    }
  }

  function bindActions() {
    var newLibrary = qs('#caNewLibraryItem');
    if (newLibrary) {
      newLibrary.addEventListener('click', function (event) {
        event.preventDefault();
        openLibraryModal(null);
      });
    }

    var newRule = qs('#caNewRule');
    if (newRule) {
      newRule.addEventListener('click', function (event) {
        event.preventDefault();
        openRuleModal(null);
      });
    }

    var libraryForm = qs('#caLibraryForm');
    if (libraryForm) {
      libraryForm.addEventListener('submit', submitLibraryForm);
    }

    var ruleForm = qs('#caRuleForm');
    if (ruleForm) {
      ruleForm.addEventListener('submit', submitRuleForm);
    }

    document.addEventListener('click', function (event) {
      var librarySelect = event.target.closest('[data-ca-library-select]');
      if (librarySelect && !event.target.closest('[data-ca-library-edit]') && !event.target.closest('[data-ca-library-toggle]')) {
        state.selectedLibraryItemUuid = librarySelect.getAttribute('data-ca-library-select');
        renderLibrary();
        renderRules();
        return;
      }

      var libraryEdit = event.target.closest('[data-ca-library-edit]');
      if (libraryEdit) {
        openLibraryModal(libraryRow(libraryEdit.getAttribute('data-ca-library-edit')));
        return;
      }

      var libraryToggle = event.target.closest('[data-ca-library-toggle]');
      if (libraryToggle) {
        toggleLibrary(libraryToggle.getAttribute('data-ca-library-toggle'));
        return;
      }

      var ruleEdit = event.target.closest('[data-ca-rule-edit]');
      if (ruleEdit) {
        openRuleModal(ruleRow(ruleEdit.getAttribute('data-ca-rule-edit')));
        return;
      }

      var ruleToggle = event.target.closest('[data-ca-rule-toggle]');
      if (ruleToggle) {
        toggleRule(ruleToggle.getAttribute('data-ca-rule-toggle'));
      }
    });
  }

  function init() {
    if (!qs('[data-ca-page="controls"]')) { return; }
    bindFilters();
    bindActions();
    loadAll();
  }

  document.addEventListener('DOMContentLoaded', init);
})();
