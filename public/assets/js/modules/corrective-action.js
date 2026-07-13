(function () {
  'use strict';

  if (!window.App) { return; }

  var App = window.App;
  var state = {
    actions: [],
    recommendations: [],
    assessments: [],
    users: [],
    usersById: {},
    detail: null
  };

  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  function text(selector, value) {
    var el = qs(selector);
    if (el) { el.textContent = value == null || value === '' ? '-' : String(value); }
  }

  function escape(value) {
    return App.utils.escapeHtml(value == null ? '' : String(value));
  }

  function human(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); });
  }

  function today(offsetDays) {
    var date = new Date();
    if (offsetDays) {
      date.setDate(date.getDate() + offsetDays);
    }
    return date.toISOString().slice(0, 10);
  }

  function statusBadge(status) {
    var map = {
      assigned: 'bg-label-primary',
      in_progress: 'bg-label-info',
      completed: 'bg-label-warning',
      verified: 'bg-label-success',
      overdue: 'bg-label-danger',
      rejected: 'bg-label-secondary',
      generated: 'bg-label-primary',
      accepted: 'bg-label-success'
    };
    return '<span class="badge ' + (map[status] || 'bg-label-secondary') + '">' + escape(human(status)) + '</span>';
  }

  function priorityBadge(priority) {
    var map = { critical: 'bg-label-danger', high: 'bg-label-warning', medium: 'bg-label-primary', low: 'bg-label-secondary' };
    return '<span class="badge ' + (map[priority] || 'bg-label-secondary') + '">' + escape(human(priority)) + '</span>';
  }

  function emptyRow(colspan, title, subtitle) {
    return '<tr><td colspan="' + colspan + '" class="text-center py-5">' +
      '<div class="mb-2"><i class="bi bi-inbox fs-1 text-muted"></i></div>' +
      '<h6 class="mb-1">' + escape(title) + '</h6>' +
      '<p class="text-muted mb-0">' + escape(subtitle || '') + '</p>' +
      '</td></tr>';
  }

  function actionsMenu(items) {
    return '<div class="dropdown text-end">' +
      '<button class="btn btn-sm btn-icon btn-outline-secondary cursor-pointer" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">' +
      '<i class="bi bi-three-dots-vertical"></i></button>' +
      '<ul class="dropdown-menu dropdown-menu-end">' + items.join('') + '</ul></div>';
  }

  function menuButton(label, action, uuid, extraClass) {
    return '<li><button class="dropdown-item cursor-pointer ' + (extraClass || '') + '" type="button" data-ca-action="' + action + '" data-uuid="' + escape(uuid) + '">' + escape(label) + '</button></li>';
  }

  function currentOrgUuid() {
    var meta = qs('meta[name="org-uuid"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function currentAssessmentUuid() {
    var select = qs('#caAssessmentSelect');
    return select ? select.value : '';
  }

  function showValidationErrors(form, errors) {
    if (!form || !errors || !App.forms || !App.forms.showValidationErrors) {
      return { fieldErrors: {}, formErrors: [] };
    }

    return App.forms.showValidationErrors(form, errors);
  }

  function recommendationByUuid(uuid) {
    return state.recommendations.find(function (item) { return item.uuid === uuid; }) || null;
  }

  function userById(id) {
    return state.usersById[String(id || '')] || null;
  }

  function formatUser(user) {
    if (!user) { return '-'; }
    var name = user.profile && user.profile.fullName ? user.profile.fullName : user.email;
    return name || user.email || '-';
  }

  function formatAssessmentOption(assessment) {
    var score = assessment.finalScore && assessment.finalScore.normalized != null ? Math.round(assessment.finalScore.normalized) : null;
    var parts = [
      String(assessment.model || '').toUpperCase(),
      human(assessment.status || ''),
      score == null ? null : 'Score ' + score
    ].filter(Boolean);
    return (parts.join(' • ') || 'Assessment') + ' • Task ' + (assessment.taskUuid || '').slice(0, 8);
  }

  function renderAssessmentOptions() {
    var select = qs('#caAssessmentSelect');
    if (!select) { return; }

    var options = ['<option value="">Select reviewed assessment</option>'];
    state.assessments.forEach(function (assessment) {
      options.push('<option value="' + escape(assessment.uuid) + '">' + escape(formatAssessmentOption(assessment)) + '</option>');
    });
    select.innerHTML = options.join('');
    if (state.assessments.length === 1) {
      select.value = state.assessments[0].uuid;
    }
  }

  function renderUserOptions() {
    var select = qs('#caAssignedToUserUuid');
    if (!select) { return; }

    var options = ['<option value="">Select responsible person</option>'];
    state.users.forEach(function (user) {
      options.push('<option value="' + escape(user.uuid) + '">' + escape(formatUser(user)) + (user.email ? ' • ' + escape(user.email) : '') + '</option>');
    });
    select.innerHTML = options.join('');
  }

  function loadAssessments() {
    var orgUuid = currentOrgUuid();
    if (!orgUuid || !qs('#caAssessmentSelect')) { return Promise.resolve(); }

    return App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/assessments').then(function (res) {
      if (!res.ok) { throw new Error(res.message || 'Could not load assessments.'); }
      state.assessments = (Array.isArray(res.data) ? res.data : []).filter(function (assessment) {
        return assessment.status === 'reviewed' || assessment.status === 'locked';
      });
      renderAssessmentOptions();
    });
  }

  function loadUsers() {
    var needsUsers = qs('#caAssignedToUserUuid') || qs('[data-ca-page="actions"]') || qs('[data-ca-page="show"]');
    if (!needsUsers) { return Promise.resolve(); }
    var orgUuid = currentOrgUuid();
    if (!orgUuid) { return Promise.resolve(); }

    return App.api.get('/api/v1/organizations/' + encodeURIComponent(orgUuid) + '/members').then(function (res) {
      if (!res.ok) { throw new Error(res.message || 'Could not load organization members.'); }
      state.users = (Array.isArray(res.data) ? res.data : []).map(function (member) {
        var user = member.user || {};
        return {
          id: user.id,
          uuid: user.uuid,
          email: user.email || '',
          profile: {
            fullName: user.fullName || user.email || '',
            phone: user.phone || null
          },
          membership: {
            id: member.id,
            organizationUuid: member.organizationUuid,
            roleSlug: member.roleSlug,
            status: member.status,
            worksiteId: member.worksiteId,
            departmentId: member.departmentId,
            jobRoleId: member.jobRoleId,
            isPrimary: member.isPrimary
          }
        };
      }).filter(function (member) {
        return !!member.uuid;
      });
      state.usersById = {};
      state.users.forEach(function (user) {
        if (user.id != null) {
          state.usersById[String(user.id)] = user;
        }
      });
      renderUserOptions();
      renderActions();
      renderDetail();
    });
  }

  function loadActions() {
    var card = qs('#caActionsCard');
    var body = qs('#caActionsBody');
    if (!card || !body) { return; }

    body.innerHTML = emptyRow(6, 'Loading actions', 'Fetching corrective action register.');
    App.api.get(card.getAttribute('data-endpoint') || '/api/v1/corrective-actions').then(function (res) {
      if (!res.ok) {
        App.notify.error(res.message || 'Could not load corrective actions.');
        body.innerHTML = emptyRow(6, 'Actions unavailable', 'Try refreshing the page.');
        return;
      }
      state.actions = Array.isArray(res.data) ? res.data : [];
      renderActions();
    });
  }

  function filteredActions() {
    var query = (qs('#caSearch') ? qs('#caSearch').value : '').toLowerCase();
    var status = qs('#caStatusFilter') ? qs('#caStatusFilter').value : '';
    var priority = qs('#caPriorityFilter') ? qs('#caPriorityFilter').value : '';

    return state.actions.filter(function (action) {
      var owner = formatUser(userById(action.assignedToUserId)).toLowerCase();
      var haystack = [action.title, action.reason, action.assessmentUuid, owner, action.status, action.priority].join(' ').toLowerCase();
      if (query && haystack.indexOf(query) === -1) { return false; }
      if (status && action.status !== status) { return false; }
      if (priority && action.priority !== priority) { return false; }
      return true;
    });
  }

  function renderActions() {
    var body = qs('#caActionsBody');
    if (!body) { return; }
    var records = filteredActions();

    text('#caStatOpen', state.actions.filter(function (a) { return ['assigned', 'in_progress'].indexOf(a.status) !== -1; }).length);
    text('#caStatOverdue', state.actions.filter(function (a) { return a.status === 'overdue'; }).length);
    text('#caStatCompleted', state.actions.filter(function (a) { return a.status === 'completed'; }).length);
    text('#caStatVerified', state.actions.filter(function (a) { return a.status === 'verified'; }).length);
    text('#caActionCount', records.length + ' actions');

    if (!records.length) {
      body.innerHTML = emptyRow(6, 'No corrective actions match', 'Try clearing filters or review accepted recommendations.');
      return;
    }

    body.innerHTML = records.map(function (action) {
      var owner = userById(action.assignedToUserId);
      var evidence = Array.isArray(action.evidenceRequirements) ? action.evidenceRequirements : [];
      var menu = actionsMenu([
        '<li><a class="dropdown-item" href="/corrective-actions/' + escape(action.uuid) + '">View detail</a></li>',
        '<li><a class="dropdown-item" href="/corrective-actions/' + escape(action.uuid) + '/evidence">Upload evidence</a></li>'
      ]);
      return '<tr>' +
        '<td><div class="fw-semibold">' + escape(action.title) + '</div>' +
        '<small class="text-muted d-block">' + escape(action.reason || action.description || 'No reason recorded.') + '</small>' +
        '<small class="text-muted d-block">' + escape(formatUser(owner)) + '</small>' +
        (evidence.length ? '<small class="text-muted d-block">Evidence: ' + escape(evidence.map(human).join(', ')) + '</small>' : '') +
        '</td>' +
        '<td>' + priorityBadge(action.priority) + '</td>' +
        '<td>' + statusBadge(action.status) + '</td>' +
        '<td>' + escape(App.utils.formatDate(action.dueDate)) + '</td>' +
        '<td>' + escape(App.utils.formatDate(action.followUpAssessmentDueDate)) + '</td>' +
        '<td>' + menu + '</td>' +
      '</tr>';
    }).join('');
  }

  function loadRecommendations(generate) {
    var body = qs('#caRecommendationsBody');
    var assessmentUuid = currentAssessmentUuid();
    if (!body) { return; }
    if (!assessmentUuid) {
      App.notify.warning('Select a reviewed assessment first.');
      return;
    }

    body.innerHTML = emptyRow(6, generate ? 'Generating recommendations' : 'Loading recommendations', 'This can take a moment.');
    var request = generate
      ? App.api.post('/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/corrective-action-recommendations', {})
      : App.api.get('/api/v1/assessments/' + encodeURIComponent(assessmentUuid) + '/corrective-action-recommendations');

    request.then(function (res) {
      if (!res.ok) {
        App.notify.error(res.message || 'Could not load recommendations.');
        body.innerHTML = emptyRow(6, 'No recommendations loaded', 'Check assessment review status and permissions.');
        return;
      }
      state.recommendations = Array.isArray(res.data) ? res.data : [];
      renderRecommendations();
      if (generate) { App.notify.success('Recommendations generated.'); }
    });
  }

  function renderRecommendations() {
    var body = qs('#caRecommendationsBody');
    if (!body) { return; }
    text('#caRecommendationCount', state.recommendations.length + ' recommendations');
    if (!state.recommendations.length) {
      body.innerHTML = emptyRow(6, 'No recommendations yet', 'Generate recommendations from a reviewed assessment.');
      return;
    }

    body.innerHTML = state.recommendations.map(function (rec) {
      var evidenceTypes = Array.isArray(rec.evidence && rec.evidence.evidence_types) ? rec.evidence.evidence_types : [];
      var items = [];
      if (rec.status === 'generated') {
        items.push(menuButton('Review and accept', 'open-review', rec.uuid));
        items.push(menuButton('Reject with reason', 'open-reject', rec.uuid, 'text-danger'));
      }
      if (rec.status === 'accepted') {
        items.push(menuButton('Assign responsible person', 'open-assign', rec.uuid));
      }
      return '<tr>' +
        '<td><div class="fw-semibold">' + escape(rec.title) + '</div>' +
        '<small class="text-muted d-block">' + escape(rec.reason || rec.description || rec.controlCode) + '</small>' +
        (evidenceTypes.length ? '<small class="text-muted d-block">Evidence: ' + escape(evidenceTypes.map(human).join(', ')) + '</small>' : '') +
        (rec.rejectReason ? '<small class="text-danger d-block">Rejected: ' + escape(rec.rejectReason) + '</small>' : '') +
        '</td>' +
        '<td><span class="badge bg-label-info">' + escape(human(rec.hierarchyLevel)) + '</span></td>' +
        '<td>' + escape(rec.expectedRiskReductionPct) + '%</td>' +
        '<td>' + statusBadge(rec.status) + '</td>' +
        '<td><span class="d-block">' + escape(rec.dueDays || '-') + ' day(s)</span><small class="text-muted">Follow-up ' + escape(rec.followUpDays || '-') + ' day(s)</small></td>' +
        '<td>' + actionsMenu(items) + '</td>' +
      '</tr>';
    }).join('');
  }

  function renderRecommendationsPlaceholder(title, subtitle) {
    var body = qs('#caRecommendationsBody');
    if (!body) { return; }
    text('#caRecommendationCount', state.recommendations.length + ' recommendations');
    body.innerHTML = emptyRow(6, title, subtitle);
  }

  function toggleEvidenceTypeCheckboxes(rootSelector, values) {
    var allowed = {};
    (values || []).forEach(function (value) { allowed[String(value)] = true; });
    qsa(rootSelector + ' input[type="checkbox"]').forEach(function (input) {
      input.checked = !!allowed[input.value];
    });
  }

  function checkedValues(rootSelector) {
    return qsa(rootSelector + ' input[type="checkbox"]:checked').map(function (input) { return input.value; });
  }

  function openReview(uuid) {
    var rec = recommendationByUuid(uuid);
    if (!rec) { return; }

    qs('#caReviewRecommendationUuid').value = rec.uuid;
    qs('#caReviewTitle').value = rec.title || '';
    qs('#caReviewDescription').value = rec.description || '';
    qs('#caReviewReason').value = rec.reason || '';
    qs('#caReviewPriority').value = rec.priority || 'medium';
    qs('#caReviewDueDays').value = rec.dueDays || '';
    qs('#caReviewFollowUpDays').value = rec.followUpDays || '';
    qs('#caReviewEvidenceRequired').checked = !!(rec.evidence && rec.evidence.evidence_required);
    toggleEvidenceTypeCheckboxes('#caReviewEvidenceTypes', rec.evidence && rec.evidence.evidence_types);
    App.modals.open('#caReviewModal');
  }

  function openReject(uuid) {
    var rec = recommendationByUuid(uuid);
    if (!rec) { return; }
    qs('#caRejectRecommendationUuid').value = rec.uuid;
    qs('#caRejectReason').value = '';
    qs('#caRejectRecommendationSummary').textContent = rec.title + (rec.reason ? ' • ' + rec.reason : '');
    App.modals.open('#caRejectModal');
  }

  function openAssign(uuid) {
    var rec = recommendationByUuid(uuid);
    if (!rec) { return; }

    qs('#caAssignRecommendationUuid').value = uuid;
    qs('#caAssignRecommendationSummary').textContent = rec.title + (rec.reason ? ' • ' + rec.reason : '');
    qs('#caAssignedToUserUuid').value = '';
    qs('#caAssignDueDate').value = today(rec.dueDays || 30);
    qs('#caAssignFollowUpDate').value = rec.followUpDays ? today((rec.dueDays || 30) + rec.followUpDays) : '';
    App.modals.open('#caAssignModal');
  }

  function bindRecommendationsForms() {
    var reviewForm = qs('#caReviewForm');
    if (reviewForm) {
      reviewForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (App.forms && App.forms.clearValidationErrors) {
          App.forms.clearValidationErrors(reviewForm);
        }
        var uuid = qs('#caReviewRecommendationUuid').value;
        var payload = {
          title: qs('#caReviewTitle').value.trim(),
          description: qs('#caReviewDescription').value.trim(),
          reason: qs('#caReviewReason').value.trim(),
          priority: qs('#caReviewPriority').value,
          dueDays: Number(qs('#caReviewDueDays').value || 0) || undefined,
          followUpDays: Number(qs('#caReviewFollowUpDays').value || 0) || undefined,
          evidenceRequired: qs('#caReviewEvidenceRequired').checked,
          evidenceTypes: checkedValues('#caReviewEvidenceTypes')
        };
        App.api.post('/api/v1/corrective-action-recommendations/' + encodeURIComponent(uuid) + '/accept', payload).then(function (res) {
          if (!res.ok) {
            var rendered = showValidationErrors(reviewForm, res.errors || {});
            if (rendered.formErrors.length) {
              App.notify.error(rendered.formErrors.join(' '));
            } else if (!Object.keys(rendered.fieldErrors).length) {
              App.notify.error(res.message || 'Could not accept recommendation.');
            }
            return;
          }
          App.notify.success('Recommendation accepted.');
          App.modals.close('#caReviewModal');
          loadRecommendations(false);
        });
      });
    }

    var rejectForm = qs('#caRejectForm');
    if (rejectForm) {
      rejectForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (App.forms && App.forms.clearValidationErrors) {
          App.forms.clearValidationErrors(rejectForm);
        }
        var uuid = qs('#caRejectRecommendationUuid').value;
        App.api.post('/api/v1/corrective-action-recommendations/' + encodeURIComponent(uuid) + '/reject', {
          reason: qs('#caRejectReason').value.trim()
        }).then(function (res) {
          if (!res.ok) {
            var rendered = showValidationErrors(rejectForm, res.errors || {});
            if (rendered.formErrors.length) {
              App.notify.error(rendered.formErrors.join(' '));
            } else if (!Object.keys(rendered.fieldErrors).length) {
              App.notify.error(res.message || 'Could not reject recommendation.');
            }
            return;
          }
          App.notify.success('Recommendation rejected.');
          App.modals.close('#caRejectModal');
          loadRecommendations(false);
        });
      });
    }

    var assignForm = qs('#caAssignForm');
    if (assignForm) {
      assignForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (App.forms && App.forms.clearValidationErrors) {
          App.forms.clearValidationErrors(assignForm);
        }
        var uuid = qs('#caAssignRecommendationUuid').value;
        App.api.post('/api/v1/corrective-action-recommendations/' + encodeURIComponent(uuid) + '/assign', {
          assignedToUserUuid: qs('#caAssignedToUserUuid').value,
          dueDate: qs('#caAssignDueDate').value || null,
          followUpDueDate: qs('#caAssignFollowUpDate').value || null
        }).then(function (res) {
          if (!res.ok) {
            var rendered = showValidationErrors(assignForm, res.errors || {});
            if (rendered.formErrors.length) {
              App.notify.error(rendered.formErrors.join(' '));
            } else if (!Object.keys(rendered.fieldErrors).length) {
              App.notify.error(res.message || 'Could not assign corrective action.');
            }
            return;
          }
          App.notify.success('Corrective action assigned.');
          App.modals.close('#caAssignModal');
          if (res.data && res.data.uuid) { window.location.href = '/corrective-actions/' + res.data.uuid; }
        });
      });
    }
  }

  function loadDetail() {
    var root = qs('[data-ca-page="show"], [data-ca-page="evidence"]');
    if (!root) { return; }
    var actionId = root.getAttribute('data-action-id');
    if (!actionId) { return; }

    App.api.get('/api/v1/corrective-actions/' + encodeURIComponent(actionId)).then(function (res) {
      if (!res.ok) { App.notify.error(res.message || 'Could not load action.'); return; }
      state.detail = res.data || {};
      renderDetail();
    });
  }

  function renderDetail() {
    var data = state.detail || {};
    var action = data.action || {};
    if (!action.uuid) { return; }

    text('#caDetailTitle', action.title);
    text('#caDetailDescription', action.description || 'No description provided.');
    text('#caDetailPriority', human(action.priority));
    text('#caDetailDue', App.utils.formatDate(action.dueDate));
    text('#caDetailFollowUp', App.utils.formatDate(action.followUpAssessmentDueDate));
    text('#caDetailAssessment', action.assessmentUuid);
    text('#caDetailHierarchy', human(action.hierarchyLevel));
    text('#caDetailControlType', human(action.controlType));
    text('#caDetailOwner', formatUser(userById(action.assignedToUserId)));
    text('#caDetailReason', action.reason || '-');
    text('#caDetailEvidenceRequirements', Array.isArray(action.evidenceRequirements) && action.evidenceRequirements.length ? action.evidenceRequirements.map(human).join(', ') : '-');
    text('#caDetailRejectReason', action.rejectReason || '-');
    var status = qs('#caDetailStatus');
    if (status) { status.innerHTML = statusBadge(action.status); }
    var followUp = qs('#caFollowUpDueDate');
    if (followUp) { followUp.value = action.followUpAssessmentDueDate || ''; }
    renderComparisonLink(action);
    renderEvidence(data.evidence || []);
    renderHistory(data.history || []);
  }

  function renderComparisonLink(action) {
    var target = qs('#caComparisonLinkWrap');
    if (!target) { return; }

    if (!action || !action.assessmentUuid || !action.uuid) {
      target.innerHTML = '';
      return;
    }

    target.innerHTML = '<a href="/assessments/comparisons/new?baseline=' + encodeURIComponent(action.assessmentUuid) +
      '&correctiveAction=' + encodeURIComponent(action.uuid) +
      '" class="btn btn-outline-secondary btn-sm"><i class="bi bi-intersect me-1"></i>Generate comparison</a>';
  }

  function renderEvidence(items) {
    var target = qs('#caEvidenceList');
    if (!target) { return; }
    if (!items.length) {
      target.innerHTML = '<div class="text-center py-4"><i class="bi bi-cloud-upload fs-1 text-muted"></i><h6 class="mt-2">No evidence uploaded</h6><p class="text-muted mb-0">Upload proof before verification.</p></div>';
      return;
    }
    target.innerHTML = items.map(function (item) {
      return '<article class="d-flex gap-3 border rounded p-3 mb-3">' +
        '<span class="rounded p-2 bg-label-primary align-self-start"><i class="bi bi-paperclip"></i></span>' +
        '<div class="flex-grow-1">' +
        '<div class="d-flex justify-content-between gap-2"><strong>' + escape(human(item.evidence_type || item.evidenceType)) + '</strong><small class="text-muted">' + escape(App.utils.formatDate(item.created_at || item.createdAt)) + '</small></div>' +
        '<p class="text-muted small mb-1">' + escape(item.notes || 'No notes.') + '</p>' +
        '<small class="text-break">Storage: ' + escape(item.storage_file_uuid || item.storageFileUuid) + '</small>' +
        '</div></article>';
    }).join('');
  }

  function renderHistory(items) {
    var target = qs('#caHistoryList');
    if (!target) { return; }
    if (!items.length) {
      target.innerHTML = '<p class="text-muted mb-0">No status history yet.</p>';
      return;
    }
    target.innerHTML = items.map(function (item) {
      return '<div class="d-flex gap-3 mb-3">' +
        '<span class="rounded-circle bg-label-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem"><i class="bi bi-clock-history"></i></span>' +
        '<div><div>' + statusBadge(item.status) + '</div><small class="text-muted">' + escape(App.utils.formatDate(item.created_at || item.createdAt)) + ' by user #' + escape(item.actor_id || item.actorId || 'system') + '</small>' +
        (item.notes ? '<p class="small mb-0 mt-1">' + escape(item.notes) + '</p>' : '') + '</div></div>';
    }).join('');
  }

  function updateActionStatus(status, notes) {
    var root = qs('[data-ca-page="show"]');
    if (!root) { return; }
    var actionId = root.getAttribute('data-action-id');
    App.api.post('/api/v1/corrective-actions/' + encodeURIComponent(actionId) + '/status', { status: status, notes: notes || null }).then(function (res) {
      if (!res.ok) { App.notify.error(res.message || 'Could not update status.'); return; }
      App.notify.success('Status updated.');
      loadDetail();
    });
  }

  function verifyAction() {
    var root = qs('[data-ca-page="show"]');
    if (!root) { return; }
    var actionId = root.getAttribute('data-action-id');
    var notes = qs('#caStatusNotes') ? qs('#caStatusNotes').value : '';
    App.api.post('/api/v1/corrective-actions/' + encodeURIComponent(actionId) + '/verify', { notes: notes || null }).then(function (res) {
      if (!res.ok) { App.notify.error(res.message || 'Could not verify action.'); return; }
      App.notify.success('Action verified and follow-up scheduled.');
      loadDetail();
    });
  }

  function scheduleFollowUp() {
    var root = qs('[data-ca-page="show"]');
    var input = qs('#caFollowUpDueDate');
    if (!root || !input || !input.value) {
      App.notify.warning('Choose a follow-up date first.');
      return;
    }

    App.api.post('/api/v1/corrective-actions/' + encodeURIComponent(root.getAttribute('data-action-id')) + '/follow-up', {
      dueDate: input.value
    }).then(function (res) {
      if (!res.ok) { App.notify.error(res.message || 'Could not save follow-up date.'); return; }
      App.notify.success('Follow-up date updated.');
      loadDetail();
    });
  }

  function bindEvidenceForm() {
    var form = qs('#caEvidenceForm');
    if (!form) { return; }
    var root = qs('[data-ca-page="evidence"]');
    var actionId = root ? root.getAttribute('data-action-id') : '';
    App.forms.bindAjaxForm(form, {
      method: 'POST',
      url: '/api/v1/corrective-actions/' + encodeURIComponent(actionId) + '/evidence',
      useFormData: true,
      submitBtn: '#caEvidenceSubmit',
      resetOnSuccess: true,
      onSuccess: function () {
        App.notify.success('Evidence uploaded.');
        loadDetail();
      },
      onError: function (res) {
        App.notify.error(res.message || 'Evidence upload failed.');
      }
    });
  }

  function bindActionsPage() {
    ['#caSearch', '#caStatusFilter', '#caPriorityFilter'].forEach(function (selector) {
      var el = qs(selector);
      if (el) { el.addEventListener('input', renderActions); el.addEventListener('change', renderActions); }
    });
    var clear = qs('#caClearFilters');
    if (clear) {
      clear.addEventListener('click', function () {
        ['#caSearch', '#caStatusFilter', '#caPriorityFilter'].forEach(function (selector) {
          var el = qs(selector);
          if (el) { el.value = ''; }
        });
        renderActions();
      });
    }
    var refresh = qs('#caRefreshActions');
    if (refresh) { refresh.addEventListener('click', function (event) { event.preventDefault(); loadActions(); }); }
    Promise.all([loadUsers().catch(function () {}), Promise.resolve()]).finally(loadActions);
  }

  function bindRecommendationsPage() {
    var load = qs('#caLoadRecommendations');
    var generate = qs('#caGenerateRecommendations');
    var refresh = qs('#caRefreshRecommendations');
    if (load) { load.addEventListener('click', function () { loadRecommendations(false); }); }
    if (generate) { generate.addEventListener('click', function () { loadRecommendations(true); }); }
    if (refresh) { refresh.addEventListener('click', function () { loadRecommendations(false); }); }

    bindRecommendationsForms();

    document.addEventListener('click', function (event) {
      var button = event.target.closest('[data-ca-action]');
      if (!button) { return; }
      var action = button.getAttribute('data-ca-action');
      var uuid = button.getAttribute('data-uuid');
      if (action === 'open-review') { openReview(uuid); }
      if (action === 'open-reject') { openReject(uuid); }
      if (action === 'open-assign') { openAssign(uuid); }
    });

    Promise.all([loadAssessments(), loadUsers()])
      .then(function () {
        if (!state.assessments.length) {
          renderRecommendationsPlaceholder(
            'No reviewed assessments available',
            'Review or lock an assessment before generating corrective action recommendations.'
          );
          return;
        }

        if (currentAssessmentUuid()) {
          loadRecommendations(false);
          return;
        }

        renderRecommendationsPlaceholder(
          'Select a reviewed assessment',
          'Choose an assessment above to load or generate corrective action recommendations.'
        );
      })
      .catch(function (error) {
        renderRecommendationsPlaceholder(
          'Recommendations unavailable',
          'The workspace could not be prepared. Check assessment and organization access, then refresh.'
        );
        App.notify.error(error.message || 'Could not prepare recommendation review workspace.');
      });
  }

  function bindShowPage() {
    var update = qs('#caUpdateStatus');
    var verify = qs('#caVerifyAction');
    var followUp = qs('#caScheduleFollowUp');
    if (update) {
      update.addEventListener('click', function () {
        updateActionStatus(qs('#caNextStatus').value, qs('#caStatusNotes').value);
      });
    }
    if (verify) { verify.addEventListener('click', verifyAction); }
    if (followUp) { followUp.addEventListener('click', scheduleFollowUp); }
    Promise.all([loadUsers().catch(function () {}), Promise.resolve()]).finally(loadDetail);
  }

  function init() {
    var root = qs('[data-ca-page]');
    if (!root) { return; }
    var page = root.getAttribute('data-ca-page');
    if (page === 'actions') { bindActionsPage(); }
    if (page === 'recommendations') { bindRecommendationsPage(); }
    if (page === 'show') { bindShowPage(); }
    if (page === 'evidence') { bindEvidenceForm(); Promise.all([loadUsers().catch(function () {}), Promise.resolve()]).finally(loadDetail); }
  }

  document.addEventListener('DOMContentLoaded', init);
})();
